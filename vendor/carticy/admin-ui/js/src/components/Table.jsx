/**
 * Table — wraps the .cv-table component contract.
 *
 * A thin, ergonomic wrapper over the shared table styling: a columns config
 * with optional per-cell render, optional row selection (checkbox column), and
 * optional extra rows rendered full-width below a row (for detail panels or
 * inline forms) via renderRowExtra(row, colSpan). Selection state stays with
 * the consumer, so the component is presentational and reusable.
 *
 * columns: [{ key, header, render?(row), className?, headerClassName? }]
 */
import { Fragment } from '@wordpress/element';

export function Table( {
	columns = [],
	rows = [],
	getRowId = ( row ) => row.id,
	selectable = false,
	selectedIds = [],
	onToggleSelect,
	selectLabel = 'Select',
	renderRowExtra,
	emptyLabel = '',
	className = '',
} ) {
	const colSpan = columns.length + ( selectable ? 1 : 0 );
	const classes = [ 'cv-table', className ].filter( Boolean ).join( ' ' );

	return (
		<div className="cv-table-wrap">
		<table className={ classes }>
			<thead>
				<tr>
					{ selectable && <th className="cv-table__check" aria-label={ selectLabel } /> }
					{ columns.map( ( col ) => (
						<th key={ col.key } className={ col.headerClassName || '' }>{ col.header }</th>
					) ) }
				</tr>
			</thead>
			<tbody>
				{ rows.length === 0 ? (
					<tr><td colSpan={ colSpan } className="cv-table__empty">{ emptyLabel }</td></tr>
				) : (
					rows.map( ( row ) => {
						const id = getRowId( row );
						const extra = renderRowExtra ? renderRowExtra( row, colSpan ) : null;
						return (
							<Fragment key={ id }>
								<tr>
									{ selectable && (
										<td className="cv-table__check">
											<input
												type="checkbox"
												checked={ selectedIds.includes( id ) }
												onChange={ () => onToggleSelect && onToggleSelect( id ) }
												aria-label={ selectLabel }
											/>
										</td>
									) }
									{ columns.map( ( col ) => (
										<td key={ col.key } className={ col.className || '' }>
											{ col.render ? col.render( row ) : row[ col.key ] }
										</td>
									) ) }
								</tr>
								{ extra }
							</Fragment>
						);
					} )
				) }
			</tbody>
		</table>
		</div>
	);
}
