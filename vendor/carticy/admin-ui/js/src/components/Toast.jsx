/**
 * Toasts — short confirmations of something the reader just did ("Store
 * saved", "Import finished"), stacked in the bottom corner on the end side of
 * the reading direction (.cv-toast-stack / .cv-toast). A toast has a `title`
 * and an optional `description` line.
 *
 * Wrap the app once in ToastProvider (inside the mount) and call useToast()
 * where an action settles:
 *
 *   const toast = useToast();
 *   toast.success( 'Store saved' );
 *   toast.critical( 'The import failed', { description: 'Row 12 has no address.' } );
 *   const id = toast.show( { tone: 'info', title: 'Geocoding…', duration: 0 } );
 *   toast.dismiss( id );
 *
 * Tones: success | info | warning | critical. Success, info and warning toasts
 * are announced politely and leave after `duration` ms (default 5000), paused
 * while the pointer is over them or focus is inside them. Critical toasts are
 * announced at once and stay until dismissed. `duration: 0` keeps any toast
 * until dismissed. Showing a toast with the id of one on screen replaces it.
 *
 * Toasts are for feedback that is also visible elsewhere or safe to miss; an
 * error the reader must act on belongs in a Banner next to the thing that failed.
 *
 * The stack stays clear of the bars pinned to the bottom of the viewport: a
 * SaveBar and an open Drawer's footer report their height (useToastOffset) and
 * the stack sits above the tallest one, so their buttons stay reachable.
 */
import { createContext, createPortal, useCallback, useContext, useEffect, useLayoutEffect, useMemo, useRef, useState } from '@wordpress/element';
import { focusElement } from './focus';
import { CloseIcon } from './icons';
import { useMountRoot } from './layer';

const ToastContext = createContext( null );

/** Registers a bottom bar's height with the closest ToastProvider (null without one). */
const OffsetContext = createContext( null );

/** Time a resumed toast stays at least, so it never vanishes the moment the pointer leaves. */
const RESUME_MIN_MS = 1000;

let sequence = 0;

function ToastItem( { toast, duration, dismissLabel, onDismiss } ) {
	const node = useRef( null );
	const lifetime = toast.duration ?? duration;
	const timed = 'critical' !== toast.tone && lifetime > 0 && Number.isFinite( lifetime );
	const remaining = useRef( lifetime );
	const started = useRef( 0 );
	const timer = useRef( 0 );
	const hovered = useRef( false );
	const focused = useRef( false );
	const dismissRef = useRef( onDismiss );
	dismissRef.current = onDismiss;

	const pause = () => {
		if ( timer.current ) {
			window.clearTimeout( timer.current );
			timer.current = 0;
			remaining.current = Math.max( RESUME_MIN_MS, remaining.current - ( Date.now() - started.current ) );
		}
	};
	const run = () => {
		if ( ! timed || timer.current || hovered.current || focused.current ) {
			return;
		}
		started.current = Date.now();
		timer.current = window.setTimeout( () => dismissRef.current( toast.id ), remaining.current );
	};

	useEffect( () => {
		run();
		return () => window.clearTimeout( timer.current );
	}, [] ); // One timer per shown toast; a replaced toast remounts under a new key.

	return (
		<div
			ref={ node }
			className={ `cv-toast cv-toast--${ toast.tone }` }
			onMouseEnter={ () => {
				hovered.current = true;
				pause();
			} }
			onMouseLeave={ () => {
				hovered.current = false;
				run();
			} }
			onFocus={ () => {
				focused.current = true;
				pause();
			} }
			onBlur={ ( event ) => {
				if ( ! node.current?.contains( event.relatedTarget ) ) {
					focused.current = false;
					run();
				}
			} }
		>
			<div className="cv-toast__body">
				{ toast.title && <p className="cv-toast__title">{ toast.title }</p> }
				{ toast.description && <p className="cv-toast__desc">{ toast.description }</p> }
			</div>
			<button
				type="button"
				className="cv-icon-btn cv-toast__dismiss"
				aria-label={ dismissLabel }
				onClick={ () => {
					// Keyboard readers continue at the neighbouring toast instead of the top of the page.
					const neighbour = node.current?.nextElementSibling || node.current?.previousElementSibling;
					onDismiss( toast.id );
					const next = neighbour?.querySelector( '.cv-toast__dismiss' );
					if ( next ) {
						focusElement( next );
					}
				} }
			>
				<CloseIcon />
			</button>
		</div>
	);
}

