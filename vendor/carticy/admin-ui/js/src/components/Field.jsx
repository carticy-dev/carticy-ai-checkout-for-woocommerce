/**
 * Form fields — wrap .cv-label / .cv-input / .cv-select / .cv-textarea
 * with .cv-help and .cv-error-msg.
 *
 * Each field forwards its ref to the control itself (input, textarea or
 * select), so a consumer can focus it, for example as a Drawer's
 * initialFocusRef. The help or error text below a control is tied to it with
 * aria-describedby, so a screen reader reads it with the label; an
 * aria-describedby the consumer passes is kept and comes first.
 *
 * TextField takes an optional leading `icon` (an inline SVG) drawn inside the
 * input's border, for fields a reader finds faster by their symbol (phone,
 * email, link).
 *
 * A `required` field shows `requiredLabel` ("Required") beside its label. The
 * control's own required state is what screen readers announce, so the visible
 * word is hidden from them.
 *
 * useDescription and FieldShell are shared with Combobox (internal, not
 * exported from the package).
 */
import { forwardRef, useId } from '@wordpress/element';
import { classes } from './classes';

/**
 * Ids for a field's control, label, help and error lines, and the control's
 * aria-describedby: the consumer's own value first, then the help (hidden
 * while an error shows) or the error.
 *
 * @param {Object} options
 * @param {*}      options.help        Help line, if any.
 * @param {*}      options.error       Error line, if any.
 * @param {string} options.describedBy The consumer's aria-describedby, kept first.
 * @return {{id: string, labelId: string, helpId: string, errorId: string, showHelp: boolean, describedBy: (string|undefined)}} Ids and state.
 */
export function useDescription( { help, error, describedBy } ) {
	const id = useId();
	const helpId = `${ id }-help`;
	const errorId = `${ id }-error`;
	const showHelp = !! help && ! error;
	return {
		id,
		labelId: `${ id }-label`,
		helpId,
		errorId,
		showHelp,
		describedBy: classes( describedBy, showHelp && helpId, error && errorId ) || undefined,
	};
}

/** The .cv-field frame: label (with the required word), the control, then the help or error line. */
export function FieldShell( { description, label, help, error, required, requiredLabel, className = '', children, ...rest } ) {
	return (
		<div className={ classes( 'cv-field', className ) } { ...rest }>
			{ label && (
				<label className="cv-label" id={ description.labelId } htmlFor={ description.id }>
					{ label }
					{ required && requiredLabel && <span className="cv-label__required" aria-hidden="true">{ requiredLabel }</span> }
				</label>
			) }
			{ children }
			{ description.showHelp && <p className="cv-help" id={ description.helpId }>{ help }</p> }
			{ error && <span className="cv-error-msg" id={ description.errorId }>{ error }</span> }
		</div>
	);
}

export const TextField = forwardRef( function TextField( { label, help, error, icon = null, requiredLabel = 'Required', className = '', 'aria-describedby': describedBy, ...rest }, ref ) {
	const description = useDescription( { help, error, describedBy } );
	const control = (
		<input
			ref={ ref }
			id={ description.id }
			className={ classes( 'cv-input', error && 'cv-input--invalid', className ) }
			aria-invalid={ error ? 'true' : undefined }
			aria-describedby={ description.describedBy }
			{ ...rest }
		/>
	);

	return (
		<FieldShell description={ description } label={ label } help={ help } error={ error } required={ rest.required } requiredLabel={ requiredLabel }>
			{ icon ? (
				<div className={ classes( 'cv-input-wrap', error && 'cv-input-wrap--invalid', rest.disabled && 'is-disabled' ) }>
					<span className="cv-input-wrap__icon" aria-hidden="true">{ icon }</span>
					{ control }
				</div>
			) : control }
		</FieldShell>
	);
} );

export const TextareaField = forwardRef( function TextareaField( { label, help, error, requiredLabel = 'Required', className = '', 'aria-describedby': describedBy, ...rest }, ref ) {
	const description = useDescription( { help, error, describedBy } );

	return (
		<FieldShell description={ description } label={ label } help={ help } error={ error } required={ rest.required } requiredLabel={ requiredLabel }>
			<textarea
				ref={ ref }
				id={ description.id }
				className={ classes( 'cv-textarea', error && 'cv-textarea--invalid', className ) }
				aria-invalid={ error ? 'true' : undefined }
				aria-describedby={ description.describedBy }
				{ ...rest }
			/>
		</FieldShell>
	);
} );

export const SelectField = forwardRef( function SelectField( { label, help, error, options = [], requiredLabel = 'Required', className = '', children, 'aria-describedby': describedBy, ...rest }, ref ) {
	const description = useDescription( { help, error, describedBy } );

	return (
		<FieldShell description={ description } label={ label } help={ help } error={ error } required={ rest.required } requiredLabel={ requiredLabel }>
			<select
				ref={ ref }
				id={ description.id }
				className={ classes( 'cv-select', error && 'cv-select--invalid', className ) }
				aria-invalid={ error ? 'true' : undefined }
				aria-describedby={ description.describedBy }
				{ ...rest }
			>
				{ options.map( ( option ) => (
					<option key={ option.value } value={ option.value } disabled={ option.disabled }>
						{ option.label }
					</option>
				) ) }
				{ children }
			</select>
		</FieldShell>
	);
} );
