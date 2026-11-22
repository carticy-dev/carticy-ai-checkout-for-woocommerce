/**
 * Chip — wraps .cv-chip (compact tabular-numeric tag).
 *
 * tone: 'neutral' (default) | 'success' | 'warning' | 'critical'
 */
import { classes } from './classes';

export function Chip( { tone = 'neutral', className = '', children, ...rest } ) {
	return (
		<span className={ classes( 'cv-chip', tone && `cv-chip--${ tone }`, className ) } { ...rest }>
			{ children }
		</span>
	);
}
