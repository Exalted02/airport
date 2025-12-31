<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * PMS → Mailchimp tag sync (FULLY DYNAMIC)
 * - Tag = EXACT PMS plan name
 * - Paid / Free based on plan PRICE
 * - Handles insert, update, cancel, expire, remove
 * - Clears "deleted user" tag on re-registration
 */

/* ======================================
   CONFIG
====================================== */

$settings = get_option('pms_email_marketing_settings');

if ( ! is_array($settings) || empty($settings['platforms']['mailchimp']) ) {
    return;
}

define('PMS_MC_API_KEY', $settings['platforms']['mailchimp']['api_key'] ?? '');
define('PMS_MC_LIST_ID', $settings['platforms']['mailchimp']['list_id'] ?? '');

/* ======================================
   HELPERS
====================================== */

function pms_mc_get_dc() {
    if ( empty(PMS_MC_API_KEY) || strpos(PMS_MC_API_KEY, '-') === false ) return false;
    return substr(PMS_MC_API_KEY, strrpos(PMS_MC_API_KEY, '-') + 1);
}

function pms_mc_get_plan( $plan_id ) {
    if ( function_exists('pms_get_subscription_plan') ) {
        return pms_get_subscription_plan( $plan_id );
    }
    return false;
}

/* ======================================
   PAID / FREE (PRICE BASED)
====================================== */

function pms_mc_is_paid_plan( $plan_id ) {
    $plan = pms_mc_get_plan( $plan_id );
    if ( ! $plan ) return false;

    return floatval($plan->price) > 0;
}

function pms_mc_is_free_plan( $plan_id ) {
    $plan = pms_mc_get_plan( $plan_id );
    if ( ! $plan ) return false;

    return floatval($plan->price) <= 0;
}

/* ======================================
   PLAN TAG = EXACT PLAN NAME
====================================== */

function pms_mc_get_tag_by_plan( $plan_id ) {
    $plan = pms_mc_get_plan( $plan_id );
    if ( ! $plan || empty($plan->name) ) return false;

    return preg_replace('/\s+/', ' ', trim($plan->name));
}

/* ======================================
   MAILCHIMP CORE
====================================== */

function pms_mc_upsert_member( $email, $fname = '', $lname = '' ) {
    $dc = pms_mc_get_dc();
    if ( ! $dc ) return false;

    $member_id = md5(strtolower($email));
    $url = "https://{$dc}.api.mailchimp.com/3.0/lists/" . PMS_MC_LIST_ID . "/members/{$member_id}";

    return wp_remote_request($url, [
        'method' => 'PUT',
        'headers'=> [
            'Authorization' => 'apikey ' . PMS_MC_API_KEY,
            'Content-Type'  => 'application/json',
        ],
        'body' => wp_json_encode([
            'email_address' => $email,
            'status_if_new' => 'subscribed',
            'status'        => 'subscribed',
            'merge_fields'  => [
                'FNAME' => $fname,
                'LNAME' => $lname,
            ],
        ]),
        'timeout' => 20,
    ]);
}

function pms_mc_set_tag( $email, $tag, $status = 'active' ) {
    if ( empty($tag) ) return false;

    $dc = pms_mc_get_dc();
    if ( ! $dc ) return false;

    pms_mc_upsert_member($email);

    $member_id = md5(strtolower($email));
    $url = "https://{$dc}.api.mailchimp.com/3.0/lists/" . PMS_MC_LIST_ID . "/members/{$member_id}/tags";

    return wp_remote_post($url, [
        'headers' => [
            'Authorization' => 'apikey ' . PMS_MC_API_KEY,
            'Content-Type'  => 'application/json',
        ],
        'body' => wp_json_encode([
            'tags' => [[
                'name'   => $tag,
                'status' => $status
            ]]
        ]),
        'timeout' => 20,
    ]);
}

/* ======================================
   DELETED USER CLEANUP
====================================== */

function pms_mc_clear_deleted_user_tag( $email ) {
    pms_mc_set_tag($email, 'deleted user', 'inactive');
}

