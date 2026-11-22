/**
 * Product Feed screen.
 *
 * The catalog management surface for ChatGPT: four KPI stat cards, a search +
 * filter toolbar with feed/quality maintenance actions, a selectable product
 * table (per-row enable/disable, quality chip with expandable issues, feed
 * preview), bulk enable/disable, and pagination. Built from the shared
 * design-system primitives so it matches every other Carticy admin screen.
 */

import { useState, useEffect, useCallback } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import {
	Card,
	Chip,
	LoadingPanel,
	Banner,
	Button,
	SectionHeader,
	StatCard,
	Table,
	SearchBar,
	BulkActionBar,
	SelectField,
	Modal,
	EmptyState,
} from '@carticy/admin-ui';
import { apiGet, apiPost, useBusy } from '../api';
import TablePagination from '../TablePagination';

/**
 * Map a 0-100 quality score to a chip tone + label (mirrors the legacy table).
 *
 * @param {number} score Quality score 0-100.
 * @return {{tone: string, label: string}} Chip tone and label.
 */
function qualityChip( score ) {
	if ( score >= 80 ) {
		return { tone: 'success', label: __( 'Excellent', 'carticy-ai-checkout-for-woocommerce' ) };
	}
	if ( score >= 60 ) {
		return { tone: 'warning', label: __( 'Good', 'carticy-ai-checkout-for-woocommerce' ) };
	}
	if ( score >= 40 ) {
		return { tone: 'warning', label: __( 'Fair', 'carticy-ai-checkout-for-woocommerce' ) };
	}
	return { tone: 'critical', label: __( 'Poor', 'carticy-ai-checkout-for-woocommerce' ) };
}

