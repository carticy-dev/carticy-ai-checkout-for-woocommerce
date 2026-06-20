/**
 * Mount-root helper.
 *
 * Every token and global reset in this design system is scoped to the
 * `.cv-root` class so multiple Carticy plugins (and versions) can coexist
 * on one site without leaking styles into general wp-admin. Call this on
 * your plugin's mount element before rendering the React app.
 */

/**
 * Mark an element as the design-system root.
 *
 * @param {HTMLElement|null} element  Mount element (e.g. document.getElementById('root')).
 * @param {Object}           options  Optional flags.
 * @param {string}           options.page  Optional data-page attribute value.
 * @return {HTMLElement|null} The same element, for chaining into render().
 */
export function adminUiRoot( element, options = {} ) {
	if ( ! element || ! element.classList ) {
		return element;
	}

	element.classList.add( 'cv-root' );
	if ( options.page ) {
		element.setAttribute( 'data-page', options.page );
	}

	return element;
}
