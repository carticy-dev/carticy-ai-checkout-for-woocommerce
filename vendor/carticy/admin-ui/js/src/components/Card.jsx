/**
 * Card — wraps .cv-card. Accent variants render the inset left-edge stripe.
 *
 * variant: '' | 'critical' | 'active' | 'locked'
 */
export function Card( { variant = '', size, flush = false, clickable = false, className = '', children, ...rest } ) {
	const classes = [
		'cv-card',
		variant ? `cv-card--${ variant }` : '',
		size ? `cv-card--${ size }` : '',
		flush ? 'cv-card--flush' : '',
		clickable ? 'cv-card--clickable' : '',
		className,
	]
		.filter( Boolean )
		.join( ' ' );

	return (
		<div className={ classes } { ...rest }>
			{ children }
		</div>
	);
}