export default function ProductFeed() {
	const [ search, setSearch ] = useState( '' );
	const [ query, setQuery ] = useState( '' );
	const [ chatgpt, setChatgpt ] = useState( '' );
	const [ paged, setPaged ] = useState( 1 );

	const [ data, setData ] = useState( null );
	const [ error, setError ] = useState( null );
	const [ notice, setNotice ] = useState( null );

	const [ selectedIds, setSelectedIds ] = useState( [] );
	const [ expandedId, setExpandedId ] = useState( null );
	const [ preview, setPreview ] = useState( null );

	const [ busy, run ] = useBusy();

	// Single fetch used by both the filter effect and post-mutation refreshes.
	// Reads the current filters and replaces the table data + clears selection.
	const load = useCallback( () => {
		setData( null );
		setError( null );
		const params =
			'?search=' + encodeURIComponent( query ) +
			'&chatgpt=' + encodeURIComponent( chatgpt ) +
			'&paged=' + encodeURIComponent( paged );
		return apiGet( '/products' + params )
			.then( ( r ) => {
				setData( r );
				setSelectedIds( [] );
			} )
			.catch( ( e ) => setError( e ) );
	}, [ query, chatgpt, paged ] );

	useEffect( () => {
		load();
	}, [ load ] );

	const submitSearch = ( value ) => {
		setPaged( 1 );
		setQuery( value );
	};

	const clearSearch = () => {
		setSearch( '' );
		setPaged( 1 );
		setQuery( '' );
	};

	const toggleSelect = ( id ) => {
		setSelectedIds( ( prev ) =>
			prev.includes( id ) ? prev.filter( ( x ) => x !== id ) : [ ...prev, id ]
		);
	};

	const toggleRow = ( id ) =>
		run( 'toggle-' + id, async () => {
			setNotice( null );
			await apiPost( '/products/toggle', { id } );
			await load();
		} );

	const bulk = ( action ) =>
		run( 'bulk-' + action, async () => {
			setNotice( null );
			const res = await apiPost( '/products/bulk', { ids: selectedIds, action } );
			setNotice( { tone: 'success', message: res.message } );
			await load();
		} );

	const regenerate = run( 'regenerate', async () => {
		setNotice( null );
		const res = await apiPost( '/products/regenerate-feed', {} );
		setNotice( { tone: 'success', message: res.message } );
	} );

	const recalculate = run( 'recalculate', async () => {
		setNotice( null );
		const res = await apiPost( '/products/recalculate-quality', {} );
		setNotice( { tone: 'success', message: res.message } );
		await load();
	} );

	// The dialog opens at once and loads its feed inside, so focus moves into it
	// from the Preview button and returns there on close. A reply that lands
	// after the dialog closed is dropped.
	const openPreview = ( id ) => () => {
		setPreview( { loading: true } );
		apiGet( '/products/preview?id=' + encodeURIComponent( id ) )
			.then( ( res ) => setPreview( ( prev ) => prev && { feed: res.feed } ) )
			.catch( ( e ) => setPreview( ( prev ) => prev && { error: e.message } ) );
	};

	const columns = [
		{
			key: 'image',
			header: __( 'Image', 'carticy-ai-checkout-for-woocommerce' ),
			render: ( row ) =>
				row.image
					? <img className="caic-feed__image" src={ row.image } alt="" width={ 40 } height={ 40 } />
					: '—',
		},
		{
			key: 'name',
			header: __( 'Product', 'carticy-ai-checkout-for-woocommerce' ),
			render: ( row ) => <strong>{ row.name }</strong>,
		},
		{ key: 'sku', header: __( 'SKU', 'carticy-ai-checkout-for-woocommerce' ), render: ( row ) => row.sku || '—' },
		{ key: 'price', header: __( 'Price', 'carticy-ai-checkout-for-woocommerce' ) },
		{
			key: 'chatgpt',
			header: __( 'ChatGPT', 'carticy-ai-checkout-for-woocommerce' ),
			render: ( row ) => (
				<div className="caic-feed__status">
					<Chip tone={ row.enabled ? 'success' : 'neutral' }>
						{ row.enabled ? __( 'Enabled', 'carticy-ai-checkout-for-woocommerce' ) : __( 'Disabled', 'carticy-ai-checkout-for-woocommerce' ) }
					</Chip>
					<Button
						variant="tertiary"
						size="sm"
						loading={ busy === 'toggle-' + row.id }
						onClick={ toggleRow( row.id ) }
					>
						{ row.enabled ? __( 'Disable', 'carticy-ai-checkout-for-woocommerce' ) : __( 'Enable', 'carticy-ai-checkout-for-woocommerce' ) }
					</Button>
				</div>
			),
		},
		{
			key: 'quality',
			header: __( 'Quality Score', 'carticy-ai-checkout-for-woocommerce' ),
			render: ( row ) => {
				const chip = qualityChip( row.quality_score );
				const count = row.quality_issues.length;
				return (
					<div className="caic-feed__quality">
						<Chip tone={ chip.tone }>{ row.quality_score }% - { chip.label }</Chip>
						{ count > 0 && (
							<Button
								variant="link"
								size="sm"
								aria-expanded={ expandedId === row.id }
								aria-controls={ 'caic-issues-' + row.id }
								onClick={ () => setExpandedId( expandedId === row.id ? null : row.id ) }
							>
								{ sprintf(
									/* translators: %d: number of problems found in the product's data. */
									_n( '%d issue', '%d issues', count, 'carticy-ai-checkout-for-woocommerce' ),
									count
								) }
							</Button>
						) }
					</div>
				);
			},
		},
		{
			key: 'actions',
			header: __( 'Actions', 'carticy-ai-checkout-for-woocommerce' ),
			render: ( row ) => (
				<Button
					variant="secondary"
					size="sm"
					onClick={ openPreview( row.id ) }
				>
					{ __( 'Preview', 'carticy-ai-checkout-for-woocommerce' ) }
				</Button>
			),
		},
	];

	const renderRowExtra = ( row, colSpan ) => {
		if ( expandedId !== row.id || row.quality_issues.length === 0 ) {
			return null;
		}
		return (
			<tr className="cv-table__row-extra">
				<td colSpan={ colSpan }>
					<ul id={ 'caic-issues-' + row.id } className="caic-feed__issues-list">
						{ row.quality_issues.map( ( issue, i ) => (
							<li key={ i }>{ issue }</li>
						) ) }
					</ul>
				</td>
			</tr>
		);
	};

	const stats = data ? data.stats : null;
	const pagination = data ? data.pagination : null;

	const chatgptFilter = (
		<SelectField
			className="caic-range"
			aria-label={ __( 'ChatGPT status', 'carticy-ai-checkout-for-woocommerce' ) }
			value={ chatgpt }
			onChange={ ( e ) => { setPaged( 1 ); setChatgpt( e.target.value ); } }
			options={ [
				{ value: '', label: __( 'All Products', 'carticy-ai-checkout-for-woocommerce' ) },
				{ value: 'enabled', label: __( 'ChatGPT Enabled', 'carticy-ai-checkout-for-woocommerce' ) },
				{ value: 'disabled', label: __( 'ChatGPT Disabled', 'carticy-ai-checkout-for-woocommerce' ) },
			] }
		/>
	);

	return (
		<div className="caic-stack">
			<section className="cv-section">
				<SectionHeader title={ __( 'Product Feed', 'carticy-ai-checkout-for-woocommerce' ) } />

				{ error && <Banner tone="critical">{ error.message }</Banner> }
				{ notice && <Banner tone={ notice.tone }>{ notice.message }</Banner> }

				{ stats && (
					<div className="caic-stats">
						<StatCard accent value={ stats.total_products } label={ __( 'Total Products', 'carticy-ai-checkout-for-woocommerce' ) } />
						<StatCard value={ stats.chatgpt_enabled } label={ __( 'ChatGPT Enabled', 'carticy-ai-checkout-for-woocommerce' ) } />
						<StatCard value={ stats.avg_quality + '%' } label={ __( 'Avg Quality', 'carticy-ai-checkout-for-woocommerce' ) } />
						<StatCard value={ stats.products_with_issues } label={ __( 'With Issues', 'carticy-ai-checkout-for-woocommerce' ) } />
					</div>
				) }
			</section>

			<section className="cv-section">
				<div className="caic-feed__toolbar">
					<SearchBar
						value={ search }
						onChange={ setSearch }
						onSubmit={ submitSearch }
						onClear={ clearSearch }
						placeholder={ __( 'Search by name or SKU…', 'carticy-ai-checkout-for-woocommerce' ) }
						submitLabel={ __( 'Search', 'carticy-ai-checkout-for-woocommerce' ) }
						clearLabel={ __( 'Clear', 'carticy-ai-checkout-for-woocommerce' ) }
					/>
					{ chatgptFilter }
					<div className="caic-feed__toolbar-actions">
						<Button
							variant="secondary"
							loading={ busy === 'regenerate' }
							onClick={ regenerate }
						>
							{ __( 'Regenerate Feed', 'carticy-ai-checkout-for-woocommerce' ) }
						</Button>
						<Button
							variant="secondary"
							loading={ busy === 'recalculate' }
							onClick={ recalculate }
						>
							{ __( 'Recalculate Quality', 'carticy-ai-checkout-for-woocommerce' ) }
						</Button>
					</div>
				</div>

				{ selectedIds.length > 0 && (
					<BulkActionBar
						/* translators: %d: number of selected products. */
						label={ sprintf( __( '%d selected', 'carticy-ai-checkout-for-woocommerce' ), selectedIds.length ) }
					>
						<Button
							variant="secondary"
							size="sm"
							loading={ busy === 'bulk-enable' }
							onClick={ bulk( 'enable' ) }
						>
							{ __( 'Enable Selected', 'carticy-ai-checkout-for-woocommerce' ) }
						</Button>
						<Button
							variant="secondary"
							size="sm"
							loading={ busy === 'bulk-disable' }
							onClick={ bulk( 'disable' ) }
						>
							{ __( 'Disable Selected', 'carticy-ai-checkout-for-woocommerce' ) }
						</Button>
					</BulkActionBar>
				) }

				{ ! data && ! error && <LoadingPanel label={ __( 'Loading products…', 'carticy-ai-checkout-for-woocommerce' ) } /> }

				{ data && data.rows.length === 0 && (
					<Card flush>
						<EmptyState title={ __( 'No products found', 'carticy-ai-checkout-for-woocommerce' ) }>
							{ __( 'No products match the current filters.', 'carticy-ai-checkout-for-woocommerce' ) }
						</EmptyState>
					</Card>
				) }

				{ data && data.rows.length > 0 && (
					<Card flush>
						<Table
							columns={ columns }
							rows={ data.rows }
							getRowId={ ( row ) => row.id }
							selectable
							selectedIds={ selectedIds }
							onToggleSelect={ toggleSelect }
							selectLabel={ __( 'Select product', 'carticy-ai-checkout-for-woocommerce' ) }
							renderRowExtra={ renderRowExtra }
							emptyLabel={ __( 'No products found.', 'carticy-ai-checkout-for-woocommerce' ) }
						/>
						<TablePagination page={ pagination.current_page } total={ pagination.total_pages } onChange={ setPaged } />
					</Card>
				) }
			</section>

			<Modal
				open={ null !== preview }
				onClose={ () => setPreview( null ) }
				title={ __( 'Feed Preview', 'carticy-ai-checkout-for-woocommerce' ) }
				size="lg"
				closeLabel={ __( 'Close', 'carticy-ai-checkout-for-woocommerce' ) }
			>
				{ preview && preview.loading && <LoadingPanel label={ __( 'Loading the preview…', 'carticy-ai-checkout-for-woocommerce' ) } /> }
				{ preview && preview.error && <Banner tone="critical">{ preview.error }</Banner> }
				{ preview && undefined !== preview.feed && <pre className="caic-pre">{ preview.feed }</pre> }
			</Modal>
		</div>
	);
}
