/**
 * Button — wraps the .cv-btn component contract.
 *
 * Variants: primary | secondary | tertiary | destructive | link.
 * Violet accent is never a button background (design doctrine);
 * primary is near-black.
 */
import { Spinner } from './Spinner';

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
	const classes = [
		'cv-btn',
		`cv-btn--${ variant }`,
		size ? `cv-btn--${ size }` : '',
		block ? 'cv-btn--block' : '',
		loading ? 'cv-btn--loading' : '',
		className,
	]
		.filter( Boolean )
		.join( ' ' );

	return (
		<button type={ type } className={ classes } disabled={ loading || rest.disabled } { ...rest }>
			{ loading && <Spinner /> }
			{ ! loading && icon && <span className="cv-btn__icon">{ icon }</span> }
			{ children }
		</button>
	);
}
