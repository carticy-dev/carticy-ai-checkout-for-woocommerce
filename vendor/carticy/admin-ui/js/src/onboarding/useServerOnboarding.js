/**
 * useServerOnboarding — the server-validated onboarding state machine client.
 *
 * The SERVER owns the state string and validates every transition; the
 * client only renders the current state and requests transitions. Reaching
 * a "complete" state must mean the server verified it (e.g. a wire-confirmed
 * test purchase), never that the client clicked through — components must
 * not invent local progress the server would reject.
 *
 * Contract expected from the REST endpoint (path is plugin-specific):
 *   GET  {path}            -> { state: string, ...context }
 *   POST {path}/transition -> body { to: string, ...payload }
 *                             200 { state: string, ...context } on success,
 *                             4xx { message } when the transition is invalid.
 */
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

export function useServerOnboarding( path, { autoFetch = true } = {} ) {
	const [ data, setData ] = useState( null );
	const [ loading, setLoading ] = useState( Boolean( autoFetch ) );
	const [ submitting, setSubmitting ] = useState( false );
	const [ error, setError ] = useState( null );
	const mounted = useRef( true );

	useEffect( () => {
		mounted.current = true;
		return () => {
			mounted.current = false;
		};
	}, [] );

	const refetch = useCallback( async () => {
		setLoading( true );
		setError( null );
		try {
			const response = await apiFetch( { path } );
			if ( mounted.current ) {
				setData( response );
			}
			return response;
		} catch ( err ) {
			if ( mounted.current ) {
				setError( err );
			}
			throw err;
		} finally {
			if ( mounted.current ) {
				setLoading( false );
			}
		}
	}, [ path ] );

	useEffect( () => {
		if ( autoFetch ) {
			refetch().catch( () => {} );
		}
	}, [ autoFetch, refetch ] );

	const transition = useCallback(
		async ( to, payload = {} ) => {
			setSubmitting( true );
			setError( null );
			try {
				const response = await apiFetch( {
					path: `${ path }/transition`,
					method: 'POST',
					data: { to, ...payload },
				} );
				if ( mounted.current ) {
					setData( response );
				}
				return response;
			} catch ( err ) {
				if ( mounted.current ) {
					setError( err );
				}
				throw err;
			} finally {
				if ( mounted.current ) {
					setSubmitting( false );
				}
			}
		},
		[ path ]
	);

	return {
		state: data ? data.state : null,
		data,
		loading,
		submitting,
		error,
		refetch,
		transition,
	};
}
