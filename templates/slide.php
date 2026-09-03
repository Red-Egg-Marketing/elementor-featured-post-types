<?php
/**
 * Single slide.
 *
 * Included from Widget::render() with these in scope:
 *   $slide      array  post_id, post_type, label
 *   $index      int    zero-based slide index
 *   $settings   array  widget settings
 *   $title_tag  string validated heading tag
 *   $image_size string registered image size name
 *
 * @package RedEgg\FeaturedPostTypes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$refp_post_id   = $slide['post_id'];
$refp_permalink = get_permalink( $refp_post_id );
$refp_title     = get_the_title( $refp_post_id );
?>
<div class="swiper-slide refp-rotator__slide" data-post-type="<?php echo esc_attr( $slide['post_type'] ); ?>">
	<div class="refp-rotator__panel">

		<?php if ( has_post_thumbnail( $refp_post_id ) ) : ?>
			<div class="refp-rotator__media">
				<a href="<?php echo esc_url( $refp_permalink ); ?>" tabindex="-1" aria-hidden="true">
					<?php
						echo get_the_post_thumbnail(
							$refp_post_id,
							$image_size,
							[
								'loading' => 'lazy',
								'alt'     => $refp_title,
							]
						);
					?>
				</a>
			</div><!-- .refp-rotator__media -->
		<?php endif; ?>

		<div class="refp-rotator__content">

			<<?php echo esc_html( $title_tag ); ?> class="refp-rotator__title">
				<a href="<?php echo esc_url( $refp_permalink ); ?>"><?php echo esc_html( $refp_title ); ?></a>
			</<?php echo esc_html( $title_tag ); ?>>

			<?php if ( 'yes' === $settings['show_excerpt'] ) : ?>
				<p class="refp-rotator__excerpt">
					<?php echo esc_html( $this->get_excerpt( $refp_post_id, $settings ) ); ?>
				</p>
			<?php endif; ?>

			<?php if ( 'yes' === $settings['show_cta'] && ! empty( $settings['cta_text'] ) ) : ?>
				<a class="refp-rotator__cta elementor-button" href="<?php echo esc_url( $refp_permalink ); ?>">
					<span><?php echo esc_html( $settings['cta_text'] ); ?></span>
					<span class="screen-reader-text">
						<?php
							printf(
								/* translators: %s Post title. */
								esc_html__( 'about %s', 'elementor-featured-post-types' ),
								esc_html( $refp_title )
							);
						?>
					</span>
				</a>
			<?php endif; ?>

		</div><!-- .refp-rotator__content -->
	</div><!-- .refp-rotator__panel -->
</div><!-- .refp-rotator__slide -->
