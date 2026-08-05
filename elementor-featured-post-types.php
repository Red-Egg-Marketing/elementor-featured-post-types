<?php
/**
 * Plugin Name: Elementor Featured Post Types
 * Description: An Elementor widget that rotates one featured post per post type, with a labelled countdown tab for each. Adds sticky support to custom post types.
 * Version:     1.0.0
 * Author:      Red Egg Marketing
 * Author URI:  https://redeggmarketing.com
 * Text Domain: elementor-featured-post-types
 * License:     GPL-2.0-or-later
 *
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Requires Plugins:  elementor
 *
 * @package RedEgg\FeaturedPostTypes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'RE_FEATURED_VERSION', '1.0.0' );
define( 'RE_FEATURED_FILE', __FILE__ );
define( 'RE_FEATURED_DIR', plugin_dir_path( __FILE__ ) );
define( 'RE_FEATURED_URL', plugin_dir_url( __FILE__ ) );

/**
 * Minimum Elementor version this widget's control API is known to work against.
 */
define( 'RE_FEATURED_MIN_ELEMENTOR', '3.5.0' );

/**
 * Boot the plugin once all others have loaded.
 *
 * Elementor is checked here rather than on activation, so deactivating Elementor
 * later degrades to an admin notice instead of a fatal.
 */
function re_featured_bootstrap() {

	if ( ! did_action( 'elementor/loaded' ) ) {
		add_action( 'admin_notices', 're_featured_missing_elementor_notice' );
		return;
	}

	if ( defined( 'ELEMENTOR_VERSION' ) && version_compare( ELEMENTOR_VERSION, RE_FEATURED_MIN_ELEMENTOR, '<' ) ) {
		add_action( 'admin_notices', 're_featured_old_elementor_notice' );
		return;
	}

	require_once RE_FEATURED_DIR . 'includes/class-sticky-posts.php';
	require_once RE_FEATURED_DIR . 'includes/class-plugin.php';

	\RedEgg\FeaturedPostTypes\Plugin::instance();
}
add_action( 'plugins_loaded', 're_featured_bootstrap' );

/**
 * Notice shown when Elementor is not active.
 */
function re_featured_missing_elementor_notice() {

	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	printf(
		'<div class="notice notice-warning"><p>%s</p></div>',
		esc_html__( 'Elementor Featured Post Types requires Elementor to be installed and active.', 'elementor-featured-post-types' )
	);
}

/**
 * Notice shown when Elementor is too old.
 */
function re_featured_old_elementor_notice() {

	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	printf(
		'<div class="notice notice-warning"><p>%s</p></div>',
		sprintf(
			/* translators: %s Minimum Elementor version. */
			esc_html__( 'Elementor Featured Post Types requires Elementor %s or newer.', 'elementor-featured-post-types' ),
			esc_html( RE_FEATURED_MIN_ELEMENTOR )
		)
	);
}
