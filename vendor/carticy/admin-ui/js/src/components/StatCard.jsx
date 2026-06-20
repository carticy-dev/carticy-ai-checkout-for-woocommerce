/**
 * StatCard — a single metric: a value, a label, and an optional sub-line.
 *
 * Wraps .cv-card as .cv-stat. Pass onClick to make the whole card a button
 * (for click-through to a filtered view); pass accent to tint the value with
 * the brand accent.
 */
import { Card } from './Card';

export function StatCard( { value, label, sublabel, accent = false, onClick, className = '' } ) {
	const classes = [
		'cv-stat',
		accent ? 'cv-stat--accent' : '',
		onClick ? 'cv-stat--clickable' : '',
		className,
	]
		.filter( Boolean )
		.join( ' ' );

	const body = [
		<div key="v" className="cv-stat__value">{ value }</div>,
		<div key="l" className="cv-stat__label">{ label }</div>,
		sublabel ? <div key="s" className="cv-stat__sub">{ sublabel }</div> : null,
	];

	return (
		<Card className={ classes }>
			{ onClick ? (
				<button type="button" className="cv-stat__btn" onClick={ onClick }>{ body }</button>
			) : (
				body
			) }
		</Card>
	);
}
