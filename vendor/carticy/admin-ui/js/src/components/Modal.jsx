/**
 * Modal — a centred dialog for a short decision (.cv-scrim + .cv-modal,
 * sizes sm/md/lg/xl) with a header, body and optional footer.
 *
 * While open it is a modal dialog, like Drawer: focus moves in (to
 * `initialFocusRef`, or the close button), Tab and Shift+Tab stay inside it,
 * the page behind does not scroll, and on close focus returns to the element
 * that opened it. It renders through a portal into the closest `.cv-root` (or
 * `container`), above the wp-admin toolbar, so a Modal opened from inside a
 * Drawer is not trapped in the drawer's panel. Escape closes only the dialog
 * on top.
 *
 * Escape and a click on the scrim close it by default; pass
 * dismissible={false} for confirm-style modals that must resolve through
 * their actions. The title names the dialog (aria-labelledby); without one,
 * pass an `aria-label`. Other props go to the dialog element.
 */
import { createPortal, useId, useRef } from '@wordpress/element';
import { classes } from './classes';
import { CloseIcon } from './icons';
import { useModalLayer, useMountRoot } from './layer';

export function Modal( {
	open = false,
	onClose,
	title,
	size = 'md',
	footer = null,
	dismissible = true,
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
	const titleId = useId();
	const close = dismissible && onClose ? onClose : undefined;

	useModalLayer( { active: open && !! root, panelRef: panel, layerRef: layer, initialFocusRef: initialFocusRef || closeButton, onClose: close } );

	return (
		<>
			<span ref={ anchor } hidden />
			{ root && open && createPortal(
				<div
					ref={ layer }
					className="cv-scrim"
					onClick={ ( event ) => close && event.target === event.currentTarget && close() }
				>
					<div
						{ ...rest }
						ref={ panel }
						className={ classes( 'cv-modal', `cv-modal--${ size }`, className ) }
						role="dialog"
						aria-modal="true"
						aria-labelledby={ title ? titleId : undefined }
						tabIndex={ -1 }
					>
						{ title && (
							<div className="cv-modal__header">
								<h2 id={ titleId } className="cv-modal__title">{ title }</h2>
								{ close && (
									<button ref={ closeButton } type="button" className="cv-icon-btn" aria-label={ closeLabel } onClick={ close }>
										<CloseIcon />
									</button>
								) }
							</div>
						) }
						<div className="cv-modal__body">{ children }</div>
						{ footer && <div className="cv-modal__footer">{ footer }</div> }
					</div>
				</div>,
				root
			) }
		</>
	);
}
