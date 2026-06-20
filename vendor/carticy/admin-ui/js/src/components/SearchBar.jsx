/**
 * SearchBar — a controlled search input with submit and optional clear.
 *
 * Presentational: the consumer owns the value and decides what submit does
 * (the input value is distinct from the applied query, so a store can keep
 * typing without re-querying until submit). Wraps .cv-search.
 */
import { Button } from './Button';

export function SearchBar( {
	value = '',
	onChange,
	onSubmit,
	onClear,
	placeholder = '',
	submitLabel = 'Search',
	clearLabel = 'Clear',
	className = '',
} ) {
	const classes = [ 'cv-search', className ].filter( Boolean ).join( ' ' );

	return (
		<form
			className={ classes }
			role="search"
			onSubmit={ ( event ) => {
				event.preventDefault();
				if ( onSubmit ) {
					onSubmit( value );
				}
			} }
		>
			<input
				type="search"
				className="cv-input cv-search__input"
				placeholder={ placeholder }
				value={ value }
				onChange={ ( event ) => onChange && onChange( event.target.value ) }
			/>
			<Button variant="secondary" size="sm" type="submit">{ submitLabel }</Button>
			{ onClear && '' !== value && (
				<Button variant="tertiary" size="sm" type="button" onClick={ onClear }>{ clearLabel }</Button>
			) }
		</form>
	);
}
