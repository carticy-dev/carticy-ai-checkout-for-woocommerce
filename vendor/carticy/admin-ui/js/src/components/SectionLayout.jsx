/**
 * SectionLayout — a long screen split into sections, with a side navigation
 * on wide screens and a drawer on narrow ones. The navigation marks the
 * section being read; choosing one focuses its heading and scrolls to it
 * without changing the plugin's route or unmounting anything, so drafts stay.
 *
 * Two ways to use it:
 * - sections: [ { id, title, content } ] — the layout renders each section
 *   with its heading (level `headingLevel`, h2-h6, default 2; an h2 takes the
 *   section-title size, deeper levels the card-title size). Use stable ids and
 *   pass visible sections only, in display order. An optional `icon` (inline
 *   SVG) is drawn in a small tile before the heading text; the navigation
 *   shows the title only.
 * - items: [ { id, label, element } ] with children — the plugin renders the
 *   content and passes the heading elements to navigate to (for example
 *   headings of panels that load later). Each element must be in the page.
 *
 * label names the navigation ("On this page"); closeLabel names the drawer's
 * close button. On narrow screens the navigation is a Drawer that opens from
 * the start edge. With one section or fewer, no navigation is shown. Other
 * props and the ref go to the root.
 */
import { forwardRef, useEffect, useRef, useState } from '@wordpress/element';
import { classes } from './classes';
import { Drawer } from './Drawer';
import { focusElement } from './focus';
import { headingTag } from './heading';
import { IconTile } from './icons';

/** How long a chosen heading is kept in view while the sections around it finish loading. */
const FOLLOW_MS = 5000;
const READER_INPUT = [ 'wheel', 'touchmove', 'keydown', 'pointerdown' ];

/**
 * Sections above a chosen heading can still be filling in when the reader
 * jumps to it (their data arrives after the navigation is shown), which would
 * leave the page short of the heading. Until the reader scrolls, types or
 * clicks, or a few seconds pass, every change in the sections' size realigns
 * the heading. Returns the function that stops following.
 */
function keepInView( heading, container ) {
	if ( ! container || typeof ResizeObserver === 'undefined' ) {
		return null;
	}
	let frame = 0;
	const align = () => {
		frame = 0;
		if ( heading.isConnected ) {
			heading.scrollIntoView( { block: 'start' } );
		}
	};
	const observer = new ResizeObserver( () => {
		if ( ! frame ) {
			frame = window.requestAnimationFrame( align );
		}
	} );
	let timer = 0;
	const stop = () => {
		observer.disconnect();
		window.cancelAnimationFrame( frame );
		window.clearTimeout( timer );
		READER_INPUT.forEach( ( type ) => window.removeEventListener( type, stop, true ) );
	};
	timer = window.setTimeout( stop, FOLLOW_MS );
	READER_INPUT.forEach( ( type ) => window.addEventListener( type, stop, { capture: true, passive: true } ) );
	observer.observe( container );
	return stop;
}

/** The section whose heading has passed the reading line is current; at the end of the page, the chosen or last one. */
function useCurrentSection( ids, lookup ) {
	const [ current, setCurrent ] = useState( ids[ 0 ] );
	const chosen = useRef( null );
	const key = ids.join( '\n' );

	useEffect( () => {
		let frame = 0;
		const measure = () => {
			frame = 0;
			const visible = ids.filter( ( id ) => lookup.current( id )?.getClientRects().length );
			if ( ! visible.length ) {
				return;
			}
			const line = Math.min( 160, window.innerHeight * 0.3 );
			let next = visible[ 0 ];
			for ( const id of visible ) {
				if ( lookup.current( id ).getBoundingClientRect().top <= line ) {
					next = id;
				}
			}
			const bottom = window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 2;
			if ( bottom ) {
				next = visible.includes( chosen.current ) ? chosen.current : visible[ visible.length - 1 ];
			}
			setCurrent( next );
		};
		const schedule = () => {
			if ( ! frame ) {
				frame = window.requestAnimationFrame( measure );
			}
		};
		// A choice holds at the end of the page until the reader scrolls again by hand.
		const release = () => {
			chosen.current = null;
		};
		measure();
		window.addEventListener( 'scroll', schedule, { passive: true } );
		window.addEventListener( 'resize', schedule );
		window.addEventListener( 'wheel', release, { passive: true } );
		window.addEventListener( 'touchmove', release, { passive: true } );
		return () => {
			window.cancelAnimationFrame( frame );
			window.removeEventListener( 'scroll', schedule );
			window.removeEventListener( 'resize', schedule );
			window.removeEventListener( 'wheel', release );
			window.removeEventListener( 'touchmove', release );
		};
	}, [ key ] ); // ids are compared by value through key; lookup is a ref.

	const choose = ( id ) => {
		chosen.current = id;
		setCurrent( id );
	};
	return [ current, choose ];
}

