#!/usr/bin/env node
/**
 * Generate the translation template with WP-CLI:
 *
 *   node bin/make-pot.mjs <wp i18n make-pot arguments>
 *
 * WP-CLI's i18n command (before i18n-command 3.0) makes the plugin header's
 * reference relative by removing the plugin folder followed by a forward slash,
 * which never matches a Windows path, so a template made on Windows names the
 * main file by its absolute local path. On Windows the command therefore runs
 * under WSL, whose paths use forward slashes, and every reference in the
 * template stays relative to the plugin. Drop the WSL branch once the WP-CLI in
 * use bundles i18n-command 3.0 or later.
 */
import { spawnSync } from 'node:child_process';

const wp = [ 'wp', 'i18n', 'make-pot', ...process.argv.slice( 2 ) ];
const [ command, ...args ] = 'win32' === process.platform ? [ 'wsl', ...wp ] : wp;
const run = spawnSync( command, args, { stdio: 'inherit' } );

if ( run.error ) {
	process.stderr.write( `Could not run ${ command }: ${ run.error.message }\n` );
}
process.exit( run.status ?? 1 );
