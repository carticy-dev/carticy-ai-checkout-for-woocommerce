/**
 * SectionHeader — wraps .cv-section__head with optional right-side meta.
 */
export function SectionHeader( { title, meta = null, className = '', ...rest } ) {
	return (
		<div className={ [ 'cv-section__head', className ].filter( Boolean ).join( ' ' ) } { ...rest }>
			<h2 className="cv-section__title">{ title }</h2>
			{ meta && <span className="cv-section__meta">{ meta }</span> }
		</div>
	);
}
