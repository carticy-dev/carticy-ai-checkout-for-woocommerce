/**
 * EmptyState — wraps .cv-empty (icon / title / desc / actions / meta).
 *
 * headingLevel picks the title's element (h2-h6, default h3); size="compact"
 * fits the state inside a card.
 */
import { classes } from './classes';
import { headingTag } from './heading';

export function EmptyState( { icon = null, title, headingLevel = 3, size, actions = null, meta = null, className = '', children, ...rest } ) {
	const Heading = headingTag( headingLevel, 3 );

	return (
		<div className={ classes( 'cv-empty', size && `cv-empty--${ size }`, className ) } { ...rest }>
			{ icon && <div className="cv-empty__icon" aria-hidden="true">{ icon }</div> }
			{ title && <Heading className="cv-empty__title">{ title }</Heading> }
			{ children && <p className="cv-empty__desc">{ children }</p> }
			{ actions && <div className="cv-empty__actions">{ actions }</div> }
			{ meta && <p className="cv-empty__meta">{ meta }</p> }
		</div>
	);
}