export function ToastProvider( { children, duration = 5000, max = 4, dismissLabel = 'Dismiss', container } ) {
	const [ toasts, setToasts ] = useState( [] );
	const [ anchor, root ] = useMountRoot( container );
	const [ offsets, setOffsets ] = useState( () => new Map() );

	const setOffset = useCallback( ( owner, height ) => {
		setOffsets( ( current ) => {
			if ( current.get( owner ) === height || ( null === height && ! current.has( owner ) ) ) {
				return current;
			}
			const next = new Map( current );
			if ( null === height ) {
				next.delete( owner );
			} else {
				next.set( owner, height );
			}
			return next;
		} );
	}, [] );
	const offset = Math.max( 0, ...offsets.values() );

	const dismiss = useCallback( ( id ) => {
		setToasts( ( list ) => list.filter( ( toast ) => toast.id !== id ) );
	}, [] );

	const show = useCallback( ( toast ) => {
		sequence += 1;
		const id = toast.id || `cv-toast-${ sequence }`;
		const entry = { tone: 'info', ...toast, id, key: sequence };
		setToasts( ( list ) => {
			const next = [ ...list.filter( ( item ) => item.id !== id ), entry ];
			// Over the limit, the oldest timed toast goes first; critical ones wait for the reader.
			while ( next.length > max ) {
				const oldest = next.findIndex( ( item ) => 'critical' !== item.tone );
				if ( -1 === oldest ) {
					break;
				}
				next.splice( oldest, 1 );
			}
			return next;
		} );
		return id;
	}, [ max ] );

	const api = useMemo( () => {
		const shorthand = ( tone ) => ( title, options = {} ) => show( { ...options, title, tone } );
		return {
			show,
			dismiss,
			success: shorthand( 'success' ),
			info: shorthand( 'info' ),
			warning: shorthand( 'warning' ),
			critical: shorthand( 'critical' ),
		};
	}, [ show, dismiss ] );

	const render = ( list ) =>
		list.map( ( toast ) => (
			<ToastItem key={ toast.key } toast={ toast } duration={ duration } dismissLabel={ dismissLabel } onDismiss={ dismiss } />
		) );

	return (
		<ToastContext.Provider value={ api }>
			<OffsetContext.Provider value={ setOffset }>{ children }</OffsetContext.Provider>
			<span ref={ anchor } hidden />
			{ /* Both live regions exist before the first toast, so screen readers announce it. */ }
			{ root && createPortal(
				<div className="cv-toast-stack" style={ offset ? { '--cv-toast-offset': `${ offset }px` } : undefined }>
					<div className="cv-toast-stack__region" aria-live="assertive">
						{ render( toasts.filter( ( toast ) => 'critical' === toast.tone ) ) }
					</div>
					<div className="cv-toast-stack__region" aria-live="polite">
						{ render( toasts.filter( ( toast ) => 'critical' !== toast.tone ) ) }
					</div>
				</div>,
				root
			) }
		</ToastContext.Provider>
	);
}

/**
 * Internal (not exported from the package): keep the toast stack above an
 * element pinned to the bottom of the viewport while `active`, by its height.
 * Does nothing outside a ToastProvider.
 *
 * @param {Object}  ref    Ref of the element (a SaveBar, a Drawer's footer).
 * @param {boolean} active Whether the element is on screen.
 */
export function useToastOffset( ref, active = true ) {
	const setOffset = useContext( OffsetContext );
	useLayoutEffect( () => {
		const node = ref.current;
		if ( ! setOffset || ! active || ! node ) {
			return undefined;
		}
		const owner = {};
		const measure = () => setOffset( owner, node.offsetHeight );
		measure();
		const observer = typeof ResizeObserver === 'undefined' ? null : new ResizeObserver( measure );
		observer?.observe( node );
		return () => {
			observer?.disconnect();
			setOffset( owner, null );
		};
	}, [ setOffset, active ] ); // The ref is stable.
}

/**
 * The toast API of the closest ToastProvider.
 *
 * @return {{show: Function, dismiss: Function, success: Function, info: Function, warning: Function, critical: Function}} Toast API.
 */
export function useToast() {
	const api = useContext( ToastContext );
	if ( ! api ) {
		throw new Error( 'useToast() needs a ToastProvider above it in the tree.' );
	}
	return api;
}
