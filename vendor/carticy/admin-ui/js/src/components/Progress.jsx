/**
 * Progress — a progress bar (.cv-progress) for work the screen is waiting on.
 *
 * Determinate when `value` is a number (0…max): the bar fills and the
 * percentage shows next to the label. Leave `value` out while the amount of
 * work is not known yet: the bar runs an indeterminate animation (a static
 * bar when the reader reduces motion) and no value is announced.
 *
 * label names the bar for screen readers and sits above it; valueText
 * replaces the visible percentage and the announced value ("12 of 40 stores");
 * meta is a line below the bar; tone: 'warning' | 'critical'. Other props go
 * to the root.
 */
import { useId } from '@wordpress/element';
import { classes } from './classes';

export function Progress( { value, max = 100, label, valueText, showValue = true, meta = null, tone, className = '', ...rest } ) {
	const labelId = useId();
	const limit = max > 0 ? max : 100;
	const determinate = 'number' === typeof value && Number.isFinite( value );
	const now = determinate ? Math.min( limit, Math.max( 0, value ) ) : undefined;
	const percent = determinate ? Math.round( ( now / limit ) * 100 ) : 0;
	const shownValue = determinate && showValue ? valueText || `${ percent }%` : null;

	return (
		<div className={ classes( 'cv-progress', tone && `cv-progress--${ tone }`, ! determinate && 'cv-progress--indeterminate', className ) } { ...rest }>
			{ ( label || shownValue ) && (
				<div className="cv-progress__label">
					<span id={ labelId }>{ label }</span>
					{ /* The bar announces the value itself. */ }
					{ shownValue && <span aria-hidden="true">{ shownValue }</span> }
				</div>
			) }
			<div
				className="cv-progress__track"
				role="progressbar"
				aria-labelledby={ label ? labelId : undefined }
				aria-valuemin={ 0 }
				aria-valuemax={ limit }
				aria-valuenow={ now }
				aria-valuetext={ determinate ? valueText : undefined }
			>
				<div className="cv-progress__fill" style={ determinate ? { width: `${ percent }%` } : undefined } />
			</div>
			{ meta && <p className="cv-progress__meta">{ meta }</p> }
		</div>
	);
}
