/**
 * Admin app shell: the shared Carticy AppHeader over the active screen.
 *
 * The plugin keeps five separate wp-admin pages, so the header links point at
 * those pages (preserving deep links and the wp-admin menu highlight) and the
 * current link is the screen named in the boot object.
 */

import { AppHeader, Banner } from '@carticy/admin-ui';
import { __ } from '@wordpress/i18n';
import { boot } from './api';
import { CartIcon } from './icons';
import Analytics from './screens/Analytics';
import Settings from './screens/Settings';
import Logs from './screens/Logs';
import ProductFeed from './screens/ProductFeed';
import Wizard from './screens/Wizard';

const SECTIONS = [
	{ id: 'settings', label: __( 'Settings', 'carticy-ai-checkout-for-woocommerce' ), slug: 'carticy-ai-checkout-for-woocommerce' },
	{ id: 'product-feed', label: __( 'Product feed', 'carticy-ai-checkout-for-woocommerce' ), slug: 'carticy-ai-checkout-product-feed' },
	{ id: 'wizard', label: __( 'Application wizard', 'carticy-ai-checkout-for-woocommerce' ), slug: 'carticy-ai-checkout-application-wizard' },
	{ id: 'analytics', label: __( 'Analytics', 'carticy-ai-checkout-for-woocommerce' ), slug: 'carticy-ai-checkout-analytics' },
	{ id: 'logs', label: __( 'Logs & monitoring', 'carticy-ai-checkout-for-woocommerce' ), slug: 'carticy-ai-checkout-logs' },
];

const SCREENS = {
	settings: Settings,
	'product-feed': ProductFeed,
	wizard: Wizard,
	analytics: Analytics,
	logs: Logs,
};

export default function App() {
	const Screen = SCREENS[ boot.screen ];
	const items = SECTIONS.map( ( section ) => ( {
		id: section.id,
		label: section.label,
		href: ( boot.adminUrl || '/wp-admin/' ) + 'admin.php?page=' + section.slug,
	} ) );

	return (
		<>
			<AppHeader
				title={ __( 'AI Checkout', 'carticy-ai-checkout-for-woocommerce' ) }
				icon={ <CartIcon /> }
				navLabel={ __( 'AI Checkout sections', 'carticy-ai-checkout-for-woocommerce' ) }
				items={ items }
				current={ boot.screen }
			/>

			<main className="cv-page caic-content">
				{ Screen ? <Screen /> : <Banner tone="critical">{ __( 'Unknown screen.', 'carticy-ai-checkout-for-woocommerce' ) }</Banner> }
			</main>
		</>
	);
}
