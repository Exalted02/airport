<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

function custom_airport_form_shortcode() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'airport_list';
    $airports = $wpdb->get_results("SELECT * FROM $table_name ORDER BY name ASC");

    $user_id     = get_current_user_id();
    $airport_ids = get_user_meta($user_id, 'airport', true);

    // Convert comma-separated string to array
    $selected_airports = !empty($airport_ids) ? explode(',', $airport_ids) : [];

    ob_start();

    // Success message after redirect
    if ( isset($_GET['updated']) && $_GET['updated'] === 'true' ) {
        echo '<div style="color: green;padding-right: calc(10px / 2);padding-left: calc(10px / 2);">Lotniska zostały pomyślnie zaktualizowane.</div>';
        // Remove the query param (prevents repeat on refresh)
        $url = remove_query_arg('updated');
        echo '<script>history.replaceState(null, null, "'.$url.'");</script>';
    }
	if ( isset($_GET['deleted']) && $_GET['deleted'] === 'true' ) {
		echo '<div style="color: green;padding-right: calc(10px / 2);padding-left: calc(10px / 2);">Lotnisko zostało usunięte.</div>';
		$url = remove_query_arg('deleted');
		echo '<script>history.replaceState(null, null, "'.$url.'");</script>';
	}
    ?>
    <form method="post" action="" id="airportForm" class="elementor-form" style="max-width:500px;">
        <div class="elementor-form-fields-wrapper elementor-labels-above">

            <!-- Error Message -->
            <div id="airportError" style="color: red; display: none; margin-bottom: 10px;padding-right: calc(10px / 2);padding-left: calc(10px / 2);">
                Proszę wybrać co najmniej jedno lotnisko.
            </div>
            
            <!-- Multi Select Field -->
            <div class="elementor-field-type-select elementor-field-group">
                <label for="airport" class="elementor-field-label">Dodaj lotnisko</label>
                <select 
                    name="airport[]" 
                    id="airport" 
                    class="elementor-field elementor-select common-select2" 
                    multiple 
                    data-placeholder="Wybierz lotniska"
                    style="width:100%;"
                >
                    <?php if ( $airports ) : ?>
                        <?php foreach ( $airports as $airport ) : ?>
                            <option 
                                value="<?php echo esc_attr( $airport->id ); ?>" 
                                <?php echo in_array( $airport->id, $selected_airports ) ? 'selected' : ''; ?>>
                                <?php echo esc_html( $airport->name . ' (' . $airport->code . ')' ); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>

            <!-- Submit Button -->
            <div class="elementor-field-type-submit elementor-field-group">
                <button type="submit" name="submit_airport" class="elementor-button-custom elementor-size-sm">
                    <span class="elementor-button-content-wrapper">
                        <span class="elementor-button-text">Aktualizuj</span>
                    </span>
                </button>
            </div>
        </div>
    </form>

    <script>
    document.addEventListener("DOMContentLoaded", function() {
        const form = document.getElementById('airportForm');
        const select = document.getElementById('airport');
        const errorMsg = document.getElementById('airportError');

        form.addEventListener('submit', function(e) {
            const selected = Array.from(select.selectedOptions).map(o => o.value);
            if (selected.length === 0) {
                e.preventDefault(); // stop form submit
                errorMsg.style.display = 'block'; // show error
            } else {
                errorMsg.style.display = 'none'; // hide error if valid
            }
        });
    });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode( 'airport_form', 'custom_airport_form_shortcode' );


// =======================
// Handle Form Submission
// =======================
function handle_airport_form_submission() {
    if ( isset($_POST['submit_airport']) && is_user_logged_in() ) {
        $user_id  = get_current_user_id();
        $airports = isset($_POST['airport']) ? array_map('intval', $_POST['airport']) : [];

        if ( !empty($airports) ) {
            // Convert array to comma-separated string
            $airport_string = implode(',', $airports);
            update_user_meta($user_id, 'airport', $airport_string);

            // Redirect back with success message
            wp_redirect( esc_url( add_query_arg( 'updated', 'true', wp_get_referer() ) ) );
            exit;
        }
    }
}
add_action('template_redirect', 'handle_airport_form_submission');


