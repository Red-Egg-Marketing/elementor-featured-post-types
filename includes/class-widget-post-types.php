<?php
/**
 * Featured Post Types widget.
 *
 * One featured post per selected post type, resolved from the sticky_posts
 * option. Everything else lives in Rotator_Widget.
 *
 * @package RedEgg\FeaturedPostTypes
 */

namespace RedEgg\FeaturedPostTypes;

use Elementor\Controls_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Widget_Post_Types
 */
class Widget_Post_Types extends Rotator_Widget {

	/**
	 * Widget slug.
	 *
	 * Unchanged from 1.0.0 -- pages already built with this widget depend on it.
	 *
	 * @return string
	 */
	public function get_name() {
		return 're-featured-post-types';
	}

	/**
	 * Widget title.
	 *
	 * @return string
	 */
	public function get_title() {
		return esc_html__( 'Featured Post Types', 'elementor-featured-post-types' );
	}

	/**
	 * Search keywords.
	 *
	 * @return array
	 */
	public function get_keywords() {
		return [ 'featured', 'insights', 'post', 'rotator', 'carousel', 'sticky' ];
	}

	/**
	 * Public post types as an id => label map for the control.
	 *
	 * @return array
	 */
	private function get_post_type_options() {

		$post_types = get_post_types( [ 'public' => true ], 'objects' );
		$options    = [];

		foreach ( $post_types as $post_type ) {

			if ( 'attachment' === $post_type->name ) {
				continue;
			}

			$options[ $post_type->name ] = $post_type->label;
		}

		return apply_filters( 're_featured_post_type_options', $options );
	}

	/**
	 * Query controls.
	 */
	protected function register_query_section() {

		$this->start_controls_section(
			'section_query',
			[
				'label' => esc_html__( 'Query', 'elementor-featured-post-types' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'post_types',
			[
				'label'       => esc_html__( 'Post Types', 'elementor-featured-post-types' ),
				'description' => esc_html__( 'One featured item is shown per post type, in the order listed here.', 'elementor-featured-post-types' ),
				'type'        => Controls_Manager::SELECT2,
				'multiple'    => true,
				'label_block' => true,
				'options'     => $this->get_post_type_options(),
				'default'     => [ 'post' ],
			]
		);

		$this->add_control(
			'fallback_to_latest',
			[
				'label'        => esc_html__( 'Fall Back To Latest', 'elementor-featured-post-types' ),
				'description'  => esc_html__( 'When nothing of a post type is featured, show its most recent item instead of skipping the tab.', 'elementor-featured-post-types' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Yes', 'elementor-featured-post-types' ),
				'label_off'    => esc_html__( 'No', 'elementor-featured-post-types' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			]
		);

		$this->end_controls_section();
	}

	/**
	 * Resolve one featured post per selected post type.
	 *
	 * @param array $settings Widget settings.
	 * @return array
	 */
	protected function get_slides( $settings ) {

		$post_types = isset( $settings['post_types'] ) ? $settings['post_types'] : [];

		if ( ! is_array( $post_types ) ) {
			$post_types = array_filter( [ $post_types ] );
		}

		$fallback = ( 'yes' === $settings['fallback_to_latest'] );

		return Sticky_Posts::get_slides( $post_types, $fallback );
	}

	/**
	 * Editor-only notice when nothing resolves.
	 */
	protected function get_empty_message() {
		return esc_html__( 'No featured items found. Choose one or more post types under Query, then feature an item from its edit screen.', 'elementor-featured-post-types' );
	}
}
