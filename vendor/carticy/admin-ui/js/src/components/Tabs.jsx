/**
 * Tabs — wraps .cv-tabs / .cv-tabs__tab (underline tabs, accent active bar).
 *
 * tabs: [ { id, label, count?, disabled?, severity? } ]
 */
export function Tabs( { tabs = [], activeId, onChange, className = '', ...rest } ) {
	return (
		<nav className={ [ 'cv-tabs', className ].filter( Boolean ).join( ' ' ) } role="tablist" { ...rest }>
			{ tabs.map( ( tab ) => (
				<button
					key={ tab.id }
					type="button"
					role="tab"
					aria-selected={ tab.id === activeId }
					className={ [ 'cv-tabs__tab', tab.id === activeId ? 'is-active' : '' ].filter( Boolean ).join( ' ' ) }
					disabled={ tab.disabled }
					onClick={ () => onChange && onChange( tab.id ) }
				>
					{ tab.severity && <span className="cv-tabs__sev-dot" aria-hidden="true" /> }
					{ tab.label }
					{ undefined !== tab.count && <span className="cv-tabs__count">{ tab.count }</span> }
				</button>
			) ) }
		</nav>
	);
}
