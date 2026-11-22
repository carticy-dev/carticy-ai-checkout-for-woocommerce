/**
 * Button — wraps the .cv-btn component contract.
 *
 * Variants: primary | secondary | tertiary | ghost | destructive | link.
 * Sizes: sm | lg (leave `size` out for the default). Violet accent is never a button
 * background (design doctrine); primary is near-black. `ghost` has no fill or
 * border until hovered (toolbar and row actions).
 *
 * A `loading` button stays focusable: a disabled button would drop keyboard
 * focus to the page while its request runs. It is announced as busy and
 * unavailable (aria-busy, aria-disabled) and ignores clicks, Enter and Space,
 * including a form submit, until `loading` is false. `disabled` is for actions
 * that are not available at all. The ref goes to the button, for example as a
 * Modal's initialFocusRef.
 */
import { forwardRef } from '@wordpress/element';
import { Spinner } from './Spinner';
import { classes } from './classes';

const ignoreActivation = ( event ) => event.preventDefault();

export const Button = forwardRef( function Button( {
	variant = 'secondary',
	size,
	block = false,
	loading = false,
	icon = null,
	type = 'button',
	className = '',
	onClick,
	children,
	...rest
}, ref ) {
	const busy = loading ? { 'aria-disabled': 'true', 'aria-busy': 'true', onClick: ignoreActivation } : { onClick };

	return (
		<button
			ref={ ref }
			type={ type }
			className={ classes( 'cv-btn', `cv-btn--${ variant }`, size && `cv-btn--${ size }`, block && 'cv-btn--block', loading && 'cv-btn--loading', className ) }
			{ ...rest }
			{ ...busy }
		>
			{ loading && <Spinner /> }
			{ ! loading && icon && <span className="cv-btn__icon">{ icon }</span> }
			{ children }
		</button>
	);
} );
