/**
 * Internal helper (not exported from the package): focus moved by script
 * shows the focus ring only when the reader is using the keyboard.
 *
 * Browsers decide whether script focus shows a ring from the element that had
 * focus before it. Safari (iOS and macOS) does not focus a button on a tap or
 * a click, so after a tap on a section link, a tab or a drawer's opener the
 * element focused next would show the keyboard ring. After a pointer press,
 * focus therefore moves with `focusVisible: false`; after a key press the
 * browser decides, so keyboard readers keep the ring.
 *
 * The reader's input is followed from the moment this module loads, before
 * any component mounts, so the press that opens a dialog rendered only while
 * open is already known when the dialog takes focus.
 */
let pointerLast = false;

if ( 'undefined' !== typeof document ) {
	document.addEventListener( 'pointerdown', () => {
		pointerLast = true;
	}, { capture: true, passive: true } );
	document.addEventListener( 'keydown', () => {
		pointerLast = false;
	}, { capture: true, passive: true } );
}

/**
 * Move focus to an element, without the ring when a pointer press led here.
 *
 * @param {HTMLElement} element The element to focus.
 * @param {Object}      options Options for element.focus(), such as preventScroll.
 */
export function focusElement( element, options = {} ) {
	element.focus( pointerLast ? { ...options, focusVisible: false } : options );
}
