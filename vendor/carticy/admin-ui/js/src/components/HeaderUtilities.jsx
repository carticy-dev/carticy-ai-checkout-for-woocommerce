/**
 * HeaderUtilities — the plugin's secondary destinations (Contact us, Account,
 * documentation…) at the end of its header, as labelled links with an icon.
 *
 * links: [ { href, label, icon?: 'contact'|'account'|'help'|'docs' } ]
 * Links without an href are left out, so a destination the current user cannot
 * open (for example an account screen without the right capability) is omitted
 * by passing an empty href.
 */
import { classes } from './classes';

const ICONS = {
	contact: <><rect x="3" y="5" width="18" height="14" rx="2" /><path d="m3 7 9 6 9-6" /></>,
	account: <><circle cx="12" cy="8" r="4" /><path d="M4 21v-1a8 8 0 0 1 16 0v1" /></>,
	help: <><circle cx="12" cy="12" r="9" /><path d="M9.5 9a2.5 2.5 0 1 1 3.5 2.3c-.6.3-1 .9-1 1.6v.6" /><path d="M12 17h.01" /></>,
	docs: <><path d="M4 5a2 2 0 0 1 2-2h12v16H6a2 2 0 0 0-2 2z" /><path d="M4 21a2 2 0 0 1 2-2h12v2" /></>,
};

export function HeaderUtilities( { links = [], className = '', ...rest } ) {
	const shown = links.filter( ( link ) => link && link.href );
	if ( ! shown.length ) {
		return null;
	}

	return (
		<div className={ classes( 'cv-header-utilities', className ) } { ...rest }>
			{ shown.map( ( { href, label, icon } ) => (
				<a key={ href } className="cv-utility-link" href={ href }>
					{ ICONS[ icon ] && (
						<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">{ ICONS[ icon ] }</svg>
					) }
					{ label }
				</a>
			) ) }
		</div>
	);
}
