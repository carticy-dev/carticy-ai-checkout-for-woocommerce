/**
 * SaveBar — the sticky bar of a settings screen with unsaved changes.
 *
 * Render it only while the draft differs from the saved settings; pass the
 * Save and Discard buttons as children. It sticks to the bottom of the
 * viewport and leaves room after the last field, so every control stays
 * reachable on a phone. Toasts stack above it.
 */
import { useRef } from '@wordpress/element';
import { classes } from './classes';
import { useToastOffset } from './Toast';

export function SaveBar( { label, message, className = '', children, ...rest } ) {
	const bar = useRef( null );
	useToastOffset( bar );

	return (
		<div ref={ bar } className={ classes( 'cv-save-bar', className ) } role="region" aria-label={ label } { ...rest }>
			{ message && <p className="cv-save-bar__message">{ message }</p> }
			<div className="cv-save-bar__actions">{ children }</div>
		</div>
	);
}
