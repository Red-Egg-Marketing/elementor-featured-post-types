<?php
/**
 * Shared base for the rotator widgets.
 *
 * Both widgets render an identical panel and identical countdown tabs; the only
 * thing that differs is how the slide list is resolved. Everything except that
 * resolution lives here, so a new rotator variant is one small subclass rather
 * than a fork of 650 lines.
 *
 * Subclasses supply:
 *   get_name()              Elementor widget slug
 *   get_title()             Editor label
 *   register_query_section() The controls that decide which posts appear
 *   get_slides()            [ post_id, post_type, label ] in display order
 *
 * @package RedEgg\FeaturedPostTypes
 */

namespace RedEgg\FeaturedPostTypes;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Image_Size;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;
use Elementor\Core\Kits\Documents\Tabs\Global_Colors;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Rotator_Widget
 */
abstract class Rotator_Widget extends Widget_Base {

	/**
	 * Resolve the slides to render.
	 *
	 * @param array $settings Widget settings.
	 * @return array List of [ post_id, post_type, label ].
	 */
	abstract protected function get_slides( $settings );

	/**
	 * Register the controls that decide which posts appear.
	 */
	abstract protected function register_query_section();

	/**
	 * Message shown in the editor when no slides resolve.
	 *
	 * @return string
	 */
	abstract protected function get_empty_message();

	/**
	 * Widget icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-post-slider';
	}

	/**
	 * Widget categories.
	 *
	 * @return array
	 */
	public function get_categories() {
		return [ 'general' ];
	}

	/**
	 * Scripts this widget needs.
	 *
	 * Elementor enqueues these only on pages where the widget is present, which
	 * is why registration lives in Plugin and not here.
	 *
	 * @return array
	 */
	public function get_script_depends() {
		return [ 'swiper', Plugin::HANDLE ];
	}

	/**
	 * Styles this widget needs.
	 *
	 * @return array
	 */
	public function get_style_depends() {
		return [ 'swiper', Plugin::HANDLE ];
	}

