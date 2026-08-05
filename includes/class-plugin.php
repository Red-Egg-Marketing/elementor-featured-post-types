<?php
/**
 * Plugin bootstrap.
 *
 * @package RedEgg\FeaturedPostTypes
 */

namespace RedEgg\FeaturedPostTypes;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Plugin
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Script and style handle for the rotator.
	 */
	const HANDLE = 're-featured-post-types';

	/**
	 * Get the singleton.
	 *
	 * @return Plugin
	 */
	public static function instance() {

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {

		Sticky_Posts::instance();

		if ( is_admin() ) {
			Sticky_Admin::instance();
		}

		add_action( 'elementor/widgets/register', [ $this, 'register_widgets' ] );
		add_action( 'elementor/frontend/after_register_scripts', [ $this, 'register_scripts' ] );
		add_action( 'elementor/frontend/after_register_styles', [ $this, 'register_styles' ] );
		add_action( 'init', [ $this, 'load_textdomain' ] );
	}

	/**
	 * Load translations.
	 */
	public function load_textdomain() {

		load_plugin_textdomain(
			'elementor-featured-post-types',
			false,
			dirname( plugin_basename( RE_FEATURED_FILE ) ) . '/languages'
		);
	}

	/**
	 * Register the widget with Elementor.
	 *
	 * @param \Elementor\Widgets_Manager $widgets_manager Elementor widget manager.
	 */
	public function register_widgets( $widgets_manager ) {

		require_once RE_FEATURED_DIR . 'includes/abstract-rotator-widget.php';
		require_once RE_FEATURED_DIR . 'includes/class-widget-post-types.php';
		require_once RE_FEATURED_DIR . 'includes/class-widget-selected-posts.php';

		$widgets_manager->register( new Widget_Post_Types() );
		$widgets_manager->register( new Widget_Selected_Posts() );
	}

	/**
	 * Register frontend scripts.
	 *
	 * Elementor ships and registers its own `swiper` handle. We only fall back to
	 * a CDN copy if that handle is absent, so on a normal install no third-party
	 * request is made at all.
	 */
	public function register_scripts() {

		if ( ! wp_script_is( 'swiper', 'registered' ) ) {
			wp_register_script(
				'swiper',
				'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js',
				[],
				'11.0.0',
				true
			);
		}

		wp_register_script(
			self::HANDLE,
			RE_FEATURED_URL . 'assets/js/rotator.js',
			[ 'swiper' ],
			RE_FEATURED_VERSION,
			true
		);
	}

	/**
	 * Register frontend styles.
	 */
	public function register_styles() {

		if ( ! wp_style_is( 'swiper', 'registered' ) ) {
			wp_register_style(
				'swiper',
				'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css',
				[],
				'11.0.0'
			);
		}

		wp_register_style(
			self::HANDLE,
			RE_FEATURED_URL . 'assets/css/rotator.css',
			[ 'swiper' ],
			RE_FEATURED_VERSION
		);
	}
}
