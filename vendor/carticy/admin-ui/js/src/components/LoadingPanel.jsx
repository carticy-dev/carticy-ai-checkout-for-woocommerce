/**
 * LoadingPanel — a whole screen or panel waiting for data whose layout is not
 * known yet: a centered spinner with room around it and its label, read by
 * screen readers. With showLabel the label also shows under the spinner, for a
 * wait the reader should see explained ("Checking for a consent platform…").
 *
 * Content whose layout is known keeps its shape with Skeleton instead; use
 * the inline Spinner (or Button's `loading`) for busy controls. When the
 * request settles, replace the panel with the result, an EmptyState or a
 * Banner with a retry action.
 */
import { Spinner } from './Spinner';
import { classes } from './classes';

export function LoadingPanel( { label, showLabel = false, className = '', ...rest } ) {
	return (
		<div className={ classes( 'cv-loading-panel', className ) } role="status" { ...rest }>
			<Spinner />
			<span className={ showLabel ? 'cv-loading-panel__label' : 'cv-sr-only' }>{ label }</span>
		</div>
	);
}
