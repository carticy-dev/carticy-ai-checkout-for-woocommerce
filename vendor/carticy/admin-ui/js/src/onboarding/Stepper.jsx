/**
 * Stepper — wraps .cv-stepper (numbered steps, accent active, success done).
 *
 * steps: [ { id, label } ]; activeId marks the current step; every step
 * before it renders as done.
 */
import { Fragment } from '@wordpress/element';

export function Stepper( { steps = [], activeId, className = '', ...rest } ) {
	const activeIndex = steps.findIndex( ( step ) => step.id === activeId );

	return (
		<div className={ [ 'cv-stepper', className ].filter( Boolean ).join( ' ' ) } { ...rest }>
			{ steps.map( ( step, index ) => {
				const stateClass =
					index === activeIndex ? 'is-active' : index < activeIndex ? 'is-done' : '';

				return (
					<Fragment key={ step.id }>
						{ index > 0 && <span className="cv-stepper__connector" aria-hidden="true" /> }
						<div className={ [ 'cv-stepper__step', stateClass ].filter( Boolean ).join( ' ' ) }>
							<span className="cv-stepper__num">{ index < activeIndex ? '✓' : index + 1 }</span>
							<span className="cv-stepper__label">{ step.label }</span>
						</div>
					</Fragment>
				);
			} ) }
		</div>
	);
}
