/**
 * Table — wraps the .cv-table component contract.
 *
 * A thin, ergonomic wrapper over the shared table styling: a columns config
 * with optional per-cell render, optional row selection (checkbox column), and
 * optional extra rows rendered full-width below a row (for detail panels or
 * inline forms) via renderRowExtra(row, colSpan). Selection state stays with
 * the consumer, so the component is presentational and reusable. className,
 * other props and the ref go to the <table> (inside its scroll container,
 * the table's parent element).
 *
 * columns: [{ key, header, render?(row), className?, headerClassName?, sortable?, defaultSortDirection? }]
 *
 * Sorting (optional): pass `sort` ({ key, direction: 'asc'|'desc' }) and
 * `onSortChange( key, direction )`; columns marked `sortable` get a button in
 * their header and the sorted column carries aria-sort. The consumer sorts the
 * rows (or re-queries).
 *
 * Select all (optional): with `selectable`, pass `onToggleSelectAll( checked,
 * ids )` to put a checkbox in the selection column's header. It is checked when
 * every row in `rows` is selected and mixed when some are; `checked` is what
 * the reader asked for and `ids` are the ids of the rows shown.
 */
import { forwardRef, Fragment } from '@wordpress/element';
import { classes } from './classes';

const SORT_ICONS = {
	ascending: 'm7 14 5-5 5 5',
	descending: 'm7 10 5 5 5-5',
	none: 'm8 9 4-4 4 4M8 15l4 4 4-4',
};

function SelectAll( { ids, selectedIds, onToggleSelectAll, label } ) {
	const chosen = ids.filter( ( id ) => selectedIds.includes( id ) ).length;
	const all = ids.length > 0 && chosen === ids.length;
	const some = chosen > 0 && ! all;

	return (
		<input
			type="checkbox"
			className="cv-check"
			checked={ all }
			ref={ ( node ) => {
				if ( node ) {
					node.indeterminate = some;
				}
			} }
			disabled={ 0 === ids.length }
			onChange={ () => onToggleSelectAll( ! all, ids ) }
			aria-label={ label }
		/>
	);
}

function HeaderCell( { col, sort, onSortChange } ) {
	if ( ! col.sortable || ! onSortChange ) {
		return <th className={ col.headerClassName || '' }>{ col.header }</th>;
	}
	const sorted = sort && sort.key === col.key;
	const state = sorted ? ( 'desc' === sort.direction ? 'descending' : 'ascending' ) : 'none';
	const next = () => {
		if ( sorted ) {
			return 'desc' === sort.direction ? 'asc' : 'desc';
		}
		return col.defaultSortDirection || 'asc';
	};

	return (
		<th className={ col.headerClassName || '' } aria-sort={ sorted ? state : undefined }>
			<button
				type="button"
				className={ classes( 'cv-sort', sorted && 'is-sorted' ) }
				onClick={ () => onSortChange( col.key, next() ) }
			>
				{ col.header }
				<svg className="cv-sort__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
					<path d={ SORT_ICONS[ state ] } />
				</svg>
			</button>
		</th>
	);
}

export const Table = forwardRef( function Table( {
	columns = [],
	rows = [],
	getRowId = ( row ) => row.id,
	selectable = false,
	selectedIds = [],
	onToggleSelect,
	selectLabel = 'Select',
	onToggleSelectAll,
	selectAllLabel = 'Select all',
	sort,
	onSortChange,
	renderRowExtra,
	emptyLabel = '',
	className = '',
	...rest
}, ref ) {
	const colSpan = columns.length + ( selectable ? 1 : 0 );

	return (
		<div className="cv-table-wrap">
		<table ref={ ref } className={ classes( 'cv-table', className ) } { ...rest }>
			<thead>
				<tr>
					{ selectable && ( onToggleSelectAll ? (
						<th className="cv-table__check">
							<SelectAll
								ids={ rows.map( getRowId ) }
								selectedIds={ selectedIds }
								onToggleSelectAll={ onToggleSelectAll }
								label={ selectAllLabel }
							/>
						</th>
					) : (
						<th className="cv-table__check" aria-label={ selectLabel } />
					) ) }
					{ columns.map( ( col ) => (
						<HeaderCell key={ col.key } col={ col } sort={ sort } onSortChange={ onSortChange } />
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
												className="cv-check"
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
} );
