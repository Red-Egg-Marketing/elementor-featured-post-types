/**
 * Quick Edit support for the featured flag.
 *
 * WordPress's inline editor only knows how to populate the fields core put there.
 * For anything custom you have to read the current value out of the table row and
 * copy it into the edit row yourself, which means wrapping inlineEditPost.edit --
 * the long-standing pattern for this, and still the only hook available.
 */
( function ( $ ) {
	'use strict';

	if ( typeof window.inlineEditPost === 'undefined' ) {
		return;
	}

	var wpInlineEdit = window.inlineEditPost.edit;

	window.inlineEditPost.edit = function ( id ) {
		// Let core build and open the edit row first.
		var result = wpInlineEdit.apply( this, arguments );

		var postId = 0;

		if ( typeof id === 'object' ) {
			postId = parseInt( this.getId( id ), 10 );
		} else {
			postId = parseInt( id, 10 );
		}

		if ( ! postId ) {
			return result;
		}

		var $row = $( '#post-' + postId );
		var $editRow = $( '#edit-' + postId );

		if ( ! $row.length || ! $editRow.length ) {
			return result;
		}

		var featured = $row.find( '.re-featured-flag' ).attr( 'data-featured' );

		$editRow
			.find( 'input[name="re_featured_sticky_inline"]' )
			.prop( 'checked', '1' === String( featured ) );

		return result;
	};

	/**
	 * Repaint the row's star after a Quick Edit save.
	 *
	 * Core replaces the row markup with fresh HTML from the server, so the column
	 * is already correct -- but only if the save actually ran. This keeps the
	 * checkbox and the star from disagreeing if a request fails silently.
	 */
	$( document ).on( 'ajaxComplete', function ( event, xhr, settings ) {
		if ( ! settings || ! settings.data ) {
			return;
		}

		if ( settings.data.indexOf( 'action=inline-save' ) === -1 ) {
			return;
		}

		if ( xhr && xhr.status !== 200 ) {
			window.console && window.console.warn(
				'Featured flag may not have saved: inline-save returned ' + xhr.status
			);
		}
	} );
} )( jQuery );
