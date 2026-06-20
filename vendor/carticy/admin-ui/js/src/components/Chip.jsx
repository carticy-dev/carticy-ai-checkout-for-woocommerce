/**
 * Chip — wraps .cv-chip (compact tabular-numeric tag).
 *
 * tone: 'pos' | 'neg' | 'warn' | 'flat' | 'first'
 */
export function Chip( { tone = 'flat', className = '', children, ...rest } ) {
	const classes = [ 'cv-chip', tone ? `cv-chip--${ tone }` : '', className ].filter( Boolean ).join( ' ' );

	return (
		<span className={ classes } { ...rest }>
			{ children }
		</span>
	);
}
