/**
 * Card — wraps .cv-card. Accent variants render the inset left-edge stripe.
 *
 * variant: '' | 'critical' | 'active' | 'locked'
 */
import { classes } from './classes';

export function Card( { variant = '', size, flush = false, clickable = false, className = '', children, ...rest } ) {
	return (
		<div className={ classes( 'cv-card', variant && `cv-card--${ variant }`, size && `cv-card--${ size }`, flush && 'cv-card--flush', clickable && 'cv-card--clickable', className ) } { ...rest }>
			{ children }
		</div>
	);
}
