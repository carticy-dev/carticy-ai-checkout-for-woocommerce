/**
 * AppHeader — the top bar of a plugin screen: brand, section links, actions
 * that belong to the whole screen and the plugin's utility links (see
 * HeaderUtilities).
 *
 * items: [ { id, label, href } ] — one link per screen section; the plugin
 * owns the routes (a hash, a query argument or another admin page).
 * current: the id of the section on screen (marked aria-current="page").
 * actions: controls for the whole screen ("Test a search"), shown before the
 * utility links; on narrow screens they move with the links to a row under
 * the brand and wrap there. Without actions the links stay beside the brand
 * while both fit on one line, and take that row when the title is longer.
 *
 * The title is the screen's level-one heading; give each section a level-two
 * heading and cards level-three headings.
 *
 * On narrow screens the section links form one row that scrolls sideways;
 * the current link is kept in view.
 */
import { useEffect, useRef } from '@wordpress/element';
import { HeaderUtilities } from './HeaderUtilities';
import { classes } from './classes';

export function AppHeader( { title, icon = null, items = [], current, navLabel, actions = null, links = [], className = '', ...rest } ) {
	const nav = useRef( null );
	useEffect( () => {
		const list = nav.current;
		const link = list?.querySelector( '[aria-current="page"]' );
		if ( link && list.scrollWidth > list.clientWidth ) {
			link.scrollIntoView( { block: 'nearest', inline: 'nearest' } );
		}
	}, [ current ] );

	return (
		<header className={ classes( 'cv-app-header', actions && 'cv-app-header--actions', className ) } { ...rest }>
			<div className="cv-app-header__brand">
				{ icon && <span className="cv-app-header__mark" aria-hidden="true">{ icon }</span> }
				<h1 className="cv-app-header__title">{ title }</h1>
			</div>
			{ items.length > 0 && (
				<nav className="cv-app-nav" aria-label={ navLabel || title } ref={ nav }>
					{ items.map( ( item ) => (
						<a
							key={ item.id }
							className="cv-app-nav__link"
							href={ item.href }
							aria-current={ item.id === current ? 'page' : undefined }
						>
							{ item.label }
						</a>
					) ) }
				</nav>
			) }
			<div className="cv-app-header__utilities">
				{ actions && <div className="cv-app-header__actions">{ actions }</div> }
				<HeaderUtilities links={ links } />
			</div>
		</header>
	);
}
