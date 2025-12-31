<?php
/*
Plugin Name: Custom Functions
Description: Common functions for the site.
*/

// Generate 5-character unique alphanumeric code
function generate_unique_code($length = 5) {
    $characters = 'abcdefghijklmnopqrstuvwxyz0123456789';
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[wp_rand(0, strlen($characters) - 1)];
    }
    return $randomString;
}

function send_frontend_welcome_email( $user_id, $args = array() ) {

    $defaults = array(
        'type'     => 'normal', // normal | google
        'password' => '',
    );

    $args = wp_parse_args( $args, $defaults );

    $user      = get_userdata( $user_id );
    $login_url = home_url('/login');
    $site_name = get_bloginfo('name');

    if ( $args['type'] === 'google' ) {
        $subject = 'Witaj w ' . $site_name . ' – logowanie Google';

        $extra = '
            <p>Konto zostało utworzone przy użyciu logowania Google.</p>
            <p><strong>Adres e-mail:</strong> ' . esc_html($user->user_email) . '</p>
        ';
        $button_text = 'Zaloguj się przez Google';
        $button_color = '#db4437';
    } else {
        $subject = 'Twoje konto w ' . $site_name;

        $extra = '
            <p>Twoje konto zostało pomyślnie utworzone.</p>
            <p><strong>Adres e-mail:</strong> ' . esc_html($user->user_email) . '</p>
            <p><strong>Hasło:</strong> ' . esc_html($args['password']) . '</p>
        ';
        $button_text = 'Zaloguj się';
        $button_color = '#0073aa';
    }

    $message = '
    <html>
    <body style="font-family:Arial,sans-serif;background:#f7f7f7;padding:30px;">
        <div style="max-width:600px;margin:auto;background:#ffffff;padding:30px;border-radius:6px;">
            <h2>Witaj w ' . esc_html($site_name) . ' </h2>

            ' . $extra . '

            <p style="margin-top:20px;">
                <a href="' . esc_url($login_url) . '"
                   style="background:' . esc_attr($button_color) . ';color:#ffffff;
                          padding:12px 20px;text-decoration:none;border-radius:4px;">
                    ' . esc_html($button_text) . '
                </a>
            </p>

            <p style="margin-top:30px;font-size:12px;color:#666;">
                Przy kolejnych logowaniach użyj tej samej metody.
            </p>
        </div>
    </body>
    </html>';

    wp_mail(
        $user->user_email,
        $subject,
        $message,
        array( 'Content-Type: text/html; charset=UTF-8' )
    );
}