	/**
	 * Whether we are in a context where expensive control option lists are worth
	 * building.
	 *
	 * Elementor instantiates widgets and registers controls on the frontend too.
	 * Prefetching post lists there would mean a query per public post type on
	 * every page view, for options nobody will see -- and saved values are read
	 * back by ID, so an empty option list on the frontend renders identically.
	 *
	 * @return bool
	 */
	protected function is_editing_context() {

		if ( is_admin() || wp_doing_ajax() ) {
			return true;
		}

		if ( class_exists( '\Elementor\Plugin' ) ) {
			$elementor = \Elementor\Plugin::$instance;

			if ( isset( $elementor->editor ) && $elementor->editor->is_edit_mode() ) {
				return true;
			}

			if ( isset( $elementor->preview ) && $elementor->preview->is_preview_mode() ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Register controls.
	 */
	protected function register_controls() {

		$this->register_query_section();
		$this->register_panel_section();
		$this->register_rotator_section();
		$this->register_panel_style_section();
		$this->register_tabs_style_section();
	}

	/**
	 * Panel content controls.
	 */
	protected function register_panel_section() {

		$this->start_controls_section(
			'section_panel',
			[
				'label' => esc_html__( 'Panel', 'elementor-featured-post-types' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'eyebrow',
			[
				'label'       => esc_html__( 'Eyebrow Heading', 'elementor-featured-post-types' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => esc_html__( 'Featured Insights', 'elementor-featured-post-types' ),
				'label_block' => true,
			]
		);

		$this->add_control(
			'title_tag',
			[
				'label'   => esc_html__( 'Title HTML Tag', 'elementor-featured-post-types' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'h3',
				'options' => [
					'h1'   => 'H1',
					'h2'   => 'H2',
					'h3'   => 'H3',
					'h4'   => 'H4',
					'h5'   => 'H5',
					'h6'   => 'H6',
					'div'  => 'div',
					'span' => 'span',
					'p'    => 'p',
				],
			]
		);

		$this->add_control(
			'show_excerpt',
			[
				'label'        => esc_html__( 'Show Excerpt', 'elementor-featured-post-types' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			]
		);

		$this->add_control(
			'excerpt_length',
			[
				'label'     => esc_html__( 'Excerpt Words', 'elementor-featured-post-types' ),
				'type'      => Controls_Manager::NUMBER,
				'default'   => 40,
				'min'       => 5,
				'condition' => [ 'show_excerpt' => 'yes' ],
			]
		);

		$this->add_control(
			'show_cta',
			[
				'label'        => esc_html__( 'Show Button', 'elementor-featured-post-types' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			]
		);

		$this->add_control(
			'cta_text',
			[
				'label'     => esc_html__( 'Button Text', 'elementor-featured-post-types' ),
				'type'      => Controls_Manager::TEXT,
				'default'   => esc_html__( 'Read More', 'elementor-featured-post-types' ),
				'condition' => [ 'show_cta' => 'yes' ],
			]
		);

		$this->add_group_control(
			Group_Control_Image_Size::get_type(),
			[
				'name'    => 'thumbnail',
				'default' => 'large',
				'exclude' => [ 'custom' ],
			]
		);

		$this->end_controls_section();
	}

	/**
	 * Rotator behaviour controls.
	 */
	protected function register_rotator_section() {

		$this->start_controls_section(
			'section_rotator',
			[
				'label' => esc_html__( 'Rotator', 'elementor-featured-post-types' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'autoplay_speed',
			[
				'label'       => esc_html__( 'Duration Per Slide (ms)', 'elementor-featured-post-types' ),
				'description' => esc_html__( 'Also sets how long the countdown bar takes to fill.', 'elementor-featured-post-types' ),
				'type'        => Controls_Manager::NUMBER,
				'default'     => 6000,
				'min'         => 1000,
				'step'        => 500,
			]
		);

		$this->add_control(
			'transition_speed',
			[
				'label'   => esc_html__( 'Fade Duration (ms)', 'elementor-featured-post-types' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 500,
				'min'     => 0,
				'step'    => 50,
			]
		);

		$this->add_control(
			'pause_on_hover',
			[
				'label'        => esc_html__( 'Pause On Hover', 'elementor-featured-post-types' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			]
		);

		$this->end_controls_section();
	}

	/**
	 * Panel style controls.
	 */
	protected function register_panel_style_section() {

		$this->start_controls_section(
			'section_panel_style',
			[
				'label' => esc_html__( 'Panel', 'elementor-featured-post-types' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_responsive_control(
			'image_width',
			[
				'label'      => esc_html__( 'Image Column Width', 'elementor-featured-post-types' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ '%' ],
				'range'      => [
					'%' => [
						'min' => 30,
						'max' => 80,
					],
				],
				'default'    => [
					'size' => 62,
					'unit' => '%',
				],
			]
		);

		$this->add_responsive_control(
			'column_gap',
			[
				'label'      => esc_html__( 'Column Gap', 'elementor-featured-post-types' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'rem' ],
				'range'      => [
					'px' => [
						'min' => 0,
						'max' => 120,
					],
				],
				'default'    => [
					'size' => 48,
					'unit' => 'px',
				],
				'selectors'  => [
					'{{WRAPPER}} .refp-rotator__panel' => 'gap: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'eyebrow_color',
			[
				'label'     => esc_html__( 'Eyebrow Color', 'elementor-featured-post-types' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .refp-rotator__eyebrow' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'eyebrow_typography',
				'global'   => [ 'default' => Global_Typography::TYPOGRAPHY_ACCENT ],
				'selector' => '{{WRAPPER}} .refp-rotator__eyebrow',
			]
		);

		$this->add_control(
			'title_color',
			[
				'label'     => esc_html__( 'Title Color', 'elementor-featured-post-types' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .refp-rotator__title a' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'title_typography',
				'global'   => [ 'default' => Global_Typography::TYPOGRAPHY_PRIMARY ],
				'selector' => '{{WRAPPER}} .refp-rotator__title',
			]
		);

		$this->add_control(
			'excerpt_color',
			[
				'label'     => esc_html__( 'Excerpt Color', 'elementor-featured-post-types' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .refp-rotator__excerpt' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'excerpt_typography',
				'global'   => [ 'default' => Global_Typography::TYPOGRAPHY_TEXT ],
				'selector' => '{{WRAPPER}} .refp-rotator__excerpt',
			]
		);

		$this->end_controls_section();
	}

	/**
	 * Tab and countdown style controls.
	 */
	protected function register_tabs_style_section() {

		$this->start_controls_section(
			'section_tabs_style',
			[
				'label' => esc_html__( 'Tabs & Countdown', 'elementor-featured-post-types' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_control(
			'tab_label_color',
			[
				'label'     => esc_html__( 'Label Color', 'elementor-featured-post-types' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .refp-rotator__tab-label' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'tab_label_color_active',
			[
				'label'     => esc_html__( 'Active Label Color', 'elementor-featured-post-types' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .refp-rotator__tab.is-active .refp-rotator__tab-label' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'tab_label_typography',
				'global'   => [ 'default' => Global_Typography::TYPOGRAPHY_ACCENT ],
				'selector' => '{{WRAPPER}} .refp-rotator__tab-label',
			]
		);

		$this->add_control(
			'track_color',
			[
				'label'     => esc_html__( 'Track Color', 'elementor-featured-post-types' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .refp-rotator__tab-track' => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'fill_color',
			[
				'label'     => esc_html__( 'Countdown Fill Color', 'elementor-featured-post-types' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => [ 'default' => Global_Colors::COLOR_PRIMARY ],
				'selectors' => [
					'{{WRAPPER}} .refp-rotator__tab-fill' => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'track_height',
			[
				'label'      => esc_html__( 'Track Height', 'elementor-featured-post-types' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px' ],
				'range'      => [
					'px' => [
						'min' => 1,
						'max' => 12,
					],
				],
				'default'    => [
					'size' => 3,
					'unit' => 'px',
				],
				'selectors'  => [
					'{{WRAPPER}} .refp-rotator__tab-track' => 'height: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();
	}

	/**
	 * Trim an excerpt for the panel.
	 *
	 * @param int   $post_id  Post ID.
	 * @param array $settings Widget settings.
	 * @return string
	 */
	protected function get_excerpt( $post_id, $settings ) {

		$length = isset( $settings['excerpt_length'] ) ? absint( $settings['excerpt_length'] ) : 40;
		$length = $length ? $length : 40;

		$raw = get_the_excerpt( $post_id );

		if ( empty( $raw ) ) {
			$raw = get_post_field( 'post_content', $post_id );
		}

		return wp_trim_words( wp_strip_all_tags( $raw ), $length, '&hellip;' );
	}

	/**
	 * Validate a title tag against an allow-list.
	 *
	 * @param string $tag Requested tag.
	 * @return string
	 */
	protected function get_title_tag( $tag ) {

		$allowed = [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span', 'p' ];

		return in_array( $tag, $allowed, true ) ? $tag : 'h3';
	}

	/**
	 * Render the widget.
	 */
	protected function render() {

		$settings = $this->get_settings_for_display();

		$slides = $this->get_slides( $settings );

		if ( empty( $slides ) ) {
			$this->render_empty_notice();
			return;
		}

		$delay = absint( $settings['autoplay_speed'] );
		$delay = $delay ? $delay : 6000;
		$speed = absint( $settings['transition_speed'] );

		$config = [
			'delay'        => $delay,
			'speed'        => $speed,
			'pauseOnHover' => ( 'yes' === $settings['pause_on_hover'] ),
		];

		$title_tag  = $this->get_title_tag( $settings['title_tag'] );
		$image_size = isset( $settings['thumbnail_size'] ) ? $settings['thumbnail_size'] : 'large';
		?>
		<div class="refp-rotator" data-refp-config="<?php echo esc_attr( wp_json_encode( $config ) ); ?>">

			<?php if ( ! empty( $settings['eyebrow'] ) ) : ?>
				<div class="refp-rotator__eyebrow"><?php echo esc_html( $settings['eyebrow'] ); ?></div>
			<?php endif; ?>

			<div class="swiper refp-rotator__swiper">
				<div class="swiper-wrapper">
					<?php
					foreach ( $slides as $index => $slide ) {
						include RE_FEATURED_DIR . 'templates/slide.php';
					}
					?>
				</div><!-- .swiper-wrapper -->
			</div><!-- .refp-rotator__swiper -->

			<?php if ( count( $slides ) > 1 ) : ?>
			<div class="refp-rotator__tabs" role="tablist">
				<?php foreach ( $slides as $index => $slide ) : ?>
					<button
						type="button"
						class="refp-rotator__tab<?php echo ( 0 === $index ) ? ' is-active' : ''; ?>"
						role="tab"
						aria-selected="<?php echo ( 0 === $index ) ? 'true' : 'false'; ?>"
						data-index="<?php echo esc_attr( $index ); ?>">
						<span class="refp-rotator__tab-track">
							<span class="refp-rotator__tab-fill"></span>
						</span>
						<span class="refp-rotator__tab-label"><?php echo esc_html( $slide['label'] ); ?></span>
					</button>
				<?php endforeach; ?>
			</div><!-- .refp-rotator__tabs -->
			<? endif; ?>

		</div><!-- .refp-rotator -->
		<?php
	}

	/**
	 * Editor-only notice when nothing resolves.
	 */
	protected function render_empty_notice() {

		if ( ! \Elementor\Plugin::instance()->editor->is_edit_mode() ) {
			return;
		}
		?>
		<div class="elementor-alert elementor-alert-warning">
			<span class="elementor-alert-description">
				<?php echo esc_html( $this->get_empty_message() ); ?>
			</span>
		</div>
		<?php
	}
}
