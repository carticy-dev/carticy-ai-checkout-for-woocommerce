/**
 * Banner — wraps .cv-banner (page-level notice with left-edge severity).
 *
 * tone: 'info' | 'success' | 'warning' | 'critical'
 * WP-native `.notice` shells remain valid alongside this component for
 * PHP-rendered admin notices (the icon doctrine in the README).
 *
 * icon: an inline SVG drawn before the text in the tone's colour.
 */
import { classes } from './classes';

export function Banner( { tone = 'info', title, icon = null, actions = null, className = '', children, ...rest } ) {
	return (
		<div className={ classes( 'cv-banner', tone && `cv-banner--${ tone }`, className ) } role={ 'critical' === tone ? 'alert' : 'status' } { ...rest }>
			{ icon && <span className="cv-banner__icon" aria-hidden="true">{ icon }</span> }
			<div className="cv-banner__body">
				{ title && <p className="cv-banner__title">{ title }</p> }
				{ children && <p className="cv-banner__desc">{ children }</p> }
			</div>
			{ actions && <div className="cv-banner__actions">{ actions }</div> }
		</div>
	);
}
