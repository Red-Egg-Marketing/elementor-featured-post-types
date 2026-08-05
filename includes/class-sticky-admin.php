<?php
/**
 * List-table admin surface for the featured flag.
 *
 * Core already knows how to save a `sticky` field for any post type: both
 * edit_post() and bulk_edit_posts() stick or unstick from it, gated on
 * capabilities rather than post type. It also already emits the hidden marker
 * that inline-edit-post.js reads to pre-check the box, for every
 * non-hierarchical post type.
 *
 * The only thing missing for custom post types is the markup, because
 * WP_Posts_List_Table::inline_edit() renders the sticky control for `post` only.
 * So this class supplies the UI and nothing else -- no save handler, no
 * JavaScript.
 *
 *   +-----------+----------+
 *   | Title     | Featured |
 *   +-----------+----------+
 *   | Case A    |    *     |
 *   | Case B    |    -     |
 *   +-----------+----------+
 *
 * @package RedEgg\FeaturedPostTypes
 */

namespace RedEgg\FeaturedPostTypes;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Sticky_Admin
 */
class Sticky_Admin {

	/**
	 * Singleton instance.
	 *
	 * @var Sticky_Admin|null
	 */
	private static $instance = null;

	/**
	 * List table column key.
	 */
	const COLUMN = 're_featured';

	/**
	 * Get the singleton.
	 *
	 * @return Sticky_Admin
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

		add_action( 'admin_init', [ $this, 'register_columns' ] );
		add_action( 'quick_edit_custom_box', [ $this, 'render_quick_edit' ], 10, 2 );
		add_action( 'bulk_edit_custom_box', [ $this, 'render_bulk_edit' ], 10, 2 );
		add_action( 'add_inline_data', [ $this, 'add_inline_marker' ], 10, 2 );
	}

	/**
	 * Whether a post type gets the featured controls.
	 *
	 * @param string $post_type Post type name.
	 * @return bool
	 */
	private function supports( $post_type ) {
		return in_array( $post_type, Sticky_Posts::get_supported_post_types(), true );
	}

	/**
	 * Hook the column filters for each supported post type.
	 *
	 * Runs on admin_init so post type registration on `init` has completed.
	 */
	public function register_columns() {

		foreach ( Sticky_Posts::get_supported_post_types() as $post_type ) {
			add_filter( "manage_{$post_type}_posts_columns", [ $this, 'add_column' ] );
			add_action( "manage_{$post_type}_posts_custom_column", [ $this, 'render_column' ], 10, 2 );
		}
	}

	/**
	 * Add the Featured column, just before Date when one exists.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public function add_column( $columns ) {

		if ( ! isset( $columns['date'] ) ) {
			$columns[ self::COLUMN ] = esc_html__( 'Featured', 'elementor-featured-post-types' );
			return $columns;
		}

		$reordered = [];

		foreach ( $columns as $key => $label ) {

			if ( 'date' === $key ) {
				$reordered[ self::COLUMN ] = esc_html__( 'Featured', 'elementor-featured-post-types' );
			}

			$reordered[ $key ] = $label;
		}

		return $reordered;
	}

	/**
	 * Render the column cell.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 */
	public function render_column( $column, $post_id ) {

		if ( self::COLUMN !== $column ) {
			return;
		}

		$is_featured = is_sticky( $post_id );

		printf(
			'<span class="re-featured-flag" title="%1$s">%2$s</span>',
			esc_attr(
				$is_featured
					? esc_html__( 'Featured', 'elementor-featured-post-types' )
					: esc_html__( 'Not featured', 'elementor-featured-post-types' )
			),
			$is_featured ? '&#9733;' : '&mdash;'
		);
	}

	/**
	 * Emit the marker core's inline editor reads to pre-check the box.
	 *
	 * get_inline_data() already does this for every non-hierarchical post type,
	 * which covers the normal case. Hierarchical types are skipped there, so this
	 * fills the gap and keeps behaviour consistent either way.
	 *
	 * @param \WP_Post      $post             Current post.
	 * @param \WP_Post_Type $post_type_object Current post type object.
	 */
	public function add_inline_marker( $post, $post_type_object ) {

		if ( ! $post instanceof \WP_Post || ! $this->supports( $post->post_type ) ) {
			return;
		}

		// Core already printed it for non-hierarchical types.
		if ( ! $post_type_object || empty( $post_type_object->hierarchical ) ) {
			return;
		}

		printf(
			'<div class="sticky">%s</div>',
			is_sticky( $post->ID ) ? 'sticky' : ''
		);
	}

	/**
	 * Render the Quick Edit checkbox.
	 *
	 * The field is named `sticky` so core's inline-edit JS pre-checks it and
	 * edit_post() saves it. Nothing else is required.
	 *
	 * @param string $column    Column key.
	 * @param string $post_type Current post type.
	 */
	public function render_quick_edit( $column, $post_type ) {

		if ( self::COLUMN !== $column || ! $this->supports( $post_type ) ) {
			return;
		}
		?>
		<fieldset class="inline-edit-col-right">
			<div class="inline-edit-col">
				<label class="alignleft">
					<input type="checkbox" name="<?php echo esc_attr( Sticky_Posts::FIELD ); ?>" value="sticky" />
					<span class="checkbox-title"><?php esc_html_e( 'Featured', 'elementor-featured-post-types' ); ?></span>
				</label>
			</div>
		</fieldset>
		<?php
	}

	/**
	 * Render the Bulk Edit dropdown.
	 *
	 * Values mirror core's own sticky bulk control exactly: bulk_edit_posts()
	 * discards the field when it is '' or '-1', treats the literal 'sticky' as on,
	 * and anything else as off.
	 *
	 * @param string $column    Column key.
	 * @param string $post_type Current post type.
	 */
	public function render_bulk_edit( $column, $post_type ) {

		if ( self::COLUMN !== $column || ! $this->supports( $post_type ) ) {
			return;
		}
		?>
		<fieldset class="inline-edit-col-right">
			<div class="inline-edit-col">
				<label class="alignleft">
					<span class="title"><?php esc_html_e( 'Featured', 'elementor-featured-post-types' ); ?></span>
					<select name="<?php echo esc_attr( Sticky_Posts::FIELD ); ?>">
						<option value="-1">&mdash; <?php esc_html_e( 'No Change', 'elementor-featured-post-types' ); ?> &mdash;</option>
						<option value="sticky"><?php esc_html_e( 'Featured', 'elementor-featured-post-types' ); ?></option>
						<option value="unsticky"><?php esc_html_e( 'Not Featured', 'elementor-featured-post-types' ); ?></option>
					</select>
				</label>
				<p class="description" style="margin:4px 0 0;">
					<?php esc_html_e( 'Only one item per post type stays featured, so featuring several of the same type in one action leaves just the last.', 'elementor-featured-post-types' ); ?>
				</p>
			</div>
		</fieldset>
		<?php
	}
}
