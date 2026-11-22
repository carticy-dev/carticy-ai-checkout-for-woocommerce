/**
 * Toggle — wraps .cv-toggle (accessible checkbox track/thumb) and the
 * .cv-toggle-row labeled variant.
 *
 * ToggleRow takes an optional `help` line under its label. The help sits
 * outside the label, so a screen reader reads the label as the switch's name
 * and the help once, as its description (aria-describedby).
 */
import { useId } from '@wordpress/element';
import { classes } from './classes';

export function Toggle( { size, indeterminate = false, className = '', ...rest } ) {
	return (
		<span className={ classes( 'cv-toggle', size && `cv-toggle--${ size }`, indeterminate && 'is-indeterminate', className ) }>
			<input type="checkbox" className="cv-toggle__input" { ...rest } />
			<span className="cv-toggle__track" aria-hidden="true">
				<span className="cv-toggle__thumb" />
			</span>
		</span>
	);
}

export function ToggleRow( { label, help, 'aria-describedby': describedBy, ...rest } ) {
	const helpId = `${ useId() }-help`;
	if ( ! help ) {
		return (
			<label className="cv-toggle-row">
				<Toggle aria-describedby={ describedBy } { ...rest } />
				<span className="cv-toggle-row__label">{ label }</span>
			</label>
		);
	}
	return (
		<div className="cv-toggle-row cv-toggle-row--help">
			<label className="cv-toggle-row__main">
				<Toggle aria-describedby={ classes( describedBy, helpId ) } { ...rest } />
				<span className="cv-toggle-row__label">{ label }</span>
			</label>
			<p className="cv-toggle-row__help" id={ helpId }>{ help }</p>
		</div>
	);
}
