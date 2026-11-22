/**
 * Settings screen.
 *
 * The plugin's configuration surface: prerequisites, general options, API
 * security, credentials, and uninstall behavior. A second-level Tabs strip
 * switches between the five sub-sections client-side; everything loads from and
 * saves through the admin Settings REST controller. Built from the shared
 * design-system primitives so it matches every other Carticy admin screen.
 */

import { useState, useEffect } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	Card,
	Banner,
	LoadingPanel,
	Tabs,
	TextField,
	ToggleRow,
	Button,
	StatusPill,
	SectionHeader,
	Table,
} from '@carticy/admin-ui';
import { apiGet, apiPost, useBusy } from '../api';

const SUB_TABS = [
	{ id: 'prerequisites', label: __( 'Prerequisites', 'carticy-ai-checkout-for-woocommerce' ) },
	{ id: 'general', label: __( 'General', 'carticy-ai-checkout-for-woocommerce' ) },
	{ id: 'security', label: __( 'Security', 'carticy-ai-checkout-for-woocommerce' ) },
	{ id: 'api', label: __( 'API', 'carticy-ai-checkout-for-woocommerce' ) },
	{ id: 'uninstall', label: __( 'Uninstall', 'carticy-ai-checkout-for-woocommerce' ) },
];

export default function Settings() {
	const [ data, setData ] = useState( null );
	const [ loadError, setLoadError ] = useState( null );
	const [ active, setActive ] = useState( 'prerequisites' );
	const [ settings, setSettings ] = useState( null );
	const [ notice, setNotice ] = useState( null );
	const [ busy, run ] = useBusy();

	useEffect( () => {
		let live = true;
		apiGet( '/settings' )
			.then( ( r ) => {
				if ( live ) {
					setData( r );
					setSettings( r.settings );
				}
			} )
			.catch( ( e ) => { if ( live ) { setLoadError( e ); } } );
		return () => { live = false; };
	}, [] );

	const applyResponse = ( r ) => {
		setData( r );
		setSettings( r.settings );
	};

	const saveSettings = run( 'save', async () => {
		setNotice( null );
		try {
			const r = await apiPost( '/settings', settings );
			applyResponse( r );
			setNotice( { tone: 'success', message: __( 'Settings saved.', 'carticy-ai-checkout-for-woocommerce' ) } );
		} catch ( e ) {
			setNotice( { tone: 'critical', message: e.message } );
		}
	} );

	const refreshIpRanges = run( 'ip', async () => {
		setNotice( null );
		try {
			const r = await apiPost( '/settings/refresh-ip-ranges', {} );
			setData( ( prev ) => ( { ...prev, ip_allowlist: { ...prev.ip_allowlist, count: r.count } } ) );
			setNotice( { tone: 'success', message: r.message } );
		} catch ( e ) {
			setNotice( { tone: 'critical', message: e.message } );
		}
	} );

	const testWebhook = run( 'test_webhook', async () => {
		setNotice( null );
		try {
			const r = await apiPost( '/settings/test-webhook', {} );
			setNotice( { tone: 'success', message: r.message } );
		} catch ( e ) {
			setNotice( { tone: 'critical', message: e.message } );
		}
	} );

	const regenerate = ( key, path, field ) => run( key, async () => {
		setNotice( null );
		try {
			const r = await apiPost( path, {} );
			setData( ( prev ) => ( { ...prev, [ field ]: r[ field ] } ) );
			setNotice( { tone: 'success', message: r.message } );
		} catch ( e ) {
			setNotice( { tone: 'critical', message: e.message } );
		}
	} );

	const copy = ( value ) => () => {
		if ( navigator.clipboard ) {
			navigator.clipboard.writeText( value )
				.then( () => setNotice( { tone: 'success', message: __( 'Copied to clipboard.', 'carticy-ai-checkout-for-woocommerce' ) } ) )
				.catch( () => setNotice( { tone: 'critical', message: __( 'Copy failed. Please copy manually.', 'carticy-ai-checkout-for-woocommerce' ) } ) );
		}
	};

	const setOption = ( field ) => ( value ) => setSettings( ( prev ) => ( { ...prev, [ field ]: value } ) );

	if ( loadError ) {
		return (
			<div className="caic-stack">
				<section className="cv-section">
					<Banner tone="critical">{ loadError.message }</Banner>
				</section>
			</div>
		);
	}

	if ( ! data || ! settings ) {
		return (
			<div className="caic-stack">
				<section className="cv-section">
					<LoadingPanel label={ __( 'Loading settings…', 'carticy-ai-checkout-for-woocommerce' ) } />
				</section>
			</div>
		);
	}

	const prerequisiteColumns = [
		{ key: 'label', header: __( 'Check', 'carticy-ai-checkout-for-woocommerce' ) },
		{
			key: 'status',
			header: __( 'Status', 'carticy-ai-checkout-for-woocommerce' ),
			render: ( row ) => (
				<StatusPill status={ 'pass' === row.status ? 'connected' : 'expired' }>
					{ 'pass' === row.status ? __( 'OK', 'carticy-ai-checkout-for-woocommerce' ) : __( 'Failed', 'carticy-ai-checkout-for-woocommerce' ) }
				</StatusPill>
			),
		},
		{
			key: 'message',
			header: __( 'Message', 'carticy-ai-checkout-for-woocommerce' ),
			render: ( row ) => (
				<>
					{ row.message }
					{ row.action_url && (
						<>
							{ ' ' }
							<a href={ row.action_url } target="_blank" rel="noreferrer">{ row.action_label }</a>
						</>
					) }
				</>
			),
		},
	];

	return (
		<div className="caic-stack">
			<section className="cv-section">
				<SectionHeader title={ __( 'Settings', 'carticy-ai-checkout-for-woocommerce' ) } />
				<Tabs tabs={ SUB_TABS } activeId={ active } onChange={ setActive } label={ __( 'Settings sections', 'carticy-ai-checkout-for-woocommerce' ) } />

				{ notice && <Banner tone={ notice.tone }>{ notice.message }</Banner> }

				{ 'prerequisites' === active && (
					<Card flush>
						<Table
							columns={ prerequisiteColumns }
							rows={ data.prerequisites }
							getRowId={ ( row ) => row.label }
							emptyLabel={ __( 'No prerequisite checks available.', 'carticy-ai-checkout-for-woocommerce' ) }
						/>
					</Card>
				) }

				{ 'general' === active && (
					<Card>
						<ToggleRow
							label={ __( 'Enable plugin-wide test mode', 'carticy-ai-checkout-for-woocommerce' ) }
							help={ __( 'When enabled, SSL and IP allowlist checks are bypassed and Stripe test keys are used.', 'carticy-ai-checkout-for-woocommerce' ) }
							checked={ settings.test_mode }
							onChange={ ( e ) => setOption( 'test_mode' )( e.target.checked ) }
						/>
						<TextField
							type="url"
							label={ __( 'OpenAI Webhook URL', 'carticy-ai-checkout-for-woocommerce' ) }
							help={ __( 'Unique webhook URL provided by OpenAI during merchant onboarding (required for production).', 'carticy-ai-checkout-for-woocommerce' ) }
							value={ settings.webhook_url }
							onChange={ ( e ) => setOption( 'webhook_url' )( e.target.value ) }
						/>
						<div className="caic-actions">
							<Button variant="primary" loading={ 'save' === busy } onClick={ saveSettings }>{ __( 'Save Changes', 'carticy-ai-checkout-for-woocommerce' ) }</Button>
							<Button loading={ 'test_webhook' === busy } onClick={ testWebhook }>{ __( 'Send Test Webhook', 'carticy-ai-checkout-for-woocommerce' ) }</Button>
						</div>
					</Card>
				) }

				{ 'security' === active && (
					<Card>
						<ToggleRow
							label={ __( 'Only allow requests from OpenAI IP addresses', 'carticy-ai-checkout-for-woocommerce' ) }
							/* translators: %d: number of OpenAI IP ranges loaded. */
							help={ sprintf( __( 'OpenAI IP ranges loaded: %d', 'carticy-ai-checkout-for-woocommerce' ), data.ip_allowlist.count ) }
							checked={ settings.enable_ip_allowlist }
							onChange={ ( e ) => setOption( 'enable_ip_allowlist' )( e.target.checked ) }
						/>
						<div className="caic-actions">
							<Button variant="primary" loading={ 'save' === busy } onClick={ saveSettings }>{ __( 'Save Changes', 'carticy-ai-checkout-for-woocommerce' ) }</Button>
							<Button loading={ 'ip' === busy } onClick={ refreshIpRanges }>{ __( 'Refresh IP Ranges', 'carticy-ai-checkout-for-woocommerce' ) }</Button>
						</div>
					</Card>
				) }

				{ 'api' === active && (
					<Card>
						<TextField
							readOnly
							label={ __( 'API Key (Bearer Token)', 'carticy-ai-checkout-for-woocommerce' ) }
							value={ data.api_key }
							onFocus={ ( e ) => e.target.select() }
						/>
						<div className="caic-actions">
							<Button onClick={ copy( data.api_key ) }>{ __( 'Copy', 'carticy-ai-checkout-for-woocommerce' ) }</Button>
							<Button loading={ 'api_key' === busy } onClick={ regenerate( 'api_key', '/settings/regenerate-api-key', 'api_key' ) }>{ __( 'Regenerate', 'carticy-ai-checkout-for-woocommerce' ) }</Button>
						</div>

						<TextField
							readOnly
							label={ __( 'Webhook Secret (HMAC)', 'carticy-ai-checkout-for-woocommerce' ) }
							value={ data.webhook_secret }
							onFocus={ ( e ) => e.target.select() }
						/>
						<div className="caic-actions">
							<Button onClick={ copy( data.webhook_secret ) }>{ __( 'Copy', 'carticy-ai-checkout-for-woocommerce' ) }</Button>
							<Button loading={ 'webhook_secret' === busy } onClick={ regenerate( 'webhook_secret', '/settings/regenerate-webhook-secret', 'webhook_secret' ) }>{ __( 'Regenerate', 'carticy-ai-checkout-for-woocommerce' ) }</Button>
						</div>

						<TextField
							readOnly
							label={ __( 'Product Feed URL', 'carticy-ai-checkout-for-woocommerce' ) }
							value={ data.product_feed_url }
							onFocus={ ( e ) => e.target.select() }
						/>
						<div className="caic-actions">
							<Button onClick={ copy( data.product_feed_url ) }>{ __( 'Copy', 'carticy-ai-checkout-for-woocommerce' ) }</Button>
							<a className="cv-btn cv-btn--secondary" href={ data.product_feed_url } target="_blank" rel="noreferrer">{ __( 'View', 'carticy-ai-checkout-for-woocommerce' ) }</a>
						</div>
					</Card>
				) }

				{ 'uninstall' === active && (
					<Card>
						<ToggleRow
							label={ __( 'Remove all plugin data when uninstalling', 'carticy-ai-checkout-for-woocommerce' ) }
							help={ __( 'When enabled, all settings, transients, and metadata are permanently deleted on uninstall.', 'carticy-ai-checkout-for-woocommerce' ) }
							checked={ settings.delete_data_on_uninstall }
							onChange={ ( e ) => setOption( 'delete_data_on_uninstall' )( e.target.checked ) }
						/>
						<div className="caic-actions">
							<Button variant="primary" loading={ 'save' === busy } onClick={ saveSettings }>{ __( 'Save Changes', 'carticy-ai-checkout-for-woocommerce' ) }</Button>
						</div>
					</Card>
				) }
			</section>
		</div>
	);
}
