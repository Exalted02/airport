<?php
if (! defined( 'ABSPATH' )) exit;

/**
 * Robust Mailchimp tag sync for Paid Member Subscriptions
 * - Handles insert, update (plan change), cancel, expire, remove
 * - Removes old tag on plan change (so tag is updated, not duplicated)
 * - Upserts subscriber before tagging (Mailchimp requires member exists)
 */

/* -------- CONFIG (replace these) -------- */
$settings = get_option('pms_email_marketing_mailchimp_settings');

if ( is_array($settings) ) {
    $api_key = $settings['api_key'] ?? '';
    $list_id = $settings['list_id'] ?? '';
} else {
    $api_key = '';
    $list_id = '';
}
define('PMS_MC_API_KEY', $api_key);
define('PMS_MC_LIST_ID', $list_id);

/* -------- Plan ID -> tag mapping -------- */
function pms_mc_plan_tags() {
    return [
        2342 => 'premium_yearly',
        2340 => 'premium_quarterly',
        1350 => 'premium_monthly',
        2345 => 'darmowy',
    ];
}

function pms_mc_get_tag_by_plan($plan_id) {
    $map = pms_mc_plan_tags();
    return isset($map[$plan_id]) ? $map[$plan_id] : false;
}

/* -------- Helper: datacenter from API key -------- */
function pms_mc_get_dc() {
    if (empty(PMS_MC_API_KEY) || strpos(PMS_MC_API_KEY, '-') === false) return false;
    return substr(PMS_MC_API_KEY, strrpos(PMS_MC_API_KEY, '-') + 1);
}

/* -------- Upsert a member (PUT) - required before using tags -------- */
function pms_mc_upsert_member($email, $fname = '', $lname = '') {
    $api_key = PMS_MC_API_KEY;
    $list_id = PMS_MC_LIST_ID;
    $dc = pms_mc_get_dc();
    if (!$api_key || !$list_id || !$dc) {
        error_log('PMS-MC: Missing API / List or DC');
        return false;
    }

    $member_id = md5(strtolower($email));
    $url = "https://{$dc}.api.mailchimp.com/3.0/lists/{$list_id}/members/{$member_id}";

    $body = [
        'email_address' => $email,
        'status_if_new' => 'subscribed',
        'status' => 'subscribed',
        'merge_fields' => [
            'FNAME' => $fname,
            'LNAME' => $lname,
        ],
    ];

    $resp = wp_remote_request( $url, [
        'method' => 'PUT',
        'headers' => [
            'Authorization' => 'apikey ' . $api_key,
            'Content-Type' => 'application/json',
        ],
        'body' => wp_json_encode($body),
        'timeout' => 20,
    ]);

    if ( is_wp_error($resp) ) {
        error_log('PMS-MC upsert error: ' . $resp->get_error_message());
    } else {
        error_log('PMS-MC upsert result: ' . wp_remote_retrieve_response_code($resp) . ' - ' . wp_remote_retrieve_body($resp));
    }
    return $resp;
}

/* -------- Add or remove a tag (POST tags endpoint) -------- */
function pms_mc_set_tag($email, $tag_name, $status = 'active') {
    $api_key = PMS_MC_API_KEY;
    $list_id = PMS_MC_LIST_ID;
    $dc = pms_mc_get_dc();
    if (!$api_key || !$list_id || !$dc) {
        error_log('PMS-MC: Missing API / List or DC');
        return false;
    }

    // Upsert member first (Mailchimp requires a member to exist to set tags)
    pms_mc_upsert_member($email);

    $member_id = md5(strtolower($email));
    $url = "https://{$dc}.api.mailchimp.com/3.0/lists/{$list_id}/members/{$member_id}/tags";
    $payload = ['tags' => [['name' => $tag_name, 'status' => $status]]];

    $resp = wp_remote_post($url, [
        'headers' => [
            'Authorization' => 'apikey ' . $api_key,
            'Content-Type' => 'application/json',
        ],
        'body' => wp_json_encode($payload),
        'timeout' => 20,
    ]);

    if ( is_wp_error($resp) ) {
        error_log("PMS-MC set_tag error ({$tag_name} / {$status}): " . $resp->get_error_message());
    } else {
        error_log("PMS-MC set_tag ({$tag_name} / {$status}): " . wp_remote_retrieve_response_code($resp) . ' - ' . wp_remote_retrieve_body($resp));
    }

    return $resp;
}

