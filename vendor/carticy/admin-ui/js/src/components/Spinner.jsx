/**
 * Spinner — the .cv-spinner inline indicator (currentColor, reduced-motion aware).
 */
export function Spinner( { className = '', ...rest } ) {
	return <span className={ [ 'cv-spinner', className ].filter( Boolean ).join( ' ' ) } aria-hidden="true" { ...rest } />;
}
