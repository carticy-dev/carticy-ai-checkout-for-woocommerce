/**
 * Button — wraps the .cv-btn component contract.
 *
 * Variants: primary | secondary | tertiary | ghost | destructive | link.
 * Sizes: sm | lg (leave `size` out for the default). Violet accent is never a button
 * background (design doctrine); primary is near-black. `ghost` has no fill or
 * border until hovered (toolbar and row actions).
 */
import { Spinner } from './Spinner';
import { classes } from './classes';

export function Button( {
	variant = 'secondary',
	size,
	block = false,
	loading = false,
	icon = null,
	type = 'button',
	className = '',
	children,
	...rest
} ) {
	return (
		<button
			type={ type }
			className={ classes( 'cv-btn', `cv-btn--${ variant }`, size && `cv-btn--${ size }`, block && 'cv-btn--block', loading && 'cv-btn--loading', className ) }
			disabled={ loading || rest.disabled }
			{ ...rest }
		>
			{ loading && <Spinner /> }
			{ ! loading && icon && <span className="cv-btn__icon">{ icon }</span> }
			{ children }
		</button>
	);
}
