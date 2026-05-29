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

		function normalize( s ) {
			return ( s || '' ).toString().toLowerCase().trim();
		}

		function apply() {
			var term = input ? normalize( input.value ) : '';
			var visible = 0;

			rows.forEach( function ( row ) {
				var id = row.getAttribute( 'data-id' );
				var name = normalize( row.getAttribute( 'data-name' ) );

				var inTab = activeIds === null || activeIds.indexOf( id ) !== -1;
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

				var data = tab.getAttribute( 'data-products' );
				if ( ! data || data === 'all' ) {
					activeIds = null;
				} else {
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
