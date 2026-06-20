/**
 * Logs & Monitoring screen.
 *
 * Four sub-tabs (API logs, error logs, webhook activity, session status), each
 * loaded on demand from /logs?tab=… and rendered with the shared design-system
 * primitives so it matches every other Carticy admin screen. A row-level "View
 * context" action opens the raw JSON context in a Modal.
 */

import { useState, useEffect } from '@wordpress/element';
import {
	Card,
	Chip,
	Spinner,
	Banner,
	Button,
	Modal,
	Tabs,
	Table,
	StatCard,
	SectionHeader,
	SelectField,
	EmptyState,
} from '@carticy/admin-ui';
import { apiGet, apiPost, t, useBusy } from '../api';

const SUB_TABS = [
	{ id: 'api', label: 'API Logs' },
	{ id: 'errors', label: 'Error Logs' },
	{ id: 'webhooks', label: 'Webhook Activity' },
	{ id: 'sessions', label: 'Session Status' },
];

const ERROR_CATEGORIES = [
	{ value: '', label: 'All Categories' },
	{ value: 'payment', label: 'Payment' },
	{ value: 'api', label: 'API' },
	{ value: 'webhook', label: 'Webhook' },
	{ value: 'validation', label: 'Validation' },
	{ value: 'system', label: 'System' },
];

/**
 * Map a log level to a Chip tone (the design system has no "info" tone).
 *
 * @param {string} level Log level string.
 * @return {string} Chip tone.
 */
function levelTone( level ) {
	switch ( String( level ).toLowerCase() ) {
		case 'error':
		case 'critical':
		case 'emergency':
		case 'alert':
			return 'neg';
		case 'warning':
		case 'notice':
			return 'warn';
		default:
			return 'flat';
	}
}

/**
 * Map an HTTP status code to a Chip tone.
 *
 * @param {number} code HTTP status code.
 * @return {string} Chip tone.
 */
function statusTone( code ) {
	if ( code >= 500 ) {
		return 'neg';
	}
	if ( code >= 400 ) {
		return 'warn';
	}
	return 'pos';
}

/**
 * Stamp a stable list key onto each row. Log entries carry no unique id, so we
 * derive one from the row position; the list is replaced wholesale on reload, so
 * positional keys are stable for the render's lifetime.
 *
 * @param {Array<Object>} rows Rows to key.
 * @return {Array<Object>} Rows with a `_key` field.
 */
function withRowKey( rows ) {
	return rows.map( ( row, i ) => ( { ...row, _key: i } ) );
}

/**
 * Render an epoch (seconds) as a localized date-time, or a dash when absent.
 *
 * @param {number|null} epoch Unix timestamp in seconds.
 * @return {string} Formatted date or em dash.
 */
function formatEpoch( epoch ) {
	if ( ! epoch ) {
		return '—';
	}
	return new Date( epoch * 1000 ).toLocaleString();
}

export default function Logs() {
	const [ tab, setTab ] = useState( 'api' );

	const tabs = SUB_TABS.map( ( s ) => ( { id: s.id, label: t( s.label ) } ) );

	return (
		<div className="caic-stack">
			<section className="cv-section">
				<SectionHeader
					title={ t( 'Logs & Monitoring' ) }
					meta={ <Tabs tabs={ tabs } activeId={ tab } onChange={ setTab } /> }
				/>
				{ 'api' === tab && <ApiTab /> }
				{ 'errors' === tab && <ErrorsTab /> }
				{ 'webhooks' === tab && <WebhooksTab /> }
				{ 'sessions' === tab && <SessionsTab /> }
			</section>
		</div>
	);
}

/**
 * Shared loader: returns [ data, error, loading, reload ] for a logs sub-tab.
 *
 * @param {string} path Query path passed to apiGet.
 * @return {Array} Tuple of data, error, loading flag, and a reload callback.
 */
function useTabData( path ) {
	const [ data, setData ] = useState( null );
	const [ error, setError ] = useState( null );
	const [ nonce, setNonce ] = useState( 0 );

	useEffect( () => {
		let active = true;
		setData( null );
		setError( null );
		apiGet( path )
			.then( ( r ) => { if ( active ) { setData( r ); } } )
			.catch( ( e ) => { if ( active ) { setError( e ); } } );
		return () => { active = false; };
	}, [ path, nonce ] );

	return [ data, error, ! data && ! error, () => setNonce( ( n ) => n + 1 ) ];
}

