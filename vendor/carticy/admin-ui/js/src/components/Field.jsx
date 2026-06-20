/**
 * Form fields — wrap .cv-label / .cv-input / .cv-select / .cv-textarea
 * with .cv-help and .cv-error-msg.
 */
import { useId } from '@wordpress/element';

function FieldShell( { id, label, help, error, children } ) {
	return (
		<div className="cv-field">
			{ label && (
				<label className="cv-label" htmlFor={ id }>
					{ label }
				</label>
			) }
			{ children }
			{ help && ! error && <p className="cv-help">{ help }</p> }
			{ error && <span className="cv-error-msg">{ error }</span> }
		</div>
	);
}

export function TextField( { label, help, error, className = '', ...rest } ) {
	const id = useId();
	const classes = [ 'cv-input', error ? 'cv-input--invalid' : '', className ].filter( Boolean ).join( ' ' );

	return (
		<FieldShell id={ id } label={ label } help={ help } error={ error }>
			<input id={ id } className={ classes } aria-invalid={ error ? 'true' : undefined } { ...rest } />
		</FieldShell>
	);
}

export function TextareaField( { label, help, error, className = '', ...rest } ) {
	const id = useId();
	const classes = [ 'cv-textarea', error ? 'cv-textarea--invalid' : '', className ].filter( Boolean ).join( ' ' );

	return (
		<FieldShell id={ id } label={ label } help={ help } error={ error }>
			<textarea id={ id } className={ classes } aria-invalid={ error ? 'true' : undefined } { ...rest } />
		</FieldShell>
	);
}

export function SelectField( { label, help, error, options = [], className = '', children, ...rest } ) {
	const id = useId();
	const classes = [ 'cv-select', className ].filter( Boolean ).join( ' ' );

	return (
		<FieldShell id={ id } label={ label } help={ help } error={ error }>
			<select id={ id } className={ classes } aria-invalid={ error ? 'true' : undefined } { ...rest }>
				{ options.map( ( option ) => (
					<option key={ option.value } value={ option.value } disabled={ option.disabled }>
						{ option.label }
					</option>
				) ) }
				{ children }
			</select>
		</FieldShell>
	);
}
