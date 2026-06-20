/**
 * BulkActionBar — the strip shown above a table when rows are selected.
 *
 * Presentational: the consumer renders it conditionally (when a selection
 * exists), passes a formatted count label, and supplies the action controls as
 * children. Wraps .cv-bulkbar.
 */
export function BulkActionBar( { label, children, className = '' } ) {
	const classes = [ 'cv-bulkbar', className ].filter( Boolean ).join( ' ' );

	return (
		<div className={ classes }>
			{ label && <span className="cv-bulkbar__count">{ label }</span> }
			<div className="cv-bulkbar__actions">{ children }</div>
		</div>
	);
}
