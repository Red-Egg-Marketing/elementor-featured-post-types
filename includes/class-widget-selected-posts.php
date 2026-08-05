<?php
/**
 * Featured Posts widget.
 *
 * Same panel and countdown tabs as Widget_Post_Types, but the editor hand-picks
 * each post rather than the plugin resolving one per post type from sticky flags.
 *
 * @package RedEgg\FeaturedPostTypes
 */

namespace RedEgg\FeaturedPostTypes;

use Elementor\Controls_Manager;
use Elementor\Repeater;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Widget_Selected_Posts
 */
class Widget_Selected_Posts extends Rotator_Widget {

	/**
	 * Widget slug.
	 *
	 * @return string
	 */
	public function get_name() {
		return 're-featured-selected-posts';
	}

	/**
	 * Widget title.
	 *
	 * @return string
	 */
	public function get_title() {
		return esc_html__( 'Featured Posts (Manual)', 'elementor-featured-post-types' );
	}

	/**
	 * Search keywords.
	 *
	 * @return array
	 */
	public function get_keywords() {
		return [ 'featured', 'insights', 'post', 'rotator', 'carousel', 'manual', 'curated' ];
	}

	/**
	 * Selectable posts as an ID => "Post Type — Title" map.
	 *
	 * Elementor's free SELECT2 has no AJAX search, so the list is prefetched. That
	 * is fine for a curated picker on a normal site and awful on one with tens of
	 * thousands of posts, hence the cap and the filter. Only built in an editing
	 * context -- see Rotator_Widget::is_editing_context().
	 *
	 * @return array
	 */
	private function get_post_options() {

		if ( ! $this->is_editing_context() ) {
			return [];
		}

		/**
		 * Maximum posts listed per post type in the picker.
		 *
		 * @param int $limit Default 100.
		 */
		$limit = (int) apply_filters( 're_featured_picker_limit', 100 );
		$limit = $limit > 0 ? $limit : 100;

		$post_types = get_post_types( [ 'public' => true ], 'objects' );
		$options    = [];

		foreach ( $post_types as $post_type ) {

			if ( 'attachment' === $post_type->name ) {
				continue;
			}

			$posts = get_posts(
				[
					'post_type'           => $post_type->name,
					'post_status'         => 'publish',
					'posts_per_page'      => $limit,
					'orderby'             => 'title',
					'order'               => 'ASC',
					'ignore_sticky_posts' => true,
					'no_found_rows'       => true,
					'suppress_filters'    => false,
				]
			);

			$singular = isset( $post_type->labels->singular_name )
				? $post_type->labels->singular_name
				: $post_type->name;

			foreach ( $posts as $post ) {

				$title = ! empty( $post->post_title )
					? $post->post_title
					/* translators: %d Post ID. */
					: sprintf( esc_html__( '(no title) #%d', 'elementor-featured-post-types' ), $post->ID );

				$options[ $post->ID ] = sprintf( '%1$s — %2$s', $singular, $title );
			}
		}

		return apply_filters( 're_featured_picker_options', $options );
	}

	/**
	 * Query controls.
	 */
	protected function register_query_section() {

		$this->start_controls_section(
			'section_query',
			[
				'label' => esc_html__( 'Posts', 'elementor-featured-post-types' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			]
		);

		$repeater = new Repeater();

		$repeater->add_control(
			'post_id',
			[
				'label'       => esc_html__( 'Post', 'elementor-featured-post-types' ),
				'type'        => Controls_Manager::SELECT2,
				'label_block' => true,
				'options'     => $this->get_post_options(),
				'default'     => '',
			]
		);

		$repeater->add_control(
			'label_override',
			[
				'label'       => esc_html__( 'Tab Label', 'elementor-featured-post-types' ),
				'description' => esc_html__( 'Leave blank to use the post type name. Set this when two picks share a post type, so the tabs are not identical.', 'elementor-featured-post-types' ),
				'type'        => Controls_Manager::TEXT,
				'label_block' => true,
				'default'     => '',
			]
		);

		$this->add_control(
			'featured_items',
			[
				'label'       => esc_html__( 'Featured Posts', 'elementor-featured-post-types' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $repeater->get_controls(),
				'default'     => [],
				'title_field' => '{{{ label_override ? label_override : ( post_id ? "Post #" + post_id : "New item" ) }}}',
			]
		);

		$this->end_controls_section();
	}

	/**
	 * Resolve slides from the hand-picked list.
	 *
	 * Silently skips anything that has since been trashed, unpublished, or had
	 * its post type made non-public, so a stale pick degrades to a missing tab
	 * rather than a broken panel.
	 *
	 * @param array $settings Widget settings.
	 * @return array
	 */
	protected function get_slides( $settings ) {

		$items = isset( $settings['featured_items'] ) ? $settings['featured_items'] : [];

		if ( ! is_array( $items ) ) {
			return [];
		}

		$slides = [];

		foreach ( $items as $item ) {

			$post_id = isset( $item['post_id'] ) ? absint( $item['post_id'] ) : 0;

			if ( ! $post_id ) {
				continue;
			}

			$post = get_post( $post_id );

			if ( ! $post instanceof \WP_Post || 'publish' !== $post->post_status ) {
				continue;
			}

			$post_type_obj = get_post_type_object( $post->post_type );

			if ( ! $post_type_obj || empty( $post_type_obj->public ) ) {
				continue;
			}

			$label = ! empty( $item['label_override'] )
				? $item['label_override']
				: ( isset( $post_type_obj->labels->singular_name ) ? $post_type_obj->labels->singular_name : $post->post_type );

			$slides[] = [
				'post_id'   => $post_id,
				'post_type' => $post->post_type,
				'label'     => $label,
			];
		}

		return apply_filters( 're_featured_selected_slides', $slides, $settings );
	}

	/**
	 * Editor-only notice when nothing resolves.
	 */
	protected function get_empty_message() {
		return esc_html__( 'No posts selected yet. Add one or more items under Posts.', 'elementor-featured-post-types' );
	}
}
