/**
 * Modal — wraps .cv-scrim + .cv-modal (sm/md/lg/xl) with header/body/footer.
 *
 * Escape and scrim-click close by default; pass dismissible={false} for
 * confirm-style modals that must resolve through their actions.
 */
import { useEffect } from '@wordpress/element';

export function Modal( { open, onClose, title, size = 'md', footer = null, dismissible = true, children } ) {
	useEffect( () => {
		if ( ! open || ! dismissible ) {
			return undefined;
		}
		const onKey = ( event ) => {
			if ( 'Escape' === event.key && onClose ) {
				onClose();
			}
		};
		document.addEventListener( 'keydown', onKey );
		return () => document.removeEventListener( 'keydown', onKey );
	}, [ open, dismissible, onClose ] );

	if ( ! open ) {
		return null;
	}

	return (
		<div className="cv-scrim" onClick={ dismissible && onClose ? onClose : undefined }>
			<div
				className={ `cv-modal cv-modal--${ size }` }
				role="dialog"
				aria-modal="true"
				aria-label={ 'string' === typeof title ? title : undefined }
				onClick={ ( event ) => event.stopPropagation() }
			>
				{ title && (
					<div className="cv-modal__header">
						<h2 className="cv-modal__title">{ title }</h2>
						{ dismissible && onClose && (
							<button type="button" className="cv-icon-btn" aria-label="Close" onClick={ onClose }>
								<svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true">
									<path d="M6 6l12 12M18 6L6 18" stroke="currentColor" strokeWidth="2" fill="none" strokeLinecap="round" />
								</svg>
							</button>
						) }
					</div>
				) }
				<div className="cv-modal__body">{ children }</div>
				{ footer && <div className="cv-modal__footer">{ footer }</div> }
			</div>
		</div>
	);
}
