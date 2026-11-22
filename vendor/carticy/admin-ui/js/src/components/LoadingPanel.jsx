/**
 * LoadingPanel — a whole screen or panel waiting for its data: a centered
 * spinner with room around it and a label for screen readers.
 *
 * Use the inline Spinner (or Button's `loading`) for busy controls. When the
 * request settles, replace the panel with the result, an EmptyState or a
 * Banner with a retry action.
 */
import { Spinner } from './Spinner';
import { classes } from './classes';

export function LoadingPanel( { label, className = '', ...rest } ) {
	return (
		<div className={ classes( 'cv-loading-panel', className ) } role="status" { ...rest }>
			<Spinner />
			<span className="cv-sr-only">{ label }</span>
		</div>
	);
}
