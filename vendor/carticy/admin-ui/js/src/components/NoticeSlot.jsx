/**
 * NoticeSlot — the place below the header where the screen's own notices
 * appear.
 *
 * The plugin prints its notices on the server inside one area element that
 * starts with WordPress's `<hr class="wp-header-end">` marker (see the README,
 * "Notices"). This slot moves that element into the application. React renders
 * the slot without children, so it never reconciles the server markup.
 *
 * A notice the store can dismiss carries a close button with the REST path
 * that records the dismissal (`data-cv-dismiss`, optional JSON body in
 * `data-cv-dismiss-data`). The slot sends it and removes the notice in place;
 * if the request fails, the notice stays and the button works again. Other
 * props go to the slot.
 */
import apiFetch from '@wordpress/api-fetch';
import { useCallback, useEffect, useRef } from '@wordpress/element';
import { classes } from './classes';

const pending = new WeakSet();

async function dismiss( button ) {
	const notice = button.closest( '.notice' );
	if ( ! notice || pending.has( button ) ) {
		return;
	}
	let data;
	try {
		data = button.dataset.cvDismissData ? JSON.parse( button.dataset.cvDismissData ) : undefined;
	} catch {
		return;
	}
	pending.add( button );
	button.setAttribute( 'aria-disabled', 'true' );
	try {
		await apiFetch( { path: button.dataset.cvDismiss, method: 'POST', data } );
	} catch {
		pending.delete( button );
		button.removeAttribute( 'aria-disabled' );
		return;
	}
	const area = notice.parentElement;
	const hadFocus = notice.contains( button.ownerDocument.activeElement );
	notice.remove();
	// Keyboard users continue at the next notice's close button rather than at the top of the page.
	if ( hadFocus ) {
		area?.querySelector( '[data-cv-dismiss]' )?.focus();
	}
}

export function NoticeSlot( { areaId, className = '', ...rest } ) {
	const slot = useRef( null );
	const adopt = useCallback( ( node ) => {
		slot.current = node;
		const area = node && areaId ? document.getElementById( areaId ) : null;
		if ( area && area.parentNode !== node ) {
			node.append( area );
		}
	}, [ areaId ] );

	useEffect( () => {
		const node = slot.current;
		if ( ! node ) {
			return undefined;
		}
		const onClick = ( event ) => {
			const button = event.target.closest?.( '[data-cv-dismiss]' );
			if ( button && node.contains( button ) ) {
				event.preventDefault();
				dismiss( button );
			}
		};
		node.addEventListener( 'click', onClick );
		return () => node.removeEventListener( 'click', onClick );
	}, [] );

	return <div className={ classes( 'cv-notice-slot', className ) } { ...rest } ref={ adopt } />;
}
