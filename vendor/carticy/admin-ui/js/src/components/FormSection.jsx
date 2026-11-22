/**
 * FormSection — one titled group of fields in a long form (.cv-form-section):
 * an optional icon, a title, an optional one-line description and optional
 * actions on the right, then the fields.
 *
 * Long forms (a drawer editor, a settings card) read as a list of named parts
 * instead of one column of inputs. The section is a labelled group
 * (role="group", named by its title), so a screen reader announces the
 * group's name when focus enters it, as it would for a fieldset legend.
 * Consecutive sections are divided by a hairline; with variant="card" each
 * section is its own white card instead (on a grey surface such as a Drawer
 * with surface="subtle").
 *
 * tone colours the icon tile: 'neutral' (default), 'accent', 'success',
 * 'warning' or 'critical'. Keep it neutral unless the colour says something,
 * for example the state of what the section edits.
 *
 * headingLevel picks the title's element (h2-h6, default h3) so the outline
 * stays right wherever the form sits.
 */
import { useId } from '@wordpress/element';
import { classes } from './classes';
import { headingTag } from './heading';
import { IconTile } from './icons';

export function FormSection( { title, icon = null, description = null, actions = null, variant = 'plain', tone = 'neutral', headingLevel = 3, className = '', children, ...rest } ) {
	const id = useId();
	const Heading = headingTag( headingLevel, 3 );

	return (
		<div role="group" aria-labelledby={ `${ id }-title` } className={ classes( 'cv-form-section', 'card' === variant && 'cv-card cv-form-section--card', className ) } { ...rest }>
			<div className="cv-form-section__head">
				{ icon && <IconTile tone={ tone }>{ icon }</IconTile> }
				<div className="cv-form-section__titles">
					<Heading id={ `${ id }-title` } className="cv-form-section__title">{ title }</Heading>
					{ description && <p className="cv-form-section__desc">{ description }</p> }
				</div>
				{ actions && <div className="cv-form-section__actions">{ actions }</div> }
			</div>
			{ children && <div className="cv-form-section__body">{ children }</div> }
		</div>
	);
}
