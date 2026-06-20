/**
 * Banner — wraps .cv-banner (page-level notice with left-edge severity).
 *
 * tone: 'info' | 'warning' | 'critical' | 'success' | 'persistent'
 * WP-native `.notice` shells remain valid alongside this component for
 * PHP-rendered admin notices (hybrid doctrine from the v2 audit).
 */
export function Banner( { tone = 'info', title, icon = null, actions = null, className = '', children, ...rest } ) {
	const classes = [ 'cv-banner', tone ? `cv-banner--${ tone }` : '', className ].filter( Boolean ).join( ' ' );

	return (
		<div className={ classes } role={ 'critical' === tone ? 'alert' : 'status' } { ...rest }>
			{ icon }
			<div className="cv-banner__body">
				{ title && <p className="cv-banner__title">{ title }</p> }
				{ children && <p className="cv-banner__desc">{ children }</p> }
			</div>
			{ actions && <div className="cv-banner__actions">{ actions }</div> }
		</div>
	);
}
