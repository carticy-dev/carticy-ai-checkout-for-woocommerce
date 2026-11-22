/**
 * Skeleton — placeholder shapes (.cv-skeleton) for content whose layout is
 * known while its data loads: a card's text, a stat value, table cells, a
 * chart area. The page keeps its shape, so nothing moves when the data
 * arrives. LoadingPanel is for a panel whose layout is not known yet.
 *
 * variant 'text' (default) draws `lines` bars, each the height of a line of
 * the surrounding text, the last of several shorter; placed inside a
 * .cv-stat__value it takes the value's size. variant 'block' is one
 * rectangle, `height` tall (a chart or an image). `width` and `height` take a
 * CSS length or a number of pixels.
 *
 * Decorative (aria-hidden): the region it fills carries aria-busy and names
 * what is loading in a status line (README, "Loading"). Static when the reader
 * reduces motion. Other props go to the root.
 */
import { forwardRef } from '@wordpress/element';
import { classes } from './classes';

const length = ( value ) => ( 'number' === typeof value ? `${ value }px` : value );

export const Skeleton = forwardRef( function Skeleton( { variant = 'text', lines = 1, width, height, className = '', style, ...rest }, ref ) {
	const block = 'block' === variant;
	const count = Math.max( 1, Math.round( Number( lines ) ) || 1 );

	return (
		<span
			ref={ ref }
			className={ classes( 'cv-skeleton', block && 'cv-skeleton--block', className ) }
			style={ { width: length( width ), height: block ? length( height ) : undefined, ...style } }
			aria-hidden="true"
			{ ...rest }
		>
			{ ! block && Array.from( { length: count }, ( _, index ) => <span key={ index } className="cv-skeleton__line" /> ) }
		</span>
	);
} );
