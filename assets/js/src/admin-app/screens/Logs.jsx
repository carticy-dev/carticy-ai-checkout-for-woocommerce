/**
 * Logs & Monitoring screen.
 *
 * Four sub-tabs (API logs, error logs, webhook activity, session status), each
 * loaded on demand from /logs?tab=… and rendered with the shared design-system
 * primitives so it matches every other Carticy admin screen. A row-level "View
 * context" action opens the raw JSON context in a Modal.
 */

import { useState, useEffect } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import {
	Card,
	Chip,
	LoadingPanel,
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
import { formatDate } from '@carticy/admin-ui/date';
import { apiGet, apiPost, useBusy } from '../api';
import TablePagination from '../TablePagination';

const SUB_TABS = [
	{ id: 'api', label: __( 'API logs', 'carticy-ai-checkout-for-woocommerce' ) },
	{ id: 'errors', label: __( 'Error logs', 'carticy-ai-checkout-for-woocommerce' ) },
	{ id: 'webhooks', label: __( 'Webhook activity', 'carticy-ai-checkout-for-woocommerce' ) },
	{ id: 'sessions', label: __( 'Session status', 'carticy-ai-checkout-for-woocommerce' ) },
];

const ERROR_CATEGORIES = [
	{ value: '', label: __( 'All categories', 'carticy-ai-checkout-for-woocommerce' ) },
	{ value: 'payment', label: __( 'Payment', 'carticy-ai-checkout-for-woocommerce' ) },
	{ value: 'api', label: __( 'API', 'carticy-ai-checkout-for-woocommerce' ) },
	{ value: 'webhook', label: __( 'Webhook', 'carticy-ai-checkout-for-woocommerce' ) },
	{ value: 'validation', label: __( 'Validation', 'carticy-ai-checkout-for-woocommerce' ) },
	{ value: 'system', label: __( 'System', 'carticy-ai-checkout-for-woocommerce' ) },
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
			return 'critical';
		case 'warning':
		case 'notice':
			return 'warning';
		default:
			return 'neutral';
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
		return 'critical';
	}
	if ( code >= 400 ) {
		return 'warning';
	}
	return 'success';
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
 * A session time (seconds since the epoch) in the site's timezone and formats,
 * or a dash when the session has none.
 *
 * @param {number|null} epoch Unix timestamp in seconds.
 * @return {string} Formatted date and time, or an em dash.
 */
function formatEpoch( epoch ) {
	return epoch ? formatDate( epoch * 1000 ) : '—';
}

export default function Logs() {
	const [ tab, setTab ] = useState( 'api' );

	return (
		<div className="caic-stack">
			<section className="cv-section">
				<SectionHeader title={ __( 'Logs & monitoring', 'carticy-ai-checkout-for-woocommerce' ) } />
				<Tabs tabs={ SUB_TABS } activeId={ tab } onChange={ setTab } label={ __( 'Log types', 'carticy-ai-checkout-for-woocommerce' ) } />
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
		return <span className="cv-help">{ __( 'No data', 'carticy-ai-checkout-for-woocommerce' ) }</span>;
	}

	return (
		<>
			<Button variant="secondary" size="sm" onClick={ () => setOpen( true ) }>
				{ __( 'View context', 'carticy-ai-checkout-for-woocommerce' ) }
			</Button>
			<Modal
				open={ open }
				onClose={ () => setOpen( false ) }
				title={ title || __( 'Context', 'carticy-ai-checkout-for-woocommerce' ) }
				size="lg"
				closeLabel={ __( 'Close', 'carticy-ai-checkout-for-woocommerce' ) }
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
		return <LoadingPanel label={ __( 'Loading logs…', 'carticy-ai-checkout-for-woocommerce' ) } />;
	}
	return children;
}

function ApiTab() {
	const [ page, setPage ] = useState( 1 );
	const [ data, error, loading ] = useTabData( '/logs?tab=api&paged=' + page + '&per_page=25' );

	const columns = [
		{ key: 'timestamp', header: __( 'Timestamp', 'carticy-ai-checkout-for-woocommerce' ), render: ( row ) => formatDate( row.timestamp ) },
		{
			key: 'level',
			header: __( 'Level', 'carticy-ai-checkout-for-woocommerce' ),
			render: ( row ) => <Chip tone={ levelTone( row.level ) }>{ String( row.level ).toUpperCase() }</Chip>,
		},
		{ key: 'endpoint', header: __( 'Request', 'carticy-ai-checkout-for-woocommerce' ), render: ( row ) => <code>{ row.context.endpoint || '—' }</code> },
		{
			key: 'method',
			header: __( 'Method', 'carticy-ai-checkout-for-woocommerce' ),
			render: ( row ) => <Chip>{ String( row.context.method || '—' ).toUpperCase() }</Chip>,
		},
		{
			key: 'status',
			header: __( 'Status', 'carticy-ai-checkout-for-woocommerce' ),
			render: ( row ) => <Chip tone={ statusTone( Number( row.context.status_code ) || 0 ) }>{ row.context.status_code || '—' }</Chip>,
		},
		{ key: 'message', header: __( 'Message', 'carticy-ai-checkout-for-woocommerce' ) },
		{
			key: 'context',
			header: __( 'Details', 'carticy-ai-checkout-for-woocommerce' ),
			render: ( row ) => <ContextButton context={ row.context } title={ __( 'API context', 'carticy-ai-checkout-for-woocommerce' ) } />,
		},
	];

	return (
		<TabShell loading={ loading } error={ error }>
			{ data && ( data.entries.length === 0 ? (
				<Card flush>
					<EmptyState title={ __( 'No API logs found', 'carticy-ai-checkout-for-woocommerce' ) }>
						{ __( 'Logs will appear here when API requests are made.', 'carticy-ai-checkout-for-woocommerce' ) }
					</EmptyState>
				</Card>
			) : (
				<Card flush>
					<Table
						columns={ columns }
						rows={ withRowKey( data.entries ) }
						getRowId={ ( row ) => row._key }
						emptyLabel={ __( 'No API logs found.', 'carticy-ai-checkout-for-woocommerce' ) }
					/>
					<TablePagination page={ data.pagination.current_page } total={ data.pagination.total_pages } onChange={ setPage } />
				</Card>
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
		{ key: 'timestamp', header: __( 'Timestamp', 'carticy-ai-checkout-for-woocommerce' ), render: ( row ) => formatDate( row.timestamp ) },
		{
			key: 'category',
			header: __( 'Category', 'carticy-ai-checkout-for-woocommerce' ),
			render: ( row ) => <Chip tone="warning">{ row.category.charAt( 0 ).toUpperCase() + row.category.slice( 1 ) }</Chip>,
		},
		{ key: 'message', header: __( 'Message', 'carticy-ai-checkout-for-woocommerce' ) },
		{
			key: 'context',
			header: __( 'Context', 'carticy-ai-checkout-for-woocommerce' ),
			render: ( row ) => <ContextButton context={ row.context } title={ __( 'Error details', 'carticy-ai-checkout-for-woocommerce' ) } />,
		},
	];

	const actions = (
		<>
			<SelectField
				aria-label={ __( 'Error category', 'carticy-ai-checkout-for-woocommerce' ) }
				value={ category }
				onChange={ ( e ) => setCategory( e.target.value ) }
				options={ ERROR_CATEGORIES }
			/>
			<Button variant="secondary" size="sm" loading={ 'error' === busy } onClick={ clearCache }>
				{ __( 'Clear cache', 'carticy-ai-checkout-for-woocommerce' ) }
			</Button>
		</>
	);

	return (
		<>
			<SectionHeader title={ __( 'Errors in the last 24 hours', 'carticy-ai-checkout-for-woocommerce' ) } headingLevel={ 3 } actions={ actions } />
			{ notice && <Banner tone="success">{ notice }</Banner> }
			<TabShell loading={ loading } error={ error }>
				{ data && (
					<>
						<div className="cv-kpi-strip">
							{ ERROR_CATEGORIES.filter( ( c ) => c.value ).map( ( c ) => (
								<StatCard
									key={ c.value }
									value={ ( data.statistics && data.statistics[ c.value ] ) || 0 }
									label={ c.label }
								/>
							) ) }
						</div>
						<Card flush>
							{ data.entries.length === 0 ? (
								<EmptyState title={ __( 'No errors found.', 'carticy-ai-checkout-for-woocommerce' ) }>{ __( 'This is good!', 'carticy-ai-checkout-for-woocommerce' ) }</EmptyState>
							) : (
								<Table
									columns={ columns }
									rows={ withRowKey( data.entries ) }
									getRowId={ ( row ) => row._key }
									emptyLabel={ __( 'No errors found.', 'carticy-ai-checkout-for-woocommerce' ) }
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
		{ key: 'timestamp', header: __( 'Timestamp', 'carticy-ai-checkout-for-woocommerce' ), render: ( row ) => formatDate( row.timestamp ) },
		{ key: 'event_type', header: __( 'Event type', 'carticy-ai-checkout-for-woocommerce' ), render: ( row ) => <code>{ row.event_type }</code> },
		{ key: 'url', header: __( 'URL', 'carticy-ai-checkout-for-woocommerce' ), render: ( row ) => <small>{ row.url }</small> },
		{ key: 'status_code', header: __( 'Status', 'carticy-ai-checkout-for-woocommerce' ), render: ( row ) => <Chip>{ row.status_code }</Chip> },
		{ key: 'attempt', header: __( 'Attempt', 'carticy-ai-checkout-for-woocommerce' ) },
		{
			key: 'success',
			header: __( 'Result', 'carticy-ai-checkout-for-woocommerce' ),
			render: ( row ) => (
				<Chip tone={ row.success ? 'success' : 'critical' }>{ row.success ? __( 'Success', 'carticy-ai-checkout-for-woocommerce' ) : __( 'Failed', 'carticy-ai-checkout-for-woocommerce' ) }</Chip>
			),
		},
	];

	const actions = (
		<Button variant="secondary" size="sm" loading={ 'webhook' === busy } onClick={ clearStats }>
			{ __( 'Clear cache', 'carticy-ai-checkout-for-woocommerce' ) }
		</Button>
	);

	return (
		<>
			<SectionHeader title={ __( 'Webhooks in the last 24 hours', 'carticy-ai-checkout-for-woocommerce' ) } headingLevel={ 3 } actions={ actions } />
			{ notice && <Banner tone="success">{ notice }</Banner> }
			<TabShell loading={ loading } error={ error }>
				{ data && (
					<>
						<div className="cv-kpi-strip">
							<StatCard value={ data.statistics.total_sent || 0 } label={ __( 'Total sent', 'carticy-ai-checkout-for-woocommerce' ) } />
							<StatCard accent value={ data.statistics.total_success || 0 } label={ __( 'Successful', 'carticy-ai-checkout-for-woocommerce' ) } />
							<StatCard value={ data.statistics.total_failed || 0 } label={ __( 'Failed', 'carticy-ai-checkout-for-woocommerce' ) } />
							<StatCard value={ ( data.statistics.retry_rate || 0 ) + '%' } label={ __( 'Retry rate', 'carticy-ai-checkout-for-woocommerce' ) } />
						</div>

						{ data.retry_queue.length > 0 && (
							<Banner
								tone="warning"
								title={ __( 'Failed webhooks waiting to retry', 'carticy-ai-checkout-for-woocommerce' ) }
								actions={ (
									<Button
										variant="destructive"
										size="sm"
										loading={ 'retry-queue' === busy }
										onClick={ clearQueue }
									>
										{ __( 'Clear queue', 'carticy-ai-checkout-for-woocommerce' ) }
									</Button>
								) }
							>
								{ sprintf(
									/* translators: %d: number of webhooks waiting to be sent again. */
									_n( '%d webhook is waiting to be sent again', '%d webhooks are waiting to be sent again', data.retry_queue.length, 'carticy-ai-checkout-for-woocommerce' ),
									data.retry_queue.length
								) }
							</Banner>
						) }

						<Card flush>
							{ data.entries.length === 0 ? (
								<EmptyState title={ __( 'No webhook activity found.', 'carticy-ai-checkout-for-woocommerce' ) }>
									{ __( 'Webhooks are only sent for orders created through ChatGPT Instant Checkout.', 'carticy-ai-checkout-for-woocommerce' ) }
								</EmptyState>
							) : (
								<Table
									columns={ columns }
									rows={ withRowKey( data.entries ) }
									getRowId={ ( row ) => row._key }
									emptyLabel={ __( 'No webhook activity found.', 'carticy-ai-checkout-for-woocommerce' ) }
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
			header: __( 'Session ID', 'carticy-ai-checkout-for-woocommerce' ),
			render: ( row ) => <code>{ row.session_id.slice( 0, 20 ) }…</code>,
		},
		{
			key: 'status',
			header: __( 'Status', 'carticy-ai-checkout-for-woocommerce' ),
			render: ( row ) => (
				<Chip tone={ 'active' === row.status || 'completed' === row.status ? 'success' : 'neutral' }>
					{ row.status.charAt( 0 ).toUpperCase() + row.status.slice( 1 ) }
				</Chip>
			),
		},
		{ key: 'items_count', header: __( 'Items', 'carticy-ai-checkout-for-woocommerce' ) },
		{ key: 'created_at', header: __( 'Created', 'carticy-ai-checkout-for-woocommerce' ), render: ( row ) => formatEpoch( row.created_at ) },
		{ key: 'updated_at', header: __( 'Last updated', 'carticy-ai-checkout-for-woocommerce' ), render: ( row ) => formatEpoch( row.updated_at ) },
		{ key: 'expires_at', header: __( 'Expires', 'carticy-ai-checkout-for-woocommerce' ), render: ( row ) => formatEpoch( row.expires_at ) },
		{
			key: 'has_shipping',
			header: __( 'Shipping', 'carticy-ai-checkout-for-woocommerce' ),
			render: ( row ) => ( row.has_shipping ? __( 'Yes', 'carticy-ai-checkout-for-woocommerce' ) : '—' ),
		},
	];

	const endpointColumns = [
		{ key: 'endpoint', header: __( 'Request', 'carticy-ai-checkout-for-woocommerce' ), render: ( row ) => <code>{ row.endpoint }</code> },
		{ key: 'count', header: __( 'Count', 'carticy-ai-checkout-for-woocommerce' ) },
		{ key: 'average_duration', header: __( 'Avg duration', 'carticy-ai-checkout-for-woocommerce' ), render: ( row ) => row.average_duration + 's' },
		{ key: 'slowest_duration', header: __( 'Slowest', 'carticy-ai-checkout-for-woocommerce' ), render: ( row ) => row.slowest_duration + 's' },
	];

	const actions = (
		<Button variant="secondary" size="sm" loading={ 'performance' === busy } onClick={ clearPerf }>
			{ __( 'Clear cache', 'carticy-ai-checkout-for-woocommerce' ) }
		</Button>
	);

	return (
		<TabShell loading={ loading } error={ error }>
			{ data && (
				<>
					<SectionHeader title={ __( 'Session status overview', 'carticy-ai-checkout-for-woocommerce' ) } headingLevel={ 3 } actions={ actions } />
					{ notice && <Banner tone="success">{ notice }</Banner> }
					<div className="cv-kpi-strip">
						<StatCard accent value={ data.active_count } label={ __( 'Active sessions', 'carticy-ai-checkout-for-woocommerce' ) } />
						<StatCard value={ data.total_count } label={ __( 'Total sessions', 'carticy-ai-checkout-for-woocommerce' ) } />
						<StatCard value={ data.status_counts.completed || 0 } label={ __( 'Completed', 'carticy-ai-checkout-for-woocommerce' ) } />
						<StatCard value={ data.status_counts.failed || 0 } label={ __( 'Failed', 'carticy-ai-checkout-for-woocommerce' ) } />
						<StatCard value={ data.status_counts.cancelled || 0 } label={ __( 'Cancelled', 'carticy-ai-checkout-for-woocommerce' ) } />
						<StatCard value={ data.status_counts.expired || 0 } label={ __( 'Expired', 'carticy-ai-checkout-for-woocommerce' ) } />
					</div>

					<SectionHeader title={ __( 'Recent sessions', 'carticy-ai-checkout-for-woocommerce' ) } headingLevel={ 3 } />
					<Card flush>
						<Table
							columns={ sessionColumns }
							rows={ data.recent_sessions }
							getRowId={ ( row ) => row.session_id }
							emptyLabel={ __( 'No active sessions found.', 'carticy-ai-checkout-for-woocommerce' ) }
						/>
					</Card>

					<SectionHeader title={ __( 'API performance in the last 24 hours', 'carticy-ai-checkout-for-woocommerce' ) } headingLevel={ 3 } />
					<div className="cv-kpi-strip">
						<StatCard value={ data.performance.api_requests.total || 0 } label={ __( 'Total requests', 'carticy-ai-checkout-for-woocommerce' ) } />
						<StatCard value={ ( data.performance.api_requests.average_duration || 0 ) + 's' } label={ __( 'Average duration', 'carticy-ai-checkout-for-woocommerce' ) } />
						<StatCard value={ ( data.performance.api_requests.slowest_duration || 0 ) + 's' } label={ __( 'Slowest request', 'carticy-ai-checkout-for-woocommerce' ) } />
						<StatCard value={ ( data.performance.api_requests.average_memory || 0 ) + ' MB' } label={ __( 'Average memory', 'carticy-ai-checkout-for-woocommerce' ) } />
						<StatCard value={ data.performance.api_requests.average_db_queries || 0 } label={ __( 'Average database queries', 'carticy-ai-checkout-for-woocommerce' ) } />
					</div>

					{ Object.keys( data.performance.by_endpoint || {} ).length > 0 && (
						<>
							<SectionHeader title={ __( 'Performance by request', 'carticy-ai-checkout-for-woocommerce' ) } headingLevel={ 3 } />
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

					<SectionHeader title={ __( 'System health', 'carticy-ai-checkout-for-woocommerce' ) } headingLevel={ 3 } />
					<Card>
						<dl className="cv-dl">
							<HealthRow label={ __( 'PHP version', 'carticy-ai-checkout-for-woocommerce' ) } value={ data.system_health.php_version } />
							<HealthRow label={ __( 'Memory limit', 'carticy-ai-checkout-for-woocommerce' ) } value={ data.system_health.memory_limit } />
							<HealthRow label={ __( 'WordPress version', 'carticy-ai-checkout-for-woocommerce' ) } value={ data.system_health.wordpress_version } />
							<HealthRow label={ __( 'WooCommerce version', 'carticy-ai-checkout-for-woocommerce' ) } value={ data.system_health.woocommerce_version } />
							<div className="cv-dl__row">
								<dt className="cv-dl__label">{ __( 'Object cache', 'carticy-ai-checkout-for-woocommerce' ) }</dt>
								<dd className="cv-dl__value">
									<Chip tone={ data.system_health.cache_status.object_cache ? 'success' : 'neutral' }>
										{ data.system_health.cache_status.object_cache ? __( 'Active', 'carticy-ai-checkout-for-woocommerce' ) : __( 'Inactive', 'carticy-ai-checkout-for-woocommerce' ) }
									</Chip>
								</dd>
							</div>
							<div className="cv-dl__row">
								<dt className="cv-dl__label">{ __( 'OPCache', 'carticy-ai-checkout-for-woocommerce' ) }</dt>
								<dd className="cv-dl__value">
									<Chip tone={ data.system_health.cache_status.opcode_cache ? 'success' : 'neutral' }>
										{ data.system_health.cache_status.opcode_cache ? __( 'Active', 'carticy-ai-checkout-for-woocommerce' ) : __( 'Inactive', 'carticy-ai-checkout-for-woocommerce' ) }
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
