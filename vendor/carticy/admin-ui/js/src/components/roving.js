/**
 * Internal helper (not exported from the package): one tab stop for a row of
 * buttons, moved with the arrow keys (Segmented, Tabs).
 */
import { useRef } from '@wordpress/element';
import { focusElement } from './focus';

/**
 * Roving focus, as in the WAI-ARIA radio group (Segmented) and tabs (Tabs)
 * patterns.
 *
 * The selected item, or the first enabled one, is the only item in the Tab
 * order. Left and Right (following the reading direction) move to the next or
 * previous enabled item and select it, wrapping at the ends; Home and End go
 * to the first and last. With `upDown`, Down and Up move too, as a radio group
 * does; a horizontal tab list leaves them to scroll the page.
 *
 * @param {Object}   options
 * @param {Array}    options.keys      The items' keys (option values, tab ids), in display order.
 * @param {*}        options.current   The selected item's key.
 * @param {Function} options.isEnabled Whether the item with this key can take focus.
 * @param {Function} options.onSelect  Called with the key of the item the reader moved to or clicked.
 * @param {boolean}  options.upDown    Whether Down and Up move as well.
 * @return {{ref: Function, tabIndex: Function, select: Function, onKeyDown: Function}} Per-item
 *         `ref( key )` and `tabIndex( key )`, `select( key )` for clicks, and `onKeyDown( event, key )`.
 */
export function useRovingFocus( { keys, current, isEnabled, onSelect, upDown = false } ) {
	const nodes = useRef( new Map() );
	const stops = keys.filter( isEnabled );
	const stop = stops.includes( current ) ? current : stops[ 0 ];

	const select = ( key ) => {
		const node = nodes.current.get( key );
		if ( node ) {
			focusElement( node );
		}
		onSelect( key );
	};

	const onKeyDown = ( event, key ) => {
		if ( ! stops.length ) {
			return;
		}
		const rtl = 'rtl' === window.getComputedStyle( event.currentTarget ).direction;
		// Move from the item that has focus, even if the consumer has not applied the last choice.
		const at = stops.indexOf( key );
		const next = () => stops[ ( at + 1 ) % stops.length ];
		const previous = () => stops[ ( at - 1 + stops.length ) % stops.length ];
		const moves = {
			ArrowRight: rtl ? previous : next,
			ArrowLeft: rtl ? next : previous,
			Home: () => stops[ 0 ],
			End: () => stops[ stops.length - 1 ],
			...( upDown ? { ArrowDown: next, ArrowUp: previous } : {} ),
		};
		const move = moves[ event.key ];
		if ( move ) {
			event.preventDefault();
			select( move() );
		}
	};

	return {
		ref: ( key ) => ( node ) => ( node ? nodes.current.set( key, node ) : nodes.current.delete( key ) ),
		tabIndex: ( key ) => ( key === stop ? 0 : -1 ),
		select,
		onKeyDown,
	};
}
