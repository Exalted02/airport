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
