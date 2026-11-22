/**
 * SectionHeader — wraps .cv-section__head: a section title and optional
 * actions on the end side (a filter control, a Button…).
 *
 * headingLevel picks the title's element (h2-h6, default h2); an h2 takes
 * the section-title size, deeper levels the card-title size.
 */
import { classes } from './classes';
import { headingTag } from './heading';

export function SectionHeader( { title, headingLevel = 2, actions = null, className = '', ...rest } ) {
	const Heading = headingTag( headingLevel, 2 );

	return (
		<div className={ classes( 'cv-section__head', className ) } { ...rest }>
			<Heading className={ classes( 'cv-section__title', 'h2' === Heading ? 'cv-h2' : 'cv-h3' ) }>{ title }</Heading>
			{ actions && <div className="cv-section__actions">{ actions }</div> }
		</div>
	);
}
