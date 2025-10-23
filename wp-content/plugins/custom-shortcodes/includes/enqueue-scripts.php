<?php
function custom_shortcodes_enqueue_select2_assets() {
    global $post;

    // Only load if shortcode [custom_register] or [airport_form] is present on the page
    if ( isset( $post->post_content ) && 
        ( has_shortcode( $post->post_content, 'custom_register' ) || has_shortcode( $post->post_content, 'airport_form' ) ) || is_admin() || isset($_GET['elementor-preview']) || isset($_GET['action']) && $_GET['action'] === 'elementor'
    ) {
		// Select2 CSS + JS (from CDN)
		wp_enqueue_style('select2-css', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
		wp_enqueue_script('select2-js', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array('jquery'), null, true);

		// Custom shared Select2 setup
		wp_enqueue_script(
			'common-select2-js',
			plugin_dir_url(__FILE__) . '../assets/js/common-select2.js',
			array('select2-js'),
			null,
			true
		);

		// Optional: custom styling (inline)
		wp_add_inline_style('select2-css', '
			.select2-container .select2-selection--multiple {
				border-radius: 6px;
				border: 1px solid #ccc;
				min-height: 40px;
				padding: 4px 6px;
			}
			.select2-container--default .select2-selection--multiple .select2-selection__choice {
				background-color: #e9f4ff;
				color: #0073aa;
				border: none;
				border-radius: 15px;
				padding: 3px 10px;
				margin-top: 4px;
			}
			.select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
				color: #0073aa;
				margin-right: 4px;
				font-weight: bold;
				line-height: 30px;
				border-radius: 0;
				padding: 0px 8px;
				border-right: 0;
			}
			.select2-container--default .select2-selection--multiple .select2-selection__choice__display {
				padding-left: 15px;
			}
		');
	}
	wp_enqueue_style(
        'custom-style',
        plugin_dir_url( __FILE__ ) . '../assets/css/custom.css',
        array(),
        '1.0'
    );
    wp_enqueue_style('google-fonts', 'https://fonts.googleapis.com/css2?family=Open+Sans:ital,wght@0,300..800;1,300..800&display=swap', [], null);
}
add_action('wp_enqueue_scripts', 'custom_shortcodes_enqueue_select2_assets');