/**
 * A button that opens a Modal showing a JSON context blob.
 *
 * @param {Object} props         Component props.
 * @param {Object} props.context The context object to display.
 * @param {string} props.title   Modal title.
 */
function ContextButton( { context, title } ) {
	const [ open, setOpen ] = useState( false );
	const hasContext = context && Object.keys( context ).length > 0;

	if ( ! hasContext ) {
		return <span className="cv-help">{ t( 'No data' ) }</span>;
	}

	return (
		<>
			<Button variant="secondary" size="sm" onClick={ () => setOpen( true ) }>
				{ t( 'View context' ) }
			</Button>
			<Modal
				open={ open }
				onClose={ () => setOpen( false ) }
				title={ title || t( 'Context' ) }
				size="lg"
			>
				<pre className="caic-json">{ JSON.stringify( context, null, 2 ) }</pre>
			</Modal>
		</>
	);
}

/**
 * Loading / error / empty gate shared by every sub-tab.
 *
 * @param {Object}      props          Component props.
 * @param {boolean}     props.loading  Whether data is still loading.
 * @param {Object|null} props.error    Normalized API error (or null).
 * @param {*}           props.children Content to render once loaded.
 */
function TabShell( { loading, error, children } ) {
	if ( error ) {
		return <Banner tone="critical">{ error.message }</Banner>;
	}
	if ( loading ) {
		return <div className="caic-loading"><Spinner /> { t( 'Loading…' ) }</div>;
	}
	return children;
}

function ApiTab() {
	const [ page, setPage ] = useState( 1 );
	const [ data, error, loading ] = useTabData( '/logs?tab=api&paged=' + page + '&per_page=25' );

	const columns = [
		{ key: 'timestamp', header: t( 'Timestamp' ) },
		{
			key: 'level',
			header: t( 'Level' ),
			render: ( row ) => <Chip tone={ levelTone( row.level ) }>{ String( row.level ).toUpperCase() }</Chip>,
		},
		{ key: 'endpoint', header: t( 'Endpoint' ), render: ( row ) => <code>{ row.context.endpoint || '—' }</code> },
		{
			key: 'method',
			header: t( 'Method' ),
			render: ( row ) => <Chip tone="flat">{ String( row.context.method || '—' ).toUpperCase() }</Chip>,
		},
		{
			key: 'status',
			header: t( 'Status' ),
			render: ( row ) => <Chip tone={ statusTone( Number( row.context.status_code ) || 0 ) }>{ row.context.status_code || '—' }</Chip>,
		},
		{ key: 'message', header: t( 'Message' ) },
		{
			key: 'context',
			header: t( 'Details' ),
			render: ( row ) => <ContextButton context={ row.context } title={ t( 'API Context' ) } />,
		},
	];

	return (
		<TabShell loading={ loading } error={ error }>
			{ data && ( data.entries.length === 0 ? (
				<Card flush>
					<EmptyState title={ t( 'No API logs found' ) }>
						{ t( 'Logs will appear here when API requests are made.' ) }
					</EmptyState>
				</Card>
			) : (
				<>
					<Card flush>
						<Table
							columns={ columns }
							rows={ withRowKey( data.entries ) }
							getRowId={ ( row ) => row._key }
							emptyLabel={ t( 'No API logs found.' ) }
						/>
					</Card>
					{ data.pagination.total_pages > 1 && (
						<div className="caic-pagination">
							<Button
								variant="secondary"
								size="sm"
								disabled={ page <= 1 }
								onClick={ () => setPage( ( p ) => Math.max( 1, p - 1 ) ) }
							>
								{ t( 'Previous' ) }
							</Button>
							<span className="cv-help">
								{ t( 'Page %1$d of %2$d' )
									.replace( '%1$d', data.pagination.current_page )
									.replace( '%2$d', data.pagination.total_pages ) }
							</span>
							<Button
								variant="secondary"
								size="sm"
								disabled={ page >= data.pagination.total_pages }
								onClick={ () => setPage( ( p ) => p + 1 ) }
							>
								{ t( 'Next' ) }
							</Button>
						</div>
					) }
				</>
			) ) }
		</TabShell>
	);
}

