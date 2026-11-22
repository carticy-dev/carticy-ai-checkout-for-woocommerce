/**
 * Internal helpers shared by the overlay components (Drawer, Modal,
 * ToastProvider). Not exported from the package.
 *
 * Tokens and resets are scoped to the `.cv-root` mount, so an overlay rendered
 * into `document.body` would lose every --cv-* value. Overlays therefore portal
 * into the closest `.cv-root` of the place they are rendered from: a hidden
 * anchor marks that place, and the root is resolved before the first paint.
 */
import { useEffect, useLayoutEffect, useRef, useState } from '@wordpress/element';

/**
 * Resolve the element an overlay portals into.
 *
 * @param {HTMLElement|undefined} container Explicit container; wins when given.
 * @return {[Object, HTMLElement|null]} The anchor ref to render as a hidden span, and the resolved root.
 */
export function useMountRoot( container ) {
	const anchor = useRef( null );
	const [ root, setRoot ] = useState( null );

	useLayoutEffect( () => {
		const node = anchor.current;
		setRoot( container || node?.closest( '.cv-root' ) || node?.ownerDocument.body || null );
	}, [ container ] );

	return [ anchor, root ];
}

let scrollLocks = 0;
let savedOverflow = '';

/**
 * Stop the page behind an overlay from scrolling. Counted, so nested overlays
 * keep the page locked until the last one closes, and the page's own overflow
 * value comes back unchanged.
 *
 * @param {Document} doc The overlay's document.
 * @return {Function} Releases this lock.
 */
function lockScroll( doc ) {
	if ( 0 === scrollLocks ) {
		savedOverflow = doc.body.style.overflow;
		doc.body.style.overflow = 'hidden';
	}
	scrollLocks += 1;
	let released = false;
	return () => {
		if ( released ) {
			return;
		}
		released = true;
		scrollLocks -= 1;
		if ( 0 === scrollLocks ) {
			doc.body.style.overflow = savedOverflow;
		}
	};
}

const FOCUSABLE = [
	'a[href]',
	'button:not([disabled])',
	'input:not([disabled]):not([type="hidden"])',
	'select:not([disabled])',
	'textarea:not([disabled])',
	'[tabindex]:not([tabindex="-1"])',
].join( ',' );

/**
 * The elements Tab can reach inside a container, in document order. Elements
 * without a box (inside a collapsed or display:none part) are left out, because
 * focusing them silently fails and would let focus escape the trap.
 *
 * @param {HTMLElement} container The element to search.
 * @return {HTMLElement[]} Focusable descendants.
 */
function tabbableIn( container ) {
	return Array.from( container.querySelectorAll( FOCUSABLE ) ).filter(
		( element ) => element.tabIndex >= 0 && ! element.closest( '[hidden], [inert]' ) && element.getClientRects().length > 0
	);
}

/** True when the reader asked the system to reduce motion. */
export function prefersReducedMotion() {
	return typeof window !== 'undefined' && !! window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
}

/** Open modal layers (drawers and modals), oldest first; only the last one answers Escape. */
const openLayers = [];

/** Keep Tab and Shift+Tab inside the panel the listener is attached to. */
function trapTab( event ) {
	const panel = event.currentTarget;
	if ( 'Tab' !== event.key ) {
		return;
	}
	const tabbable = tabbableIn( panel );
	if ( ! tabbable.length ) {
		event.preventDefault();
		panel.focus( { preventScroll: true } );
		return;
	}
	const first = tabbable[ 0 ];
	const last = tabbable[ tabbable.length - 1 ];
	const current = panel.ownerDocument.activeElement;
	const inList = tabbable.includes( current );
	if ( event.shiftKey && ( current === first || ! inList ) ) {
		event.preventDefault();
		last.focus();
	} else if ( ! event.shiftKey && ( current === last || ! inList ) ) {
		event.preventDefault();
		first.focus();
	}
}

/**
 * The lifetime of a modal dialog (Drawer, Modal) while `active`: focus moves
 * into the panel (to `initialFocusRef`, or the panel itself), Tab and
 * Shift+Tab stay inside it, the page behind does not scroll, and Escape calls
 * `onClose`. Layers stack: Escape reaches only the one on top, and a control
 * that handles Escape itself (an open Combobox list) calls preventDefault so
 * the layer stays open. When the layer ends, focus returns to the element
 * that had it before, unless the reader has already moved on elsewhere.
 *
 * @param {Object}   options
 * @param {boolean}  options.active          True while the dialog is open and mounted.
 * @param {Object}   options.panelRef        Ref of the dialog element (role="dialog", tabIndex -1).
 * @param {Object}   options.layerRef        Ref of the element around the panel and its scrim.
 * @param {Object}   options.initialFocusRef Ref of the element to focus first; read once, on open.
 * @param {Function} options.onClose         Runs on Escape; leave it out for a dialog Escape must not close.
 */
export function useModalLayer( { active, panelRef, layerRef, initialFocusRef, onClose } ) {
	const onCloseRef = useRef( onClose );
	onCloseRef.current = onClose;

	useEffect( () => {
		const panel = panelRef.current;
		if ( ! active || ! panel ) {
			return undefined;
		}
		const wrapper = layerRef.current;
		const doc = panel.ownerDocument;
		const opener = doc.activeElement;
		openLayers.push( panel );
		const unlock = lockScroll( doc );

		// The panel is laid out (a sliding drawer starts off-canvas, not hidden), so focus lands in this commit.
		( initialFocusRef?.current || panel ).focus( { preventScroll: true } );

		const onKey = ( event ) => {
			if ( 'Escape' !== event.key || event.defaultPrevented || openLayers[ openLayers.length - 1 ] !== panel ) {
				return;
			}
			// The top layer keeps Escape even when it cannot be dismissed, so the one below stays open.
			event.preventDefault();
			onCloseRef.current?.();
		};
		doc.addEventListener( 'keydown', onKey );
		// A DOM listener, not a React prop: React would also deliver the Tab
		// presses of a dialog opened from inside this one, through the tree.
		panel.addEventListener( 'keydown', trapTab );

		return () => {
			doc.removeEventListener( 'keydown', onKey );
			panel.removeEventListener( 'keydown', trapTab );
			openLayers.splice( openLayers.indexOf( panel ), 1 );
			unlock();
			// Return focus only when it is still in this layer or was dropped to the
			// page; a reader already working in another dialog keeps their place.
			const now = doc.activeElement;
			const stranded = ! now || now === doc.body || ( wrapper && wrapper.contains( now ) );
			if ( stranded && opener && opener.isConnected && 'function' === typeof opener.focus ) {
				opener.focus( { preventScroll: true } );
			}
		};
	}, [ active ] ); // The refs are stable; initialFocusRef is read once, when the layer opens.
}
