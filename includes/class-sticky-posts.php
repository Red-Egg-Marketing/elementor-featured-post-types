<?php
/**
 * Sticky support for custom post types.
 *
 * WordPress keeps sticky posts in a single global `sticky_posts` option and only
 * renders the "stick this post" checkbox for the built-in `post` type. This adds
 * that checkbox to public custom post types and writes through core's
 * stick_post() / unstick_post(), so the option stays in a shape WP_Query already
 * understands.
 *
 *      ___
 *     [___]  one featured item per post type, enforced on save
 *     |   |
 *
 * @package RedEgg\FeaturedPostTypes
 */

namespace RedEgg\FeaturedPostTypes;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Sticky_Posts
 */
class Sticky_Posts {

	/**
	 * Singleton instance.
	 *
	 * @var Sticky_Posts|null
	 */
	private static $instance = null;

	/**
	 * Checkbox field name.
	 *
	 * Core's own. edit_post() and bulk_edit_posts() both stick/unstick from a
	 * field called `sticky` for ANY post type -- WordPress simply never renders
	 * the control outside the built-in `post` type. Reusing the name means core
	 * does the saving and we only supply the missing UI.
	 */
	const FIELD = 'sticky';

	/**
	 * Get the singleton.
	 *
	 * @return Sticky_Posts
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

		add_action( 'post_submitbox_misc_actions', [ $this, 'render_checkbox' ] );
		add_action( 'post_stuck', [ $this, 'enforce_exclusive' ] );
	}

	/**
	 * Post types that get the featured checkbox.
	 *
	 * `post` is excluded because core already renders its own sticky UI; showing
	 * ours too would mean two checkboxes driving one option.
	 *
	 * @return array Post type names.
	 */
	public static function get_supported_post_types() {

		$types = get_post_types( [ 'public' => true ], 'names' );

		unset( $types['post'], $types['page'], $types['attachment'] );

		return apply_filters( 're_featured_sticky_post_types', array_values( $types ) );
	}

