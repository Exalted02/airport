<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

function custom_airport_form_shortcode() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'airport_list';
    $airports = $wpdb->get_results("SELECT * FROM $table_name ORDER BY name ASC");

    $user_id    = get_current_user_id();
    $airport_id = get_user_meta($user_id, 'airport', true);

    ob_start();

    // Success message after redirect
    if ( isset($_GET['updated']) && $_GET['updated'] === 'true' ) {
        echo '<div style="color: green;">
                Airport updated successfully.
              </div>';
			 
		// Redirect to same page without 'updated' param to prevent repeat
		$url = remove_query_arg('updated');
		echo '<script>history.replaceState(null, null, "'.$url.'");</script>';
    }
    ?>

    <form method="post" action="" class="elementor-form" style="max-width:500px;">
        <div class="elementor-form-fields-wrapper elementor-labels-above">
            
            <!-- Select Field -->
            <div class="elementor-field-type-select elementor-field-group">
                <label for="airport" class="elementor-field-label">Zmień lotnisko</label>
                <select name="airport" id="airport" class="elementor-field elementor-select">
                    <option value="">-- Select an airport --</option>
                    <?php if ( $airports ) : ?>
                        <?php foreach ( $airports as $airport ) : ?>
                            <option value="<?php echo esc_attr( $airport->id ); ?>" <?php selected( $airport->id, $airport_id ); ?>>
                                <?php echo esc_html( $airport->name . ' (' . $airport->code . ')' ); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>

            <!-- Submit Button -->
            <div class="elementor-field-type-submit elementor-field-group">
                <button type="submit" name="submit_airport" class="elementor-button elementor-size-sm">
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
add_shortcode( 'airport_form', 'custom_airport_form_shortcode' );


// =======================
// Handle Form Submission
// =======================
function handle_airport_form_submission() {
    if ( isset($_POST['submit_airport']) && is_user_logged_in() ) {
        $user_id = get_current_user_id();
        $airport = intval($_POST['airport']);
        if ( $airport ) {
            update_user_meta($user_id, 'airport', $airport);
			
            wp_redirect( esc_url( add_query_arg( 'updated', 'true', wp_get_referer() ) ) );
            exit;
        }
    }
}
add_action('template_redirect', 'handle_airport_form_submission');


// =======================
// Shortcodes for Airport Name & Code
// =======================
function get_user_airport_name() {
    global $wpdb;
    $user_id = get_current_user_id();
    $airport_id = get_user_meta($user_id, 'airport', true);

    if ( $airport_id ) {
        $table   = $wpdb->prefix . 'airport_list';
        $airport = $wpdb->get_row( $wpdb->prepare("SELECT name FROM $table WHERE id = %d", $airport_id) );
        if ( $airport ) {
            return esc_html( $airport->name );
        }
    }
    return '';
}
add_shortcode('user_airport_name', 'get_user_airport_name');


function get_user_airport_code() {
    global $wpdb;
    $user_id = get_current_user_id();
    $airport_id = get_user_meta($user_id, 'airport', true);

    if ( $airport_id ) {
        $table   = $wpdb->prefix . 'airport_list';
        $airport = $wpdb->get_row( $wpdb->prepare("SELECT code FROM $table WHERE id = %d", $airport_id) );
        if ( $airport ) {
            return esc_html( $airport->code );
        }
    }
    return '';
}
add_shortcode('user_airport_code', 'get_user_airport_code');
