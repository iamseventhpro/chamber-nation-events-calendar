/**
 * Chamber Nation Events Calendar — progressive enhancement only.
 *
 * All events, navigation and filtering are rendered server-side. This script
 * adds the event-details popup, whole-card clicks and auto-submitting the
 * category filter.
 */
( function () {
	'use strict';

	function initModal( root ) {
		var modal = root.querySelector( '.cn-modal' );
		if ( ! modal ) {
			return null;
		}

		// Escape any transformed theme containers so position:fixed works.
		document.body.appendChild( modal );

		var titleEl = modal.querySelector( '.cn-modal__title' );
		var bodyEl = modal.querySelector( '.cn-modal__body' );
		var closeBtn = modal.querySelector( '.cn-modal__close' );
		var lastFocus = null;

		function close() {
			if ( ! modal.classList.contains( 'is-open' ) ) {
				return;
			}
			modal.classList.remove( 'is-open' );
			modal.setAttribute( 'aria-hidden', 'true' );
			bodyEl.innerHTML = '';
			document.body.style.overflow = '';
			if ( lastFocus ) {
				lastFocus.focus();
			}
		}

		function open( url, title ) {
			lastFocus = document.activeElement;
			titleEl.textContent = title || '';

			var wrap = document.createElement( 'div' );
			wrap.className = 'cn-modal__iframeWrap';
			var frame = document.createElement( 'iframe' );
			frame.className = 'cn-modal__iframe';
			frame.src = url;
			frame.title = title || '';
			frame.setAttribute( 'loading', 'eager' );
			wrap.appendChild( frame );
			bodyEl.innerHTML = '';
			bodyEl.appendChild( wrap );

			modal.classList.add( 'is-open' );
			modal.setAttribute( 'aria-hidden', 'false' );
			document.body.style.overflow = 'hidden';
			closeBtn.focus();
		}

		closeBtn.addEventListener( 'click', close );
		modal.addEventListener( 'click', function ( e ) {
			if ( e.target === modal ) {
				close();
			}
		} );
		document.addEventListener( 'keydown', function ( e ) {
			if ( 'Escape' === e.key ) {
				close();
			}
		} );

		return open;
	}

	function initRoot( root ) {
		var openModal = initModal( root );

		root.addEventListener( 'click', function ( e ) {
			if ( e.defaultPrevented || 0 !== e.button || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey ) {
				return;
			}

			var link = e.target.closest( 'a.cnc-ev-link' );
			if ( ! link ) {
				// Clicking anywhere on a card behaves like clicking its title.
				var item = e.target.closest( '.cnc-item' );
				if ( ! item || e.target.closest( 'a, button' ) ) {
					return;
				}
				link = item.querySelector( 'a.cnc-ev-link' );
				if ( ! link ) {
					return;
				}
				if ( 'modal' !== link.getAttribute( 'data-open' ) || ! openModal ) {
					link.click();
					return;
				}
			}

			if ( 'modal' !== link.getAttribute( 'data-open' ) || ! openModal ) {
				return;
			}
			e.preventDefault();
			openModal( link.href, link.textContent.trim() );
		} );

		var select = root.querySelector( '.cnc-cat-dropdown' );
		if ( select && select.form ) {
			select.addEventListener( 'change', function () {
				select.form.submit();
			} );
		}
	}

	function init() {
		var roots = document.querySelectorAll( '.cnc-root' );
		for ( var i = 0; i < roots.length; i++ ) {
			initRoot( roots[ i ] );
		}
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
