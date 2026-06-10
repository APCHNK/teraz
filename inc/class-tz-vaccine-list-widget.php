<?php
/**
 * Elementor widget: Lista szczepień (TZ Vaccine List)
 *
 * Dynamiczna tabela produktów z kategorii (domyślnie "szczepienia"):
 *  - kolumny: Preparat / Dawki / Cena za dawkę / Akcja (Szczegóły + Rezerwuj)
 *  - wyszukiwarka po nazwie (po stronie klienta)
 *  - zakładki-filtry tworzone ręcznie (repeater): nazwa + ikona + wybrane produkty
 *  - przycisk "Rezerwuj" z konfigurowalnym celem (strona produktu / koszyk / własny link)
 *
 * Plik motywu — zgodnie z zasadą: zmiany tylko w motywie, nie w pluginach.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TZ_Vaccine_List_Widget extends \Elementor\Widget_Base {

	public function get_name() {
		return 'tz_vaccine_list';
	}

	public function get_title() {
		return __( 'Lista szczepień', 'teraz' );
	}

	public function get_icon() {
		return 'eicon-table';
	}

	public function get_categories() {
		return [ 'general' ];
	}

	public function get_keywords() {
		return [ 'szczepienia', 'lista', 'tabela', 'preparat', 'vaccine' ];
	}

	public function get_style_depends() {
		return [ 'tz-vaccine-list' ];
	}

	public function get_script_depends() {
		return [ 'tz-vaccine-list' ];
	}

	/**
	 * Lista produktów wybranej kategorii jako opcje do kontrolek Select2.
	 *
	 * @param string $category_slug
	 * @return array id => title
	 */
	private function get_product_options( $category_slug = 'szczepienia' ) {
		// Lista potrzebna jest tylko w panelu edytora (Select2). Na froncie
		// render czyta zapisane ID, więc nie wykonujemy tu zbędnego zapytania.
		if ( ! is_admin() ) {
			return [];
		}
		if ( ! function_exists( 'wc_get_product' ) ) {
			return [];
		}

		$args = [
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'fields'         => 'ids',
			'no_found_rows'  => true,
			// tylko realne preparaty (mają liczbę dawek), bez stron-poradników krajów
			'meta_query'     => [
				[
					'key'     => 'liczba_dawek',
					'value'   => '',
					'compare' => '!=',
				],
			],
		];

		if ( $category_slug ) {
			$args['tax_query'] = [
				[
					'taxonomy' => 'product_cat',
					'field'    => 'slug',
					'terms'    => $category_slug,
				],
			];
		}

		$ids     = get_posts( $args );
		$options = [];
		foreach ( $ids as $id ) {
			$options[ $id ] = get_the_title( $id );
		}

		return $options;
	}

	/**
	 * Wartości pola "filtry" (z importu oferty) jako opcje SELECT.
	 * Tylko w panelu edytora.
	 *
	 * @param string $category_slug
	 * @return array wartość => wartość
	 */
	private function get_filter_options( $category_slug = 'szczepienia' ) {
		if ( ! is_admin() ) {
			return [];
		}
		$ids = get_posts( [
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'meta_query'     => [ [ 'key' => 'filtry', 'compare' => 'EXISTS' ] ],
			'tax_query'      => $category_slug ? [
				[
					'taxonomy' => 'product_cat',
					'field'    => 'slug',
					'terms'    => $category_slug,
				],
			] : [],
		] );
		$options = [];
		foreach ( $ids as $id ) {
			foreach ( (array) get_post_meta( $id, 'filtry', true ) as $f ) {
				$f = trim( (string) $f );
				if ( '' !== $f ) {
					$options[ $f ] = $f;
				}
			}
		}
		ksort( $options );
		return $options;
	}

	/**
	 * Lokalizacje / usługi Booknetic jako opcje SELECT. Tylko w panelu edytora.
	 *
	 * @param string $what 'locations' lub 'services'
	 * @return array id => nazwa
	 */
	private function get_booknetic_options( $what ) {
		$options = [ '' => __( '— bez preselect —', 'teraz' ) ];
		if ( ! is_admin() ) {
			return $options;
		}
		global $wpdb;
		$table = $wpdb->prefix . ( 'services' === $what ? 'bkntc_services' : 'bkntc_locations' );
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return $options;
		}
		$rows = $wpdb->get_results( "SELECT id, name FROM {$table} WHERE is_active = 1 ORDER BY name" );
		foreach ( (array) $rows as $row ) {
			$options[ (string) $row->id ] = $row->name . ' (#' . $row->id . ')';
		}
		return $options;
	}

	/**
	 * Lista stron (do wyboru strony rezerwacji). Tylko w panelu edytora.
	 *
	 * @return array id => title
	 */
	private function get_page_options() {
		if ( ! is_admin() ) {
			return [];
		}

		$pages   = get_posts(
			[
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'fields'         => 'ids',
				'no_found_rows'  => true,
			]
		);
		$options = [ '' => __( '— wybierz stronę —', 'teraz' ) ];
		foreach ( $pages as $id ) {
			$options[ $id ] = get_the_title( $id );
		}

		return $options;
	}

	protected function register_controls() {

		// ---- Sekcja: Ustawienia ogólne ----
		$this->start_controls_section(
			'section_general',
			[
				'label' => __( 'Ustawienia ogólne', 'teraz' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'category_slug',
			[
				'label'       => __( 'Slug kategorii produktów', 'teraz' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => 'szczepienia',
				'description' => __( 'Z tej kategorii pobierane są wszystkie wiersze tabeli.', 'teraz' ),
			]
		);

		$this->add_control(
			'only_with_doses',
			[
				'label'        => __( 'Tylko produkty z liczbą dawek', 'teraz' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'description'  => __( 'Pomija strony-poradniki (np. kraje) bez pola "liczba_dawek".', 'teraz' ),
			]
		);

		$this->add_control(
			'strip_prefix',
			[
				'label'       => __( 'Usuń przedrostek z nazwy', 'teraz' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => 'Szczepienie',
				'description' => __( 'Usuwa ten wyraz z początku każdej nazwy (np. "Szczepienie"). Zostaw puste, aby pokazać pełne nazwy.', 'teraz' ),
			]
		);

		$this->add_control(
			'search_placeholder',
			[
				'label'   => __( 'Tekst w wyszukiwarce', 'teraz' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'Szukaj szczepienia (np. żółta gorączka, HPV…)', 'teraz' ),
			]
		);

		$this->add_control(
			'filter_city',
			[
				'label'       => __( 'Miasto', 'teraz' ),
				'type'        => \Elementor\Controls_Manager::SELECT,
				'default'     => '',
				'options'     => $this->get_city_options( 'szczepienia' ),
				'description' => __( 'Pokaż tylko produkty z wybranego miasta (podkategorii kategorii głównej). „Wszystkie miasta” = bez filtra.', 'teraz' ),
			]
		);

		$this->add_control(
			'all_tab_label',
			[
				'label'   => __( 'Nazwa zakładki "wszystkie"', 'teraz' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'Wszystkie', 'teraz' ),
			]
		);

		$this->add_control(
			'all_tab_icon',
			[
				'label'   => __( 'Ikona zakładki "wszystkie"', 'teraz' ),
				'type'    => \Elementor\Controls_Manager::ICONS,
				'default' => [
					'value'   => 'fas fa-pen',
					'library' => 'fa-solid',
				],
			]
		);

		$this->add_control(
			'col_preparat',
			[
				'label'   => __( 'Nagłówek: Preparat', 'teraz' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'PREPARAT', 'teraz' ),
			]
		);

		$this->add_control(
			'col_dawki',
			[
				'label'   => __( 'Nagłówek: Dawki', 'teraz' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'DAWKI', 'teraz' ),
			]
		);

		$this->add_control(
			'col_cena',
			[
				'label'   => __( 'Nagłówek: Cena za dawkę', 'teraz' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'CENA ZA DAWKĘ', 'teraz' ),
			]
		);

		$this->add_control(
			'col_akcja',
			[
				'label'   => __( 'Nagłówek: Akcja', 'teraz' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'AKCJA', 'teraz' ),
			]
		);

		$this->add_control(
			'details_label',
			[
				'label'   => __( 'Tekst przycisku "Szczegóły"', 'teraz' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'Szczegóły', 'teraz' ),
			]
		);

		$this->add_control(
			'empty_text',
			[
				'label'   => __( 'Tekst gdy brak wyników', 'teraz' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'Brak wyników', 'teraz' ),
			]
		);

		$this->end_controls_section();

		// ---- Sekcja: Wyszukiwarka krajów ----
		$this->start_controls_section(
			'section_country',
			[
				'label' => __( 'Wyszukiwarka krajów', 'teraz' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'country_search',
			[
				'label'       => __( 'Szukaj po kraju', 'teraz' ),
				'type'        => \Elementor\Controls_Manager::SWITCHER,
				'default'     => '',
				'description' => __( 'Drugie pole wyszukiwania: filtruje szczepienia po kraju podróży (pole „kraje" produktu) z podpowiedziami podczas pisania.', 'teraz' ),
			]
		);

		$this->add_control(
			'country_placeholder',
			[
				'label'     => __( 'Tekst w polu kraju', 'teraz' ),
				'type'      => \Elementor\Controls_Manager::TEXT,
				'default'   => __( 'Szukaj po nazwie kraju…', 'teraz' ),
				'condition' => [ 'country_search' => 'yes' ],
			]
		);

		$this->add_control(
			'country_presets',
			[
				'label'       => __( 'Szybki wybór (po przecinku)', 'teraz' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => 'Tajlandia, Kenia, Wietnam',
				'label_block' => true,
				'description' => __( 'Przyciski obok wyszukiwarki — kliknięcie od razu filtruje. Zostaw puste, aby ukryć.', 'teraz' ),
				'condition'   => [ 'country_search' => 'yes' ],
			]
		);

		$this->end_controls_section();

		// ---- Sekcja: Zakładki (filtry) ----
		$this->start_controls_section(
			'section_tabs',
			[
				'label' => __( 'Zakładki (filtry)', 'teraz' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'tabs_source',
			[
				'label'       => __( 'Źródło zakładek', 'teraz' ),
				'type'        => \Elementor\Controls_Manager::SELECT,
				'default'     => 'manual',
				'options'     => [
					'manual'  => __( 'Ręcznie (wybór produktów)', 'teraz' ),
					'filters' => __( 'Automatycznie (pole „filtry” produktu)', 'teraz' ),
				],
				'description' => __( 'Pole „filtry” pochodzi z importu oferty (Podróżne / HPV / Dla dzieci / Sezonowe) — produkty same trafiają do zakładek.', 'teraz' ),
			]
		);

		// Zakładki automatyczne: filtr + opcjonalna nazwa i ikona.
		$filter_repeater = new \Elementor\Repeater();

		$filter_repeater->add_control(
			'tab_filter',
			[
				'label'       => __( 'Filtr', 'teraz' ),
				'type'        => \Elementor\Controls_Manager::SELECT,
				'options'     => $this->get_filter_options( 'szczepienia' ),
				'label_block' => true,
			]
		);

		$filter_repeater->add_control(
			'tab_label',
			[
				'label'       => __( 'Nazwa zakładki (opcjonalnie)', 'teraz' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'placeholder' => __( 'Domyślnie: nazwa filtra', 'teraz' ),
				'label_block' => true,
			]
		);

		$filter_repeater->add_control(
			'tab_icon',
			[
				'label' => __( 'Ikona', 'teraz' ),
				'type'  => \Elementor\Controls_Manager::ICONS,
			]
		);

		$this->add_control(
			'filter_tabs',
			[
				'label'       => __( 'Zakładki z filtrów', 'teraz' ),
				'type'        => \Elementor\Controls_Manager::REPEATER,
				'fields'      => $filter_repeater->get_controls(),
				'default'     => [],
				'title_field' => '{{{ tab_label || tab_filter }}}',
				'description' => __( 'Zostaw puste, aby pokazać zakładkę dla każdego filtra występującego w produktach.', 'teraz' ),
				'condition'   => [ 'tabs_source' => 'filters' ],
			]
		);

		$repeater = new \Elementor\Repeater();

		$repeater->add_control(
			'tab_label',
			[
				'label'       => __( 'Nazwa zakładki', 'teraz' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => __( 'Nowa zakładka', 'teraz' ),
				'label_block' => true,
			]
		);

		$repeater->add_control(
			'tab_icon',
			[
				'label' => __( 'Ikona', 'teraz' ),
				'type'  => \Elementor\Controls_Manager::ICONS,
			]
		);

		$repeater->add_control(
			'tab_products',
			[
				'label'       => __( 'Produkty w tej zakładce', 'teraz' ),
				'type'        => \Elementor\Controls_Manager::SELECT2,
				'multiple'    => true,
				'label_block' => true,
				'options'     => $this->get_product_options( 'szczepienia' ),
				'description' => __( 'Wybierz produkty, które mają być widoczne po kliknięciu tej zakładki.', 'teraz' ),
			]
		);

		$this->add_control(
			'tabs',
			[
				'label'       => __( 'Zakładki', 'teraz' ),
				'type'        => \Elementor\Controls_Manager::REPEATER,
				'fields'      => $repeater->get_controls(),
				'default'     => [],
				'title_field' => '{{{ tab_label }}}',
				'condition'   => [ 'tabs_source' => 'manual' ],
			]
		);

		$this->end_controls_section();

		// ---- Sekcja: Przycisk "Rezerwuj" ----
		$this->start_controls_section(
			'section_reserve',
			[
				'label' => __( 'Przycisk "Rezerwuj"', 'teraz' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'reserve_label',
			[
				'label'   => __( 'Tekst przycisku', 'teraz' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'Rezerwuj', 'teraz' ),
			]
		);

		$this->add_control(
			'reserve_action',
			[
				'label'   => __( 'Akcja po kliknięciu', 'teraz' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'product',
				'options' => [
					'product'   => __( 'Strona produktu', 'teraz' ),
					'cart'      => __( 'Dodaj do koszyka', 'teraz' ),
					'page'      => __( 'Strona rezerwacji', 'teraz' ),
					'booknetic' => __( 'Booknetic (preselect lokalizacji/usługi)', 'teraz' ),
					'custom'    => __( 'Własny link', 'teraz' ),
				],
			]
		);

		$this->add_control(
			'reserve_page',
			[
				'label'       => __( 'Strona rezerwacji', 'teraz' ),
				'type'        => \Elementor\Controls_Manager::SELECT2,
				'label_block' => true,
				'options'     => $this->get_page_options(),
				'condition'   => [ 'reserve_action' => [ 'page', 'booknetic' ] ],
			]
		);

		$this->add_control(
			'booknetic_location',
			[
				'label'       => __( 'Lokalizacja (Booknetic)', 'teraz' ),
				'type'        => \Elementor\Controls_Manager::SELECT,
				'default'     => '',
				'options'     => $this->get_booknetic_options( 'locations' ),
				'description' => __( 'Przekazywana jako ?location= - Booknetic pomija ten krok.', 'teraz' ),
				'condition'   => [ 'reserve_action' => 'booknetic' ],
			]
		);

		$this->add_control(
			'booknetic_service',
			[
				'label'       => __( 'Usługa (Booknetic)', 'teraz' ),
				'type'        => \Elementor\Controls_Manager::SELECT,
				'default'     => '',
				'options'     => $this->get_booknetic_options( 'services' ),
				'description' => __( 'Przekazywana jako ?service= - Booknetic pomija ten krok.', 'teraz' ),
				'condition'   => [ 'reserve_action' => 'booknetic' ],
			]
		);

		$this->add_control(
			'reserve_custom_url',
			[
				'label'       => __( 'Własny link', 'teraz' ),
				'type'        => \Elementor\Controls_Manager::URL,
				'placeholder' => 'https://…',
				'options'     => [ 'url', 'is_external', 'nofollow' ],
				'condition'   => [ 'reserve_action' => 'custom' ],
			]
		);

		$this->add_control(
			'reserve_new_tab',
			[
				'label'        => __( 'Otwórz w nowej karcie', 'teraz' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'default'      => '',
				'condition'    => [ 'reserve_action!' => 'cart' ],
			]
		);

		$this->end_controls_section();
	}

	/**
	 * Wartość kolumny "Dawki" — z meta liczba_dawek.
	 */
	/**
	 * Usuwa zadany przedrostek (np. "Szczepienie") z początku nazwy.
	 */
	private function strip_prefix( $title, $prefix ) {
		$prefix = trim( (string) $prefix );
		if ( '' === $prefix ) {
			return $title;
		}
		// dopasowanie bez względu na wielkość liter, na początku ciągu
		$pattern = '/^' . preg_quote( $prefix, '/' ) . '\s*/iu';
		$result  = preg_replace( $pattern, '', $title, 1 );
		return ( '' !== trim( (string) $result ) ) ? $result : $title;
	}

	private function format_doses( $product_id ) {
		$raw = trim( (string) get_post_meta( $product_id, 'liczba_dawek', true ) );
		if ( '' === $raw ) {
			return '';
		}
		// Czysta liczba -> "N×"; wartości opisowe (np. "3 lub 4") zostawiamy bez zmian.
		if ( is_numeric( $raw ) ) {
			return ( (int) $raw ) . '×';
		}
		return $raw;
	}

	/**
	 * Wartość kolumny "Cena za dawkę".
	 */
	private function format_price( $product ) {
		$id    = $product->get_id();
		$price = get_post_meta( $id, 'cena_za_1_dawke', true );
		if ( '' === $price || null === $price ) {
			$price = $product->get_price();
		}
		if ( '' === $price || null === $price ) {
			return '';
		}
		// Liczba całkowita bez zbędnych zer (np. 229), z dopiskiem "zł".
		$num = (float) $price;
		$str = ( floor( $num ) == $num ) ? number_format_i18n( $num, 0 ) : number_format_i18n( $num, 2 );
		return $str . ' zł';
	}

	/**
	 * URL przycisku "Rezerwuj" dla danego produktu.
	 */
	private function reserve_url( $product, $settings ) {
		$action = isset( $settings['reserve_action'] ) ? $settings['reserve_action'] : 'product';

		if ( 'cart' === $action ) {
			return method_exists( $product, 'add_to_cart_url' ) ? $product->add_to_cart_url() : get_permalink( $product->get_id() );
		}

		if ( 'page' === $action || 'booknetic' === $action ) {
			$page_id = ! empty( $settings['reserve_page'] ) ? (int) $settings['reserve_page'] : 0;
			$url     = $page_id ? get_permalink( $page_id ) : '';
			if ( ! $url ) {
				return '#';
			}
			if ( 'booknetic' === $action ) {
				// Booknetic czyta te parametry z URL i pomija odpowiednie kroki.
				if ( ! empty( $settings['booknetic_location'] ) ) {
					$url = add_query_arg( 'location', (int) $settings['booknetic_location'], $url );
				}
				if ( ! empty( $settings['booknetic_service'] ) ) {
					$url = add_query_arg( 'service', (int) $settings['booknetic_service'], $url );
				}
			}
			return $url;
		}

		if ( 'custom' === $action ) {
			if ( ! empty( $settings['reserve_custom_url']['url'] ) ) {
				return $settings['reserve_custom_url']['url'];
			}
			return '#';
		}

		return get_permalink( $product->get_id() );
	}

	/**
	 * Mapa miast = podkategorie kategorii głównej (term_id => term).
	 * Miasto produktu rozpoznajemy po przynależności do jednej z tych podkategorii.
	 *
	 * @param string $category_slug slug kategorii głównej (np. "szczepienia")
	 * @return array term_id => WP_Term
	 */
	private function get_city_terms( $category_slug ) {
		$main = get_term_by( 'slug', $category_slug, 'product_cat' );
		if ( ! $main || is_wp_error( $main ) ) {
			return [];
		}
		$children = get_terms(
			[
				'taxonomy'   => 'product_cat',
				'parent'     => $main->term_id,
				'hide_empty' => false,
			]
		);
		if ( is_wp_error( $children ) || empty( $children ) ) {
			return [];
		}
		$map = [];
		foreach ( $children as $term ) {
			$map[ $term->term_id ] = $term;
		}
		return $map;
	}

	/**
	 * Opcje listy miast do kontrolki SELECT (panel edytora).
	 * "Kraje" pomijamy — to poradniki, nie miasto.
	 *
	 * @param string $category_slug slug kategorii głównej
	 * @return array slug => nazwa (pierwsza pozycja: "" => "Wszystkie miasta")
	 */
	private function get_city_options( $category_slug = 'szczepienia' ) {
		$options = [ '' => __( 'Wszystkie miasta', 'teraz' ) ];
		if ( ! is_admin() ) {
			return $options;
		}
		foreach ( $this->get_city_terms( $category_slug ) as $term ) {
			if ( 'kraje' === $term->slug ) {
				continue;
			}
			$options[ $term->slug ] = $term->name;
		}
		return $options;
	}

	protected function render() {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return;
		}

		$settings = $this->get_settings_for_display();
		$category = ! empty( $settings['category_slug'] ) ? sanitize_title( $settings['category_slug'] ) : 'szczepienia';

		$args = [
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'no_found_rows'  => true,
		];
		$filter_city = isset( $settings['filter_city'] ) ? sanitize_title( $settings['filter_city'] ) : '';

		$tax_query = [];
		if ( $category ) {
			$tax_query[] = [
				'taxonomy' => 'product_cat',
				'field'    => 'slug',
				'terms'    => $category,
			];
		}
		// Filtr miasta = podkategoria kategorii głównej (wybrana w panelu Elementora).
		if ( $filter_city ) {
			$tax_query[] = [
				'taxonomy' => 'product_cat',
				'field'    => 'slug',
				'terms'    => $filter_city,
			];
		}
		if ( $tax_query ) {
			if ( count( $tax_query ) > 1 ) {
				$tax_query['relation'] = 'AND';
			}
			$args['tax_query'] = $tax_query;
		}

		$only_with_doses = isset( $settings['only_with_doses'] ) ? $settings['only_with_doses'] : 'yes';
		if ( 'yes' === $only_with_doses ) {
			$args['meta_query'] = [
				[
					'key'     => 'liczba_dawek',
					'value'   => '',
					'compare' => '!=',
				],
			];
		}

		$query = new WP_Query( $args );

		if ( ! $query->have_posts() ) {
			echo '<div class="tz-vac tz-vac--empty">' . esc_html( $settings['empty_text'] ) . '</div>';
			wp_reset_postdata();
			return;
		}

		$reserve_label  = isset( $settings['reserve_label'] ) ? $settings['reserve_label'] : __( 'Rezerwuj', 'teraz' );
		$details_label  = isset( $settings['details_label'] ) ? $settings['details_label'] : __( 'Szczegóły', 'teraz' );
		$reserve_action = isset( $settings['reserve_action'] ) ? $settings['reserve_action'] : 'product';
		$reserve_target = ( 'cart' !== $reserve_action && ! empty( $settings['reserve_new_tab'] ) ) ? ' target="_blank" rel="noopener"' : '';
		$details_target = '';

		$tabs_source = isset( $settings['tabs_source'] ) ? $settings['tabs_source'] : 'manual';

		// Wyszukiwarka krajów: kraje produktu z meta kraje_upsell (mapa kraj => true).
		$country_search  = ! empty( $settings['country_search'] );
		$country_presets = [];
		$all_countries   = [];
		if ( $country_search ) {
			foreach ( $query->posts as $p ) {
				$kraje = get_post_meta( $p->ID, 'kraje_upsell', true );
				if ( is_array( $kraje ) ) {
					foreach ( array_keys( $kraje ) as $k ) {
						$all_countries[ $k ] = true;
					}
				}
			}
			$all_countries = array_keys( $all_countries );
			sort( $all_countries, SORT_LOCALE_STRING );
			if ( ! empty( $settings['country_presets'] ) ) {
				$country_presets = array_filter( array_map( 'trim', explode( ',', $settings['country_presets'] ) ) );
			}
		}

		// Zakładki w trybie "filters": z repeatera, a gdy pusty - po jednej
		// dla każdej wartości pola "filtry" występującej w produktach.
		$filter_tabs = [];
		if ( 'filters' === $tabs_source ) {
			if ( ! empty( $settings['filter_tabs'] ) && is_array( $settings['filter_tabs'] ) ) {
				foreach ( $settings['filter_tabs'] as $tab ) {
					if ( empty( $tab['tab_filter'] ) ) {
						continue;
					}
					$filter_tabs[] = [
						'filter' => $tab['tab_filter'],
						'label'  => ! empty( $tab['tab_label'] ) ? $tab['tab_label'] : $tab['tab_filter'],
						'icon'   => isset( $tab['tab_icon'] ) ? $tab['tab_icon'] : [],
					];
				}
			} else {
				$seen = [];
				foreach ( $query->posts as $p ) {
					foreach ( (array) get_post_meta( $p->ID, 'filtry', true ) as $f ) {
						$f = trim( (string) $f );
						if ( '' !== $f ) {
							$seen[ $f ] = true;
						}
					}
				}
				ksort( $seen );
				foreach ( array_keys( $seen ) as $f ) {
					$filter_tabs[] = [
						'filter' => $f,
						'label'  => $f,
						'icon'   => [],
					];
				}
			}
		}

		?>
		<div class="tz-vac">
			<div class="tz-vac__bar">
				<div class="tz-vac__search">
					<svg class="tz-vac__search-ic" viewBox="0 0 24 24" aria-hidden="true"><path d="M10 4a6 6 0 104.47 10.03l4.25 4.25 1.41-1.41-4.25-4.25A6 6 0 0010 4zm0 2a4 4 0 110 8 4 4 0 010-8z"/></svg>
					<input type="search" class="tz-vac__input" placeholder="<?php echo esc_attr( $settings['search_placeholder'] ); ?>" aria-label="<?php echo esc_attr( $settings['search_placeholder'] ); ?>">
				</div>
				<?php if ( $country_search ) : ?>
				<div class="tz-vac__search tz-vac__search--country">
					<svg class="tz-vac__search-ic" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a10 10 0 100 20 10 10 0 000-20zm7.93 9h-3.02a15.7 15.7 0 00-1.5-6.06A8.02 8.02 0 0119.93 11zM12 4.04c.96 1.32 1.93 3.7 2.18 6.96H9.82c.25-3.26 1.22-5.64 2.18-6.96zM4.07 13h3.02c.18 2.3.7 4.38 1.5 6.06A8.02 8.02 0 014.07 13zm3.02-2H4.07a8.02 8.02 0 014.52-6.06A15.7 15.7 0 007.09 11zM12 19.96c-.96-1.32-1.93-3.7-2.18-6.96h4.36c-.25 3.26-1.22 5.64-2.18 6.96zm3.41-.9c.8-1.68 1.32-3.76 1.5-6.06h3.02a8.02 8.02 0 01-4.52 6.06z"/></svg>
					<input type="search" class="tz-vac__input tz-vac__input--country" placeholder="<?php echo esc_attr( $settings['country_placeholder'] ); ?>" aria-label="<?php echo esc_attr( $settings['country_placeholder'] ); ?>" autocomplete="off">
					<div class="tz-vac__suggest" hidden></div>
				</div>
				<?php if ( $country_presets ) : ?>
				<div class="tz-vac__chips">
					<?php foreach ( $country_presets as $chip ) : ?>
					<button type="button" class="tz-vac__chip" data-country="<?php echo esc_attr( $chip ); ?>"><?php echo esc_html( $chip ); ?></button>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>
				<script type="application/json" class="tz-vac__countries"><?php echo wp_json_encode( $all_countries ); ?></script>
				<?php endif; ?>
				<div class="tz-vac__tabs" role="tablist">
					<button type="button" class="tz-vac__tab is-active" data-products="all">
						<?php
						if ( ! empty( $settings['all_tab_icon']['value'] ) ) {
							\Elementor\Icons_Manager::render_icon( $settings['all_tab_icon'], [ 'aria-hidden' => 'true' ] );
						}
						?>
						<span><?php echo esc_html( $settings['all_tab_label'] ); ?></span>
					</button>
					<?php
					if ( 'filters' === $tabs_source ) {
						foreach ( $filter_tabs as $tab ) {
							?>
							<button type="button" class="tz-vac__tab" data-filter="<?php echo esc_attr( $tab['filter'] ); ?>">
								<?php
								if ( ! empty( $tab['icon']['value'] ) ) {
									\Elementor\Icons_Manager::render_icon( $tab['icon'], [ 'aria-hidden' => 'true' ] );
								}
								?>
								<span><?php echo esc_html( $tab['label'] ); ?></span>
							</button>
							<?php
						}
					} elseif ( ! empty( $settings['tabs'] ) && is_array( $settings['tabs'] ) ) {
						foreach ( $settings['tabs'] as $tab ) {
							$ids = ! empty( $tab['tab_products'] ) ? array_map( 'intval', (array) $tab['tab_products'] ) : [];
							?>
							<button type="button" class="tz-vac__tab" data-products="<?php echo esc_attr( implode( ',', $ids ) ); ?>">
								<?php
								if ( ! empty( $tab['tab_icon']['value'] ) ) {
									\Elementor\Icons_Manager::render_icon( $tab['tab_icon'], [ 'aria-hidden' => 'true' ] );
								}
								?>
								<span><?php echo esc_html( $tab['tab_label'] ); ?></span>
							</button>
							<?php
						}
					}
					?>
				</div>
			</div>

			<div class="tz-vac__table">
				<div class="tz-vac__thead" aria-hidden="true">
					<span class="tz-vac__th tz-vac__th--name"><?php echo esc_html( $settings['col_preparat'] ); ?></span>
					<span class="tz-vac__th tz-vac__th--doses"><?php echo esc_html( $settings['col_dawki'] ); ?></span>
					<span class="tz-vac__th tz-vac__th--price"><?php echo esc_html( $settings['col_cena'] ); ?></span>
					<span class="tz-vac__th tz-vac__th--actions"><?php echo esc_html( $settings['col_akcja'] ); ?></span>
				</div>
				<div class="tz-vac__rows">
					<?php
					while ( $query->have_posts() ) {
						$query->the_post();
						$product = wc_get_product( get_the_ID() );
						if ( ! $product ) {
							continue;
						}
						$pid   = $product->get_id();
						$title = $this->strip_prefix( get_the_title(), isset( $settings['strip_prefix'] ) ? $settings['strip_prefix'] : '' );
						$doses = $this->format_doses( $pid );
						$price = $this->format_price( $product );
						$resv  = $this->reserve_url( $product, $settings );
						$row_filters = array_filter( array_map( 'trim', (array) get_post_meta( $pid, 'filtry', true ) ) );
						$row_kraje   = '';
						if ( $country_search ) {
							$kraje_meta = get_post_meta( $pid, 'kraje_upsell', true );
							$row_kraje  = is_array( $kraje_meta ) ? implode( '|', array_keys( $kraje_meta ) ) : '';
						}
						?>
						<div class="tz-vac__row" data-id="<?php echo esc_attr( $pid ); ?>" data-name="<?php echo esc_attr( wp_strip_all_tags( $title ) ); ?>" data-filters="<?php echo esc_attr( implode( '|', $row_filters ) ); ?>"<?php echo $country_search ? ' data-kraje="' . esc_attr( $row_kraje ) . '"' : ''; ?>>
							<div class="tz-vac__cell tz-vac__name"><?php echo esc_html( $title ); ?></div>
							<div class="tz-vac__cell tz-vac__doses"><span class="tz-vac__th-m"><?php echo esc_html( $settings['col_dawki'] ); ?></span><?php echo esc_html( $doses ); ?></div>
							<div class="tz-vac__cell tz-vac__price"><span class="tz-vac__th-m"><?php echo esc_html( $settings['col_cena'] ); ?></span><?php echo esc_html( $price ); ?></div>
							<div class="tz-vac__cell tz-vac__actions">
								<a class="tz-vac__btn tz-vac__btn--ghost" href="<?php echo esc_url( get_permalink( $pid ) ); ?>"<?php echo $details_target; ?>><?php echo esc_html( $details_label ); ?></a>
								<a class="tz-vac__btn tz-vac__btn--solid" href="<?php echo esc_url( $resv ); ?>"<?php echo $reserve_target; ?>><?php echo esc_html( $reserve_label ); ?></a>
							</div>
						</div>
						<?php
					}
					?>
				</div>
				<div class="tz-vac__empty" hidden><?php echo esc_html( $settings['empty_text'] ); ?></div>
			</div>
		</div>
		<?php
		wp_reset_postdata();
	}
}
