/**
 * StatusPill — wraps .cv-status (pill with leading dot via ::before).
 *
 * The status prop maps to a .cv-status--{status} modifier: the connection
 * states connected, connecting, disconnected, expired, stale and cancelled,
 * and the tones by meaning success, info, neutral, warning and critical for
 * every other state. Pass label text as children.
 */
import { classes } from './classes';

export function StatusPill( { status, className = '', children, ...rest } ) {
	return (
		<span className={ classes( 'cv-status', status && `cv-status--${ status }`, className ) } { ...rest }>
			{ children }
		</span>
	);
}
