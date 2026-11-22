/**
 * Segmented — a single choice among a few options, drawn as one pill
 * (.cv-segmented / .cv-segmented__btn) and announced as a radio group.
 *
 * Controlled: `value` is the chosen option and `onChange( value )` picks
 * another. One option is in the Tab order (the chosen one, or the first that
 * is enabled); the arrow keys move to the next or previous enabled option and
 * choose it, Home and End go to the ends, as in the WAI-ARIA radio group
 * pattern. Left and Right follow the reading direction.
 *
 * options: [ { value, label, disabled?, count? } ]
 * label names the group for screen readers ("View", "Period"…). The pill
 * wraps onto a second line when its options do not fit. Other props go to the
 * root.
 */
import { classes } from './classes';
import { useRovingFocus } from './roving';

export function Segmented( { options = [], value, onChange, label, size, block = false, disabled = false, className = '', ...rest } ) {
	const roving = useRovingFocus( {
		keys: options.map( ( option ) => option.value ),
		current: value,
		isEnabled: ( key ) => ! disabled && ! options.find( ( option ) => option.value === key )?.disabled,
		onSelect: ( key ) => {
			if ( key !== value && onChange ) {
				onChange( key );
			}
		},
		upDown: true,
	} );

	return (
		<div
			className={ classes( 'cv-segmented', size && `cv-segmented--${ size }`, block && 'cv-segmented--block', className ) }
			role="radiogroup"
			aria-label={ label }
			aria-disabled={ disabled || undefined }
			{ ...rest }
		>
			{ options.map( ( option ) => {
				const checked = option.value === value;
				return (
					<button
						key={ option.value }
						ref={ roving.ref( option.value ) }
						type="button"
						role="radio"
						aria-checked={ checked }
						tabIndex={ roving.tabIndex( option.value ) }
						className={ classes( 'cv-segmented__btn', checked && 'is-active' ) }
						disabled={ disabled || option.disabled }
						onClick={ () => roving.select( option.value ) }
						onKeyDown={ ( event ) => roving.onKeyDown( event, option.value ) }
					>
						{ option.label }
						{ undefined !== option.count && <span className="cv-segmented__count">{ option.count }</span> }
					</button>
				);
			} ) }
		</div>
	);
}
