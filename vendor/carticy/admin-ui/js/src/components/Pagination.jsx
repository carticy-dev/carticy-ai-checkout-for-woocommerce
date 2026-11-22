/**
 * Pagination — previous / next and numbered page buttons (.cv-pagination).
 *
 * Controlled: `page` is the current page (1-based), `total` the number of
 * pages, and `onChange( page )` asks for another one. Long ranges collapse to
 * the first page, the last page and the pages around the current one, with an
 * ellipsis for each gap; the number of buttons stays the same from page to
 * page, so the control does not jump under the pointer. Nothing renders for a
 * single page except the optional `summary` (e.g. "Showing 1-20 of 54").
 *
 * On narrow screens (782px and below, WordPress's own breakpoint) the buttons
 * grow to 44px for touch and `siblings` drops to 0, so the row fits a phone.
 * Other props go to the root.
 */
import { useEffect, useState } from '@wordpress/element';
import { classes } from './classes';

const NARROW = '(max-width: 782px)';

/** True while the viewport is at the package's narrow breakpoint. */
function useNarrow() {
	const query = () => ( typeof window !== 'undefined' && window.matchMedia ? window.matchMedia( NARROW ) : null );
	const [ narrow, setNarrow ] = useState( () => !! query()?.matches );
	useEffect( () => {
		const list = query();
		if ( ! list ) {
			return undefined;
		}
		const update = () => setNarrow( list.matches );
		update();
		list.addEventListener( 'change', update );
		return () => list.removeEventListener( 'change', update );
	}, [] );
	return narrow;
}

/**
 * The items to show for a page in a range: page numbers, plus 'gap' where pages
 * are left out.
 *
 * @param {number} page     Current page, 1-based.
 * @param {number} total    Number of pages.
 * @param {number} siblings Pages shown on each side of the current one.
 * @return {Array<number|string>} Page numbers and 'gap' markers, in order.
 */
function paginationItems( page, total, siblings ) {
	const range = ( from, to ) => Array.from( { length: to - from + 1 }, ( _, i ) => from + i );
	// First, last, current, the siblings and two gaps.
	const slots = 2 * siblings + 5;
	if ( total <= slots ) {
		return range( 1, total );
	}
	const leftGap = page - siblings > 3;
	const rightGap = page + siblings < total - 2;
	if ( ! leftGap ) {
		return [ ...range( 1, slots - 2 ), 'gap', total ];
	}
	if ( ! rightGap ) {
		return [ 1, 'gap', ...range( total - ( slots - 3 ), total ) ];
	}
	return [ 1, 'gap', ...range( page - siblings, page + siblings ), 'gap', total ];
}

export function Pagination( {
	page = 1,
	total = 1,
	onChange,
	siblings = 1,
	summary = null,
	label = 'Pagination',
	previousLabel = 'Previous',
	nextLabel = 'Next',
	pageLabel = ( number ) => `Page ${ number }`,
	className = '',
	...rest
} ) {
	const narrow = useNarrow();
	const count = Math.max( 0, Math.floor( total ) );
	const current = Math.min( Math.max( 1, Math.floor( page ) ), Math.max( 1, count ) );
	const go = ( target ) => {
		if ( target !== current && target >= 1 && target <= count && onChange ) {
			onChange( target );
		}
	};

	if ( count <= 1 ) {
		return summary ? (
			<div className={ classes( 'cv-pagination', className ) } { ...rest }>
				<p className="cv-pagination__summary">{ summary }</p>
			</div>
		) : null;
	}

	return (
		<nav className={ classes( 'cv-pagination', className ) } aria-label={ label } { ...rest }>
			{ summary && <p className="cv-pagination__summary">{ summary }</p> }
			<ul className="cv-pagination__list">
				<li>
					<button
						type="button"
						className="cv-pagination__btn cv-pagination__step"
						disabled={ current <= 1 }
						onClick={ () => go( current - 1 ) }
					>
						<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m15 18-6-6 6-6" /></svg>
						<span className="cv-pagination__step-label">{ previousLabel }</span>
					</button>
				</li>
				{ paginationItems( current, count, narrow ? 0 : siblings ).map( ( item, index ) =>
					'gap' === item ? (
						<li key={ `gap-${ index }` } className="cv-pagination__gap" aria-hidden="true">
							<svg viewBox="0 0 24 24" focusable="false"><circle cx="5" cy="12" r="1" /><circle cx="12" cy="12" r="1" /><circle cx="19" cy="12" r="1" /></svg>
						</li>
					) : (
						<li key={ item }>
							<button
								type="button"
								className={ classes( 'cv-pagination__btn', item === current && 'is-current' ) }
								aria-label={ pageLabel( item ) }
								aria-current={ item === current ? 'page' : undefined }
								onClick={ () => go( item ) }
							>
								{ item }
							</button>
						</li>
					)
				) }
				<li>
					<button
						type="button"
						className="cv-pagination__btn cv-pagination__step"
						disabled={ current >= count }
						onClick={ () => go( current + 1 ) }
					>
						<span className="cv-pagination__step-label">{ nextLabel }</span>
						<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m9 18 6-6-6-6" /></svg>
					</button>
				</li>
			</ul>
		</nav>
	);
}
