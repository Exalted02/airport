<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

// Dynamic login/logout button shortcode
function custom_login_logout_shortcode() {
    if ( is_user_logged_in() ) {
        // Logout link
        /*$logout_url = wp_logout_url( home_url( '/login' ) ); // Redirect to /login after logout
        return '<a href="' . esc_url( $logout_url ) . '" class="top-logout-btn">Wyloguj się</a>';*/
        $myaccount_url = home_url( '/konto' );
        return '<a href="' . esc_url( $myaccount_url ) . '" class="top-login-btn">Moje konto</a>';
    } else {
        // Login link
        $login_url = home_url( '/login' );
        return '<a href="' . esc_url( $login_url ) . '" class="top-login-btn">Zaloguj się</a>';
    }
}
add_shortcode( 'custom_login_logout', 'custom_login_logout_shortcode' );

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
    echo '<li class="elementor-icon-list-item"><a href="#">Program poleceń</a></li>';
    echo '<li class="elementor-icon-list-item"><a href="' . esc_url( home_url('/kontakt') ) . '">Kontakt</a></li>';
	if ( is_user_logged_in() ) {
		$logout_url = wp_logout_url( home_url( '/login' ) );
        echo '<li class="elementor-icon-list-item"><a href="' . esc_url( $logout_url ) . '">Wyloguj się</a></li>';
    }
    echo '</ul>';

    return ob_get_clean();
}
add_shortcode( 'custom_account_links', 'custom_account_links_shortcode' );