function ErrorsTab() {
	const [ category, setCategory ] = useState( '' );
	const [ data, error, loading, reload ] = useTabData(
		'/logs?tab=errors' + ( category ? '&category=' + encodeURIComponent( category ) : '' )
	);
	const [ busy, run ] = useBusy();
	const [ notice, setNotice ] = useState( null );

	const clearCache = run( 'error', async () => {
		const r = await apiPost( '/logs/clear-cache', { target: 'error' } );
		setNotice( r.message );
		reload();
	} );

	const columns = [
		{ key: 'timestamp', header: t( 'Timestamp' ) },
		{
			key: 'category',
			header: t( 'Category' ),
			render: ( row ) => <Chip tone="warn">{ row.category.charAt( 0 ).toUpperCase() + row.category.slice( 1 ) }</Chip>,
		},
		{ key: 'message', header: t( 'Message' ) },
		{
			key: 'context',
			header: t( 'Context' ),
			render: ( row ) => <ContextButton context={ row.context } title={ t( 'Error Details' ) } />,
		},
	];

	const meta = (
		<div className="caic-toolbar">
			<SelectField
				value={ category }
				onChange={ ( e ) => setCategory( e.target.value ) }
				options={ ERROR_CATEGORIES.map( ( c ) => ( { value: c.value, label: t( c.label ) } ) ) }
			/>
			<Button variant="secondary" size="sm" loading={ 'error' === busy } onClick={ clearCache }>
				{ t( 'Clear cache' ) }
			</Button>
		</div>
	);

	return (
		<>
			<SectionHeader title={ t( 'Error Statistics (Last 24 Hours)' ) } meta={ meta } />
			{ notice && <Banner tone="success">{ notice }</Banner> }
			<TabShell loading={ loading } error={ error }>
				{ data && (
					<>
						<div className="caic-stats">
							{ ERROR_CATEGORIES.filter( ( c ) => c.value ).map( ( c ) => (
								<StatCard
									key={ c.value }
									value={ ( data.statistics && data.statistics[ c.value ] ) || 0 }
									label={ t( c.label ) }
								/>
							) ) }
						</div>
						<Card flush>
							{ data.entries.length === 0 ? (
								<EmptyState title={ t( 'No errors found.' ) }>{ t( 'This is good!' ) }</EmptyState>
							) : (
								<Table
									columns={ columns }
									rows={ withRowKey( data.entries ) }
									getRowId={ ( row ) => row._key }
									emptyLabel={ t( 'No errors found.' ) }
								/>
							) }
						</Card>
					</>
				) }
			</TabShell>
		</>
	);
}

