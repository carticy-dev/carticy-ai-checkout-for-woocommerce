/**
 * Stepper — wraps .cv-stepper (numbered steps, accent active, success done).
 *
 * steps: [ { id, label } ]; activeId marks the current step (aria-current=
 * "step"); every step before it renders as done and shows a check icon in
 * place of its number, announced as `doneLabel` (default 'Completed'; pass a
 * translated string).
 */
import { Fragment } from '@wordpress/element';
import { classes } from '../components/classes';
import { CheckIcon } from '../components/icons';

export function Stepper( { steps = [], activeId, doneLabel = 'Completed', className = '', ...rest } ) {
	const activeIndex = steps.findIndex( ( step ) => step.id === activeId );

	return (
		<div className={ classes( 'cv-stepper', className ) } { ...rest }>
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
