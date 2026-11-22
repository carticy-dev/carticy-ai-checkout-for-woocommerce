/**
 * Stepper — wraps .cv-stepper (numbered steps, accent active, success done).
 *
 * steps: [ { id, label } ]; activeId marks the current step (aria-current=
 * "step"); every step before it renders as done and shows a check icon in
 * place of its number, announced as `doneLabel` (default 'Completed'; pass a
 * translated string).
 *
 * At 782px and below the numbered rail becomes a progress track, one segment
 * per step, under a line with the current step's label and
 * `progressLabel( number, total )` ("Step 3 of 9"). That line repeats what the
 * steps already announce, so it is hidden from screen readers; the step labels
 * stay readable to them.
 */
import { Fragment } from '@wordpress/element';
import { classes } from '../components/classes';
import { CheckIcon } from '../components/icons';

export function Stepper( {
	steps = [],
	activeId,
	doneLabel = 'Completed',
	progressLabel = ( number, total ) => `Step ${ number } of ${ total }`,
	className = '',
	...rest
} ) {
	const activeIndex = steps.findIndex( ( step ) => step.id === activeId );

	return (
		<div className={ classes( 'cv-stepper', className ) } { ...rest }>
			{ activeIndex >= 0 && (
				<div className="cv-stepper__summary" aria-hidden="true">
					<span className="cv-stepper__current">{ steps[ activeIndex ].label }</span>
					<span className="cv-stepper__count">{ progressLabel( activeIndex + 1, steps.length ) }</span>
				</div>
			) }
			{ steps.map( ( step, index ) => {
				const active = index === activeIndex;
				const done = index < activeIndex;

				return (
					<Fragment key={ step.id }>
						{ index > 0 && <span className="cv-stepper__connector" aria-hidden="true" /> }
						<div className={ classes( 'cv-stepper__step', active && 'is-active', done && 'is-done' ) } aria-current={ active ? 'step' : undefined }>
							<span className="cv-stepper__num">
								{ done ? (
									<>
										<CheckIcon />
										<span className="cv-sr-only">{ doneLabel }</span>
									</>
								) : index + 1 }
							</span>
							<span className="cv-stepper__label">{ step.label }</span>
						</div>
					</Fragment>
				);
			} ) }
		</div>
	);
}
