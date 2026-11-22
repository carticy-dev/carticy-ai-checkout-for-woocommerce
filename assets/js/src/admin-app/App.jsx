/**
 * Admin app shell: the shared Carticy AppHeader over the active screen.
 *
 * The plugin keeps five separate wp-admin pages, so the header links point at
 * those pages (preserving deep links and the wp-admin menu highlight) and the
 * current link is the screen named in the boot object.
 */

import { AppHeader, Banner } from '@carticy/admin-ui';
import { boot, t } from './api';
import { CartIcon } from './icons';
import Analytics from './screens/Analytics';
import Settings from './screens/Settings';
import Logs from './screens/Logs';
import ProductFeed from './screens/ProductFeed';
import Wizard from './screens/Wizard';

const SECTIONS = [
	{ id: 'settings', label: 'Settings', slug: 'carticy-ai-checkout-for-woocommerce' },
	{ id: 'product-feed', label: 'Product Feed', slug: 'carticy-ai-checkout-product-feed' },
	{ id: 'wizard', label: 'Application Wizard', slug: 'carticy-ai-checkout-application-wizard' },
	{ id: 'analytics', label: 'Analytics', slug: 'carticy-ai-checkout-analytics' },
	{ id: 'logs', label: 'Logs & Monitoring', slug: 'carticy-ai-checkout-logs' },
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
		label: t( section.label ),
		href: ( boot.adminUrl || '/wp-admin/' ) + 'admin.php?page=' + section.slug,
	} ) );

	return (
		<>
			<AppHeader
				title={ t( 'AI Checkout' ) }
				icon={ <CartIcon /> }
				navLabel={ t( 'AI Checkout sections' ) }
				items={ items }
				current={ boot.screen }
			/>

			<main className="cv-page caic-content">
				{ Screen ? <Screen /> : <Banner tone="critical">{ t( 'Unknown screen.' ) }</Banner> }
			</main>
		</>
	);
}
