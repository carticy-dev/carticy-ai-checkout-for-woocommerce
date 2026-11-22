/**
 * StatCard — a single metric: a value, a label, and an optional sub-line.
 *
 * Wraps .cv-card as .cv-stat. Pass onClick to make the whole card a button
 * (for click-through to a filtered view); pass accent to tint the value with
 * the brand accent. `icon` (an inline SVG) sits in a small tile in the card's
 * top corner, coloured by `tone`: 'neutral' (default), 'accent', 'success',
 * 'warning' or 'critical'. Other props go to the card.
 */
import { Card } from './Card';
import { classes } from './classes';
import { IconTile } from './icons';

export function StatCard( { value, label, sublabel, icon = null, tone = 'neutral', accent = false, onClick, className = '', ...rest } ) {
	const body = [
		icon ? <IconTile key="i" tone={ tone }>{ icon }</IconTile> : null,
		<div key="v" className="cv-stat__value">{ value }</div>,
		<div key="l" className="cv-stat__label">{ label }</div>,
		sublabel ? <div key="s" className="cv-stat__sub">{ sublabel }</div> : null,
	];

	return (
		<Card className={ classes( 'cv-stat', accent && 'cv-stat--accent', onClick && 'cv-stat--clickable', className ) } { ...rest }>
			{ onClick ? (
				<button type="button" className="cv-stat__btn" onClick={ onClick }>{ body }</button>
			) : (
				body
			) }
		</Card>
	);
}
