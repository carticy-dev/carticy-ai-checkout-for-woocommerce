/**
 * Internal helper (not exported from the package): join the class names that
 * are set, so a component writes classes( 'cv-x', active && 'is-active', className ).
 *
 * @param {...(string|false|null|undefined)} names Class names; falsy entries are left out.
 * @return {string} The class attribute value.
 */
export const classes = ( ...names ) => names.filter( Boolean ).join( ' ' );
