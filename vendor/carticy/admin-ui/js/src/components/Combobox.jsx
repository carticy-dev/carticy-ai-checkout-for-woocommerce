/**
 * Combobox — a searchable picker for one value or several (WAI-ARIA 1.2
 * combobox with a listbox popup). The list is the shared dropdown menu
 * (.cv-dropdown__menu / .cv-dropdown__option); several values show as chips.
 *
 * Controlled: `value` is the chosen value (or null) when single, an array of
 * values with `multiple`; `onChange` receives the next value or array.
 *
 * options: [ { value, label, description?, disabled? } ] — `label` is a
 * string; it is shown and searched. Typing filters the options by label; pass
 * `filter={ false }` and `onInputChange( query )` to search on the server, and
 * keep the chosen options in `options` so their chips keep their labels.
 *
 * Keyboard: Down / Up open the list and move through it, Enter chooses (with
 * `multiple`, toggles and keeps the list open), Escape closes the list (a
 * second Escape clears the typed text), Backspace in an empty field removes
 * the last chip. Focus stays in the field; the option under the keyboard is
 * announced through aria-activedescendant.
 *
 * Label, help, error, `required` / `requiredLabel` and the field's
 * aria-describedby (the consumer's first) work as in TextField. Other props
 * go to the .cv-field root.
 */
import { useEffect, useRef, useState } from '@wordpress/element';
import { classes } from './classes';
import { FieldShell, useDescription } from './Field';
import { CheckIcon, CloseIcon } from './icons';

