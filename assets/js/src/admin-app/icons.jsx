/**
 * Inline SVG icons for the admin app.
 *
 * BrandMark is the plugin mark shown in the top bar (a violet rounded square
 * with a checkout-cart glyph), matching the shared Carticy admin pattern of a
 * small square mark next to the plugin name.
 */

export function BrandMark( { size = 26 } ) {
	return (
		<svg width={ size } height={ size } viewBox="0 0 24 24" role="img" aria-hidden="true" focusable="false">
			<rect width="24" height="24" rx="6" fill="#7C3AED" />
			<g
				fill="none"
				stroke="#ffffff"
				strokeWidth="1.6"
				strokeLinecap="round"
				strokeLinejoin="round"
				transform="translate(4.2 4.2) scale(0.65)"
			>
				<circle cx="8" cy="21" r="1" />
				<circle cx="19" cy="21" r="1" />
				<path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12" />
			</g>
		</svg>
	);
}
