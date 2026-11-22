/**
 * Card — wraps .cv-card. Accent variants render the inset left-edge stripe.
 *
 * variant: '' | 'critical' | 'active' | 'locked'
 * Other props and the ref go to the card element.
 */
import { forwardRef } from '@wordpress/element';
import { classes } from './classes';

export const Card = forwardRef( function Card( { variant = '', size, flush = false, clickable = false, className = '', children, ...rest }, ref ) {
	return (
		<div ref={ ref } className={ classes( 'cv-card', variant && `cv-card--${ variant }`, size && `cv-card--${ size }`, flush && 'cv-card--flush', clickable && 'cv-card--clickable', className ) } { ...rest }>
			{ children }
		</div>
	);
} );
