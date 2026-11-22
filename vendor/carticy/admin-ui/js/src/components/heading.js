/**
 * Internal helper (not exported from the package): the element of a heading
 * whose level the consumer picks (EmptyState, FormSection, SectionHeader,
 * SectionLayout), so the outline stays right wherever the component sits.
 *
 * The screen's h1 is the AppHeader title, so levels run from 2 to 6: a level
 * outside that range is clamped, and a missing or invalid one gives `fallback`.
 *
 * @param {number|string} level    The requested level.
 * @param {number}        fallback The component's default level.
 * @return {string} The tag name, 'h2' to 'h6'.
 */
export function headingTag( level, fallback ) {
	const number = Math.round( Number( level ) );
	return `h${ level && Number.isFinite( number ) ? Math.min( Math.max( number, 2 ), 6 ) : fallback }`;
}