// =======================
// Shortcodes for Airport Name & Code
// =======================
function get_user_airport_list() {
    global $wpdb;

    if ( ! is_user_logged_in() ) {
        return '<p>Musisz być zalogowany, aby zobaczyć swoje lotniska.</p>';
    }

    $user_id = get_current_user_id();
    $airport_ids = get_user_meta($user_id, 'airport', true);
    $selected_airports = !empty($airport_ids) ? explode(',', $airport_ids) : [];

    if (empty($selected_airports)) {
        return '<p>Nie wybrałeś jeszcze żadnych lotnisk.</p>';
    }

    $table_name = $wpdb->prefix . 'airport_list';
    $placeholders = implode(',', array_fill(0, count($selected_airports), '%d'));
    $query = $wpdb->prepare("SELECT * FROM $table_name WHERE id IN ($placeholders)", $selected_airports);
    $airports = $wpdb->get_results($query);

    ob_start();
    ?>
    <style>
		.airport-card-container {
			display:flex;
			align-items:center;
			justify-content:space-between;
			width: 100%;
		}
        .airport-list { 
			display:flex;
			flex-direction:column; 
			gap:10px; 
			max-width:100%;
		}
        .airport-card {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 10px 18px;
            background: #fff;
            border: 1px solid #d7e2eb;
            border-radius: 10px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.03);
            transition: box-shadow 0.2s ease, transform 0.2s ease;
        }
        .airport-card:hover {
			box-shadow:0 3px 8px rgba(0,0,0,0.08);
			transform:translateY(-2px);
		}		
        .icon {
            font-size: 20px;
            color: #0073aa;
            flex-shrink: 0;
        }
		.dashicons, .dashicons-before:before {
			width: 45px;
			height: 45px;
			font-size: 45px;
		}
        .airport-details {
			display:flex;
			flex-direction:column;
		}
        .airport-title {
			font-weight:600;
			font-size:15px;
			margin-bottom:3px;
		}
        .airport-subtitle { 
			font-size:13px;
			color:#555; 
		}
        .airport-delete-btn {
            background:#e74c3c;
			color:#fff;
			border:none;
			padding:6px 12px;
            border-radius:5px;
			cursor:pointer;
			font-size:13px;
        }
        .airport-delete-btn:hover {
			background:#c0392b;
		}
    </style>

    <div class="airport-list">
        <?php foreach ( $airports as $airport ): ?>
            <div class="airport-card">
				<div class="icon"><i class="dashicons dashicons-location"></i> </div>
                <div class="airport-card-container">
					<div class="airport-details">
						<div class="airport-title"><?php echo esc_html( $airport->name ); ?></div>
						<div class="airport-subtitle"><?php echo esc_html( $airport->code ); ?></div>
					</div>
					<div>
						<form method="post" style="margin:0;">
							<input type="hidden" name="delete_airport_id" value="<?php echo esc_attr( $airport->id ); ?>">
							<?php wp_nonce_field('delete_airport_'.$airport->id, 'delete_airport_nonce'); ?>
							<button type="submit" class="airport-delete-btn">Usuń</button>
						</form>
					</div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('user_airport_list', 'get_user_airport_list');

function handle_delete_airport() {
    if ( isset($_POST['delete_airport_id']) && is_user_logged_in() ) {
        $airport_id = intval($_POST['delete_airport_id']);
        $user_id    = get_current_user_id();

        // Verify nonce for security
        if ( !isset($_POST['delete_airport_nonce']) || 
             !wp_verify_nonce($_POST['delete_airport_nonce'], 'delete_airport_'.$airport_id) ) {
            return;
        }

        $airport_ids = get_user_meta($user_id, 'airport', true);
        $selected_airports = !empty($airport_ids) ? explode(',', $airport_ids) : [];

        // Remove the selected airport
        if (($key = array_search($airport_id, $selected_airports)) !== false) {
            unset($selected_airports[$key]);
            $airport_string = implode(',', $selected_airports);
            update_user_meta($user_id, 'airport', $airport_string);
        }

        // Redirect safely to the current page
        $redirect_url = wp_get_referer() ? wp_get_referer() : home_url('zmiana-lotniska/');
		$redirect_url = remove_query_arg(['updated', 'deleted'], $redirect_url); // remove old query params
		$redirect_url = add_query_arg('deleted', 'true', $redirect_url);        // add deleted param
		wp_safe_redirect($redirect_url);
		exit;
    }
}

add_action('template_redirect', 'handle_delete_airport');

