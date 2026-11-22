/**
 * Drawer — a side panel over the screen (.cv-drawer-root / .cv-drawer): a
 * title, a close button, a scrolling body and an optional footer.
 *
 * Controlled: render it with `open` and close it from `onClose`, which runs on
 * the close button, Escape and a click on the scrim. While open it is a modal
 * dialog: focus moves in (to `initialFocusRef`, or the close button), Tab and
 * Shift+Tab stay inside it, the page behind does not scroll, and on close focus
 * returns to the element that had it before the drawer opened.
 *
 * It renders through a portal into the closest `.cv-root` (or `container`), so
 * the tokens apply and no card or table clips it. Escape closes only the
 * dialog on top (a drawer or a Modal opened from it), and a control inside
 * that handles Escape itself (an open Combobox list) keeps the drawer open.
 *
 * `side` is the edge it opens from: 'end' (default; the right in a
 * left-to-right page) or 'start', following the reading direction.
 * `description` is one line of context under the title (it also describes the
 * dialog to screen readers). `surface="subtle"` paints the body in the page
 * grey, for bodies made of white cards such as FormSection variant="card".
 * Toasts stack above the footer while the drawer is open. Other props go to
 * the panel (role="dialog").
 */
import { createPortal, useEffect, useId, useRef, useState } from '@wordpress/element';
import { classes } from './classes';
import { CloseIcon } from './icons';
import { prefersReducedMotion, useModalLayer, useMountRoot } from './layer';
import { useToastOffset } from './Toast';

/** Matches the .cv-drawer slide transition in components.css (--cv-duration-normal). */
const EXIT_MS = 200;

export function Drawer( {
	open = false,
	onClose,
	title,
	description = null,
	size = 'md',
	side = 'end',
	surface: bodySurface = 'default',
	footer = null,
	closeLabel = 'Close',
	initialFocusRef,
	container,
	className = '',
	children,
	...rest
} ) {
	const [ anchor, root ] = useMountRoot( container );
	const layer = useRef( null );
	const panel = useRef( null );
	const closeButton = useRef( null );
	const footerBar = useRef( null );
	const titleId = useId();
	const descriptionId = `${ titleId }-description`;
	const onCloseRef = useRef( onClose );
	onCloseRef.current = onClose;
	const close = () => onCloseRef.current?.();

	// The panel stays mounted for its slide-out after `open` turns false.
	const [ leaving, setLeaving ] = useState( false );
	const wasOpen = useRef( open );
	useEffect( () => {
		if ( open ) {
			wasOpen.current = true;
			setLeaving( false );
			return undefined;
		}
		if ( ! wasOpen.current ) {
			return undefined;
		}
		wasOpen.current = false;
		setLeaving( true );
		const timer = window.setTimeout( () => setLeaving( false ), prefersReducedMotion() ? 0 : EXIT_MS );
		return () => window.clearTimeout( timer );
	}, [ open ] );

	const active = open && !! root;

	// Slide in: the panel is painted off-canvas first, then moved, so the transition runs.
	const [ shown, setShown ] = useState( false );
	useEffect( () => {
		if ( ! active ) {
			setShown( false );
			return undefined;
		}
		let inner = 0;
		const outer = window.requestAnimationFrame( () => {
			inner = window.requestAnimationFrame( () => setShown( true ) );
		} );
		return () => {
			window.cancelAnimationFrame( outer );
			window.cancelAnimationFrame( inner );
		};
	}, [ active ] );

	useModalLayer( { active, panelRef: panel, layerRef: layer, initialFocusRef: initialFocusRef || closeButton, onClose: close } );
	useToastOffset( footerBar, active && !! footer );

	// A closing panel is still on screen for its slide-out; it must not take focus or clicks.
	useEffect( () => {
		if ( layer.current ) {
			layer.current.inert = ! open;
		}
	} );

	return (
		<>
			<span ref={ anchor } hidden />
			{ root && ( open || leaving ) && createPortal(
				<div ref={ layer } className={ classes( 'cv-drawer-root', shown && open && 'is-open' ) }>
					{ /* Pointer shortcut only; keyboard readers have Escape and the close button. */ }
					<div className="cv-drawer-scrim" aria-hidden="true" onClick={ close } />
					<div
						{ ...rest }
						ref={ panel }
						className={ classes( 'cv-drawer', `cv-drawer--${ size }`, 'start' === side && 'cv-drawer--start', 'subtle' === bodySurface && 'cv-drawer--subtle', className ) }
						role="dialog"
						aria-modal="true"
						aria-labelledby={ titleId }
						aria-describedby={ description ? descriptionId : undefined }
						tabIndex={ -1 }
					>
						<div className="cv-drawer__header">
							<div className="cv-drawer__title-block">
								<h2 id={ titleId } className="cv-drawer__title">{ title }</h2>
								{ description && <p id={ descriptionId } className="cv-drawer__desc">{ description }</p> }
							</div>
							<button ref={ closeButton } type="button" className="cv-icon-btn" aria-label={ closeLabel } onClick={ close }>
								<CloseIcon />
							</button>
						</div>
						<div className="cv-drawer__body">{ children }</div>
						{ footer && <div ref={ footerBar } className="cv-drawer__footer">{ footer }</div> }
					</div>
				</div>,
				root
			) }
		</>
	);
}
