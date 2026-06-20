/**
 * EmptyState — wraps .cv-empty (icon / title / desc / actions / meta).
 */
export function EmptyState( { icon = null, title, actions = null, meta = null, className = '', children, ...rest } ) {
	return (
		<div className={ [ 'cv-empty', className ].filter( Boolean ).join( ' ' ) } { ...rest }>
			{ icon && <div className="cv-empty__icon">{ icon }</div> }
			{ title && <h3 className="cv-empty__title">{ title }</h3> }
			{ children && <p className="cv-empty__desc">{ children }</p> }
			{ actions && <div className="cv-empty__actions">{ actions }</div> }
			{ meta && <p className="cv-empty__meta">{ meta }</p> }
		</div>
	);
}
