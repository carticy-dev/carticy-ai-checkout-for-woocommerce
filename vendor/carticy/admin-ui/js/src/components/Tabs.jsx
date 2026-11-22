/**
 * Tabs — views of one screen behind a row of underline tabs (.cv-tabs /
 * .cv-tabs__tab), announced as a WAI-ARIA tab list.
 *
 * Controlled: `activeId` is the tab shown and `onChange( id )` picks another.
 * One tab is in the Tab order (the active one, or the first that is enabled);
 * Left and Right (following the reading direction) move to the next or
 * previous enabled tab and show it, Home and End go to the ends. Each tab's
 * `panel` renders below the row as its tab panel (aria-controls / labelled
 * by its tab); inactive panels stay mounted but hidden, so their drafts stay.
 * Tabs without a `panel` are a tab row whose content the consumer renders.
 *
 * tabs: [ { id, label, panel?, count?, disabled?, severity? } ]
 * label names the tab list for screen readers. Moving between screens is
 * navigation, not tabs: use links with aria-current (AppHeader). Other props
 * go to the tab list.
 */
import { useId } from '@wordpress/element';
import { classes } from './classes';
import { useRovingFocus } from './roving';

export function Tabs( { tabs = [], activeId, onChange, label, className = '', ...rest } ) {
	const base = useId();
	const tabId = ( id ) => `${ base }-tab-${ id }`;
	const panelId = ( id ) => `${ base }-panel-${ id }`;
	const roving = useRovingFocus( {
		keys: tabs.map( ( tab ) => tab.id ),
		current: activeId,
		isEnabled: ( key ) => ! tabs.find( ( tab ) => tab.id === key )?.disabled,
		onSelect: ( key ) => {
			if ( key !== activeId && onChange ) {
				onChange( key );
			}
		},
	} );
	const panels = tabs.filter( ( tab ) => undefined !== tab.panel );

	return (
		<>
			<div className={ classes( 'cv-tabs', className ) } role="tablist" aria-label={ label } { ...rest }>
				{ tabs.map( ( tab ) => {
					const active = tab.id === activeId;
					return (
						<button
							key={ tab.id }
							ref={ roving.ref( tab.id ) }
							id={ tabId( tab.id ) }
							type="button"
							role="tab"
							aria-selected={ active }
							aria-controls={ undefined !== tab.panel ? panelId( tab.id ) : undefined }
							tabIndex={ roving.tabIndex( tab.id ) }
							className={ classes( 'cv-tabs__tab', active && 'is-active' ) }
							disabled={ tab.disabled }
							onClick={ () => roving.select( tab.id ) }
							onKeyDown={ ( event ) => roving.onKeyDown( event, tab.id ) }
						>
							{ tab.severity && <span className="cv-tabs__sev-dot" aria-hidden="true" /> }
							{ tab.label }
							{ undefined !== tab.count && <span className="cv-tabs__count">{ tab.count }</span> }
						</button>
					);
				} ) }
			</div>
			{ panels.map( ( tab ) => (
				<div
					key={ tab.id }
					id={ panelId( tab.id ) }
					className="cv-tabs__panel"
					role="tabpanel"
					aria-labelledby={ tabId( tab.id ) }
					tabIndex={ 0 }
					hidden={ tab.id !== activeId }
				>
					{ tab.panel }
				</div>
			) ) }
		</>
	);
}
