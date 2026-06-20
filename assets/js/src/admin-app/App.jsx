/**
 * Admin app shell: branded top bar + tabbed navigation + the active screen.
 *
 * Mirrors the local Carticy admin pattern (a .cv-root--app surface with a
 * surface-colored top bar carrying the brand and the design-system Tabs). The
 * plugin keeps five separate wp-admin pages, so the tabs navigate by URL
 * (preserving deep links and the wp-admin menu highlight) rather than switching
 * client-side; the active tab is the screen named in the boot object.
 */

import { Tabs, Banner } from '@carticy/admin-ui';
import { boot, t } from './api';
import { BrandMark } from './icons';
import Analytics from './screens/Analytics';
import Settings from './screens/Settings';
import Logs from './screens/Logs';
import ProductFeed from './screens/ProductFeed';
import Wizard from './screens/Wizard';

const TABS = [
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
	const tabs = TABS.map( ( tab ) => ( { id: tab.id, label: t( tab.label ) } ) );

	const navigate = ( id ) => {
		const target = TABS.find( ( tab ) => tab.id === id );
		if ( target && id !== boot.screen ) {
			window.location.href = ( boot.adminUrl || '/wp-admin/' ) + 'admin.php?page=' + target.slug;
		}
	};

	return (
		<div className="caic-app">
			<header className="caic-topbar">
				<div className="caic-topbar__brand">
					<span className="caic-topbar__mark"><BrandMark size={ 26 } /></span>
					<span className="caic-topbar__name">{ t( 'AI Checkout' ) }</span>
				</div>
				<Tabs tabs={ tabs } activeId={ boot.screen } onChange={ navigate } className="caic-topbar__tabs" />
			</header>

			<main className="caic-content">
				{ Screen ? <Screen /> : <Banner tone="critical">{ t( 'Unknown screen.' ) }</Banner> }
			</main>
		</div>
	);
}
