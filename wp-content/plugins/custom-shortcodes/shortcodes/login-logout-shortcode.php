<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

// Dynamic login/logout button shortcode
/*function custom_login_logout_shortcode() {
    if ( is_user_logged_in() ) {
        // Logout link
        $myaccount_url = home_url( '/konto' );
        return '<a href="' . esc_url( $myaccount_url ) . '" class="top-login-btn">Moje konto</a>';
    } else {
        // Login link
        $login_url = home_url( '/login' );
        return '<a href="' . esc_url( $login_url ) . '" class="top-login-btn">Zaloguj się</a>';
    }
}
add_shortcode( 'custom_login_logout', 'custom_login_logout_shortcode' );*/
function custom_login_logout_shortcode() {
    if ( is_user_logged_in() ) {
        $myaccount_url = home_url( '/konto' );
        return '
        <a href="' . esc_url( $myaccount_url ) . '" class="top-login-btn">
            <span class="login-icon"><i class="dashicons dashicons-admin-users"></i></span>
            <span class="login-text">Moje konto</span>
        </a>';
    } else {
        $login_url = home_url( '/login' );
        return '
        <a href="' . esc_url( $login_url ) . '" class="top-login-btn">
            <span class="login-icon"><i class="dashicons dashicons-lock"></i></span>
            <span class="login-text">Zaloguj się</span>
        </a>';
    }
}
add_shortcode( 'custom_login_logout', 'custom_login_logout_shortcode' );

// Make sure dashicons load on the frontend
add_action( 'wp_enqueue_scripts', function() {
    wp_enqueue_style( 'dashicons' );
});



// Footer Shortcode: [custom_account_links]
function custom_account_links_shortcode() {
    ob_start();

    // Always show account panel & contact
    echo '<ul class="elementor-icon-list-items">';

    if ( ! is_user_logged_in() ) {
        // Show these only for guests
        echo '<li class="elementor-icon-list-item"><a href="' . esc_url( home_url('/login') ) . '">Logowanie</a></li>';
        echo '<li class="elementor-icon-list-item"><a href="' . esc_url( home_url('/register') ) . '">Rejestracja</a></li>';
    }else{
		echo '<li class="elementor-icon-list-item"><a href="' . esc_url( home_url('/konto') ) . '">Panel konta</a></li>';		
	}

    // Always show other links
     echo '<li class="elementor-icon-list-item"><a href="' . esc_url( home_url('/polec-znajomym') ) . '">Program poleceń</a></li>';
    echo '<li class="elementor-icon-list-item"><a href="' . esc_url( home_url('/kontakt') ) . '">Kontakt</a></li>';
	if ( is_user_logged_in() ) {
		$logout_url = wp_logout_url( home_url( '/login' ) );
        echo '<li class="elementor-icon-list-item"><a href="' . esc_url( $logout_url ) . '">Wyloguj się</a></li>';
    }
    echo '</ul>';

    return ob_get_clean();
}
add_shortcode( 'custom_account_links', 'custom_account_links_shortcode' );
