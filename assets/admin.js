( function () {
	'use strict';

	var wrap = document.querySelector( '.zenblch-admin' );
	if ( ! wrap ) {
		return;
	}

	var tabs = wrap.querySelectorAll( '.zenblch-tabs [data-tab]' );
	var panels = wrap.querySelectorAll( '.zenblch-panel' );

	function show( key, updateUrl ) {
		var found = false;
		tabs.forEach( function ( tab ) {
			var active = tab.getAttribute( 'data-tab' ) === key;
			found = found || active;
			tab.classList.toggle( 'nav-tab-active', active );
			tab.setAttribute( 'aria-selected', active ? 'true' : 'false' );
		} );
		if ( ! found ) {
			return;
		}
		panels.forEach( function ( panel ) {
			panel.hidden = panel.id !== 'zenblch-panel-' + key;
		} );
		if ( updateUrl && window.history && window.history.replaceState ) {
			var url = new URL( window.location.href );
			url.searchParams.set( 'tab', key );
			// A finished action's message belongs to the tab that was open then.
			url.searchParams.delete( 'zenblch_result' );
			url.searchParams.delete( 'zenblch_page' );
			window.history.replaceState( null, '', url.toString() );
		}
	}

	tabs.forEach( function ( tab ) {
		tab.addEventListener( 'click', function ( event ) {
			event.preventDefault();
			show( tab.getAttribute( 'data-tab' ), true );
		} );
	} );
} )();
