/**
 * Featured Post Types rotator.
 *
 *   [====----] Case Study   [--------] Whitepaper   [--------] Event
 *
 * Swiper owns the slide transition and the autoplay clock. The countdown bars are
 * driven by CSS width transitions rather than Swiper's `autoplayTimeLeft` event,
 * because that event only exists in Swiper 8.4+ and Elementor bundles older
 * versions depending on release.
 */
( function () {
	'use strict';

	var SELECTOR = '.refp-rotator';

	function initRotator( root ) {
		if ( root.dataset.refpReady === '1' ) {
			return;
		}

		if ( typeof window.Swiper === 'undefined' ) {
			return;
		}

		var container = root.querySelector( '.refp-rotator__swiper' );
		var tabs = Array.prototype.slice.call(
			root.querySelectorAll( '.refp-rotator__tab' )
		);

		if ( ! container || ! tabs.length ) {
			return;
		}

		var config = {};

		try {
			config = JSON.parse( root.dataset.refpConfig || '{}' );
		} catch ( e ) {
			config = {};
		}

		var delay = parseInt( config.delay, 10 ) || 6000;
		var speed = parseInt( config.speed, 10 );

		if ( isNaN( speed ) ) {
			speed = 500;
		}

		// One slide has nothing to rotate through.
		var single = tabs.length < 2;

		var swiper = new window.Swiper( container, {
			effect: 'fade',
			fadeEffect: { crossFade: true },
			speed: speed,
			loop: ! single,
			allowTouchMove: ! single,
			autoHeight: true,
			autoplay: single
				? false
				: {
						delay: delay,
						disableOnInteraction: false
				  }
		} );

		function activeIndex() {
			return typeof swiper.realIndex === 'number'
				? swiper.realIndex
				: swiper.activeIndex;
		}

		function fillOf( index ) {
			return (
				tabs[ index ] &&
				tabs[ index ].querySelector( '.refp-rotator__tab-fill' )
			);
		}

		/**
		 * Reset every bar, then run the active one from 0 to 100%.
		 *
		 * The width is zeroed with transitions off and the element is reflowed
		 * before the transition is restored -- otherwise the browser coalesces
		 * both writes and the bar snaps straight to full.
		 */
		function setActive( index ) {
			tabs.forEach( function ( tab, i ) {
				var fill = tab.querySelector( '.refp-rotator__tab-fill' );
				var isActive = i === index;

				tab.classList.toggle( 'is-active', isActive );
				tab.setAttribute( 'aria-selected', isActive ? 'true' : 'false' );

				if ( ! fill ) {
					return;
				}

				fill.style.transition = 'none';
				fill.style.width = '0%';

				if ( isActive && ! single ) {
					void fill.offsetWidth;
					fill.style.transition = 'width ' + delay + 'ms linear';
					fill.style.width = '100%';
				}
			} );
		}

		swiper.on( 'slideChangeTransitionStart', function () {
			setActive( activeIndex() );
		} );

		if ( config.pauseOnHover && ! single ) {
			root.addEventListener( 'mouseenter', function () {
				var fill = fillOf( activeIndex() );

				if ( fill ) {
					var frozen = window.getComputedStyle( fill ).width;
					fill.style.transition = 'none';
					fill.style.width = frozen;
				}

				if ( swiper.autoplay ) {
					if ( swiper.autoplay.pause ) {
						swiper.autoplay.pause();
					} else if ( swiper.autoplay.stop ) {
						swiper.autoplay.stop();
					}
				}
			} );

			root.addEventListener( 'mouseleave', function () {
				var fill = fillOf( activeIndex() );

				if ( fill && fill.parentNode.offsetWidth ) {
					var done =
						parseFloat( window.getComputedStyle( fill ).width ) /
						fill.parentNode.offsetWidth;
					var remaining = Math.max( 0, delay * ( 1 - done ) );

					void fill.offsetWidth;
					fill.style.transition = 'width ' + remaining + 'ms linear';
					fill.style.width = '100%';
				}

				if ( swiper.autoplay ) {
					if ( swiper.autoplay.resume ) {
						swiper.autoplay.resume();
					} else if ( swiper.autoplay.start ) {
						swiper.autoplay.start();
					}
				}
			} );
		}

		tabs.forEach( function ( tab ) {
			tab.addEventListener( 'click', function () {
				var target = parseInt( tab.dataset.index, 10 ) || 0;

				if ( swiper.slideToLoop ) {
					swiper.slideToLoop( target );
				} else {
					swiper.slideTo( target );
				}
			} );
		} );

		var reduceMotion =
			window.matchMedia &&
			window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

		if ( reduceMotion ) {
			if ( swiper.autoplay && swiper.autoplay.stop ) {
				swiper.autoplay.stop();
			}
			root.classList.add( 'refp-rotator--static' );
		} else {
			setActive( activeIndex() );
		}

		root.dataset.refpReady = '1';
	}

	function initAll( scope ) {
		var roots = ( scope || document ).querySelectorAll( SELECTOR );

		Array.prototype.forEach.call( roots, initRotator );
	}

	if ( document.readyState !== 'loading' ) {
		initAll();
	} else {
		document.addEventListener( 'DOMContentLoaded', function () {
			initAll();
		} );
	}

	// Elementor swaps widget markup in the editor without a page reload.
	window.addEventListener( 'elementor/frontend/init', function () {
		if ( ! window.elementorFrontend || ! window.elementorFrontend.hooks ) {
			return;
		}

		window.elementorFrontend.hooks.addAction(
			'frontend/element_ready/re-featured-post-types.default',
			function ( $scope ) {
				initAll( $scope && $scope[ 0 ] ? $scope[ 0 ] : null );
			}
		);
	} );
} )();
