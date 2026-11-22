/**
 * Toggle — wraps .cv-toggle (accessible checkbox track/thumb) and the
 * .cv-toggle-row labeled variant. Both pass other props and the ref to the
 * checkbox.
 *
 * ToggleRow takes an optional `help` line under its label. The help sits
 * outside the label, so a screen reader reads the label as the switch's name
 * and the help once, as its description (aria-describedby).
 */
import { forwardRef, useId } from '@wordpress/element';
import { classes } from './classes';

export const Toggle = forwardRef( function Toggle( { size, indeterminate = false, className = '', ...rest }, ref ) {
	return (
		<span className={ classes( 'cv-toggle', size && `cv-toggle--${ size }`, indeterminate && 'is-indeterminate', className ) }>
			<input ref={ ref } type="checkbox" className="cv-toggle__input" { ...rest } />
			<span className="cv-toggle__track" aria-hidden="true">
				<span className="cv-toggle__thumb" />
			</span>
		</span>
	);
} );

export const ToggleRow = forwardRef( function ToggleRow( { label, help, 'aria-describedby': describedBy, ...rest }, ref ) {
	const helpId = `${ useId() }-help`;
	if ( ! help ) {
		return (
			<label className="cv-toggle-row">
				<Toggle ref={ ref } aria-describedby={ describedBy } { ...rest } />
				<span className="cv-toggle-row__label">{ label }</span>
			</label>
		);
	}
	return (
		<div className="cv-toggle-row cv-toggle-row--help">
			<label className="cv-toggle-row__main">
				<Toggle ref={ ref } aria-describedby={ classes( describedBy, helpId ) } { ...rest } />
				<span className="cv-toggle-row__label">{ label }</span>
			</label>
			<p className="cv-toggle-row__help" id={ helpId }>{ help }</p>
		</div>
	);
} );
