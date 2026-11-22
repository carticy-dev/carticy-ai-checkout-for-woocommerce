/**
 * Analytics screen.
 *
 * ChatGPT vs regular order metrics for a date range, re-loading when the range
 * changes. Built from the shared design-system primitives (SectionHeader,
 * StatCard, Table, Chip) so it matches every other Carticy admin screen.
 */

import { useState, useEffect } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Card, Chip, LoadingPanel, Banner, SectionHeader, StatCard, Table, SelectField } from '@carticy/admin-ui';
import { boot, apiGet } from '../api';

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
			aria-label={ __( 'Date range', 'carticy-ai-checkout-for-woocommerce' ) }
			value={ days }
			onChange={ ( e ) => setDays( e.target.value ) }
			options={ [
				{ value: '7', label: __( 'Last 7 Days', 'carticy-ai-checkout-for-woocommerce' ) },
				{ value: '30', label: __( 'Last 30 Days', 'carticy-ai-checkout-for-woocommerce' ) },
				{ value: '90', label: __( 'Last 90 Days', 'carticy-ai-checkout-for-woocommerce' ) },
			] }
		/>
	);

	const statusColumns = [
		{ key: 'label', header: __( 'Status', 'carticy-ai-checkout-for-woocommerce' ), render: ( row ) => <Chip tone={ row.tone }>{ row.label }</Chip> },
		{ key: 'count', header: __( 'Count', 'carticy-ai-checkout-for-woocommerce' ) },
		{ key: 'pct', header: __( 'Percentage', 'carticy-ai-checkout-for-woocommerce' ), render: ( row ) => row.pct + '%' },
	];

	const orderColumns = [
		{ key: 'number', header: __( 'Order', 'carticy-ai-checkout-for-woocommerce' ), render: ( row ) => <a href={ row.edit_url }>#{ row.number }</a> },
		{ key: 'date', header: __( 'Date', 'carticy-ai-checkout-for-woocommerce' ) },
		{ key: 'customer', header: __( 'Customer', 'carticy-ai-checkout-for-woocommerce' ) },
		{ key: 'total', header: __( 'Total', 'carticy-ai-checkout-for-woocommerce' ) },
		{ key: 'status', header: __( 'Status', 'carticy-ai-checkout-for-woocommerce' ), render: ( row ) => <Chip tone={ row.tone }>{ row.status_label }</Chip> },
	];

	return (
		<div className="caic-stack">
			<section className="cv-section">
				<SectionHeader title={ __( 'Performance', 'carticy-ai-checkout-for-woocommerce' ) } actions={ rangePicker } />

				{ error && <Banner tone="critical">{ error.message }</Banner> }

				{ ! data && ! error && <LoadingPanel label={ __( 'Loading analytics…', 'carticy-ai-checkout-for-woocommerce' ) } /> }

				{ data && (
					<div className="caic-stats">
						<StatCard
							accent
							value={ data.kpis.chatgpt_orders }
							label={ __( 'ChatGPT Orders', 'carticy-ai-checkout-for-woocommerce' ) }
							/* translators: %s: revenue amount. */
							sublabel={ sprintf( __( 'Revenue: %s', 'carticy-ai-checkout-for-woocommerce' ), data.kpis.chatgpt_revenue ) }
						/>
						<StatCard
							value={ data.kpis.regular_orders }
							label={ __( 'Regular Orders', 'carticy-ai-checkout-for-woocommerce' ) }
							/* translators: %s: revenue amount. */
							sublabel={ sprintf( __( 'Revenue: %s', 'carticy-ai-checkout-for-woocommerce' ), data.kpis.regular_revenue ) }
						/>
						<StatCard
							value={ data.kpis.conversion_rate + '%' }
							label={ __( 'ChatGPT Conversion', 'carticy-ai-checkout-for-woocommerce' ) }
							/* translators: 1: orders placed through ChatGPT, 2: all orders. */
							sublabel={ sprintf( _n( '%1$d of %2$d order', '%1$d of %2$d orders', data.kpis.total_orders, 'carticy-ai-checkout-for-woocommerce' ), data.kpis.chatgpt_orders, data.kpis.total_orders ) }
						/>
						<StatCard
							value={ data.kpis.chatgpt_aov }
							label={ __( 'ChatGPT AOV', 'carticy-ai-checkout-for-woocommerce' ) }
							/* translators: %s: average order value of other orders. */
							sublabel={ sprintf( __( 'Regular: %s', 'carticy-ai-checkout-for-woocommerce' ), data.kpis.regular_aov ) }
						/>
					</div>
				) }
			</section>

			{ data && (
				<>
					<section className="cv-section">
						<SectionHeader title={ __( 'ChatGPT Order Status Distribution', 'carticy-ai-checkout-for-woocommerce' ) } />
						<Card flush>
							<Table
								columns={ statusColumns }
								rows={ data.status_stats }
								getRowId={ ( row ) => row.status }
								emptyLabel={ __( 'No status data for the selected range.', 'carticy-ai-checkout-for-woocommerce' ) }
							/>
						</Card>
					</section>

					<section className="cv-section">
						<SectionHeader title={ __( 'Recent ChatGPT Orders', 'carticy-ai-checkout-for-woocommerce' ) } />
						<Card flush>
							<Table
								columns={ orderColumns }
								rows={ data.recent_orders }
								emptyLabel={ __( 'No ChatGPT orders found in the selected date range.', 'carticy-ai-checkout-for-woocommerce' ) }
							/>
						</Card>
					</section>
				</>
			) }
		</div>
	);
}
