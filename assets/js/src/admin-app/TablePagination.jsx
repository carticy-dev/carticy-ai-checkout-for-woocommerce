/**
 * Pagination bar for the bottom of a flush table card, with the plugin's
 * translated labels. Renders nothing for a single page.
 */

import { Pagination } from '@carticy/admin-ui';
import { t } from './api';

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
				summary={ t( 'Page %1$d of %2$d' ).replace( '%1$d', page ).replace( '%2$d', total ) }
				label={ t( 'Pagination' ) }
				previousLabel={ t( 'Previous' ) }
				nextLabel={ t( 'Next' ) }
				pageLabel={ ( number ) => t( 'Page %d' ).replace( '%d', number ) }
			/>
		</div>
	);
}
