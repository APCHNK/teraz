/* Lista szczepień — filtrowanie po stronie klienta (wyszukiwarka + zakładki) */
( function () {
	'use strict';

	function initWidget( root ) {
		if ( root.dataset.tzVacInit === '1' ) {
			return;
		}
		root.dataset.tzVacInit = '1';

		var input = root.querySelector( '.tz-vac__input:not(.tz-vac__input--country)' );
		var tabs = Array.prototype.slice.call( root.querySelectorAll( '.tz-vac__tab' ) );
		var rows = Array.prototype.slice.call( root.querySelectorAll( '.tz-vac__row' ) );
		var empty = root.querySelector( '.tz-vac__empty' );

		var countryInput = root.querySelector( '.tz-vac__input--country' );
		var suggestBox = root.querySelector( '.tz-vac__suggest' );
		var chips = Array.prototype.slice.call( root.querySelectorAll( '.tz-vac__chip' ) );
		var countriesEl = root.querySelector( '.tz-vac__countries' );
		var countries = [];
		if ( countriesEl ) {
			try {
				countries = JSON.parse( countriesEl.textContent ) || [];
			} catch ( e ) {}
		}

		var activeIds = null; // null = wszystkie produkty
		var activeFilter = null; // zakładka z data-filter (pole "filtry" produktu)

		function normalize( s ) {
			return ( s || '' ).toString().toLowerCase().trim();
		}

		// do porównań krajów: bez diakrytyków ("wegry" znajdzie "Węgry")
		function fold( s ) {
			return normalize( s )
				.replace( /ł/g, 'l' )
				.normalize( 'NFD' )
				.replace( /[\u0300-\u036f]/g, '' );
		}

		function rowFilters( row ) {
			var raw = row.getAttribute( 'data-filters' );
			return raw ? raw.split( '|' ) : [];
		}

		function rowMatchesCountry( row, term ) {
			if ( term === '' ) {
				return true;
			}
			var raw = row.getAttribute( 'data-kraje' );
			if ( ! raw ) {
				return false;
			}
			var list = raw.split( '|' );
			for ( var i = 0; i < list.length; i++ ) {
				if ( fold( list[ i ] ).indexOf( term ) !== -1 ) {
					return true;
				}
			}
			return false;
		}

		function apply() {
			var term = input ? normalize( input.value ) : '';
			var countryTerm = countryInput ? fold( countryInput.value ) : '';
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
				var show = inTab && inSearch && rowMatchesCountry( row, countryTerm );

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

		// ---- Wyszukiwarka krajów: podpowiedzi + szybki wybór ----
		function syncChips() {
			var val = countryInput ? fold( countryInput.value ) : '';
			chips.forEach( function ( chip ) {
				chip.classList.toggle( 'is-active', val !== '' && fold( chip.getAttribute( 'data-country' ) ) === val );
			} );
		}

		function hideSuggest() {
			if ( suggestBox ) {
				suggestBox.hidden = true;
				suggestBox.innerHTML = '';
			}
		}

		function showSuggest() {
			if ( ! suggestBox || ! countryInput ) {
				return;
			}
			var term = fold( countryInput.value );
			if ( term === '' ) {
				hideSuggest();
				return;
			}
			var matches = countries.filter( function ( c ) {
				return fold( c ).indexOf( term ) !== -1 && fold( c ) !== term;
			} ).slice( 0, 8 );
			if ( ! matches.length ) {
				hideSuggest();
				return;
			}
			suggestBox.innerHTML = '';
			matches.forEach( function ( c ) {
				var b = document.createElement( 'button' );
				b.type = 'button';
				b.className = 'tz-vac__suggest-item';
				b.textContent = c;
				// mousedown, żeby zdążyć przed blur na polu
				b.addEventListener( 'mousedown', function ( e ) {
					e.preventDefault();
					countryInput.value = c;
					hideSuggest();
					syncChips();
					apply();
				} );
				suggestBox.appendChild( b );
			} );
			suggestBox.hidden = false;
		}

		if ( countryInput ) {
			countryInput.addEventListener( 'input', function () {
				showSuggest();
				syncChips();
				apply();
			} );
			countryInput.addEventListener( 'focus', showSuggest );
			countryInput.addEventListener( 'blur', function () {
				setTimeout( hideSuggest, 150 );
			} );
			countryInput.addEventListener( 'keydown', function ( e ) {
				if ( e.key === 'Escape' ) {
					hideSuggest();
				}
			} );
		}

		chips.forEach( function ( chip ) {
			chip.addEventListener( 'click', function () {
				var c = chip.getAttribute( 'data-country' );
				if ( countryInput ) {
					// drugi klik w aktywny chip = wyczyść filtr
					countryInput.value = chip.classList.contains( 'is-active' ) ? '' : c;
				}
				hideSuggest();
				syncChips();
				apply();
			} );
		} );

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
