/**
 * Pagination bar for the bottom of a flush table card, with the plugin's
 * translated labels. Renders nothing for a single page.
 */

import { Pagination } from '@carticy/admin-ui';
import { __, sprintf } from '@wordpress/i18n';

export default function TablePagination( { page, total, onChange } ) {
	if ( total <= 1 ) {
		return null;
	}

	return (
		<div className="cv-table__pagination">
			<Pagination
				page={ page }
				total={ total }
				onChange={ onChange }
				/* translators: 1: current page number, 2: number of pages. */
				summary={ sprintf( __( 'Page %1$d of %2$d', 'carticy-ai-checkout-for-woocommerce' ), page, total ) }
				label={ __( 'Pagination', 'carticy-ai-checkout-for-woocommerce' ) }
				previousLabel={ __( 'Previous', 'carticy-ai-checkout-for-woocommerce' ) }
				nextLabel={ __( 'Next', 'carticy-ai-checkout-for-woocommerce' ) }
				/* translators: %d: page number. */
				pageLabel={ ( number ) => sprintf( __( 'Page %d', 'carticy-ai-checkout-for-woocommerce' ), number ) }
			/>
		</div>
	);
}
