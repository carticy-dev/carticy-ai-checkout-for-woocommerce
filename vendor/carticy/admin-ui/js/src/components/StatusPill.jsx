/**
 * StatusPill — wraps .cv-status (pill with leading dot via ::before).
 *
 * The status prop maps to a .cv-status--{status} modifier; the CSS ships
 * the states the products actually use (connected, connecting, expired,
 * quota, stale, cancelled, disconnected, ...). Pass label text as children.
 */
export function StatusPill( { status, className = '', children, ...rest } ) {
	const classes = [ 'cv-status', status ? `cv-status--${ status }` : '', className ].filter( Boolean ).join( ' ' );

	return (
		<span className={ classes } { ...rest }>
			{ children }
		</span>
	);
}
