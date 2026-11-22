#!/usr/bin/env node
/**
 * Plain-language check for a plugin's user-facing text.
 *
 * Reads the plugin's translation template (every PHP and JavaScript string a
 * store owner can see, REST error messages included) and fails when a string
 * uses an engineering term. Run it from the plugin root:
 *
 *   node vendor/carticy/admin-ui/bin/check-copy.mjs [copy-check.json]
 *
 * copy-check.json:
 *   {
 *     "pot": "languages/my-plugin.pot",
 *     "terms": [ "generation" ],          // added to the shared list
 *     "allow": [ "Privacy policy" ]       // exact strings that may keep a listed term
 *   }
 */
import { readFileSync } from 'node:fs';

const SHARED_TERMS = [
	'bounded', 'cursor', 'durable', 'endpoint', 'enqueue', 'handoff', 'idempotent', 'mutex',
	'nonce', 'overflow', 'payload', 'posting', 'postings', 'projection', 'projections',
	'reconcile', 'reconciled', 'reconciliation', 'runner', 'schema', 'serialized', 'transient',
	'worker', 'workers',
];

const configPath = process.argv[ 2 ] || 'copy-check.json';
const config = JSON.parse( readFileSync( configPath, 'utf8' ) );
const terms = [ ...new Set( [ ...SHARED_TERMS, ...( config.terms || [] ) ].map( ( term ) => term.toLowerCase() ) ) ];
const allowed = new Set( config.allow || [] );
const pattern = new RegExp( `\\b(${ terms.join( '|' ) })\\b`, 'i' );

/** Collect each entry's source references and its singular and plural text. */
function entries( pot ) {
	const found = [];
	let references = [];
	let current = null;
	let field = null;
	const unquote = ( value ) => JSON.parse( value );
	for ( const line of pot.split( /\r?\n/ ) ) {
		if ( line.startsWith( '#: ' ) ) {
			references.push( ...line.slice( 3 ).split( ' ' ) );
		} else if ( /^msgid(_plural)? "/.test( line ) ) {
			if ( line.startsWith( 'msgid "' ) ) {
				current = { references, texts: [] };
				found.push( current );
				references = [];
			}
			field = current.texts.push( unquote( line.slice( line.indexOf( '"' ) ) ) ) - 1;
		} else if ( line.startsWith( '"' ) && current && null !== field ) {
			current.texts[ field ] += unquote( line );
		} else if ( line.startsWith( 'msgstr' ) ) {
			field = null;
		}
	}
	return found.filter( ( entry ) => entry.texts[ 0 ] );
}

const failures = [];
for ( const { references, texts } of entries( readFileSync( config.pot, 'utf8' ) ) ) {
	for ( const text of texts ) {
		const match = text.match( pattern );
		if ( match && ! allowed.has( text ) ) {
			failures.push( `"${ match[ 1 ] }" in "${ text }"\n    ${ references.join( ' ' ) || '(no reference)' }` );
		}
	}
}

if ( failures.length ) {
	console.error( `Plain-language check: ${ failures.length } string(s) use engineering terms. Say it the way a store owner would.\n` );
	console.error( failures.map( ( failure ) => `  ${ failure }` ).join( '\n' ) );
	process.exit( 1 );
}
console.log( `Plain-language check passed (${ terms.length } terms).` );
