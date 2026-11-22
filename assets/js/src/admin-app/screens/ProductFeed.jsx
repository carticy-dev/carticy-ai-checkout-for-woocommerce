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
import { apiGet, apiPost, useBusy, t } from '../api';
import TablePagination from '../TablePagination';

/**
 * Map a 0-100 quality score to a chip tone + label (mirrors the legacy table).
 *
 * @param {number} score Quality score 0-100.
 * @return {{tone: string, label: string}} Chip tone and label.
 */
function qualityChip( score ) {
	if ( score >= 80 ) {
		return { tone: 'success', label: t( 'Excellent' ) };
	}
	if ( score >= 60 ) {
		return { tone: 'warning', label: t( 'Good' ) };
	}
	if ( score >= 40 ) {
		return { tone: 'warning', label: t( 'Fair' ) };
	}
	return { tone: 'critical', label: t( 'Poor' ) };
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
			header: t( 'Image' ),
			className: 'caic-feed__image',
			render: ( row ) =>
				row.image
					? <img src={ row.image } alt="" width={ 40 } height={ 40 } />
					: '—',
		},
		{
			key: 'name',
			header: t( 'Product' ),
			render: ( row ) => <strong>{ row.name }</strong>,
		},
		{ key: 'sku', header: t( 'SKU' ), render: ( row ) => row.sku || '—' },
		{ key: 'price', header: t( 'Price' ) },
		{
			key: 'chatgpt',
			header: t( 'ChatGPT' ),
			render: ( row ) => (
				<div className="caic-feed__status">
					<Chip tone={ row.enabled ? 'success' : 'neutral' }>
						{ row.enabled ? t( 'Enabled' ) : t( 'Disabled' ) }
					</Chip>
					<Button
						variant="tertiary"
						size="sm"
						loading={ busy === 'toggle-' + row.id }
						onClick={ toggleRow( row.id ) }
					>
						{ row.enabled ? t( 'Disable' ) : t( 'Enable' ) }
					</Button>
				</div>
			),
		},
		{
			key: 'quality',
			header: t( 'Quality Score' ),
			render: ( row ) => {
				const chip = qualityChip( row.quality_score );
				const count = row.quality_issues.length;
				return (
					<div className="caic-feed__quality">
						<Chip tone={ chip.tone }>{ row.quality_score }% - { chip.label }</Chip>
						{ count > 0 && (
							<button
								type="button"
								className="caic-feed__issues-toggle"
								onClick={ () => setExpandedId( expandedId === row.id ? null : row.id ) }
							>
								{ t( '%d issues' ).replace( '%d', count ) }
							</button>
						) }
					</div>
				);
			},
		},
		{
			key: 'actions',
			header: t( 'Actions' ),
			className: 'caic-feed__actions',
			render: ( row ) => (
				<Button
					variant="secondary"
					size="sm"
					onClick={ openPreview( row.id ) }
				>
					{ t( 'Preview' ) }
				</Button>
			),
		},
	];

	const renderRowExtra = ( row, colSpan ) => {
		if ( expandedId !== row.id || row.quality_issues.length === 0 ) {
			return null;
		}
		return (
			<tr className="caic-feed__issues-row">
				<td colSpan={ colSpan }>
					<ul className="caic-feed__issues-list">
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
			aria-label={ t( 'ChatGPT status' ) }
			value={ chatgpt }
			onChange={ ( e ) => { setPaged( 1 ); setChatgpt( e.target.value ); } }
			options={ [
				{ value: '', label: t( 'All Products' ) },
				{ value: 'enabled', label: t( 'ChatGPT Enabled' ) },
				{ value: 'disabled', label: t( 'ChatGPT Disabled' ) },
			] }
		/>
	);

	return (
		<div className="caic-stack">
			<section className="cv-section">
				<SectionHeader title={ t( 'Product Feed' ) } />

				{ error && <Banner tone="critical">{ error.message }</Banner> }
				{ notice && <Banner tone={ notice.tone }>{ notice.message }</Banner> }

				{ stats && (
					<div className="caic-stats">
						<StatCard accent value={ stats.total_products } label={ t( 'Total Products' ) } />
						<StatCard value={ stats.chatgpt_enabled } label={ t( 'ChatGPT Enabled' ) } />
						<StatCard value={ stats.avg_quality + '%' } label={ t( 'Avg Quality' ) } />
						<StatCard value={ stats.products_with_issues } label={ t( 'With Issues' ) } />
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
						placeholder={ t( 'Search by name or SKU…' ) }
						submitLabel={ t( 'Search' ) }
						clearLabel={ t( 'Clear' ) }
					/>
					{ chatgptFilter }
					<div className="caic-feed__toolbar-actions">
						<Button
							variant="secondary"
							loading={ busy === 'regenerate' }
							onClick={ regenerate }
						>
							{ t( 'Regenerate Feed' ) }
						</Button>
						<Button
							variant="secondary"
							loading={ busy === 'recalculate' }
							onClick={ recalculate }
						>
							{ t( 'Recalculate Quality' ) }
						</Button>
					</div>
				</div>

				{ selectedIds.length > 0 && (
					<BulkActionBar
						label={ t( '%d selected' ).replace( '%d', selectedIds.length ) }
					>
						<Button
							variant="secondary"
							size="sm"
							loading={ busy === 'bulk-enable' }
							onClick={ bulk( 'enable' ) }
						>
							{ t( 'Enable Selected' ) }
						</Button>
						<Button
							variant="secondary"
							size="sm"
							loading={ busy === 'bulk-disable' }
							onClick={ bulk( 'disable' ) }
						>
							{ t( 'Disable Selected' ) }
						</Button>
					</BulkActionBar>
				) }

				{ ! data && ! error && <LoadingPanel label={ t( 'Loading products…' ) } /> }

				{ data && data.rows.length === 0 && (
					<Card flush>
						<EmptyState title={ t( 'No products found' ) }>
							{ t( 'No products match the current filters.' ) }
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
							selectLabel={ t( 'Select product' ) }
							renderRowExtra={ renderRowExtra }
							emptyLabel={ t( 'No products found.' ) }
						/>
						<TablePagination page={ pagination.current_page } total={ pagination.total_pages } onChange={ setPaged } />
					</Card>
				) }
			</section>

			<Modal
				open={ null !== preview }
				onClose={ () => setPreview( null ) }
				title={ t( 'Feed Preview' ) }
				size="lg"
				closeLabel={ t( 'Close' ) }
			>
				{ preview && preview.loading && <LoadingPanel label={ t( 'Loading the preview…' ) } /> }
				{ preview && preview.error && <Banner tone="critical">{ preview.error }</Banner> }
				{ preview && undefined !== preview.feed && <pre className="caic-pre">{ preview.feed }</pre> }
			</Modal>
		</div>
	);
}
