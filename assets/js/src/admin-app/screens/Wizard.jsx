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
import {
	Stepper,
	Card,
	Banner,
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
import { boot, apiGet, apiPost, useBusy, t } from '../api';

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
		if ( ! window.confirm( t( 'Are you sure you want to restart the wizard? All progress will be lost.' ) ) ) {
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
			setNotice( { tone: 'success', message: t( 'Saved.' ) } );
		} catch ( e ) {
			setNotice( { tone: 'critical', message: e.message } );
		}
	} );

	const regenerate = ( key, path, fieldName ) => run( key, async () => {
		setNotice( null );
		try {
			const r = await apiPost( path, {} );
			setData( ( prev ) => ( { ...prev, [ fieldName ]: r[ fieldName ] } ) );
			setNotice( { tone: 'success', message: t( 'Regenerated.' ) } );
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
					<div className="caic-loading"><Spinner /> { t( 'Loading…' ) }</div>
				</section>
			</div>
		);
	}

	const current = data.current_step;
	const steps = data.steps.map( ( s ) => ( { id: String( s.n ), label: t( s.title ) } ) );

	return (
		<div className="caic-stack">
			<section className="cv-section">
				<SectionHeader
					title={ t( 'OpenAI Application Wizard' ) }
					meta={ <Button variant="link" loading={ 'reset' === busy } onClick={ resetWizard }>{ t( 'Restart Wizard' ) }</Button> }
				/>
				<Stepper steps={ steps } activeId={ String( current ) } />

				{ notice && <Banner tone={ notice.tone }>{ notice.message }</Banner> }

				{ renderStep( current ) }

				<div className="caic-actions">
					{ current > 1 && (
						<Button loading={ 'prev' === busy } onClick={ () => navigate( 'prev' )() }>{ t( 'Back' ) }</Button>
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
						<TextField label={ t( 'Business Name' ) } value={ form.business_name || '' } onChange={ field( 'business_name' ) } error={ errors.business_name } />
						<TextField label={ t( 'Business Entity Type' ) } help={ t( 'e.g. LLC, Sole Proprietor, Corporation' ) } value={ form.business_entity || '' } onChange={ field( 'business_entity' ) } error={ errors.business_entity } />
						<TextField label={ t( 'Contact Name' ) } value={ form.contact_name || '' } onChange={ field( 'contact_name' ) } error={ errors.contact_name } />
						<TextField type="email" label={ t( 'Contact Email' ) } value={ form.contact_email || '' } onChange={ field( 'contact_email' ) } error={ errors.contact_email } />
						<TextField type="url" label={ t( 'Store URL' ) } value={ form.store_url || '' } onChange={ field( 'store_url' ) } error={ errors.store_url } />
					</Card>
				);
			case 2:
				return (
					<Card>
						<p>{ t( 'Your product feed exposes ChatGPT-enabled products to OpenAI. Configure which products are included and review feed quality on the Product Feed screen.' ) }</p>
						<div className="caic-actions">
							<a className="cv-btn cv-btn--secondary" href={ ( boot.adminUrl || '/wp-admin/' ) + 'admin.php?page=' + ( data.product_feed_slug || 'carticy-ai-checkout-product-feed' ) }>{ t( 'Open Product Feed' ) }</a>
						</div>
					</Card>
				);
			case 3:
				return (
					<Card>
						<TextField type="url" label={ t( 'Terms of Service URL' ) } help={ t( 'Required.' ) } value={ form.terms_of_service || '' } onChange={ field( 'terms_of_service' ) } error={ errors.terms_of_service } />
						<TextField type="url" label={ t( 'Privacy Policy URL' ) } help={ t( 'Required.' ) } value={ form.privacy_policy || '' } onChange={ field( 'privacy_policy' ) } error={ errors.privacy_policy } />
						<TextField type="url" label={ t( 'Return / Refund Policy URL' ) } help={ t( 'Optional for digital-only stores.' ) } value={ form.return_policy || '' } onChange={ field( 'return_policy' ) } error={ errors.return_policy } />
					</Card>
				);
			case 4:
				return (
					<>
						<div className="caic-stats">
							<StatCard accent value={ data.quality.score + '%' } label={ t( 'Average Product Quality' ) } sublabel={ t( 'Across ChatGPT-enabled products' ) } />
						</div>
						{ data.quality.issues.length > 0 && (
							<Card>
								<p className="cv-help">{ t( 'Issues found in your product data:' ) }</p>
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
								{ key: 'label', header: t( 'Requirement' ) },
								{ key: 'status', header: t( 'Status' ), render: ( row ) => <Chip tone={ row.passed ? 'pos' : 'neg' }>{ row.passed ? t( 'Pass' ) : t( 'Fail' ) }</Chip> },
								{
									key: 'message',
									header: t( 'Details' ),
									render: ( row ) => (
										<>
											{ row.message }
											{ row.action_url && (
												<>{ ' ' }<a href={ row.action_url } target="_blank" rel="noreferrer">{ t( 'Fix This' ) }</a></>
											) }
										</>
									),
								},
							] }
							rows={ data.prerequisites }
							getRowId={ ( row ) => row.key }
							emptyLabel={ t( 'No prerequisite checks available.' ) }
						/>
					</Card>
				);
			case 6:
				return (
					<Card>
						<ToggleRow
							label={ t( 'Enable test mode' ) }
							checked={ !! form.test_mode }
							onChange={ ( e ) => setForm( ( prev ) => ( { ...prev, test_mode: e.target.checked } ) ) }
						/>
						<p className="cv-help">{ t( 'When test mode is on, conformance tests run against Stripe test keys and the test webhook URL.' ) }</p>
						<TextField
							type="url"
							label={ t( 'Test Webhook URL' ) }
							help={ t( 'Endpoint that receives sample webhooks while testing.' ) }
							value={ form.test_webhook_url || '' }
							onChange={ field( 'test_webhook_url' ) }
						/>
						<div className="caic-actions">
							<Button
								variant="primary"
								loading={ 'settings' === busy }
								onClick={ saveSettings( { test_mode: !! form.test_mode, test_webhook_url: form.test_webhook_url || '' }, 'settings' ) }
							>
								{ t( 'Save Test Setup' ) }
							</Button>
						</div>
					</Card>
				);
			case 7:
				return (
					<Card>
						<TextField readOnly label={ t( 'API Key (Bearer Token)' ) } value={ data.api_key } onFocus={ ( e ) => e.target.select() } />
						<div className="caic-actions">
							<Button onClick={ copy( data.api_key ) }>{ t( 'Copy' ) }</Button>
							<Button loading={ 'api_key' === busy } onClick={ regenerate( 'api_key', '/wizard/regenerate-api-key', 'api_key' ) }>{ t( 'Regenerate' ) }</Button>
						</div>

						<TextField readOnly label={ t( 'Webhook Secret (HMAC)' ) } value={ data.webhook_secret } onFocus={ ( e ) => e.target.select() } />
						<div className="caic-actions">
							<Button onClick={ copy( data.webhook_secret ) }>{ t( 'Copy' ) }</Button>
							<Button loading={ 'webhook_secret' === busy } onClick={ regenerate( 'webhook_secret', '/wizard/regenerate-webhook-secret', 'webhook_secret' ) }>{ t( 'Regenerate' ) }</Button>
						</div>

						<p className="cv-help">{ t( 'Download the generated application config to attach to your OpenAI merchant submission.' ) }</p>
						<div className="caic-actions">
							<Button variant="primary" onClick={ downloadConfig }>{ t( 'Download JSON Config' ) }</Button>
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
					<p>{ t( 'Run the 17 ACP conformance tests. All blocking tests must pass before submitting your application.' ) }</p>
					<div className="caic-actions">
						<Button variant="primary" loading={ running } onClick={ runAllTests }>{ t( 'Run All Tests' ) }</Button>
					</div>
				</Card>
				<Card flush>
					<Table
						columns={ [
							{ key: 'n', header: t( 'Test' ), render: ( row ) => '#' + row.n },
							{ key: 'result', header: t( 'Result' ), render: ( row ) => renderTestChip( row.id ) },
							{
								key: 'actions',
								header: t( 'Actions' ),
								render: ( row ) => {
									const state = testResults[ row.id ];
									return (
										<>
											<Button size="sm" loading={ row.id === busy } onClick={ () => reRunOne( row.id )() }>{ t( 'Run' ) }</Button>
											{ state && 'done' === state.status && (
												<>{ ' ' }<Button size="sm" variant="link" onClick={ () => setModalTest( row.id ) }>{ t( 'View' ) }</Button></>
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
			return <Chip tone="flat">{ blocking ? t( 'Blocking' ) : t( 'Optional' ) }</Chip>;
		}
		if ( 'running' === state.status ) {
			return <Chip tone="warn"><Spinner /> { t( 'Running…' ) }</Chip>;
		}
		if ( 'error' === state.status ) {
			return <Chip tone="neg">{ t( 'Error' ) }</Chip>;
		}
		return <Chip tone={ state.result.passed ? 'pos' : 'neg' }>{ state.result.passed ? t( 'Pass' ) : t( 'Fail' ) }</Chip>;
	}

	function renderTestModal( id ) {
		const state = testResults[ id ];
		const result = state && state.result ? state.result : null;
		return (
			<Modal open onClose={ () => setModalTest( null ) } title={ result ? result.name : id } size="lg">
				{ result ? (
					<>
						{ result.message && <p>{ result.message }</p> }
						<SectionHeader title={ t( 'Request' ) } />
						<pre className="caic-pre">{ JSON.stringify( result.request, null, 2 ) }</pre>
						<SectionHeader title={ t( 'Response' ) } />
						<pre className="caic-pre">{ JSON.stringify( result.response, null, 2 ) }</pre>
					</>
				) : (
					<p>{ t( 'No result for this test yet.' ) }</p>
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
					<SectionHeader title={ t( 'Application Summary' ) } />
					<Table
						columns={ [
							{ key: 'label', header: t( 'Field' ) },
							{ key: 'value', header: t( 'Value' ) },
						] }
						rows={ [
							{ label: t( 'Business Name' ), value: bi.business_name || '—' },
							{ label: t( 'Contact Email' ), value: bi.contact_email || '—' },
							{ label: t( 'Store URL' ), value: bi.store_url || '—' },
							{ label: t( 'Terms of Service' ), value: data.data.policies.terms_of_service || '—' },
							{ label: t( 'Privacy Policy' ), value: data.data.policies.privacy_policy || '—' },
						] }
						getRowId={ ( row ) => row.label }
					/>
				</Card>
				<Banner tone="info" title={ t( 'Manual submission required' ) }>
					{ t( 'There is no automated submission API. Submit your application through the OpenAI merchant web form at chatgpt.com/merchants, attaching the JSON config downloaded in the Integration step.' ) }
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
				} ) }>{ t( 'Save & Next' ) }</Button>
			);
		}
		if ( 3 === step ) {
			return (
				<Button variant="primary" loading={ 'next' === busy } onClick={ saveStepAndNext( 3, {
					terms_of_service: form.terms_of_service || '',
					privacy_policy: form.privacy_policy || '',
					return_policy: form.return_policy || '',
				} ) }>{ t( 'Save & Next' ) }</Button>
			);
		}
		return <Button variant="primary" loading={ 'next' === busy } onClick={ () => recordAndNext( step )() }>{ t( 'Next' ) }</Button>;
	}
}
