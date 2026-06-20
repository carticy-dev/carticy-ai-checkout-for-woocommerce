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
import {
	Card,
	Banner,
	Spinner,
	Tabs,
	TextField,
	ToggleRow,
	Button,
	StatusPill,
	SectionHeader,
	Table,
} from '@carticy/admin-ui';
import { apiGet, apiPost, useBusy, t } from '../api';

const SUB_TABS = [
	{ id: 'prerequisites', label: 'Prerequisites' },
	{ id: 'general', label: 'General' },
	{ id: 'security', label: 'Security' },
	{ id: 'api', label: 'API' },
	{ id: 'uninstall', label: 'Uninstall' },
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
			setNotice( { tone: 'success', message: t( 'Settings saved.' ) } );
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
				.then( () => setNotice( { tone: 'success', message: t( 'Copied to clipboard.' ) } ) )
				.catch( () => setNotice( { tone: 'critical', message: t( 'Copy failed. Please copy manually.' ) } ) );
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
					<div className="caic-loading"><Spinner /> { t( 'Loading…' ) }</div>
				</section>
			</div>
		);
	}

	const subTabs = SUB_TABS.map( ( tab ) => ( { id: tab.id, label: t( tab.label ) } ) );

	const prerequisiteColumns = [
		{ key: 'label', header: t( 'Check' ) },
		{
			key: 'status',
			header: t( 'Status' ),
			render: ( row ) => (
				<StatusPill status={ 'pass' === row.status ? 'connected' : 'expired' }>
					{ 'pass' === row.status ? t( 'OK' ) : t( 'Failed' ) }
				</StatusPill>
			),
		},
		{
			key: 'message',
			header: t( 'Message' ),
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
				<SectionHeader title={ t( 'Settings' ) } meta={ <Tabs tabs={ subTabs } activeId={ active } onChange={ setActive } /> } />

				{ notice && <Banner tone={ notice.tone }>{ notice.message }</Banner> }

				{ 'prerequisites' === active && (
					<Card flush>
						<Table
							columns={ prerequisiteColumns }
							rows={ data.prerequisites }
							getRowId={ ( row ) => row.label }
							emptyLabel={ t( 'No prerequisite checks available.' ) }
						/>
					</Card>
				) }

				{ 'general' === active && (
					<Card>
						<ToggleRow
							label={ t( 'Enable plugin-wide test mode' ) }
							checked={ settings.test_mode }
							onChange={ ( e ) => setOption( 'test_mode' )( e.target.checked ) }
						/>
						<p className="cv-help">{ t( 'When enabled, SSL and IP allowlist checks are bypassed and Stripe test keys are used.' ) }</p>
						<TextField
							type="url"
							label={ t( 'OpenAI Webhook URL' ) }
							help={ t( 'Unique webhook URL provided by OpenAI during merchant onboarding (required for production).' ) }
							value={ settings.webhook_url }
							onChange={ ( e ) => setOption( 'webhook_url' )( e.target.value ) }
						/>
						<div className="caic-actions">
							<Button variant="primary" loading={ 'save' === busy } onClick={ saveSettings }>{ t( 'Save Changes' ) }</Button>
							<Button loading={ 'test_webhook' === busy } onClick={ testWebhook }>{ t( 'Send Test Webhook' ) }</Button>
						</div>
					</Card>
				) }

				{ 'security' === active && (
					<Card>
						<ToggleRow
							label={ t( 'Only allow requests from OpenAI IP addresses' ) }
							checked={ settings.enable_ip_allowlist }
							onChange={ ( e ) => setOption( 'enable_ip_allowlist' )( e.target.checked ) }
						/>
						<p className="cv-help">
							{ t( 'OpenAI IP ranges loaded: %s' ).replace( '%s', String( data.ip_allowlist.count ) ) }
						</p>
						<div className="caic-actions">
							<Button variant="primary" loading={ 'save' === busy } onClick={ saveSettings }>{ t( 'Save Changes' ) }</Button>
							<Button loading={ 'ip' === busy } onClick={ refreshIpRanges }>{ t( 'Refresh IP Ranges' ) }</Button>
						</div>
					</Card>
				) }

				{ 'api' === active && (
					<Card>
						<TextField
							readOnly
							label={ t( 'API Key (Bearer Token)' ) }
							value={ data.api_key }
							onFocus={ ( e ) => e.target.select() }
						/>
						<div className="caic-actions">
							<Button onClick={ copy( data.api_key ) }>{ t( 'Copy' ) }</Button>
							<Button loading={ 'api_key' === busy } onClick={ regenerate( 'api_key', '/settings/regenerate-api-key', 'api_key' ) }>{ t( 'Regenerate' ) }</Button>
						</div>

						<TextField
							readOnly
							label={ t( 'Webhook Secret (HMAC)' ) }
							value={ data.webhook_secret }
							onFocus={ ( e ) => e.target.select() }
						/>
						<div className="caic-actions">
							<Button onClick={ copy( data.webhook_secret ) }>{ t( 'Copy' ) }</Button>
							<Button loading={ 'webhook_secret' === busy } onClick={ regenerate( 'webhook_secret', '/settings/regenerate-webhook-secret', 'webhook_secret' ) }>{ t( 'Regenerate' ) }</Button>
						</div>

						<TextField
							readOnly
							label={ t( 'Product Feed URL' ) }
							value={ data.product_feed_url }
							onFocus={ ( e ) => e.target.select() }
						/>
						<div className="caic-actions">
							<Button onClick={ copy( data.product_feed_url ) }>{ t( 'Copy' ) }</Button>
							<a className="cv-btn cv-btn--secondary" href={ data.product_feed_url } target="_blank" rel="noreferrer">{ t( 'View' ) }</a>
						</div>
					</Card>
				) }

				{ 'uninstall' === active && (
					<Card>
						<ToggleRow
							label={ t( 'Remove all plugin data when uninstalling' ) }
							checked={ settings.delete_data_on_uninstall }
							onChange={ ( e ) => setOption( 'delete_data_on_uninstall' )( e.target.checked ) }
						/>
						<p className="cv-help">{ t( 'When enabled, all settings, transients, and metadata are permanently deleted on uninstall.' ) }</p>
						<div className="caic-actions">
							<Button variant="primary" loading={ 'save' === busy } onClick={ saveSettings }>{ t( 'Save Changes' ) }</Button>
						</div>
					</Card>
				) }
			</section>
		</div>
	);
}