/* -------- Utility: Resolve subscription, user_id, plan_id from various hook signatures -------- */
function pms_mc_resolve_subscription_info( $args ) {
    // Accept many forms:
    // - ($user_id, $subscription_id, $plan_id)
    // - ($subscription_id, $data_array)
    // - ($subscription_id)  (you can load subscription)
    // - ($user_id, $subscription_id)
    $result = [
        'subscription_id' => 0,
        'user_id' => 0,
        'plan_id' => 0,
    ];

    // If first arg is an array (new_data for member_subscription_update) or second arg is array, handle separately outside
    if ( isset($args[0]) && is_numeric($args[0]) && isset($args[1]) && is_array($args[1]) ) {
        // ($subscription_id, $data_array) like pms_member_subscription_insert
        $result['subscription_id'] = intval($args[0]);
        $data = $args[1];
        $result['user_id'] = intval($data['user_id'] ?? 0);
        $result['plan_id'] = intval($data['subscription_plan_id'] ?? 0);
        return $result;
    }

    // If typical three-arg signature (user_id, subscription_id, plan_id)
    if ( isset($args[0]) && isset($args[1]) && isset($args[2]) && is_numeric($args[0]) && is_numeric($args[1]) ) {
        // Could be (user_id, subscription_id, plan_id)
        // We'll check which seems to be a user
        if ( get_userdata(intval($args[0])) ) {
            $result['user_id'] = intval($args[0]);
            $result['subscription_id'] = intval($args[1]);
            $result['plan_id'] = intval($args[2]);
            return $result;
        } else {
            // maybe (subscription_id, user_id, plan_id)
            $result['subscription_id'] = intval($args[0]);
            $result['user_id'] = intval($args[1]);
            $result['plan_id'] = intval($args[2]);
            return $result;
        }
    }

    // If single numeric arg, treat as subscription_id
    if ( isset($args[0]) && is_numeric($args[0]) ) {
        $result['subscription_id'] = intval($args[0]);
        // try load subscription
        if ( function_exists('pms_get_subscription') ) {
            $sub = pms_get_subscription($result['subscription_id']);
            if ($sub) {
                $result['user_id'] = intval($sub->user_id ?? 0);
                $result['plan_id'] = intval($sub->subscription_plan_id ?? 0);
            }
        } else if ( function_exists('pms_get_member_subscription') ) {
            $sub = pms_get_member_subscription($result['subscription_id']);
            if ($sub) {
                $result['user_id'] = intval($sub->user_id ?? 0);
                $result['plan_id'] = intval($sub->subscription_plan_id ?? 0);
            }
        }
        return $result;
    }

    // Fallback: attempt to detect user id in args
    foreach ($args as $a) {
        if ( is_numeric($a) && get_userdata(intval($a)) ) {
            $result['user_id'] = intval($a);
            break;
        }
    }

    return $result;
}

/* ----------------------------------------
   1) Insert (new subscription)
   signature: pms_member_subscription_insert( $subscription_id, $data )
   OR pms_user_added_subscription( $user_id, $subscription_id, $plan_id )
---------------------------------------- */
add_action('pms_member_subscription_insert', function() {
    $args = func_get_args();
    $info = pms_mc_resolve_subscription_info($args);
    if (empty($info['user_id']) || empty($info['plan_id'])) return;

    $user = get_userdata($info['user_id']);
    if (!$user) return;

    $tag = pms_mc_get_tag_by_plan($info['plan_id']);
    if (!$tag) return;

    pms_mc_set_tag($user->user_email, $tag, 'active');
}, 10, 2);

// Also support the older/alternate hook
add_action('pms_user_added_subscription', function($user_id, $subscription_id, $plan_id) {
    $user = get_userdata($user_id);
    if (!$user) return;

    $tag = pms_mc_get_tag_by_plan($plan_id);
    if (!$tag) return;

    pms_mc_set_tag($user->user_email, $tag, 'active');
}, 10, 3);


/* ----------------------------------------
   2) Update (plan change) - remove old tag, add new tag
   signature used earlier: pms_member_subscription_update( $subscription_id, $new_data, $old_data )
---------------------------------------- */
add_action('pms_member_subscription_update', function($subscription_id, $new_data, $old_data) {
    // new_data and old_data are arrays in many PMS versions
    $user_id = intval($new_data['user_id'] ?? ($old_data['user_id'] ?? 0));
    $new_plan = intval($new_data['subscription_plan_id'] ?? 0);
    $old_plan = intval($old_data['subscription_plan_id'] ?? 0);

    if (empty($user_id)) {
        // try to resolve from subscription id
        $info = pms_mc_resolve_subscription_info([$subscription_id]);
        $user_id = $info['user_id'];
        if (empty($new_plan)) $new_plan = $info['plan_id'];
    }

    if (!$user_id) return;
    $user = get_userdata($user_id);
    if (!$user) return;

    // if plan changed, remove old tag then add new tag
    if ($old_plan && $old_plan != $new_plan) {
        $old_tag = pms_mc_get_tag_by_plan($old_plan);
        if ($old_tag) pms_mc_set_tag($user->user_email, $old_tag, 'inactive');
    }

    // add new tag if present
    $new_tag = pms_mc_get_tag_by_plan($new_plan);
    if ($new_tag) pms_mc_set_tag($user->user_email, $new_tag, 'active');

}, 10, 3);

// Some PMS versions might call a different hook on upgrade; support common alias:
add_action('pms_user_upgraded_subscription', function($user_id, $subscription_id, $plan_id) {
    $user = get_userdata($user_id);
    if (!$user) return;

    // remove other tags (optional) and add this plan's tag
    // We don't know old plan in this hook; safer to add tag only
    $tag = pms_mc_get_tag_by_plan($plan_id);
    if ($tag) pms_mc_set_tag($user->user_email, $tag, 'active');
}, 10, 3);

/* ----------------------------------------
   3) Remove / Cancel / Expire (various signatures)
   Common stable hooks: pms_user_canceled_subscription, pms_user_expired_subscription, pms_user_removed_subscription
   Some emit ( $user_id, $subscription_id, $plan_id ), others emit ($subscription_id)
---------------------------------------- */
$remove_hooks = [
    'pms_user_canceled_subscription',
    'pms_user_expired_subscription',
    'pms_user_removed_subscription',
    // alternative older member hooks
    'pms_member_subscription_cancel',
    'pms_member_subscription_expire',
    'pms_member_subscription_delete',
];

foreach ($remove_hooks as $hook) {
    add_action($hook, function() {
        $args = func_get_args();
        $info = pms_mc_resolve_subscription_info($args);
        if (empty($info['user_id']) || empty($info['plan_id'])) return;

        $user = get_userdata($info['user_id']);
        if (!$user) return;

        $tag = pms_mc_get_tag_by_plan($info['plan_id']);
        if (!$tag) return;

        pms_mc_set_tag($user->user_email, $tag, 'inactive');
    }, 10, 3);
}
