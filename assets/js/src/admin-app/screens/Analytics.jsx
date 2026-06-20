/**
 * Analytics screen.
 *
 * ChatGPT vs regular order metrics for a date range, re-loading when the range
 * changes. Built from the shared design-system primitives (SectionHeader,
 * StatCard, Table, Chip) so it matches every other Carticy admin screen.
 */

import { useState, useEffect } from '@wordpress/element';
import { Card, Chip, Spinner, Banner, SectionHeader, StatCard, Table, SelectField } from '@carticy/admin-ui';
import { boot, apiGet, t } from '../api';

export default function Analytics() {
	const [ days, setDays ] = useState( String( ( boot.analytics && boot.analytics.defaultDays ) || 30 ) );
	const [ data, setData ] = useState( null );
	const [ error, setError ] = useState( null );

	useEffect( () => {
		let active = true;
		setData( null );
		setError( null );
		apiGet( '/analytics?days=' + encodeURIComponent( days ) )
			.then( ( r ) => { if ( active ) { setData( r ); } } )
			.catch( ( e ) => { if ( active ) { setError( e ); } } );
		return () => { active = false; };
	}, [ days ] );

	const rangePicker = (
		<SelectField
			className="caic-range"
			value={ days }
			onChange={ ( e ) => setDays( e.target.value ) }
			options={ [
				{ value: '7', label: t( 'Last 7 Days' ) },
				{ value: '30', label: t( 'Last 30 Days' ) },
				{ value: '90', label: t( 'Last 90 Days' ) },
			] }
		/>
	);

	const statusColumns = [
		{ key: 'label', header: t( 'Status' ), render: ( row ) => <Chip tone={ row.tone }>{ row.label }</Chip> },
		{ key: 'count', header: t( 'Count' ) },
		{ key: 'pct', header: t( 'Percentage' ), render: ( row ) => row.pct + '%' },
	];

	const orderColumns = [
		{ key: 'number', header: t( 'Order' ), render: ( row ) => <a href={ row.edit_url }>#{ row.number }</a> },
		{ key: 'date', header: t( 'Date' ) },
		{ key: 'customer', header: t( 'Customer' ) },
		{ key: 'total', header: t( 'Total' ) },
		{ key: 'status', header: t( 'Status' ), render: ( row ) => <Chip tone={ row.tone }>{ row.status_label }</Chip> },
	];

	return (
		<div className="caic-stack">
			<section className="cv-section">
				<SectionHeader title={ t( 'Performance' ) } meta={ rangePicker } />

				{ error && <Banner tone="critical">{ error.message }</Banner> }

				{ ! data && ! error && (
					<div className="caic-loading"><Spinner /> { t( 'Loading…' ) }</div>
				) }

				{ data && (
					<div className="caic-stats">
						<StatCard
							accent
							value={ data.kpis.chatgpt_orders }
							label={ t( 'ChatGPT Orders' ) }
							sublabel={ t( 'Revenue: %s' ).replace( '%s', data.kpis.chatgpt_revenue ) }
						/>
						<StatCard
							value={ data.kpis.regular_orders }
							label={ t( 'Regular Orders' ) }
							sublabel={ t( 'Revenue: %s' ).replace( '%s', data.kpis.regular_revenue ) }
						/>
						<StatCard
							value={ data.kpis.conversion_rate + '%' }
							label={ t( 'ChatGPT Conversion' ) }
							sublabel={ t( '%1$d of %2$d total orders' ).replace( '%1$d', data.kpis.chatgpt_orders ).replace( '%2$d', data.kpis.total_orders ) }
						/>
						<StatCard
							value={ data.kpis.chatgpt_aov }
							label={ t( 'ChatGPT AOV' ) }
							sublabel={ t( 'Regular: %s' ).replace( '%s', data.kpis.regular_aov ) }
						/>
					</div>
				) }
			</section>

			{ data && (
				<>
					<section className="cv-section">
						<SectionHeader title={ t( 'ChatGPT Order Status Distribution' ) } />
						<Card flush>
							<Table
								columns={ statusColumns }
								rows={ data.status_stats }
								getRowId={ ( row ) => row.status }
								emptyLabel={ t( 'No status data for the selected range.' ) }
							/>
						</Card>
					</section>

					<section className="cv-section">
						<SectionHeader title={ t( 'Recent ChatGPT Orders' ) } />
						<Card flush>
							<Table
								columns={ orderColumns }
								rows={ data.recent_orders }
								emptyLabel={ t( 'No ChatGPT orders found in the selected date range.' ) }
							/>
						</Card>
					</section>
				</>
			) }
		</div>
	);
}
