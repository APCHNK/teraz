<?php
/**
 * Import oferty z plików JSON (inc/import-data/*.json) wygenerowanych
 * skryptem scripts/xlsx-to-json.py z arkusza "Dr Teraz _ Tabela z ofertą".
 *
 * Strona w wp-admin: Narzędzia -> Import oferty (TZ).
 * Najpierw pokazuje podgląd zmian (dry-run), zapis dopiero po kliknięciu
 * "Zastosuj" (nonce + manage_options).
 *
 * Obsługiwane typy:
 *  - szczepienia: produkty WooCommerce, dopasowanie po SKU (+ miasto_slug
 *    przy zduplikowanym SKU - patrz Chikungunya Gdańsk PO-26 -> GD-26)
 *  - preparaty:   CPT "preparat", dopasowanie po tytule
 *  - uslugi:      CPT "usluga", dopasowanie po tytule + mieście (miasto-us)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TZ_Offer_Import {

	const PAGE_SLUG = 'tz-offer-import';

	public static function init() {
		add_action( 'admin_menu', [ __CLASS__, 'register_page' ] );
	}

	public static function register_page() {
		add_management_page(
			'Import oferty (TZ)',
			'Import oferty (TZ)',
			'manage_options',
			self::PAGE_SLUG,
			[ __CLASS__, 'render_page' ]
		);
	}

	private static function data_dir() {
		return get_stylesheet_directory() . '/inc/import-data/';
	}

	private static function load_json( $name ) {
		$file = self::data_dir() . $name . '.json';
		if ( ! file_exists( $file ) ) {
			return new WP_Error( 'tz_import', 'Brak pliku: ' . esc_html( $file ) );
		}
		$data = json_decode( file_get_contents( $file ), true );
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'tz_import', 'Nieprawidłowy JSON: ' . esc_html( $file ) );
		}
		return $data;
	}

	/** Normalizacja wartości do porównania (NULL/'NULL'/'' są równoważne). */
	private static function norm( $v ) {
		if ( $v === null || $v === false ) {
			return '';
		}
		if ( is_array( $v ) ) {
			return $v;
		}
		$v = (string) $v;
		if ( $v === 'NULL' ) {
			return '';
		}
		$v = str_replace( "\r\n", "\n", $v );
		return trim( $v );
	}

	private static function differs( $old, $new ) {
		$old = self::norm( $old );
		$new = self::norm( $new );
		if ( is_array( $old ) || is_array( $new ) ) {
			return $old !== $new;
		}
		return $old !== $new;
	}

	/* ---------------------------------------------------------------------
	 * Plany (dry-run)
	 * ------------------------------------------------------------------- */

	/**
	 * Plan dla jednego rekordu: ['action' => create|update|skip, 'post_id',
	 * 'label', 'changes' => [pole => [stare, nowe]], 'meta', 'terms', ...]
	 */
	public static function build_plan( $type ) {
		$rows = self::load_json( $type );
		if ( is_wp_error( $rows ) ) {
			return $rows;
		}
		$plan = [];
		foreach ( $rows as $row ) {
			switch ( $type ) {
				case 'szczepienia':
					$plan[] = self::plan_product( $row );
					break;
				case 'preparaty':
					$plan[] = self::plan_preparat( $row );
					break;
				case 'uslugi':
					$plan[] = self::plan_usluga( $row );
					break;
			}
		}
		return $plan;
	}

	/* ----------------------------- produkty ----------------------------- */

	private static function find_product( $row ) {
		$by_sku = function ( $sku ) {
			return get_posts( [
				'post_type'      => 'product',
				'post_status'    => 'any',
				'posts_per_page' => 5,
				'fields'         => 'ids',
				'meta_query'     => [ [ 'key' => '_sku', 'value' => $sku ] ],
			] );
		};
		$candidates = $by_sku( $row['sku'] );
		if ( ! $candidates && $row['sheet_sku'] !== $row['sku'] ) {
			// SKU w arkuszu było błędne (np. PO-26 dla Gdańska) - szukamy po
			// starym SKU i odróżniamy po mieście.
			$candidates = $by_sku( $row['sheet_sku'] );
		}
		if ( count( $candidates ) > 1 ) {
			foreach ( $candidates as $pid ) {
				if ( self::norm( get_post_meta( $pid, 'miasto_slug', true ) ) === self::norm( $row['miasto_slug'] ) ) {
					return $pid;
				}
			}
			return 0; // niejednoznaczne - zgłosimy jako problem
		}
		return $candidates ? $candidates[0] : null;
	}

	private static function product_meta_map( $row ) {
		return [
			'_sku'                 => $row['sku'],
			'choroba'              => $row['choroba'],
			'id_szczepienia'       => $row['id_szczepienia'],
			'naglowek'             => $row['naglowek'],
			'badanie'              => $row['badanie'],
			'schemat'              => $row['schemat'],
			'liczba_dawek'         => $row['liczba_dawek'],
			'rodzaj'               => $row['rodzaj'],
			'droga_zakazenia'      => $row['droga_zakazenia'],
			'preparat_google'      => $row['preparat_google'],
			'preparat'             => $row['preparat'],
			'dostepnosc'           => $row['dostepnosc'],
			'cena_za_1_dawke'      => $row['cena'],
			'czas_do_uodpornienia' => $row['czas_do_uodpornienia'],
			'miasto2'              => $row['miasto'],
			'miasto_slug'          => $row['miasto_slug'],
			'_price'               => $row['cena'],
			'_regular_price'       => $row['cena'],
		];
	}

	private static function plan_product( $row ) {
		$item = [
			'type'    => 'szczepienia',
			'label'   => $row['sku'] . ' — ' . $row['title'],
			'row'     => $row,
			'changes' => [],
		];

		$post_id = self::find_product( $row );
		if ( $post_id === 0 ) {
			$item['action'] = 'error';
			$item['error']  = 'Niejednoznaczne dopasowanie SKU ' . $row['sheet_sku'] . ' (kilka produktów, brak miasto_slug)';
			return $item;
		}

		// kraje_upsell: mapa kraj => true
		$kraje_new = [];
		foreach ( $row['kraje'] as $k ) {
			$kraje_new[ $k ] = true;
		}

		if ( ! $post_id ) {
			$item['action'] = 'create';
			return $item;
		}

		$item['action']  = 'update';
		$item['post_id'] = $post_id;
		$post            = get_post( $post_id );

		if ( self::differs( $post->post_title, $row['title'] ) ) {
			$item['changes']['post_title'] = [ $post->post_title, $row['title'] ];
		}
		if ( self::differs( $post->post_content, $row['content'] ) ) {
			$item['changes']['post_content'] = [ $post->post_content, $row['content'] ];
		}
		$menu_order = $row['menu_order'] === '' ? 0 : (int) $row['menu_order'];
		if ( (int) $post->menu_order !== $menu_order ) {
			$item['changes']['menu_order'] = [ $post->menu_order, $menu_order ];
		}

		foreach ( self::product_meta_map( $row ) as $key => $new ) {
			$old = get_post_meta( $post_id, $key, true );
			if ( self::differs( $old, $new ) ) {
				$item['changes'][ $key ] = [ $old, $new ];
			}
		}

		// kraje_upsell - porównujemy listę kluczy
		$kraje_old = get_post_meta( $post_id, 'kraje_upsell', true );
		$kraje_old = is_array( $kraje_old ) ? array_keys( $kraje_old ) : [];
		if ( $kraje_old !== array_keys( $kraje_new ) ) {
			$item['changes']['kraje_upsell'] = [ implode( ', ', $kraje_old ), implode( ', ', array_keys( $kraje_new ) ) ];
		}

		// cross-sell - porównujemy po SKU docelowych
		$cross_old_ids = get_post_meta( $post_id, '_crosssell_ids', true );
		$cross_old     = [];
		if ( is_array( $cross_old_ids ) ) {
			foreach ( $cross_old_ids as $cid ) {
				$cross_old[] = (string) get_post_meta( $cid, '_sku', true );
			}
		}
		if ( $cross_old !== $row['crosssell_skus'] ) {
			$item['changes']['_crosssell_ids'] = [ implode( ',', $cross_old ), implode( ',', $row['crosssell_skus'] ) ];
		}

		// kategorie: Szczepienia + miasto (tylko dokładamy brakujące)
		$want_slugs = self::product_cat_slugs( $row );
		$have       = wp_get_object_terms( $post_id, 'product_cat', [ 'fields' => 'slugs' ] );
		$missing    = array_diff( $want_slugs, is_wp_error( $have ) ? [] : $have );
		if ( $missing ) {
			$item['changes']['product_cat+'] = [ implode( ',', is_wp_error( $have ) ? [] : $have ), implode( ',', $want_slugs ) ];
		}

		if ( ! $item['changes'] ) {
			$item['action'] = 'skip';
		}
		return $item;
	}

	private static function product_cat_slugs( $row ) {
		$slugs = [ 'szczepienia' ];
		if ( $row['miasto_slug'] !== '' ) {
			$slugs[] = $row['miasto_slug'];
		}
		return $slugs;
	}

	private static function apply_product( $item ) {
		$row     = $item['row'];
		$post_id = isset( $item['post_id'] ) ? $item['post_id'] : 0;

		$postarr = [
			'post_title'   => $row['title'],
			'post_content' => $row['content'],
			'menu_order'   => $row['menu_order'] === '' ? 0 : (int) $row['menu_order'],
		];

		if ( $item['action'] === 'create' ) {
			$postarr += [
				'post_type'   => 'product',
				'post_status' => 'publish',
				'post_name'   => sanitize_title( $row['choroba'] . ( $row['miasto'] ? '-' . $row['miasto'] : '' ) ),
			];
			$post_id = wp_insert_post( wp_slash( $postarr ), true );
			if ( is_wp_error( $post_id ) ) {
				return $post_id;
			}
			// sensowne domyślne ustawienia jak w istniejących produktach
			foreach ( [
				'_virtual'           => 'yes',
				'_tax_status'        => 'none',
				'_tax_class'         => '',
				'_manage_stock'      => 'no',
				'_backorders'        => 'no',
				'_sold_individually' => 'no',
				'_downloadable'      => 'no',
			] as $k => $v ) {
				update_post_meta( $post_id, $k, $v );
			}
			wp_set_object_terms( $post_id, 'simple', 'product_type' );
		} else {
			$postarr['ID'] = $post_id;
			$res           = wp_update_post( wp_slash( $postarr ), true );
			if ( is_wp_error( $res ) ) {
				return $res;
			}
		}

		foreach ( self::product_meta_map( $row ) as $key => $val ) {
			update_post_meta( $post_id, $key, wp_slash( $val ) );
		}

		$kraje = [];
		foreach ( $row['kraje'] as $k ) {
			$kraje[ $k ] = true;
		}
		update_post_meta( $post_id, 'kraje_upsell', $kraje );

		$cross_ids = [];
		foreach ( $row['crosssell_skus'] as $sku ) {
			$ids = get_posts( [
				'post_type'      => 'product',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => [ [ 'key' => '_sku', 'value' => $sku ] ],
			] );
			if ( $ids ) {
				$cross_ids[] = (int) $ids[0];
			}
		}
		update_post_meta( $post_id, '_crosssell_ids', $cross_ids );

		wp_set_object_terms( $post_id, self::product_cat_slugs( $row ), 'product_cat', true );

		if ( function_exists( 'wc_delete_product_transients' ) ) {
			wc_delete_product_transients( $post_id );
		}
		return $post_id;
	}

	/* ----------------------------- preparaty ---------------------------- */

	private static function find_by_title( $post_type, $title ) {
		$ids = get_posts( [
			'post_type'      => $post_type,
			'post_status'    => 'any',
			'posts_per_page' => 2,
			'fields'         => 'ids',
			'title'          => $title,
		] );
		return $ids ? $ids[0] : null;
	}

	private static function preparat_meta_map( $row ) {
		return [
			'preparat'         => $row['title'],
			'choroby'          => $row['choroby'],
			'id_szcz'          => $row['id_szcz'],
			'schemat'          => $row['schemat'],
			'droga_podania'    => $row['droga_podania'],
			'finansowanie_nfz' => $row['finansowanie_nfz'],
			'typ'              => $row['typ'],
			'dostepnosc'       => $row['dostepnosc'],
			'ciaza'            => $row['ciaza'],
			'naglowek'         => $row['naglowek'],
			'opis'             => $row['opis'],
			'chpl'             => $row['chpl'],
		];
	}

	private static function plan_preparat( $row ) {
		$item = [
			'type'    => 'preparaty',
			'label'   => $row['title'],
			'row'     => $row,
			'changes' => [],
		];
		$post_id = self::find_by_title( 'preparat', $row['title'] );
		if ( ! $post_id ) {
			$item['action'] = 'create';
			return $item;
		}
		$item['action']  = 'update';
		$item['post_id'] = $post_id;
		foreach ( self::preparat_meta_map( $row ) as $key => $new ) {
			$old = get_post_meta( $post_id, $key, true );
			if ( self::differs( $old, $new ) ) {
				$item['changes'][ $key ] = [ $old, $new ];
			}
		}
		if ( ! $item['changes'] ) {
			$item['action'] = 'skip';
		}
		return $item;
	}

	private static function apply_preparat( $item ) {
		$row     = $item['row'];
		$post_id = isset( $item['post_id'] ) ? $item['post_id'] : 0;
		if ( $item['action'] === 'create' ) {
			$post_id = wp_insert_post( wp_slash( [
				'post_type'   => 'preparat',
				'post_status' => 'publish',
				'post_title'  => $row['title'],
			] ), true );
			if ( is_wp_error( $post_id ) ) {
				return $post_id;
			}
		}
		foreach ( self::preparat_meta_map( $row ) as $key => $val ) {
			update_post_meta( $post_id, $key, wp_slash( $val ) );
		}
		return $post_id;
	}

	/* ------------------------------ usługi ------------------------------ */

	private static function find_usluga( $row ) {
		$ids = get_posts( [
			'post_type'      => 'usluga',
			'post_status'    => 'any',
			'posts_per_page' => 5,
			'fields'         => 'ids',
			'title'          => $row['title'],
			'tax_query'      => [
				[
					'taxonomy' => 'miasto-us',
					'field'    => 'slug',
					'terms'    => sanitize_title( $row['miasto'] ),
				],
			],
		] );
		return $ids ? $ids[0] : null;
	}

	private static function usluga_meta_map( $row ) {
		$map = [
			'miasto'               => $row['miasto'],
			'dostepne_wkrotce'     => $row['dostepne_wkrotce'],
			'naglowek_wyswietlany' => $row['naglowek_wyswietlany'],
			'podtytul'             => $row['podtytul'],
			'formularz'            => $row['formularz'],
			'opis'                 => $row['opis'],
			'rezerwacja'           => $row['rezerwacja'],
			'niemcewicza'          => $row['niemcewicza'],
			'modlinska'            => $row['modlinska'],
			'grabowa'              => $row['grabowa'],
			'cena_poz'             => $row['cena_poz'],
			'cena_prywatnie'       => $row['cena_prywatnie'],
			'zasady_korzystania'   => $row['zasady_korzystania'],
			'archiwum_shortcode'   => $row['archiwum_shortcode'],
			'obraz_nad_opisem'     => $row['obraz_nad_opisem'],
		];
		foreach ( $row['faq'] as $i => $faq ) {
			$n                            = $i + 1;
			$map[ "faq_$n" ]              = $faq['enabled'] ? [ 'Tak' => true ] : '';
			$map[ "faq_tytul_$n" ]        = $faq['tytul'];
			$map[ "faq_tresc_$n" ]        = $faq['tresc'];
		}
		return $map;
	}

	private static function plan_usluga( $row ) {
		$item = [
			'type'    => 'uslugi',
			'label'   => $row['title'] . ' (' . $row['miasto'] . ')',
			'row'     => $row,
			'changes' => [],
		];
		$post_id = self::find_usluga( $row );
		if ( ! $post_id ) {
			$item['action'] = 'create';
			return $item;
		}
		$item['action']  = 'update';
		$item['post_id'] = $post_id;

		foreach ( self::usluga_meta_map( $row ) as $key => $new ) {
			$old = get_post_meta( $post_id, $key, true );
			if ( strpos( $key, 'faq_' ) === 0 && ! strpos( $key, 'tytul' ) && ! strpos( $key, 'tresc' ) ) {
				// checkbox: porównujemy stan "włączony"
				$old_on = is_array( $old ) && ! empty( $old['Tak'] );
				$new_on = is_array( $new );
				if ( $old_on !== $new_on ) {
					$item['changes'][ $key ] = [ $old_on ? 'Tak' : '', $new_on ? 'Tak' : '' ];
				}
				continue;
			}
			if ( self::differs( $old, $new ) ) {
				$item['changes'][ $key ] = [ $old, $new ];
			}
		}

		// typ-uslugi: dokładamy brakujące kategorie (nie usuwamy np. Archiwum)
		$want = array_map( 'sanitize_title', $row['kategorie'] );
		$have = wp_get_object_terms( $post_id, 'typ-uslugi', [ 'fields' => 'slugs' ] );
		$have = is_wp_error( $have ) ? [] : $have;
		if ( array_diff( $want, $have ) ) {
			$item['changes']['typ-uslugi+'] = [ implode( ',', $have ), implode( ',', $want ) ];
		}

		if ( ! $item['changes'] ) {
			$item['action'] = 'skip';
		}
		return $item;
	}

	private static function apply_usluga( $item ) {
		$row     = $item['row'];
		$post_id = isset( $item['post_id'] ) ? $item['post_id'] : 0;

		if ( $item['action'] === 'create' ) {
			$post_id = wp_insert_post( wp_slash( [
				'post_type'   => 'usluga',
				'post_status' => 'publish',
				'post_title'  => $row['title'],
				'post_name'   => sanitize_title( $row['title'] ) . $row['slug_suffix'],
			] ), true );
			if ( is_wp_error( $post_id ) ) {
				return $post_id;
			}
		}

		foreach ( self::usluga_meta_map( $row ) as $key => $val ) {
			if ( $val === '' && strpos( $key, 'faq_' ) === 0 && strpos( $key, 'tytul' ) === false && strpos( $key, 'tresc' ) === false ) {
				delete_post_meta( $post_id, $key );
				continue;
			}
			update_post_meta( $post_id, $key, is_array( $val ) ? $val : wp_slash( $val ) );
		}

		wp_set_object_terms( $post_id, sanitize_title( $row['miasto'] ), 'miasto-us', false );
		wp_set_object_terms( $post_id, array_map( 'sanitize_title', $row['kategorie'] ), 'typ-uslugi', true );
		return $post_id;
	}

	/* ---------------------------------------------------------------------
	 * Wykonanie i strona admina
	 * ------------------------------------------------------------------- */

	public static function apply_plan( $type ) {
		$plan = self::build_plan( $type );
		if ( is_wp_error( $plan ) ) {
			return $plan;
		}
		// Duże partie (121+ produktów) potrafią przekroczyć limit czasu -
		// import jest idempotentny, więc w razie przerwania można po prostu
		// uruchomić go ponownie.
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 0 );
		}
		wp_defer_term_counting( true );
		$result = [ 'created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => [] ];
		foreach ( $plan as $item ) {
			if ( $item['action'] === 'skip' ) {
				$result['skipped']++;
				continue;
			}
			if ( $item['action'] === 'error' ) {
				$result['errors'][] = $item['label'] . ': ' . $item['error'];
				continue;
			}
			switch ( $type ) {
				case 'szczepienia':
					$res = self::apply_product( $item );
					break;
				case 'preparaty':
					$res = self::apply_preparat( $item );
					break;
				case 'uslugi':
					$res = self::apply_usluga( $item );
					break;
				default:
					$res = new WP_Error( 'tz_import', 'Nieznany typ' );
			}
			if ( is_wp_error( $res ) ) {
				$result['errors'][] = $item['label'] . ': ' . $res->get_error_message();
			} elseif ( $item['action'] === 'create' ) {
				$result['created']++;
			} else {
				$result['updated']++;
			}
		}
		wp_defer_term_counting( false );
		return $result;
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Brak uprawnień.' );
		}

		$types  = [
			'szczepienia' => 'Szczepienia (produkty WooCommerce)',
			'preparaty'   => 'Preparaty (CPT preparat)',
			'uslugi'      => 'Usługi (CPT usluga)',
		];
		$notice = '';

		if ( isset( $_POST['tz_import_type'] ) ) {
			check_admin_referer( 'tz_offer_import' );
			$type = sanitize_key( $_POST['tz_import_type'] );
			if ( isset( $types[ $type ] ) ) {
				$result = self::apply_plan( $type );
				if ( is_wp_error( $result ) ) {
					$notice = '<div class="notice notice-error"><p>' . esc_html( $result->get_error_message() ) . '</p></div>';
				} else {
					$msg = sprintf(
						'%s — utworzono: %d, zaktualizowano: %d, bez zmian: %d.',
						$types[ $type ],
						$result['created'],
						$result['updated'],
						$result['skipped']
					);
					if ( $result['errors'] ) {
						$msg .= ' Błędy: ' . esc_html( implode( ' | ', $result['errors'] ) );
					}
					$notice = '<div class="notice notice-success"><p>' . $msg . '</p></div>';
				}
			}
		}

		echo '<div class="wrap"><h1>Import oferty (TZ)</h1>';
		echo $notice; // phpcs:ignore
		echo '<p>Dane: <code>' . esc_html( self::data_dir() ) . '</code>. Podgląd niczego nie zapisuje — zmiany dopiero po kliknięciu „Zastosuj”.</p>';

		foreach ( $types as $type => $label ) {
			$plan = self::build_plan( $type );
			echo '<h2>' . esc_html( $label ) . '</h2>';
			if ( is_wp_error( $plan ) ) {
				echo '<p style="color:#b32d2e">' . esc_html( $plan->get_error_message() ) . '</p>';
				continue;
			}
			$counts = [ 'create' => 0, 'update' => 0, 'skip' => 0, 'error' => 0 ];
			foreach ( $plan as $item ) {
				$counts[ $item['action'] ]++;
			}
			printf(
				'<p>Rekordów: %d — <strong>do utworzenia: %d</strong>, <strong>do aktualizacji: %d</strong>, bez zmian: %d, problemy: %d</p>',
				count( $plan ),
				$counts['create'],
				$counts['update'],
				$counts['skip'],
				$counts['error']
			);

			echo '<details style="margin:0 0 1em"><summary>Pokaż szczegóły zmian</summary>';
			echo '<table class="widefat striped" style="margin-top:8px"><thead><tr><th style="width:28%">Rekord</th><th>Zmiany</th></tr></thead><tbody>';
			foreach ( $plan as $item ) {
				if ( $item['action'] === 'skip' ) {
					continue;
				}
				echo '<tr><td>' . esc_html( $item['label'] ) . '<br><em>' . esc_html( $item['action'] ) . '</em></td><td>';
				if ( $item['action'] === 'create' ) {
					echo '<em>nowy rekord</em>';
				} elseif ( $item['action'] === 'error' ) {
					echo '<span style="color:#b32d2e">' . esc_html( $item['error'] ) . '</span>';
				} else {
					echo '<ul style="margin:0">';
					foreach ( $item['changes'] as $field => $pair ) {
						$old = is_array( $pair[0] ) ? wp_json_encode( $pair[0] ) : (string) $pair[0];
						$new = is_array( $pair[1] ) ? wp_json_encode( $pair[1] ) : (string) $pair[1];
						printf(
							'<li><code>%s</code>: <span style="color:#b32d2e">%s</span> → <span style="color:#00a32a">%s</span></li>',
							esc_html( $field ),
							esc_html( mb_strimwidth( self::norm( $old ), 0, 120, '…' ) ),
							esc_html( mb_strimwidth( self::norm( $new ), 0, 120, '…' ) )
						);
					}
					echo '</ul>';
				}
				echo '</td></tr>';
			}
			echo '</tbody></table></details>';

			echo '<form method="post" onsubmit="return confirm(\'Zastosować zmiany: ' . esc_attr( $label ) . '?\');">';
			wp_nonce_field( 'tz_offer_import' );
			echo '<input type="hidden" name="tz_import_type" value="' . esc_attr( $type ) . '">';
			submit_button( 'Zastosuj: ' . $label, 'primary', 'submit', false );
			echo '</form><hr>';
		}
		echo '</div>';
	}
}

TZ_Offer_Import::init();
