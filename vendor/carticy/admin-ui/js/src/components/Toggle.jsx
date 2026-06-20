/**
 * Toggle — wraps .cv-toggle (accessible checkbox track/thumb) and the
 * .cv-toggle-row labeled variant.
 */
export function Toggle( { size, indeterminate = false, className = '', ...rest } ) {
	const classes = [
		'cv-toggle',
		size ? `cv-toggle--${ size }` : '',
		indeterminate ? 'is-indeterminate' : '',
		className,
	]
		.filter( Boolean )
		.join( ' ' );

	return (
		<span className={ classes }>
			<input type="checkbox" className="cv-toggle__input" { ...rest } />
			<span className="cv-toggle__track" aria-hidden="true">
				<span className="cv-toggle__thumb" />
			</span>
		</span>
	);
}

export function ToggleRow( { label, ...rest } ) {
	return (
		<label className="cv-toggle-row">
			<Toggle { ...rest } />
			<span className="cv-toggle-row__label">{ label }</span>
		</label>
	);
}
