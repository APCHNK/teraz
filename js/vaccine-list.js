/* Lista szczepień — filtrowanie po stronie klienta (wyszukiwarka + zakładki) */
( function () {
	'use strict';

	function initWidget( root ) {
		if ( root.dataset.tzVacInit === '1' ) {
			return;
		}
		root.dataset.tzVacInit = '1';

		var input = root.querySelector( '.tz-vac__input' );
		var tabs = Array.prototype.slice.call( root.querySelectorAll( '.tz-vac__tab' ) );
		var rows = Array.prototype.slice.call( root.querySelectorAll( '.tz-vac__row' ) );
		var empty = root.querySelector( '.tz-vac__empty' );

		var activeIds = null; // null = wszystkie produkty
		var activeFilter = null; // zakładka z data-filter (pole "filtry" produktu)

		function normalize( s ) {
			return ( s || '' ).toString().toLowerCase().trim();
		}

		function rowFilters( row ) {
			var raw = row.getAttribute( 'data-filters' );
			return raw ? raw.split( '|' ) : [];
		}

		function apply() {
			var term = input ? normalize( input.value ) : '';
			var visible = 0;

			rows.forEach( function ( row ) {
				var id = row.getAttribute( 'data-id' );
				var name = normalize( row.getAttribute( 'data-name' ) );

				var inTab;
				if ( activeFilter !== null ) {
					inTab = rowFilters( row ).indexOf( activeFilter ) !== -1;
				} else {
					inTab = activeIds === null || activeIds.indexOf( id ) !== -1;
				}
				var inSearch = term === '' || name.indexOf( term ) !== -1;
				var show = inTab && inSearch;

				row.hidden = ! show;
				if ( show ) {
					visible++;
				}
			} );

			if ( empty ) {
				empty.hidden = visible !== 0;
			}
		}

		tabs.forEach( function ( tab ) {
			tab.addEventListener( 'click', function () {
				tabs.forEach( function ( t ) {
					t.classList.remove( 'is-active' );
				} );
				tab.classList.add( 'is-active' );

				var filter = tab.getAttribute( 'data-filter' );
				var data = tab.getAttribute( 'data-products' );
				if ( filter ) {
					activeFilter = filter;
					activeIds = null;
				} else if ( ! data || data === 'all' ) {
					activeFilter = null;
					activeIds = null;
				} else {
					activeFilter = null;
					activeIds = data.split( ',' ).map( function ( s ) {
						return s.trim();
					} ).filter( Boolean );
				}
				apply();
			} );
		} );

		if ( input ) {
			input.addEventListener( 'input', apply );
		}

		apply();
	}

	function initAll( context ) {
		var scope = context && context.querySelectorAll ? context : document;
		Array.prototype.slice.call( scope.querySelectorAll( '.tz-vac' ) ).forEach( initWidget );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			initAll( document );
		} );
	} else {
		initAll( document );
	}

	// Podgląd w edytorze Elementora
	if ( window.jQuery ) {
		window.jQuery( window ).on( 'elementor/frontend/init', function () {
			if ( window.elementorFrontend && window.elementorFrontend.hooks ) {
				window.elementorFrontend.hooks.addAction(
					'frontend/element_ready/tz_vaccine_list.default',
					function ( $scope ) {
						initWidget( $scope[ 0 ].querySelector( '.tz-vac' ) || $scope[ 0 ] );
					}
				);
			}
		} );
	}
} )();
