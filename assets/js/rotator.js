/**
 * Featured Post Types rotator.
 *
 *   [====----] Case Study   [--------] Whitepaper   [--------] Event
 *
 * The advance timer is ours, not Swiper's. Two reasons:
 *
 * 1. Swiper's autoplay pause/resume API changed shape across majors, and
 *    Elementor ships different majors depending on version and experiment
 *    flags. Calling pause() and then start() leaves autoplay flagged as
 *    running-but-paused, so it never fires again -- a permanent stall.
 * 2. The countdown bar and the slide advance have to agree to the
 *    millisecond. Sharing one timer makes desync impossible rather than
 *    merely unlikely.
 *
 * Swiper still owns the transition, looping and touch gestures.
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

		var single = tabs.length < 2;
		var reduceMotion =
			window.matchMedia &&
			window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

		var swiper = new window.Swiper( container, {
			effect: 'fade',
			fadeEffect: { crossFade: true },
			speed: speed,
			loop: ! single,
			allowTouchMove: ! single,
			autoHeight: true,
			autoplay: false
		} );

		// --- state ---------------------------------------------------------

		var timer = null;
		var remaining = delay;
		var paused = false;

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

		function clearTimer() {
			if ( timer ) {
				window.clearTimeout( timer );
				timer = null;
			}
		}

		function scheduleAdvance( ms ) {
			clearTimer();

			if ( single || reduceMotion || paused ) {
				return;
			}

			timer = window.setTimeout( function () {
				swiper.slideNext();
			}, ms );
		}

		// --- bar painting --------------------------------------------------

		function paintTabs( index ) {
			tabs.forEach( function ( tab, i ) {
				var fill = tab.querySelector( '.refp-rotator__tab-fill' );
				var isActive = i === index;

				tab.classList.toggle( 'is-active', isActive );
				tab.setAttribute( 'aria-selected', isActive ? 'true' : 'false' );

				if ( fill ) {
					fill.style.transition = 'none';
					fill.style.width = '0%';
				}
			} );
		}

		/**
		 * Run the active bar from its current width to full over `ms`.
		 *
		 * The reflow is required: without it the browser coalesces the width
		 * reset and the target width into one paint, and the bar snaps to full.
		 */
		function runBar( index, ms ) {
			var fill = fillOf( index );

			if ( ! fill || reduceMotion ) {
				return;
			}

			void fill.offsetWidth;
			fill.style.transition = 'width ' + ms + 'ms linear';
			fill.style.width = '100%';
		}

		/**
		 * Stop the active bar where it is and report the time left.
		 *
		 * @return {number} Milliseconds remaining in this slide.
		 */
		function freezeBar( index ) {
			var fill = fillOf( index );

			if ( ! fill ) {
				return delay;
			}

			var track = fill.parentNode;
			var width = parseFloat( window.getComputedStyle( fill ).width );
			var full = track ? track.offsetWidth : 0;

			fill.style.transition = 'none';

			if ( ! full || isNaN( width ) ) {
				return delay;
			}

			fill.style.width = width + 'px';

			var done = Math.min( 1, Math.max( 0, width / full ) );

			return Math.max( 0, Math.round( delay * ( 1 - done ) ) );
		}

		/**
		 * Reset the cycle for a slide: repaint tabs, restart the bar, requeue.
		 */
		function startCycle( index, ms ) {
			paintTabs( index );
			remaining = ms;

			if ( paused ) {
				return;
			}

			runBar( index, ms );
			scheduleAdvance( ms );
		}

		// --- wiring --------------------------------------------------------

		swiper.on( 'slideChangeTransitionStart', function () {
			startCycle( activeIndex(), delay );
		} );

		if ( config.pauseOnHover && ! single && ! reduceMotion ) {
			root.addEventListener( 'mouseenter', function () {
				if ( paused ) {
					return;
				}

				paused = true;
				clearTimer();
				remaining = freezeBar( activeIndex() );
			} );

			root.addEventListener( 'mouseleave', function () {
				if ( ! paused ) {
					return;
				}

				paused = false;
				runBar( activeIndex(), remaining );
				scheduleAdvance( remaining );
			} );
		}

		tabs.forEach( function ( tab ) {
			tab.addEventListener( 'click', function () {
				var target = parseInt( tab.dataset.index, 10 ) || 0;

				if ( target === activeIndex() ) {
					// No slide change will fire, so restart this slide's cycle.
					startCycle( target, delay );
					return;
				}

				if ( swiper.slideToLoop ) {
					swiper.slideToLoop( target );
				} else {
					swiper.slideTo( target );
				}
			} );
		} );

		if ( reduceMotion ) {
			root.classList.add( 'refp-rotator--static' );
			paintTabs( activeIndex() );
		} else {
			startCycle( activeIndex(), delay );
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

		[ 're-featured-post-types', 're-featured-selected-posts' ].forEach(
			function ( widget ) {
				window.elementorFrontend.hooks.addAction(
					'frontend/element_ready/' + widget + '.default',
					function ( $scope ) {
						initAll( $scope && $scope[ 0 ] ? $scope[ 0 ] : null );
					}
				);
			}
		);
	} );
} )();
