/**
 * Shared admin helpers: boot data, translations, and a thin REST client.
 *
 * The boot object is localized by AdminHandler when it enqueues the React
 * bundle. @wordpress/api-fetch sends the wp_rest nonce automatically, so no
 * nonce plumbing is needed here.
 */

import apiFetch from '@wordpress/api-fetch';
import { useState, useRef, useEffect } from '@wordpress/element';

export const boot = window.carticyAiCheckoutAdmin || {};

const strings = boot.i18n || {};

/**
 * Translate via the PHP-localized boot map, falling back to the English source.
 *
 * @param {string} text Source string.
 * @return {string} Localized string.
 */
export function t( text ) {
	return Object.prototype.hasOwnProperty.call( strings, text ) ? strings[ text ] : text;
}

function nsPath( path ) {
	return '/' + ( boot.restNamespace || 'carticy-ai-checkout-admin/v1' ) + path;
}

function normalize( error ) {
	if ( error && typeof error === 'object' ) {
		return {
			code: error.code || 'error',
			message: error.message || t( 'Something went wrong. Please try again.' ),
			data: error.data || {},
		};
	}
	return { code: 'error', message: t( 'Something went wrong. Please try again.' ), data: {} };
}

export function apiGet( path ) {
	return apiFetch( { path: nsPath( path ) } ).catch( ( error ) => {
		throw normalize( error );
	} );
}

export function apiPost( path, data ) {
	return apiFetch( { path: nsPath( path ), method: 'POST', data } ).catch( ( error ) => {
		throw normalize( error );
	} );
}

/**
 * Shared button-loading convention for async actions. `busy` holds the key of
 * the action currently running (or null); `run(key, fn)` returns a click
 * handler that marks that key busy for the lifetime of `fn`. State is left
 * untouched if the component unmounts mid-flight (guarded by a mounted ref).
 *
 * @return {[ (string|null), (key: string, fn: () => Promise<unknown>) => () => Promise<void> ]}
 */
export function useBusy() {
	const [ busy, setBusy ] = useState( null );
	const mounted = useRef( true );
	useEffect( () => () => {
		mounted.current = false;
	}, [] );

	const run = ( key, fn ) => async () => {
		setBusy( key );
		try {
			await fn();
		} catch ( e ) {
			// Callers own their error UI; we only manage the busy state.
		} finally {
			if ( mounted.current ) {
				setBusy( null );
			}
		}
	};

	return [ busy, run ];
}
