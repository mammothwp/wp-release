<?php
/**
 * Plugin Name:       MMWP Fixture Broken
 * Description:       Deliberately broken plugin used to test mammothwp/wp-release.
 * Version:           2.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Mammoth WP
 * License:           GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

function mmwp_fixture_broken_footer() {
	echo $_GET['name'];
}
add_action( 'wp_footer', 'mmwp_fixture_broken_footer' );