export function Combobox( {
	label,
	options = [],
	value,
	onChange,
	multiple = false,
	placeholder = '',
	help,
	error,
	required = false,
	requiredLabel = 'Required',
	disabled = false,
	filter = true,
	onInputChange,
	loading = false,
	loadingLabel = 'Loading…',
	noResultsLabel = 'No matches',
	removeLabel = ( optionLabel ) => `Remove ${ optionLabel }`,
	clearLabel = 'Clear selection',
	className = '',
	'aria-describedby': describedBy,
	...rest
} ) {
	const description = useDescription( { help, error, describedBy } );
	const listId = `${ description.id }-list`;
	const optionId = ( index ) => `${ description.id }-option-${ index }`;

	const input = useRef( null );
	const list = useRef( null );
	const [ open, setOpen ] = useState( false );
	// null while the reader is not typing: a single combobox then shows the chosen label.
	const [ query, setQuery ] = useState( null );
	const [ activeValue, setActiveValue ] = useState( null );

	let chosen = [];
	if ( multiple ) {
		chosen = Array.isArray( value ) ? value : [];
	} else if ( null !== value && undefined !== value && '' !== value ) {
		chosen = [ value ];
	}
	const isChosen = ( candidate ) => chosen.includes( candidate );
	const labelFor = ( candidate ) => options.find( ( option ) => option.value === candidate )?.label ?? String( candidate );

	const text = ( query ?? '' ).trim().toLowerCase();
	const visible = filter && text ? options.filter( ( option ) => String( option.label ).toLowerCase().includes( text ) ) : options;
	const enabled = visible.filter( ( option ) => ! option.disabled );
	// While typing, the first match is under the keyboard until the reader moves.
	const active = enabled.find( ( option ) => option.value === activeValue ) || ( open && text ? enabled[ 0 ] : undefined );
	const activeIndex = active ? visible.indexOf( active ) : -1;

	useEffect( () => {
		if ( open && activeIndex >= 0 ) {
			list.current?.children[ activeIndex ]?.scrollIntoView?.( { block: 'nearest' } );
		}
	}, [ open, activeIndex ] );

	const typed = ( next ) => {
		setQuery( next );
		if ( onInputChange ) {
			onInputChange( next ?? '' );
		}
	};

	const openList = ( fromEnd ) => {
		setOpen( true );
		const start = enabled.find( ( option ) => isChosen( option.value ) ) || enabled[ fromEnd ? enabled.length - 1 : 0 ];
		setActiveValue( start ? start.value : null );
	};

	const close = () => {
		setOpen( false );
		setActiveValue( null );
	};

	const move = ( step ) => {
		if ( ! enabled.length ) {
			return;
		}
		const at = active ? enabled.indexOf( active ) : -1;
		let next = at + step;
		if ( -1 === at ) {
			next = step > 0 ? 0 : enabled.length - 1;
		}
		setActiveValue( enabled[ Math.min( enabled.length - 1, Math.max( 0, next ) ) ].value );
	};

	const choose = ( option ) => {
		if ( ! option || option.disabled || ! onChange ) {
			return;
		}
		if ( multiple ) {
			onChange( isChosen( option.value ) ? chosen.filter( ( item ) => item !== option.value ) : [ ...chosen, option.value ] );
			setActiveValue( option.value );
			if ( query ) {
				typed( null );
			}
			return;
		}
		onChange( option.value );
		if ( null !== query ) {
			typed( null );
		}
		close();
	};

	const optionAt = ( target ) => visible[ Number( target.closest?.( '[role="option"]' )?.dataset.index ?? -1 ) ];

	const remove = ( item ) => {
		onChange?.( chosen.filter( ( entry ) => entry !== item ) );
		input.current?.focus();
	};

	const onKeyDown = ( event ) => {
		switch ( event.key ) {
			case 'ArrowDown':
				event.preventDefault();
				if ( ! open ) {
					openList( false );
				} else if ( ! event.altKey ) {
					move( 1 );
				}
				break;
			case 'ArrowUp':
				event.preventDefault();
				if ( ! open ) {
					openList( true );
				} else if ( event.altKey ) {
					close();
				} else {
					move( -1 );
				}
				break;
			case 'Enter':
				if ( open && active ) {
					event.preventDefault();
					choose( active );
				}
				break;
			case 'Escape':
				// Handled here, so a Drawer or Modal around the field stays open.
				if ( open ) {
					event.preventDefault();
					event.stopPropagation();
					close();
				} else if ( query ) {
					event.preventDefault();
					event.stopPropagation();
					typed( null );
				}
				break;
			case 'Backspace':
				if ( multiple && ! query && chosen.length ) {
					remove( chosen[ chosen.length - 1 ] );
				}
				break;
			case 'Tab':
				close();
				break;
			default:
		}
	};

	const shownText = query ?? ( multiple || ! chosen.length ? '' : labelFor( chosen[ 0 ] ) );
	const empty = open && ! visible.length;

	return (
		<FieldShell description={ description } label={ label } help={ help } error={ error } required={ required } requiredLabel={ requiredLabel } className={ className } { ...rest }>
			<div className={ classes( 'cv-combobox', multiple && 'cv-combobox--multi' ) }>
				<div className={ classes( 'cv-combobox__control', error && 'is-invalid', disabled && 'is-disabled' ) }>
					{ multiple && chosen.map( ( item ) => (
						<span key={ String( item ) } className="cv-chip cv-chip--neutral cv-combobox__chip">
							{ labelFor( item ) }
							<button
								type="button"
								className="cv-combobox__chip-remove"
								aria-label={ removeLabel( labelFor( item ) ) }
								disabled={ disabled }
								onClick={ () => remove( item ) }
							>
								<CloseIcon />
							</button>
						</span>
					) ) }
					<input
						ref={ input }
						id={ description.id }
						type="text"
						className="cv-combobox__input"
						role="combobox"
						aria-expanded={ open }
						aria-controls={ listId }
						aria-autocomplete="list"
						aria-activedescendant={ open && activeIndex >= 0 ? optionId( activeIndex ) : undefined }
						aria-invalid={ error ? 'true' : undefined }
						aria-required={ required || undefined }
						aria-describedby={ description.describedBy }
						autoComplete="off"
						placeholder={ multiple && chosen.length ? '' : placeholder }
						value={ shownText }
						disabled={ disabled }
						onChange={ ( event ) => {
							typed( event.target.value );
							setOpen( true );
							setActiveValue( null );
						} }
						onClick={ () => ! open && openList( false ) }
						onKeyDown={ onKeyDown }
						onBlur={ () => {
							close();
							if ( null !== query ) {
								typed( null );
							}
						} }
					/>
					{ ! multiple && chosen.length > 0 && ! disabled && (
						<button
							type="button"
							className="cv-icon-btn cv-combobox__clear"
							aria-label={ clearLabel }
							onClick={ () => {
								onChange?.( null );
								input.current?.focus();
							} }
						>
							<CloseIcon />
						</button>
					) }
					{ /* Pointer shortcut only: the field itself opens the list from the keyboard. */ }
					<button
						type="button"
						className="cv-combobox__toggle"
						tabIndex={ -1 }
						aria-hidden="true"
						disabled={ disabled }
						onMouseDown={ ( event ) => event.preventDefault() }
						onClick={ () => {
							if ( open ) {
								close();
								return;
							}
							input.current?.focus();
							openList( false );
						} }
					>
						<svg className="cv-dropdown__caret" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m6 9 6 6 6-6" /></svg>
					</button>
				</div>
				{ /* Focus stays in the field: mousedown on the list (options, scrollbar) is cancelled,
				     and the keyboard reaches the options through the field. */ }
				<ul
					ref={ list }
					id={ listId }
					className="cv-dropdown__menu cv-combobox__list"
					role="listbox"
					aria-labelledby={ label ? description.labelId : undefined }
					aria-multiselectable={ multiple || undefined }
					hidden={ ! open || ! visible.length }
					onMouseDown={ ( event ) => event.preventDefault() }
					onMouseMove={ ( event ) => {
						const option = optionAt( event.target );
						if ( option && ! option.disabled && option.value !== activeValue ) {
							setActiveValue( option.value );
						}
					} }
					onClick={ ( event ) => choose( optionAt( event.target ) ) }
				>
					{ visible.map( ( option, index ) => (
						<li
							key={ String( option.value ) }
							id={ optionId( index ) }
							data-index={ index }
							className={ classes( 'cv-dropdown__option', index === activeIndex && 'is-focused' ) }
							role="option"
							aria-selected={ isChosen( option.value ) }
							aria-disabled={ option.disabled || undefined }
						>
							{ multiple && <span className="cv-combobox__box" aria-hidden="true"><CheckIcon /></span> }
							<span className="cv-dropdown__option-main">
								<span>{ option.label }</span>
								{ option.description && <span className="cv-dropdown__option-sub">{ option.description }</span> }
							</span>
							{ ! multiple && <span className="cv-combobox__tick" aria-hidden="true"><CheckIcon /></span> }
						</li>
					) ) }
				</ul>
				{ /* Present from the start so "No matches" is announced when the list empties. */ }
				<div className="cv-combobox__status" role="status">
					{ empty && (
						<div className="cv-dropdown__menu cv-combobox__list">
							<p className="cv-dropdown__empty">{ loading ? loadingLabel : noResultsLabel }</p>
						</div>
					) }
				</div>
			</div>
		</FieldShell>
	);
}
