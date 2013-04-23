<?php
/*
Plugin Name: BP Blog Creation Moderation
Version: 0.1-alpha
Author: Boone Gorges
Author URI: http://boone.gorg.es
Text Domain: bp-blog-creation-moderation
Domain Path: /languages
*/

function bpbcm_loader() {
	if ( ! is_multisite() ) {
		return;
	}

	if ( ! bp_is_active( 'blogs' ) ) {
		return;
	}

	if ( ! in_array( get_site_option( 'registration' ), array( 'blog', 'all' ) ) ) {
		return;
	}

	require( __DIR__ . '/includes/bpbcm.php' );
}
add_action( 'bp_include', 'bpbcm_loader' );

