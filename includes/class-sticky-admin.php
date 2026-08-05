<?php
/**
 * List-table admin surface for the featured flag.
 *
 * Core exposes "Make this post sticky" in Quick Edit for the built-in `post` type
 * only -- it is hardcoded in WP_Posts_List_Table::inline_edit(). This adds the
 * equivalent for supported custom post types: a Featured column, a Quick Edit
 * checkbox, and a tri-state Bulk Edit dropdown.
 *
 *   ┌───────────┬──────────┐
 *   │ Title     │ Featured │
 *   ├───────────┼──────────┤
 *   │ Case A    │    ★     │
 *   │ Case B    │    —     │
 *   └───────────┴──────────┘
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
	 * Hidden marker proving the Quick Edit fieldset was submitted.
	 *
	 * An unchecked checkbox sends nothing, so without this we could not tell
	 * "editor cleared the box" from "this request had nothing to do with us".
	 */
	const INLINE_MARKER = 're_featured_inline';

	/**
	 * Quick Edit checkbox name.
	 */
	const INLINE_FIELD = 're_featured_sticky_inline';

	/**
	 * Bulk Edit select name.
	 */
	const BULK_FIELD = 're_featured_sticky_bulk';

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
		add_action( 'save_post', [ $this, 'save_inline' ], 10, 2 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
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
	 * The data attribute is what the Quick Edit JS reads to pre-check the box,
	 * since WordPress's inline editor has no knowledge of custom fields.
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
			'<span class="re-featured-flag" data-featured="%1$d" title="%2$s">%3$s</span>',
			$is_featured ? 1 : 0,
			esc_attr(
				$is_featured
					? esc_html__( 'Featured', 'elementor-featured-post-types' )
					: esc_html__( 'Not featured', 'elementor-featured-post-types' )
			),
			$is_featured ? '&#9733;' : '&mdash;'
		);
	}

	/**
	 * Render the Quick Edit checkbox.
	 *
	 * @param string $column    Column key.
	 * @param string $post_type Current post type.
	 */
	public function render_quick_edit( $column, $post_type ) {

		if ( self::COLUMN !== $column ) {
			return;
		}

		if ( ! in_array( $post_type, Sticky_Posts::get_supported_post_types(), true ) ) {
			return;
		}
		?>
		<fieldset class="inline-edit-col-right">
			<div class="inline-edit-col">
				<input type="hidden" name="<?php echo esc_attr( self::INLINE_MARKER ); ?>" value="1" />
				<label class="alignleft">
					<input type="checkbox" name="<?php echo esc_attr( self::INLINE_FIELD ); ?>" value="1" />
					<span class="checkbox-title"><?php esc_html_e( 'Featured', 'elementor-featured-post-types' ); ?></span>
				</label>
			</div>
		</fieldset>
		<?php
	}

	/**
	 * Render the Bulk Edit dropdown.
	 *
	 * Tri-state rather than a checkbox: bulk edit must be able to mean "leave
	 * these alone", which a checkbox cannot express.
	 *
	 * @param string $column    Column key.
	 * @param string $post_type Current post type.
	 */
	public function render_bulk_edit( $column, $post_type ) {

		if ( self::COLUMN !== $column ) {
			return;
		}

		if ( ! in_array( $post_type, Sticky_Posts::get_supported_post_types(), true ) ) {
			return;
		}
		?>
		<fieldset class="inline-edit-col-right">
			<div class="inline-edit-col">
				<label class="alignleft">
					<span class="title"><?php esc_html_e( 'Featured', 'elementor-featured-post-types' ); ?></span>
					<select name="<?php echo esc_attr( self::BULK_FIELD ); ?>">
						<option value="">&mdash; <?php esc_html_e( 'No change', 'elementor-featured-post-types' ); ?> &mdash;</option>
						<option value="1"><?php esc_html_e( 'Featured', 'elementor-featured-post-types' ); ?></option>
						<option value="0"><?php esc_html_e( 'Not featured', 'elementor-featured-post-types' ); ?></option>
					</select>
				</label>
				<p class="description" style="margin:4px 0 0;">
					<?php esc_html_e( 'Only one item per post type stays featured, so featuring several of the same type in one action leaves just the last.', 'elementor-featured-post-types' ); ?>
				</p>
			</div>
		</fieldset>
		<?php
	}

	/**
	 * Persist Quick Edit and Bulk Edit submissions.
	 *
	 * Kept separate from Sticky_Posts::save_checkbox() because each entry point
	 * carries a different nonce and a different way of expressing "no change".
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 */
	public function save_inline( $post_id, $post ) {

		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( ! in_array( $post->post_type, Sticky_Posts::get_supported_post_types(), true ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Bulk Edit posts the whole list-table form back to edit.php.
		if ( isset( $_REQUEST['bulk_edit'], $_REQUEST[ self::BULK_FIELD ] ) ) {

			check_admin_referer( 'bulk-posts' );

			$value = sanitize_text_field( wp_unslash( $_REQUEST[ self::BULK_FIELD ] ) );

			if ( '' === $value ) {
				return;
			}

			Sticky_Posts::set_featured( $post_id, '1' === $value, $post->post_type );

			return;
		}

		// Quick Edit arrives over AJAX with core's inline nonce.
		if ( isset( $_POST[ self::INLINE_MARKER ] ) ) {

			check_ajax_referer( 'inlineeditnonce', '_inline_edit' );

			Sticky_Posts::set_featured(
				$post_id,
				isset( $_POST[ self::INLINE_FIELD ] ),
				$post->post_type
			);
		}
	}

	/**
	 * Enqueue the Quick Edit helper on relevant list tables only.
	 *
	 * @param string $hook_suffix Current admin page.
	 */
	public function enqueue_scripts( $hook_suffix ) {

		if ( 'edit.php' !== $hook_suffix ) {
			return;
		}

		$screen = get_current_screen();

		if ( ! $screen || ! in_array( $screen->post_type, Sticky_Posts::get_supported_post_types(), true ) ) {
			return;
		}

		wp_enqueue_script(
			're-featured-quick-edit',
			RE_FEATURED_URL . 'assets/js/sticky-quick-edit.js',
			[ 'jquery', 'inline-edit-post' ],
			RE_FEATURED_VERSION,
			true
		);
	}
}
