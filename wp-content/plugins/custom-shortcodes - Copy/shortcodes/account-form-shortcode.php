<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

function custom_account_form_shortcode() {
    // Get current user info
    $user_id = get_current_user_id();
    $user_info = get_userdata( $user_id );
	// echo '<pre>';print_r(get_user_meta(35));exit;
	// echo '<pre>';print_r(get_user_meta($user_id));exit;
    $first_name = get_user_meta($user_id, 'first_name', true);
    $last_name = get_user_meta($user_id, 'last_name', true);

    // Retrieve saved names
    // $first_name = isset($user_info->first_name) ? $user_info->first_name : '';
    // $last_name  = isset($user_info->last_name) ? $user_info->last_name : '';

    ob_start();

    // Success message after redirect
    if ( isset($_GET['updated']) && $_GET['updated'] === 'true' ) {
        echo '<div style="color: green;">
                Konto zostało pomyślnie zaktualizowane.
              </div>';
			 
		// Redirect to same page without 'updated' param to prevent repeat
		$url = remove_query_arg('updated');
		echo '<script>history.replaceState(null, null, "'.$url.'");</script>';
    }
    ?>

    <form method="post" action="" class="elementor-form" style="max-width:500px;">
		<div class="elementor-form-fields-wrapper elementor-labels-above">
			<div class="elementor-field-type-text elementor-field-group elementor-column elementor-field-group-n elementor-col-50 elementor-field-required elementor-mark-required">
				<label for="first_name" class="elementor-field-label"> Imię</label>
				<input size="1" type="text" name="first_name" class="elementor-field elementor-size-xs elementor-field-textual" required="required" value="<?php echo esc_attr( $first_name ); ?>">
			</div>
			<div class="elementor-field-type-text elementor-field-group elementor-column  elementor-col-50">
				<label for="last_name" class="elementor-field-label"> Nazwisko </label>
				<input size="1" type="text" name="last_name" class="elementor-field elementor-size-xs elementor-field-textual" value="<?php echo esc_attr( $last_name ); ?>">
			</div>
			<div class="elementor-field-type-submit elementor-field-group">
                <button type="submit" name="submit_account" class="elementor-button-custom elementor-size-sm">
                    <span class="elementor-button-content-wrapper">
                        <span class="elementor-button-text">Zapisz</span>
                    </span>
                </button>
            </div>
		</div>
    </form>

    <?php
    return ob_get_clean();
}
add_shortcode( 'account_name_form', 'custom_account_form_shortcode' );


// =======================
// Handle Form Submission
// =======================
function handle_account_form_submission() {
    if ( isset($_POST['submit_account']) && is_user_logged_in() ) {
        $user_id = get_current_user_id();
        $first_name = sanitize_text_field($_POST['first_name'] ?? '');
        $last_name  = sanitize_text_field($_POST['last_name'] ?? '');
			
        // update_user_meta($update_data);
		update_user_meta($user_id, 'first_name', $first_name);
		update_user_meta($user_id, 'last_name', $last_name);
		
		$update_data = [
            'ID' => $user_id,
            'display_name' => $first_name.' '.$last_name,
        ];
		wp_update_user($update_data);
			
        wp_redirect( esc_url( add_query_arg( 'updated', 'true', wp_get_referer() ) ) );
        exit;
    }
}
add_action('template_redirect', 'handle_account_form_submission');
