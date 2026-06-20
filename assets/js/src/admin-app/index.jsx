/**
 * React admin app mount.
 *
 * React, api-fetch and i18n resolve to WordPress script globals (webpack
 * externals). The mount element is marked as the design-system root so the
 * scoped admin-ui tokens and resets apply.
 */

import { render } from '@wordpress/element';
import { adminUiRoot } from '@carticy/admin-ui';
import App from './App';
import { boot } from './api';

const root = document.getElementById( boot.mountId || 'carticy-ai-checkout-admin-root' );
if ( root ) {
	adminUiRoot( root, { page: 'ai-checkout' } );
	root.classList.add( 'carticy-ai-checkout', 'cv-root--app' );
	render( <App />, root );
}