function SectionNav( { label, closeLabel, items, current, onSelect } ) {
	const [ open, setOpen ] = useState( false );
	const select = ( id ) => {
		setOpen( false );
		onSelect( id );
	};
	const list = (
		<ul className="cv-sidenav__list">
			{ items.map( ( item ) => (
				<li key={ item.id }>
					<button
						type="button"
						className={ classes( 'cv-sidenav__item', item.id === current && 'is-active' ) }
						aria-current={ item.id === current ? 'location' : undefined }
						onClick={ () => select( item.id ) }
					>
						{ item.label }
					</button>
				</li>
			) ) }
		</ul>
	);

	return (
		<div className="cv-section-layout__rail">
			<nav className="cv-sidenav" aria-label={ label }>
				<p className="cv-sidenav__label" aria-hidden="true">{ label }</p>
				{ list }
			</nav>
			<button type="button" className="cv-btn cv-btn--secondary cv-section-layout__open" aria-haspopup="dialog" onClick={ () => setOpen( true ) }>
				<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4 6h16M4 12h16M4 18h10" /></svg>
				{ label }
			</button>
			<Drawer open={ open } onClose={ () => setOpen( false ) } title={ label } closeLabel={ closeLabel } side="start" size="xs">
				<nav aria-label={ label }>{ list }</nav>
			</Drawer>
		</div>
	);
}

export const SectionLayout = forwardRef( function SectionLayout( { label, closeLabel, sections, items, headingLevel = 2, className = '', children, ...rest }, ref ) {
	const headings = useRef( new Map() );
	const targets = sections ? sections.map( ( { id, title } ) => ( { id, label: title } ) ) : items || [];
	const elementFor = ( id ) => ( sections ? headings.current.get( id ) : targets.find( ( item ) => item.id === id )?.element );
	// The scroll handler outlives this render; it reads the latest elements through a ref.
	const lookup = useRef( elementFor );
	lookup.current = elementFor;
	const [ current, choose ] = useCurrentSection( targets.map( ( item ) => item.id ), lookup );
	const Heading = headingTag( headingLevel, 2 );
	const navigation = targets.length > 1;

	const body = useRef( null );
	const follow = useRef( null );
	useEffect( () => () => follow.current?.(), [] );

	const visit = ( id ) => {
		const heading = elementFor( id );
		if ( ! heading || ! heading.isConnected ) {
			return;
		}
		choose( id );
		if ( ! heading.hasAttribute( 'tabindex' ) ) {
			heading.setAttribute( 'tabindex', '-1' );
		}
		focusElement( heading, { preventScroll: true } );
		heading.scrollIntoView( { block: 'start' } );
		follow.current?.();
		follow.current = keepInView( heading, body.current );
	};

	return (
		<div ref={ ref } className={ classes( 'cv-section-layout', navigation && 'cv-section-layout--nav', className ) } { ...rest }>
			{ navigation && <SectionNav label={ label } closeLabel={ closeLabel } items={ targets } current={ current } onSelect={ visit } /> }
			<div className="cv-section-layout__body" ref={ body }>
				{ sections
					? sections.map( ( { id, title, icon, content } ) => (
						<section key={ id } className="cv-section-layout__section" aria-labelledby={ `cv-section-${ id }` }>
							<Heading
								id={ `cv-section-${ id }` }
								className={ classes( 'h2' === Heading ? 'cv-h2' : 'cv-h3', 'cv-section-layout__heading', icon && 'cv-section-layout__heading--icon' ) }
								tabIndex={ -1 }
								ref={ ( node ) => ( node ? headings.current.set( id, node ) : headings.current.delete( id ) ) }
							>
								{ icon && <IconTile>{ icon }</IconTile> }
								{ title }
							</Heading>
							{ content }
						</section>
					) )
					: children }
			</div>
		</div>
	);
} );