/* ======================================
   RESOLVE USER + PLAN FROM HOOKS
====================================== */

function pms_mc_resolve_subscription_info( $args ) {
    $out = ['user_id' => 0, 'plan_id' => 0];

    if ( isset($args[1]) && is_array($args[1]) ) {
        $out['user_id'] = intval($args[1]['user_id'] ?? 0);
        $out['plan_id'] = intval($args[1]['subscription_plan_id'] ?? 0);
        return $out;
    }

    foreach ( $args as $a ) {
        if ( is_numeric($a) && get_userdata($a) ) {
            $out['user_id'] = intval($a);
        }
        if ( is_numeric($a) && pms_mc_get_plan($a) ) {
            $out['plan_id'] = intval($a);
        }
    }

    return $out;
}

/* ======================================
   INSERT (NEW SUBSCRIPTION)
====================================== */

add_action('pms_member_subscription_insert', function() {

    $info = pms_mc_resolve_subscription_info(func_get_args());
    if ( ! $info['user_id'] || ! $info['plan_id'] ) return;

    $user = get_userdata($info['user_id']);
    if ( ! $user ) return;

    pms_mc_clear_deleted_user_tag($user->user_email);

    if ( $tag = pms_mc_get_tag_by_plan($info['plan_id']) ) {
        pms_mc_set_tag($user->user_email, $tag, 'active');
    }

    if ( pms_mc_is_paid_plan($info['plan_id']) ) {
        pms_mc_set_tag($user->user_email, 'premium', 'active');
        pms_mc_set_tag($user->user_email, 'downgrade', 'inactive');
    }

}, 10, 2);

/* ======================================
   UPDATE (PLAN CHANGE)
====================================== */

add_action('pms_member_subscription_update', function($subscription_id, $new, $old) {

    $user = get_userdata($new['user_id'] ?? 0);
    if ( ! $user ) return;

    $old_plan = intval($old['subscription_plan_id'] ?? 0);
    $new_plan = intval($new['subscription_plan_id'] ?? 0);

    if ( $old_plan && $old_plan !== $new_plan ) {
        if ( $old_tag = pms_mc_get_tag_by_plan($old_plan) ) {
            pms_mc_set_tag($user->user_email, $old_tag, 'inactive');
        }
    }

    if ( $new_tag = pms_mc_get_tag_by_plan($new_plan) ) {
        pms_mc_set_tag($user->user_email, $new_tag, 'active');
    }

    if ( pms_mc_is_paid_plan($old_plan) && pms_mc_is_free_plan($new_plan) ) {
        pms_mc_set_tag($user->user_email, 'premium', 'inactive');
        pms_mc_set_tag($user->user_email, 'downgrade', 'active');
    }

    if ( pms_mc_is_free_plan($old_plan) && pms_mc_is_paid_plan($new_plan) ) {
        pms_mc_set_tag($user->user_email, 'premium', 'active');
        pms_mc_set_tag($user->user_email, 'downgrade', 'inactive');
    }

}, 10, 3);

/* ======================================
   CANCEL / EXPIRE / REMOVE
====================================== */

$remove_hooks = [
    'pms_user_canceled_subscription',
    'pms_user_expired_subscription',
    'pms_user_removed_subscription',
	// alternative older member hooks
    'pms_member_subscription_cancel',
    'pms_member_subscription_expire',
    'pms_member_subscription_delete',
];

foreach ( $remove_hooks as $hook ) {
    add_action($hook, function() {

        $info = pms_mc_resolve_subscription_info(func_get_args());
        if ( ! $info['user_id'] || ! $info['plan_id'] ) return;

        $user = get_userdata($info['user_id']);
        if ( ! $user ) return;

        if ( $tag = pms_mc_get_tag_by_plan($info['plan_id']) ) {
            pms_mc_set_tag($user->user_email, $tag, 'inactive');
        }

        if ( pms_mc_is_paid_plan($info['plan_id']) ) {
            pms_mc_set_tag($user->user_email, 'premium', 'inactive');
            pms_mc_set_tag($user->user_email, 'downgrade', 'active');
        }

    }, 10);
}