	/**
	 * Render the checkbox in the publish box.
	 *
	 * @param \WP_Post $post Current post object.
	 */
	public function render_checkbox( $post ) {

		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		if ( ! in_array( $post->post_type, self::get_supported_post_types(), true ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return;
		}

		$post_type_obj = get_post_type_object( $post->post_type );
		$singular      = ( $post_type_obj && isset( $post_type_obj->labels->singular_name ) )
			? $post_type_obj->labels->singular_name
			: $post->post_type;

		?>
		<div class="misc-pub-section misc-pub-re-featured">
			<label>
				<input type="checkbox" name="<?php echo esc_attr( self::FIELD ); ?>" value="sticky" <?php checked( is_sticky( $post->ID ) ); ?> />
				<?php
					printf(
						/* translators: %s Singular post type label. */
						esc_html__( 'Feature this %s', 'elementor-featured-post-types' ),
						esc_html( $singular )
					);
				?>
			</label>
			<p class="description">
				<?php esc_html_e( 'Only one item per post type can be featured. Checking this clears the previous one.', 'elementor-featured-post-types' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Keep one featured item per post type.
	 *
	 * Hooked to core's post_stuck action, which fires from stick_post() however
	 * the post became sticky -- publish box, Quick Edit, Bulk Edit, REST, or a
	 * direct stick_post() call. One hook covers every entry point.
	 *
	 * unstick_post() fires post_unstuck rather than post_stuck, so there is no
	 * recursion to guard against here.
	 *
	 * @param int $post_id Post that just became sticky.
	 */
	public function enforce_exclusive( $post_id ) {

		$post_type = get_post_type( $post_id );

		// Never touch the blog's own sticky posts.
		if ( ! $post_type || ! in_array( $post_type, self::get_supported_post_types(), true ) ) {
			return;
		}

		if ( ! apply_filters( 're_featured_sticky_exclusive', true, $post_type ) ) {
			return;
		}

		foreach ( self::get_sticky_ids( $post_type ) as $sibling_id ) {
			if ( (int) $sibling_id !== (int) $post_id ) {
				unstick_post( $sibling_id );
			}
		}
	}

	/**
	 * Set or clear the featured flag on a post.
	 *
	 * Provided for programmatic use. The admin screens do not call this -- they
	 * submit core's `sticky` field and let WordPress do the writing.
	 *
	 * @param int         $post_id   Post ID.
	 * @param bool        $featured  Whether the post should be featured.
	 * @param string|null $post_type Unused; retained for backward compatibility.
	 */
	public static function set_featured( $post_id, $featured, $post_type = null ) {

		$post_id = absint( $post_id );

		if ( ! $post_id ) {
			return;
		}

		if ( $featured ) {
			// Exclusivity is applied by enforce_exclusive() on post_stuck.
			stick_post( $post_id );
		} else {
			unstick_post( $post_id );
		}
	}

	/**
	 * Sticky post IDs belonging to one post type, newest first.
	 *
	 * @param string $post_type Post type name.
	 * @return array Post IDs.
	 */
	public static function get_sticky_ids( $post_type ) {

		$sticky = get_option( 'sticky_posts' );

		if ( empty( $sticky ) || ! is_array( $sticky ) ) {
			return [];
		}

		$sticky = array_filter( array_map( 'absint', $sticky ) );

		/*
		 * Guard hard on empty. WP_Query drops the post__in clause when the array
		 * is empty, which returns every post of this type rather than none.
		 */
		if ( empty( $sticky ) ) {
			return [];
		}

		return get_posts(
			[
				'post__in'            => $sticky,
				'post_type'           => $post_type,
				'post_status'         => 'publish',
				'posts_per_page'      => -1,
				'fields'              => 'ids',
				'orderby'             => 'date',
				'order'               => 'DESC',
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
			]
		);
	}

	/**
	 * Resolve the single featured post ID for a post type.
	 *
	 * @param string $post_type Post type name.
	 * @param bool   $fallback  Whether to fall back to the most recent post.
	 * @return int Post ID, or 0 when nothing is available.
	 */
	public static function get_featured_id( $post_type, $fallback = true ) {

		$ids = self::get_sticky_ids( $post_type );

		if ( ! empty( $ids ) ) {
			return (int) $ids[0];
		}

		if ( ! $fallback ) {
			return 0;
		}

		$latest = get_posts(
			[
				'post_type'           => $post_type,
				'post_status'         => 'publish',
				'posts_per_page'      => 1,
				'fields'              => 'ids',
				'orderby'             => 'date',
				'order'               => 'DESC',
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
			]
		);

		return empty( $latest ) ? 0 : (int) $latest[0];
	}

	/**
	 * Build the ordered slide set: one post per post type.
	 *
	 * This is the piece a single WP_Query cannot give you. Querying
	 * post_type => [ a, b, c ] returns the N most recent across all three with no
	 * guarantee of one of each, so we query per type instead.
	 *
	 * @param array $post_types Post type names, in display order.
	 * @param bool  $fallback   Whether to fall back to the latest post per type.
	 * @return array List of [ post_id, post_type, label ].
	 */
	public static function get_slides( $post_types, $fallback = true ) {

		$slides = [];

		foreach ( (array) $post_types as $post_type ) {

			if ( ! is_string( $post_type ) || ! post_type_exists( $post_type ) ) {
				continue;
			}

			$post_id = self::get_featured_id( $post_type, $fallback );

			if ( ! $post_id ) {
				continue;
			}

			$post_type_obj = get_post_type_object( $post_type );

			$slides[] = [
				'post_id'   => $post_id,
				'post_type' => $post_type,
				/*
				 * Singular, to match the tab labels in the design: "Case Study",
				 * not "Case Studies". The ->label property is the plural.
				 */
				'label'     => ( $post_type_obj && isset( $post_type_obj->labels->singular_name ) )
					? $post_type_obj->labels->singular_name
					: $post_type,
			];
		}

		return apply_filters( 're_featured_slides', $slides, $post_types );
	}
}
