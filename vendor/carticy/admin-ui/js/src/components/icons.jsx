/**
 * Internal icons shared by the components (not exported from the package).
 * Each is decorative: the control that holds it carries the accessible name.
 * Stroke, fill and size come from the container's CSS.
 */
import { classes } from './classes';

/** The close cross of dialogs, toasts, chips and clear buttons. */
export const CloseIcon = () => (
	<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6 6 18" /></svg>
);

/** The check of chosen options and completed steps. */
export const CheckIcon = () => (
	<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M20 6 9 17l-5-5" /></svg>
);

/**
 * The small tile an icon sits in before a heading or in a stat card's corner.
 *
 * @param {Object}          props
 * @param {string}          props.tone     'neutral' (default) | 'accent' | 'success' | 'warning' | 'critical'.
 * @param {*}               props.children The icon (an inline SVG).
 */
export function IconTile( { tone = 'neutral', children } ) {
	return (
		<span className={ classes( 'cv-icon-tile', 'neutral' !== tone && `cv-icon-tile--${ tone }` ) } aria-hidden="true">
			{ children }
		</span>
	);
}
