/**
 * Spinner — the .cv-spinner inline indicator (currentColor, reduced-motion aware).
 */
import { classes } from './classes';

export function Spinner( { className = '', ...rest } ) {
	return <span className={ classes( 'cv-spinner', className ) } aria-hidden="true" { ...rest } />;
}
