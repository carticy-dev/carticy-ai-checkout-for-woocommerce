/**
 * SearchBar — a controlled search input with submit and optional clear.
 *
 * Presentational: the consumer owns the value and decides what submit does
 * (the input value is distinct from the applied query, so a store can keep
 * typing without re-querying until submit). Wraps .cv-search: a search icon
 * inside the field, which takes the room left beside the buttons. The field is
 * named by `aria-label`, or by the placeholder when none is given. Other
 * props go to the form.
 */
import { Button } from './Button';
import { classes } from './classes';

export function SearchBar( {
	value = '',
	onChange,
	onSubmit,
	onClear,
	placeholder = '',
	submitLabel = 'Search',
	clearLabel = 'Clear',
	'aria-label': ariaLabel = '',
	className = '',
	...rest
} ) {
	return (
		<form
			className={ classes( 'cv-search', className ) }
			role="search"
			{ ...rest }
			onSubmit={ ( event ) => {
				event.preventDefault();
				if ( onSubmit ) {
					onSubmit( value );
				}
			} }
		>
			<div className="cv-input-wrap cv-search__field">
				<span className="cv-input-wrap__icon" aria-hidden="true">
					<svg viewBox="0 0 24 24" focusable="false"><circle cx="11" cy="11" r="7" /><path d="m20 20-3.5-3.5" /></svg>
				</span>
				<input
					type="search"
					className="cv-input cv-search__input"
					placeholder={ placeholder }
					aria-label={ ariaLabel || placeholder || submitLabel }
					value={ value }
					onChange={ ( event ) => onChange && onChange( event.target.value ) }
				/>
			</div>
			<Button variant="secondary" type="submit">{ submitLabel }</Button>
			{ onClear && '' !== value && (
				<Button variant="tertiary" type="button" onClick={ onClear }>{ clearLabel }</Button>
			) }
		</form>
	);
}
