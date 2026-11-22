/**
 * Application Wizard screen.
 *
 * The nine-step OpenAI application flow: business info, product feed, policies,
 * data quality, prerequisites, test setup, integration credentials, the 17-test
 * conformance runner, and manual-submission instructions. The whole flow loads
 * from and mutates through the admin Wizard REST controller; navigation and the
 * conformance runner drive the same services the legacy multi-step PHP form +
 * jQuery did. Built from the shared design-system primitives so it matches every
 * other Carticy admin screen.
 */

import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	Stepper,
	Card,
	Banner,
	LoadingPanel,
	Spinner,
	Button,
	Chip,
	Modal,
	StatCard,
	Table,
	SectionHeader,
	TextField,
	ToggleRow,
} from '@carticy/admin-ui';
import { boot, apiGet, apiPost, useBusy } from '../api';

// Conformance test IDs are test_1..test_17; 1-10 and 17 are blocking.
const TEST_IDS = Array.from( { length: 17 }, ( _, i ) => 'test_' + ( i + 1 ) );
const BLOCKING_TESTS = [ 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 17 ];

export default function Wizard() {
	const [ data, setData ] = useState( null );
	const [ loadError, setLoadError ] = useState( null );
	const [ notice, setNotice ] = useState( null );
	const [ form, setForm ] = useState( {} );
	const [ errors, setErrors ] = useState( {} );
	const [ busy, run ] = useBusy();

	// Conformance state: per-test result keyed by test id, plus the open modal.
	const [ testResults, setTestResults ] = useState( {} );
	const [ running, setRunning ] = useState( false );
	const [ modalTest, setModalTest ] = useState( null );

	useEffect( () => {
		let live = true;
		apiGet( '/wizard' )
			.then( ( r ) => {
				if ( live ) {
					setData( r );
					seedForm( r );
				}
			} )
			.catch( ( e ) => { if ( live ) { setLoadError( e ); } } );
		return () => { live = false; };
	}, [] );

	// Seed the editable form state for the current step from the server payload.
	const seedForm = ( r ) => {
		setErrors( {} );
		setForm( {
			...r.data.business_info,
			...r.data.policies,
			test_mode: r.test_mode,
			test_webhook_url: r.data.test_setup.test_webhook_url || '',
		} );
	};

	const apply = ( r ) => {
		setData( r );
		seedForm( r );
	};

	const field = ( key ) => ( e ) => setForm( ( prev ) => ( { ...prev, [ key ]: e.target.value } ) );

	// Persist a form step (business_info / policies) then advance on success.
	const saveStepAndNext = ( step, stepData ) => run( 'next', async () => {
		setNotice( null );
		setErrors( {} );
		try {
			const r = await apiPost( '/wizard/step', { step, step_data: stepData } );
			if ( ! r.ok ) {
				setErrors( r.errors || {} );
				return;
			}
			const nav = await apiPost( '/wizard/navigate', { direction: 'next' } );
			apply( nav );
		} catch ( e ) {
			setNotice( { tone: 'critical', message: e.message } );
		}
	} );

	// Advance an informational step (records completion server-side, then moves on).
	const recordAndNext = ( step ) => run( 'next', async () => {
		setNotice( null );
		try {
			await apiPost( '/wizard/step', { step, step_data: {} } );
			const nav = await apiPost( '/wizard/navigate', { direction: 'next' } );
			apply( nav );
		} catch ( e ) {
			setNotice( { tone: 'critical', message: e.message } );
		}
	} );

	const navigate = ( direction ) => run( direction, async () => {
		setNotice( null );
		try {
			const r = await apiPost( '/wizard/navigate', { direction } );
			apply( r );
		} catch ( e ) {
			setNotice( { tone: 'critical', message: e.message } );
		}
	} );

	const resetWizard = run( 'reset', async () => {
		// eslint-disable-next-line no-alert
		if ( ! window.confirm( __( 'Are you sure you want to restart the wizard? All progress will be lost.', 'carticy-ai-checkout-for-woocommerce' ) ) ) {
			return;
		}
		setNotice( null );
		try {
			const r = await apiPost( '/wizard/reset', {} );
			apply( r );
			setTestResults( {} );
		} catch ( e ) {
			setNotice( { tone: 'critical', message: e.message } );
		}
	} );

	const saveSettings = ( payload, key ) => run( key, async () => {
		setNotice( null );
		try {
			await apiPost( '/wizard/settings', payload );
			setNotice( { tone: 'success', message: __( 'Saved.', 'carticy-ai-checkout-for-woocommerce' ) } );
		} catch ( e ) {
			setNotice( { tone: 'critical', message: e.message } );
		}
	} );

	const regenerate = ( key, path, fieldName ) => run( key, async () => {
		setNotice( null );
		try {
			const r = await apiPost( path, {} );
			setData( ( prev ) => ( { ...prev, [ fieldName ]: r[ fieldName ] } ) );
			setNotice( { tone: 'success', message: __( 'Regenerated.', 'carticy-ai-checkout-for-woocommerce' ) } );
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

	const downloadConfig = () => {
		const blob = new Blob( [ JSON.stringify( data.application_json, null, 2 ) ], { type: 'application/json' } );
		const url = URL.createObjectURL( blob );
		const a = document.createElement( 'a' );
		a.href = url;
		a.download = 'carticy-ai-checkout-application.json';
		document.body.appendChild( a );
		a.click();
		document.body.removeChild( a );
		URL.revokeObjectURL( url );
	};

	// Run one conformance test and store its result (used by run-all and re-run).
	const runOneTest = async ( id ) => {
		setTestResults( ( prev ) => ( { ...prev, [ id ]: { status: 'running' } } ) );
		try {
			const r = await apiPost( '/wizard/conformance', { action: 'run', test_id: id } );
			setTestResults( ( prev ) => ( { ...prev, [ id ]: { status: 'done', result: r.result } } ) );
			return r.result;
		} catch ( e ) {
			setTestResults( ( prev ) => ( { ...prev, [ id ]: { status: 'error', message: e.message } } ) );
			return null;
		}
	};

	// Run every test sequentially, then clean up artifacts and persist results.
	const runAllTests = async () => {
		setRunning( true );
		setNotice( null );
		const collected = [];
		for ( const id of TEST_IDS ) {
			// eslint-disable-next-line no-await-in-loop
			const result = await runOneTest( id );
			if ( result ) {
				collected.push( result );
			}
		}
		try {
			await apiPost( '/wizard/conformance', { action: 'cleanup' } );
			const passed = collected.filter( ( r ) => r.passed ).length;
			const summary = {
				total: collected.length,
				passed,
				failed: collected.length - passed,
				pass_rate: collected.length ? Math.round( ( passed / collected.length ) * 100 ) : 0,
				all_passed: passed === collected.length,
			};
			await apiPost( '/wizard/save-results', { results: { summary, tests: collected } } );
		} catch ( e ) {
			setNotice( { tone: 'critical', message: e.message } );
		}
		setRunning( false );
	};

	const reRunOne = ( id ) => run( id, async () => {
		await runOneTest( id );
	} );

	if ( loadError ) {
		return (
			<div className="caic-stack">
				<section className="cv-section"><Banner tone="critical">{ loadError.message }</Banner></section>
			</div>
		);
	}

	if ( ! data ) {
		return (
			<div className="caic-stack">
				<section className="cv-section">
					<LoadingPanel label={ __( 'Loading the wizard…', 'carticy-ai-checkout-for-woocommerce' ) } />
				</section>
			</div>
		);
	}

	const current = data.current_step;
	const steps = data.steps.map( ( s ) => ( { id: String( s.n ), label: s.title } ) );

	return (
		<div className="caic-stack">
			<section className="cv-section">
				<SectionHeader
					title={ __( 'OpenAI Application Wizard', 'carticy-ai-checkout-for-woocommerce' ) }
					actions={ <Button variant="link" loading={ 'reset' === busy } onClick={ resetWizard }>{ __( 'Restart Wizard', 'carticy-ai-checkout-for-woocommerce' ) }</Button> }
				/>
				<Stepper steps={ steps } activeId={ String( current ) } doneLabel={ __( 'Completed', 'carticy-ai-checkout-for-woocommerce' ) } />

				{ notice && <Banner tone={ notice.tone }>{ notice.message }</Banner> }

				{ renderStep( current ) }

				<div className="caic-actions">
					{ current > 1 && (
						<Button loading={ 'prev' === busy } onClick={ () => navigate( 'prev' )() }>{ __( 'Back', 'carticy-ai-checkout-for-woocommerce' ) }</Button>
					) }
					{ renderNext( current ) }
				</div>
			</section>
		</div>
	);

	// --- Per-step content -------------------------------------------------

	function renderStep( step ) {
		switch ( step ) {
			case 1:
				return (
					<Card>
						<TextField label={ __( 'Business Name', 'carticy-ai-checkout-for-woocommerce' ) } value={ form.business_name || '' } onChange={ field( 'business_name' ) } error={ errors.business_name } />
						<TextField label={ __( 'Business Entity Type', 'carticy-ai-checkout-for-woocommerce' ) } help={ __( 'e.g. LLC, Sole Proprietor, Corporation', 'carticy-ai-checkout-for-woocommerce' ) } value={ form.business_entity || '' } onChange={ field( 'business_entity' ) } error={ errors.business_entity } />
						<TextField label={ __( 'Contact Name', 'carticy-ai-checkout-for-woocommerce' ) } value={ form.contact_name || '' } onChange={ field( 'contact_name' ) } error={ errors.contact_name } />
						<TextField type="email" label={ __( 'Contact Email', 'carticy-ai-checkout-for-woocommerce' ) } value={ form.contact_email || '' } onChange={ field( 'contact_email' ) } error={ errors.contact_email } />
						<TextField type="url" label={ __( 'Store URL', 'carticy-ai-checkout-for-woocommerce' ) } value={ form.store_url || '' } onChange={ field( 'store_url' ) } error={ errors.store_url } />
					</Card>
				);
			case 2:
				return (
					<Card>
						<p>{ __( 'Your product feed exposes ChatGPT-enabled products to OpenAI. Configure which products are included and review feed quality on the Product Feed screen.', 'carticy-ai-checkout-for-woocommerce' ) }</p>
						<div className="caic-actions">
							<a className="cv-btn cv-btn--secondary" href={ ( boot.adminUrl || '/wp-admin/' ) + 'admin.php?page=' + ( data.product_feed_slug || 'carticy-ai-checkout-product-feed' ) }>{ __( 'Open Product Feed', 'carticy-ai-checkout-for-woocommerce' ) }</a>
						</div>
					</Card>
				);
			case 3:
				return (
					<Card>
						<TextField type="url" label={ __( 'Terms of Service URL', 'carticy-ai-checkout-for-woocommerce' ) } help={ __( 'Required.', 'carticy-ai-checkout-for-woocommerce' ) } value={ form.terms_of_service || '' } onChange={ field( 'terms_of_service' ) } error={ errors.terms_of_service } />
						<TextField type="url" label={ __( 'Privacy Policy URL', 'carticy-ai-checkout-for-woocommerce' ) } help={ __( 'Required.', 'carticy-ai-checkout-for-woocommerce' ) } value={ form.privacy_policy || '' } onChange={ field( 'privacy_policy' ) } error={ errors.privacy_policy } />
						<TextField type="url" label={ __( 'Return / Refund Policy URL', 'carticy-ai-checkout-for-woocommerce' ) } help={ __( 'Optional for digital-only stores.', 'carticy-ai-checkout-for-woocommerce' ) } value={ form.return_policy || '' } onChange={ field( 'return_policy' ) } error={ errors.return_policy } />
					</Card>
				);
			case 4:
				return (
					<>
						<div className="caic-stats">
							<StatCard accent value={ data.quality.score + '%' } label={ __( 'Average Product Quality', 'carticy-ai-checkout-for-woocommerce' ) } sublabel={ __( 'Across ChatGPT-enabled products', 'carticy-ai-checkout-for-woocommerce' ) } />
						</div>
						{ data.quality.issues.length > 0 && (
							<Card>
								<p className="cv-help">{ __( 'Issues found in your product data:', 'carticy-ai-checkout-for-woocommerce' ) }</p>
								<ul className="caic-issues">
									{ data.quality.issues.map( ( issue, i ) => <li key={ i }>{ issue }</li> ) }
								</ul>
							</Card>
						) }
					</>
				);
			case 5:
				return (
					<Card flush>
						<Table
							columns={ [
								{ key: 'label', header: __( 'Requirement', 'carticy-ai-checkout-for-woocommerce' ) },
								{ key: 'status', header: __( 'Status', 'carticy-ai-checkout-for-woocommerce' ), render: ( row ) => <Chip tone={ row.passed ? 'success' : 'critical' }>{ row.passed ? __( 'Pass', 'carticy-ai-checkout-for-woocommerce' ) : __( 'Fail', 'carticy-ai-checkout-for-woocommerce' ) }</Chip> },
								{
									key: 'message',
									header: __( 'Details', 'carticy-ai-checkout-for-woocommerce' ),
									render: ( row ) => (
										<>
											{ row.message }
											{ row.action_url && (
												<>{ ' ' }<a href={ row.action_url } target="_blank" rel="noreferrer">{ __( 'Fix This', 'carticy-ai-checkout-for-woocommerce' ) }</a></>
											) }
										</>
									),
								},
							] }
							rows={ data.prerequisites }
							getRowId={ ( row ) => row.key }
							emptyLabel={ __( 'No prerequisite checks available.', 'carticy-ai-checkout-for-woocommerce' ) }
						/>
					</Card>
				);
			case 6:
				return (
					<Card>
						<ToggleRow
							label={ __( 'Enable test mode', 'carticy-ai-checkout-for-woocommerce' ) }
							help={ __( 'When test mode is on, conformance tests run against Stripe test keys and the test webhook URL.', 'carticy-ai-checkout-for-woocommerce' ) }
							checked={ !! form.test_mode }
							onChange={ ( e ) => setForm( ( prev ) => ( { ...prev, test_mode: e.target.checked } ) ) }
						/>
						<TextField
							type="url"
							label={ __( 'Test Webhook URL', 'carticy-ai-checkout-for-woocommerce' ) }
							help={ __( 'Where sample webhooks are sent while testing.', 'carticy-ai-checkout-for-woocommerce' ) }
							value={ form.test_webhook_url || '' }
							onChange={ field( 'test_webhook_url' ) }
						/>
						<div className="caic-actions">
							<Button
								variant="primary"
								loading={ 'settings' === busy }
								onClick={ saveSettings( { test_mode: !! form.test_mode, test_webhook_url: form.test_webhook_url || '' }, 'settings' ) }
							>
								{ __( 'Save Test Setup', 'carticy-ai-checkout-for-woocommerce' ) }
							</Button>
						</div>
					</Card>
				);
			case 7:
				return (
					<Card>
						<TextField readOnly label={ __( 'API Key (Bearer Token)', 'carticy-ai-checkout-for-woocommerce' ) } value={ data.api_key } onFocus={ ( e ) => e.target.select() } />
						<div className="caic-actions">
							<Button onClick={ copy( data.api_key ) }>{ __( 'Copy', 'carticy-ai-checkout-for-woocommerce' ) }</Button>
							<Button loading={ 'api_key' === busy } onClick={ regenerate( 'api_key', '/wizard/regenerate-api-key', 'api_key' ) }>{ __( 'Regenerate', 'carticy-ai-checkout-for-woocommerce' ) }</Button>
						</div>

						<TextField readOnly label={ __( 'Webhook Secret (HMAC)', 'carticy-ai-checkout-for-woocommerce' ) } value={ data.webhook_secret } onFocus={ ( e ) => e.target.select() } />
						<div className="caic-actions">
							<Button onClick={ copy( data.webhook_secret ) }>{ __( 'Copy', 'carticy-ai-checkout-for-woocommerce' ) }</Button>
							<Button loading={ 'webhook_secret' === busy } onClick={ regenerate( 'webhook_secret', '/wizard/regenerate-webhook-secret', 'webhook_secret' ) }>{ __( 'Regenerate', 'carticy-ai-checkout-for-woocommerce' ) }</Button>
						</div>

						<p className="cv-help">{ __( 'Download the generated application config to attach to your OpenAI merchant submission.', 'carticy-ai-checkout-for-woocommerce' ) }</p>
						<div className="caic-actions">
							<Button variant="primary" onClick={ downloadConfig }>{ __( 'Download JSON Config', 'carticy-ai-checkout-for-woocommerce' ) }</Button>
						</div>
					</Card>
				);
			case 8:
				return renderConformance();
			case 9:
				return renderSubmit();
			default:
				return null;
		}
	}

	// --- Step 8: conformance runner --------------------------------------

	function renderConformance() {
		return (
			<>
				<Card>
					<p>{ __( 'Run the 17 ACP conformance tests. All blocking tests must pass before submitting your application.', 'carticy-ai-checkout-for-woocommerce' ) }</p>
					<div className="caic-actions">
						<Button variant="primary" loading={ running } onClick={ runAllTests }>{ __( 'Run All Tests', 'carticy-ai-checkout-for-woocommerce' ) }</Button>
					</div>
				</Card>
				<Card flush>
					<Table
						columns={ [
							{ key: 'n', header: __( 'Test', 'carticy-ai-checkout-for-woocommerce' ), render: ( row ) => '#' + row.n },
							{ key: 'result', header: __( 'Result', 'carticy-ai-checkout-for-woocommerce' ), render: ( row ) => renderTestChip( row.id ) },
							{
								key: 'actions',
								header: __( 'Actions', 'carticy-ai-checkout-for-woocommerce' ),
								render: ( row ) => {
									const state = testResults[ row.id ];
									return (
										<>
											<Button size="sm" loading={ row.id === busy } onClick={ () => reRunOne( row.id )() }>{ __( 'Run', 'carticy-ai-checkout-for-woocommerce' ) }</Button>
											{ state && 'done' === state.status && (
												<>{ ' ' }<Button size="sm" variant="link" onClick={ () => setModalTest( row.id ) }>{ __( 'View', 'carticy-ai-checkout-for-woocommerce' ) }</Button></>
											) }
										</>
									);
								},
							},
						] }
						rows={ TEST_IDS.map( ( id, i ) => ( { id, n: i + 1 } ) ) }
						getRowId={ ( row ) => row.id }
					/>
				</Card>
				{ modalTest && renderTestModal( modalTest ) }
			</>
		);
	}

	function renderTestChip( id ) {
		const n = parseInt( id.replace( 'test_', '' ), 10 );
		const state = testResults[ id ];
		const blocking = BLOCKING_TESTS.includes( n );

		if ( ! state ) {
			return <Chip>{ blocking ? __( 'Blocking', 'carticy-ai-checkout-for-woocommerce' ) : __( 'Optional', 'carticy-ai-checkout-for-woocommerce' ) }</Chip>;
		}
		if ( 'running' === state.status ) {
			return <Chip tone="warning"><Spinner /> { __( 'Running…', 'carticy-ai-checkout-for-woocommerce' ) }</Chip>;
		}
		if ( 'error' === state.status ) {
			return <Chip tone="critical">{ __( 'Error', 'carticy-ai-checkout-for-woocommerce' ) }</Chip>;
		}
		return <Chip tone={ state.result.passed ? 'success' : 'critical' }>{ state.result.passed ? __( 'Pass', 'carticy-ai-checkout-for-woocommerce' ) : __( 'Fail', 'carticy-ai-checkout-for-woocommerce' ) }</Chip>;
	}

	function renderTestModal( id ) {
		const state = testResults[ id ];
		const result = state && state.result ? state.result : null;
		return (
			<Modal open onClose={ () => setModalTest( null ) } title={ result ? result.name : id } size="lg" closeLabel={ __( 'Close', 'carticy-ai-checkout-for-woocommerce' ) }>
				{ result ? (
					<>
						{ result.message && <p>{ result.message }</p> }
						<SectionHeader title={ __( 'Request', 'carticy-ai-checkout-for-woocommerce' ) } headingLevel={ 3 } />
						<pre className="caic-pre">{ JSON.stringify( result.request, null, 2 ) }</pre>
						<SectionHeader title={ __( 'Response', 'carticy-ai-checkout-for-woocommerce' ) } headingLevel={ 3 } />
						<pre className="caic-pre">{ JSON.stringify( result.response, null, 2 ) }</pre>
					</>
				) : (
					<p>{ __( 'No result for this test yet.', 'carticy-ai-checkout-for-woocommerce' ) }</p>
				) }
			</Modal>
		);
	}

	// --- Step 9: submit instructions -------------------------------------

	function renderSubmit() {
		const bi = data.data.business_info;
		return (
			<>
				<Card>
					<SectionHeader title={ __( 'Application Summary', 'carticy-ai-checkout-for-woocommerce' ) } headingLevel={ 3 } />
					<Table
						columns={ [
							{ key: 'label', header: __( 'Field', 'carticy-ai-checkout-for-woocommerce' ) },
							{ key: 'value', header: __( 'Value', 'carticy-ai-checkout-for-woocommerce' ) },
						] }
						rows={ [
							{ label: __( 'Business Name', 'carticy-ai-checkout-for-woocommerce' ), value: bi.business_name || '—' },
							{ label: __( 'Contact Email', 'carticy-ai-checkout-for-woocommerce' ), value: bi.contact_email || '—' },
							{ label: __( 'Store URL', 'carticy-ai-checkout-for-woocommerce' ), value: bi.store_url || '—' },
							{ label: __( 'Terms of Service', 'carticy-ai-checkout-for-woocommerce' ), value: data.data.policies.terms_of_service || '—' },
							{ label: __( 'Privacy Policy', 'carticy-ai-checkout-for-woocommerce' ), value: data.data.policies.privacy_policy || '—' },
						] }
						getRowId={ ( row ) => row.label }
					/>
				</Card>
				<Banner tone="info" title={ __( 'Manual submission required', 'carticy-ai-checkout-for-woocommerce' ) }>
					{ __( 'There is no automated submission API. Submit your application through the OpenAI merchant web form at chatgpt.com/merchants, attaching the JSON config downloaded in the Integration step.', 'carticy-ai-checkout-for-woocommerce' ) }
				</Banner>
			</>
		);
	}

	// --- Footer Next button ----------------------------------------------

	function renderNext( step ) {
		if ( step >= data.total_steps ) {
			return null;
		}
		if ( 1 === step ) {
			return (
				<Button variant="primary" loading={ 'next' === busy } onClick={ saveStepAndNext( 1, {
					business_name: form.business_name || '',
					business_entity: form.business_entity || '',
					contact_name: form.contact_name || '',
					contact_email: form.contact_email || '',
					store_url: form.store_url || '',
				} ) }>{ __( 'Save & Next', 'carticy-ai-checkout-for-woocommerce' ) }</Button>
			);
		}
		if ( 3 === step ) {
			return (
				<Button variant="primary" loading={ 'next' === busy } onClick={ saveStepAndNext( 3, {
					terms_of_service: form.terms_of_service || '',
					privacy_policy: form.privacy_policy || '',
					return_policy: form.return_policy || '',
				} ) }>{ __( 'Save & Next', 'carticy-ai-checkout-for-woocommerce' ) }</Button>
			);
		}
		return <Button variant="primary" loading={ 'next' === busy } onClick={ () => recordAndNext( step )() }>{ __( 'Next', 'carticy-ai-checkout-for-woocommerce' ) }</Button>;
	}
}