function WebhooksTab() {
	const [ data, error, loading, reload ] = useTabData( '/logs?tab=webhooks' );
	const [ busy, run ] = useBusy();
	const [ notice, setNotice ] = useState( null );

	const clearStats = run( 'webhook', async () => {
		const r = await apiPost( '/logs/clear-cache', { target: 'webhook' } );
		setNotice( r.message );
		reload();
	} );

	const clearQueue = run( 'retry-queue', async () => {
		const r = await apiPost( '/logs/clear-cache', { target: 'retry-queue' } );
		setNotice( r.message );
		reload();
	} );

	const columns = [
		{ key: 'timestamp', header: t( 'Timestamp' ) },
		{ key: 'event_type', header: t( 'Event Type' ), render: ( row ) => <code>{ row.event_type }</code> },
		{ key: 'url', header: t( 'URL' ), render: ( row ) => <small>{ row.url }</small> },
		{ key: 'status_code', header: t( 'Status' ), render: ( row ) => <Chip tone="flat">{ row.status_code }</Chip> },
		{ key: 'attempt', header: t( 'Attempt' ) },
		{
			key: 'success',
			header: t( 'Result' ),
			render: ( row ) => (
				<Chip tone={ row.success ? 'pos' : 'neg' }>{ row.success ? t( 'Success' ) : t( 'Failed' ) }</Chip>
			),
		},
	];

	const meta = (
		<Button variant="secondary" size="sm" loading={ 'webhook' === busy } onClick={ clearStats }>
			{ t( 'Clear cache' ) }
		</Button>
	);

	return (
		<>
			<SectionHeader title={ t( 'Webhook Statistics (Last 24 Hours)' ) } meta={ meta } />
			{ notice && <Banner tone="success">{ notice }</Banner> }
			<TabShell loading={ loading } error={ error }>
				{ data && (
					<>
						<div className="caic-stats">
							<StatCard value={ data.statistics.total_sent || 0 } label={ t( 'Total Sent' ) } />
							<StatCard accent value={ data.statistics.total_success || 0 } label={ t( 'Successful' ) } />
							<StatCard value={ data.statistics.total_failed || 0 } label={ t( 'Failed' ) } />
							<StatCard value={ ( data.statistics.retry_rate || 0 ) + '%' } label={ t( 'Retry Rate' ) } />
						</div>

						{ data.retry_queue.length > 0 && (
							<Banner
								tone="warning"
								title={ t( 'Failed Webhooks (Retry Queue)' ) }
								actions={ (
									<Button
										variant="destructive"
										size="sm"
										loading={ 'retry-queue' === busy }
										onClick={ clearQueue }
									>
										{ t( 'Clear Queue' ) }
									</Button>
								) }
							>
								{ t( '%d webhook(s) pending retry' ).replace( '%d', data.retry_queue.length ) }
							</Banner>
						) }

						<Card flush>
							{ data.entries.length === 0 ? (
								<EmptyState title={ t( 'No webhook activity found.' ) }>
									{ t( 'Webhooks are only sent for orders created through ChatGPT Instant Checkout.' ) }
								</EmptyState>
							) : (
								<Table
									columns={ columns }
									rows={ withRowKey( data.entries ) }
									getRowId={ ( row ) => row._key }
									emptyLabel={ t( 'No webhook activity found.' ) }
								/>
							) }
						</Card>
					</>
				) }
			</TabShell>
		</>
	);
}

function SessionsTab() {
	const [ data, error, loading, reload ] = useTabData( '/logs?tab=sessions' );
	const [ busy, run ] = useBusy();
	const [ notice, setNotice ] = useState( null );

	const clearPerf = run( 'performance', async () => {
		const r = await apiPost( '/logs/clear-cache', { target: 'performance' } );
		setNotice( r.message );
		reload();
	} );

	const sessionColumns = [
		{
			key: 'session_id',
			header: t( 'Session ID' ),
			render: ( row ) => <code>{ row.session_id.slice( 0, 20 ) }…</code>,
		},
		{
			key: 'status',
			header: t( 'Status' ),
			render: ( row ) => (
				<Chip tone={ 'active' === row.status || 'completed' === row.status ? 'pos' : 'flat' }>
					{ row.status.charAt( 0 ).toUpperCase() + row.status.slice( 1 ) }
				</Chip>
			),
		},
		{ key: 'items_count', header: t( 'Items' ) },
		{ key: 'created_at', header: t( 'Created' ), render: ( row ) => formatEpoch( row.created_at ) },
		{ key: 'updated_at', header: t( 'Last Updated' ), render: ( row ) => formatEpoch( row.updated_at ) },
		{ key: 'expires_at', header: t( 'Expires' ), render: ( row ) => formatEpoch( row.expires_at ) },
		{
			key: 'has_shipping',
			header: t( 'Shipping' ),
			render: ( row ) => ( row.has_shipping ? t( 'Yes' ) : '—' ),
		},
	];

	const endpointColumns = [
		{ key: 'endpoint', header: t( 'Endpoint' ), render: ( row ) => <code>{ row.endpoint }</code> },
		{ key: 'count', header: t( 'Count' ) },
		{ key: 'average_duration', header: t( 'Avg Duration' ), render: ( row ) => row.average_duration + 's' },
		{ key: 'slowest_duration', header: t( 'Slowest' ), render: ( row ) => row.slowest_duration + 's' },
	];

	const meta = (
		<Button variant="secondary" size="sm" loading={ 'performance' === busy } onClick={ clearPerf }>
			{ t( 'Clear cache' ) }
		</Button>
	);

	return (
		<TabShell loading={ loading } error={ error }>
			{ data && (
				<>
					<SectionHeader title={ t( 'Session Status Overview' ) } meta={ meta } />
					{ notice && <Banner tone="success">{ notice }</Banner> }
					<div className="caic-stats">
						<StatCard accent value={ data.active_count } label={ t( 'Active Sessions' ) } />
						<StatCard value={ data.total_count } label={ t( 'Total Sessions' ) } />
						<StatCard value={ data.status_counts.completed || 0 } label={ t( 'Completed' ) } />
						<StatCard value={ data.status_counts.failed || 0 } label={ t( 'Failed' ) } />
						<StatCard value={ data.status_counts.cancelled || 0 } label={ t( 'Cancelled' ) } />
						<StatCard value={ data.status_counts.expired || 0 } label={ t( 'Expired' ) } />
					</div>

					<SectionHeader title={ t( 'Recent Sessions' ) } />
					<Card flush>
						<Table
							columns={ sessionColumns }
							rows={ data.recent_sessions }
							getRowId={ ( row ) => row.session_id }
							emptyLabel={ t( 'No active sessions found.' ) }
						/>
					</Card>

					<SectionHeader title={ t( 'API Performance (Last 24 Hours)' ) } />
					<div className="caic-stats">
						<StatCard value={ data.performance.api_requests.total || 0 } label={ t( 'Total Requests' ) } />
						<StatCard value={ ( data.performance.api_requests.average_duration || 0 ) + 's' } label={ t( 'Avg Duration' ) } />
						<StatCard value={ ( data.performance.api_requests.slowest_duration || 0 ) + 's' } label={ t( 'Slowest Request' ) } />
						<StatCard value={ ( data.performance.api_requests.average_memory || 0 ) + ' MB' } label={ t( 'Avg Memory' ) } />
						<StatCard value={ data.performance.api_requests.average_db_queries || 0 } label={ t( 'Avg DB Queries' ) } />
					</div>

					{ Object.keys( data.performance.by_endpoint || {} ).length > 0 && (
						<>
							<SectionHeader title={ t( 'Performance by Endpoint' ) } />
							<Card flush>
								<Table
									columns={ endpointColumns }
									rows={ Object.entries( data.performance.by_endpoint ).map( ( [ endpoint, stats ] ) => ( {
										endpoint,
										...stats,
									} ) ) }
									getRowId={ ( row ) => row.endpoint }
								/>
							</Card>
						</>
					) }

					<SectionHeader title={ t( 'System Health' ) } />
					<Card>
						<dl className="cv-dl">
							<HealthRow label={ t( 'PHP Version' ) } value={ data.system_health.php_version } />
							<HealthRow label={ t( 'Memory Limit' ) } value={ data.system_health.memory_limit } />
							<HealthRow label={ t( 'WordPress Version' ) } value={ data.system_health.wordpress_version } />
							<HealthRow label={ t( 'WooCommerce Version' ) } value={ data.system_health.woocommerce_version } />
							<div className="cv-dl__row">
								<dt className="cv-dl__label">{ t( 'Object Cache' ) }</dt>
								<dd className="cv-dl__value">
									<Chip tone={ data.system_health.cache_status.object_cache ? 'pos' : 'flat' }>
										{ data.system_health.cache_status.object_cache ? t( 'Active' ) : t( 'Inactive' ) }
									</Chip>
								</dd>
							</div>
							<div className="cv-dl__row">
								<dt className="cv-dl__label">{ t( 'OPCache' ) }</dt>
								<dd className="cv-dl__value">
									<Chip tone={ data.system_health.cache_status.opcode_cache ? 'pos' : 'flat' }>
										{ data.system_health.cache_status.opcode_cache ? t( 'Active' ) : t( 'Inactive' ) }
									</Chip>
								</dd>
							</div>
						</dl>
					</Card>
				</>
			) }
		</TabShell>
	);
}

/**
 * A single label/value row in the System Health definition list.
 *
 * @param {Object} props       Component props.
 * @param {string} props.label Row label.
 * @param {string} props.value Row value.
 */
function HealthRow( { label, value } ) {
	return (
		<div className="cv-dl__row">
			<dt className="cv-dl__label">{ label }</dt>
			<dd className="cv-dl__value">{ value || 'N/A' }</dd>
		</div>
	);
}
