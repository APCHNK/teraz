<?php

function parus_style() {
    wp_enqueue_style(
        'style-css', 
        get_stylesheet_directory_uri() . '/style.css', 
        array('elementor-frontend'),
        '2.1',
        'all'
    );
}
add_action('wp_enqueue_scripts', 'parus_style', 999);

add_action('wp_head', function() {
    ?>
    <script>
    !function(f,b,e,v,n,t,s)
    {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
    n.callMethod.apply(n,arguments):n.queue.push(arguments)};
    if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
    n.queue=[];t=b.createElement(e);t.async=!0;
    t.src=v;s=b.getElementsByTagName(e)[0];
    s.parentNode.insertBefore(t,s)}(window, document,'script',
    'https://connect.facebook.net/en_US/fbevents.js');
    fbq('init', '838957851842253');
    fbq('track', 'PageView');
    </script>
    <noscript><img height="1" width="1" style="display:none"
    src="https://www.facebook.com/tr?id=838957851842253&ev=PageView&noscript=1"
    /></noscript>
    <?php
}, 20);

function parus_enqueue_scripts() {
    if (!is_admin()) {
        wp_deregister_script('jquery');
        wp_register_script(
            'jquery',
            get_stylesheet_directory_uri() . '/js/jquery.min.js',
            [],
            '3.7.1',
            true // ładuj w stopce — nie blokuje renderowania
        );
        wp_enqueue_script('jquery');

        // Animacja nagłówka (przełączanie klasy .sa po przewinięciu).
        // Wcześniej obsługiwane przez GSAP + ScrollTrigger (~117 KB JS) —
        // zastąpione lekkim własnym skryptem motywu.
        wp_enqueue_script('parus-header', get_stylesheet_directory_uri() . '/js/header.js', [], '1.0', true);
    }
}
add_action('wp_enqueue_scripts', 'parus_enqueue_scripts');

// jQuery przeniesiony do stopki (nie blokuje renderowania). Drukujemy go
// na samym początku wp_footer (priorytet 1), ZANIM zadziałają inline-skrypty
// motywu w wp_footer (priorytet 10+), żeby globalny `jQuery` był już dostępny.
// Jeśli jakiś skrypt w <head> wymaga jQuery, WordPress i tak wydrukuje go
// wcześniej w <head> — wtedy to wywołanie po prostu nic nie zrobi.
add_action('wp_footer', function() {
    wp_print_scripts('jquery');
}, 1);

// ============================================
// WOOCOMMERCE — ładuj tylko na stronach WooCommerce
// ============================================
function drteraz_is_woo_page() {
    // Natywne warunki WooCommerce
    if (is_woocommerce() || is_cart() || is_checkout() || is_account_page()) {
        return true;
    }

    $uri = $_SERVER['REQUEST_URI'];

    $woo_prefixes = [
        '/koszyk/',
        '/zamowienie/',
        '/badania/',
        '/book/',
        '/kraje/',
        '/podsumowanie/',
        '/szczepienia/',
        '/badanie-laboratoryjne/',
    ];

    foreach ($woo_prefixes as $prefix) {
        if (strpos($uri, $prefix) !== false) {
            return true;
        }
    }

    return false;
}

add_action('wp_enqueue_scripts', function() {
    if (drteraz_is_woo_page()) {
        return;
    }

// Style
wp_dequeue_style('woocommerce-general');
wp_dequeue_style('woocommerce-layout');
wp_dequeue_style('woocommerce-smallscreen');
wp_dequeue_style('wc-blocks-style');
wp_dequeue_style('woocommerce-notices');
wp_dequeue_style('wc-blocks-vendors-style');
wp_dequeue_style('wc-blocks-editor-style');
wp_dequeue_style('woocommerce-notices');
wp_deregister_style('woocommerce-notices');
wp_dequeue_style('wc-blocks-style');
wp_deregister_style('wc-blocks-style');

// Skrypty
wp_dequeue_script('wc-cart-fragments');
wp_dequeue_script('woocommerce');
wp_dequeue_script('wc-add-to-cart');
wp_dequeue_script('wc-cart');
wp_dequeue_script('wc-order-attribution');
wp_dequeue_script('wc-order-attribution-init');


}, 99);

add_action('wp_head', function() {
    printf('<script>var bookCartApiNonce = "%s";</script>', wp_create_nonce('wc_store_api'));
});

add_shortcode('deklaracja_auto', function () {
    $miasto = isset($_GET['miasto']) ? sanitize_text_field($_GET['miasto']) : '';

    $map = [
        'poznan'             => 'acc85f0',
        'warszawa_bialoleka' => '3aaac26',
        'warszawa_ochota'    => 'a21719d',
    ];

    if ($miasto && isset($map[$miasto])) {
        $id = $map[$miasto];
    } else {
        $id = '2a1dcfe';
    }

    return do_shortcode('[contact-form-7 id="' . $id . '" title="Deklaracja"]');
});

// Permalinki produktów z kategorią
add_action('init', function() {
    // Reguła dla szczepienia (tylko dla produktów, NIE dla samej kategorii)
    add_rewrite_rule(
        '^szczepienia/([^/]+)/?$',
        'index.php?product=$matches[1]',
        'top'
    );
    
    // Reguła dla badanie-laboratoryjne
    add_rewrite_rule(
        '^badanie-laboratoryjne/([^/]+)/?$',
        'index.php?product=$matches[1]',
        'top'
    );
    
    // Reguła dla kraje
    add_rewrite_rule(
        '^kraje/([^/]+)/?$',
        'index.php?product=$matches[1]',
        'top'
    );
}, 1);

// Wyjątek - /szczepienia/ sama w sobie to kategoria, nie produkt
add_filter('request', function($query_vars) {
    // Jeśli URL to dokładnie /szczepienia/ (bez niczego po slashu)
    if (isset($query_vars['product']) && 
        $query_vars['product'] === '' && 
        isset($_SERVER['REQUEST_URI']) && 
        preg_match('#^/szczepienia/?$#', $_SERVER['REQUEST_URI'])) {
        
        // Przekieruj na kategorię produktów
        unset($query_vars['product']);
        $query_vars['product_cat'] = 'szczepienia';
    }
    
    return $query_vars;
}, 5);

add_filter('post_type_link', function($permalink, $post) {
    if ($post->post_type !== 'product') return $permalink;
    
    $terms = get_the_terms($post->ID, 'product_cat');
    if (!$terms || is_wp_error($terms)) return $permalink;
    
    // Mapowanie: slug kategorii => prefix w URL
    $category_map = [
        'szczepienia' => 'szczepienia',
        'badanie-laboratoryjne' => 'badanie-laboratoryjne',
        'kraje' => 'kraje'
    ];
    
    $url_prefix = null;
    
    foreach ($terms as $term) {
        if (isset($category_map[$term->slug])) {
            $url_prefix = $category_map[$term->slug];
            break;
        }
    }
    
    // Jeśli produkt ma jedną z naszych kategorii - custom permalink
    if ($url_prefix) {
        return home_url('/' . $url_prefix . '/' . $post->post_name . '/');
    }
    
    // Reszta produktów - standardowy WooCommerce /produkt/
    return $permalink;
}, 10, 2);

// Flush rewrite rules po aktualizacji produktu
add_action('save_post_product', function($post_id) {
    flush_rewrite_rules(false);
}, 999);

// Upewnij się, że reguły są załadowane
add_action('after_switch_theme', function() {
    flush_rewrite_rules(false);
});

// Wyłącz system kuponów WooCommerce
add_filter('woocommerce_coupons_enabled', '__return_false');

// Preload
add_action('wp_head', function() {
    $dir = get_stylesheet_directory_uri() . '/fonts/';
    echo '<link rel="preload" href="' . $dir . 'dmsans-400.woff2" as="font" type="font/woff2" crossorigin="anonymous">' . "\n";
    echo '<link rel="preload" href="' . $dir . 'dmsans-600.woff2" as="font" type="font/woff2" crossorigin="anonymous">' . "\n";
}, 1);

// @font-face
add_action('wp_head', function() {
    $dir = get_stylesheet_directory_uri() . '/fonts/';
    ?>
    <style>
    @font-face {
        font-family: 'DM Sans';
        font-style: normal;
        font-weight: 400;
        font-display: swap;
        src: url('<?= $dir ?>dmsans-400.woff2') format('woff2');
        unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;
    }
    @font-face {
        font-family: 'DM Sans';
        font-style: normal;
        font-weight: 400;
        font-display: swap;
        src: url('<?= $dir ?>dmsans-400-2.woff2') format('woff2');
        unicode-range: U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF;
    }
    @font-face {
        font-family: 'DM Sans';
        font-style: normal;
        font-weight: 600;
        font-display: swap;
        src: url('<?= $dir ?>dmsans-600.woff2') format('woff2');
        unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;
    }
    @font-face {
        font-family: 'DM Sans';
        font-style: normal;
        font-weight: 600;
        font-display: swap;
        src: url('<?= $dir ?>dmsans-600-2.woff2') format('woff2');
        unicode-range: U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF;
    }
    </style>
    <?php
}, 1);


add_action( 'wp_footer', function() {
    // Nadpisania scrollTo / animate / scrollIntoView blokują przewijanie do
    // notatek WooCommerce — są potrzebne TYLKO na stronie koszyka i zamówienia.
    // Na pozostałych stronach pomijamy je, żeby nie spowalniać przewijania
    // (każde wywołanie scrollTo tworzyło new Error().stack).
    if ( ! function_exists( 'is_cart' ) || ( ! is_cart() && ! is_checkout() ) ) {
        return;
    }
    ?>
    <script>
    (function($) {
        $(window).on('load', function() {

            // Zablokuj scroll_to_notices z WooCommerce
            if (window.wc_cart_params || window.woocommerce_params) {
                $(document.body).on('scroll_to_notices', function(e) {
                    e.stopImmediatePropagation();
                    e.preventDefault();
                    return false;
                });
            }

            // Zablokuj moveNotices z Elementor Pro + scroll WooCommerce
            var origAnimate = $.fn.animate;
            $.fn.animate = function(props, speed, easing, callback) {
                if (props && props.scrollTop !== undefined) {
                    var isHtmlBody = false;
                    this.each(function() {
                        if (this.tagName === 'HTML' || this.tagName === 'BODY') {
                            isHtmlBody = true;
                        }
                    });
                    if (isHtmlBody) {
                        if (typeof speed === 'function') speed.call(this);
                        else if (typeof easing === 'function') easing.call(this);
                        else if (typeof callback === 'function') callback.call(this);
                        return this;
                    }
                }
                return origAnimate.apply(this, arguments);
            };

            // Zablokuj window.scrollTo całkowicie gdy pochodzi z notices/observer
            var origScrollTo = window.scrollTo;
            window.scrollTo = function(x, y) {
                var stack = new Error().stack || '';
                if (
                    stack.indexOf('observer') > -1 || 
                    stack.indexOf('notices') > -1 ||
                    stack.indexOf('moveNotices') > -1 ||
                    stack.indexOf('scroll_to_notices') > -1 ||
                    stack.indexOf('woocommerce-notices') > -1 ||
                    stack.indexOf('cart.min.js') > -1
                ) {
                    return;
                }
                origScrollTo.apply(window, arguments);
            };

            // Zablokuj scrollIntoView na elementach notices
            var origScrollIntoView = Element.prototype.scrollIntoView;
            Element.prototype.scrollIntoView = function() {
                if (
                    $(this).closest('.e-woocommerce-notices-wrapper, .woocommerce-notices-wrapper').length ||
                    $(this).hasClass('woocommerce-error') ||
                    $(this).hasClass('woocommerce-message') ||
                    $(this).hasClass('woocommerce-info')
                ) {
                    return;
                }
                origScrollIntoView.apply(this, arguments);
            };

        });
    })(jQuery);
    </script>
    <?php
} );

/* ===========================
   WALIDACJA KODU POCZTOWEGO – CF7
   =========================== */
add_filter('wpcf7_validate_text*', 'validate_postcode', 20, 2);
add_filter('wpcf7_validate_text', 'validate_postcode', 20, 2);

function validate_postcode($result, $tag) {
    if ($tag->name === 'kodp') {
        $value = trim($_POST[$tag->name] ?? '');
        if (!preg_match('/^[0-9]{2}-[0-9]{3}$/', $value)) {
            $result->invalidate($tag, "Podaj kod pocztowy w formacie 00-000.");
        }
    }
    return $result;
}

/* ===========================
   WALIDACJA TELEFONU – CF7
   =========================== */
add_filter('wpcf7_validate_tel', 'cf7_validate_tel', 20, 2);
add_filter('wpcf7_validate_tel*', 'cf7_validate_tel', 20, 2);
function cf7_validate_tel($result, $tag) {
    if ($tag->name === 'tel') {
        $phone = trim($_POST['tel'] ?? '');
        if (!preg_match('/^\d{3} \d{3} \d{3}$/', $phone)) {
            $result->invalidate($tag, "Podaj numer telefonu w formacie 000 000 000.");
        }
    }
    return $result;
}

/* ===========================
   WALIDACJA PESEL – CF7
   =========================== */
add_filter('wpcf7_validate_text', 'cf7_validate_pesel', 20, 2);
add_filter('wpcf7_validate_text*', 'cf7_validate_pesel', 20, 2);

function cf7_validate_pesel($result, $tag) {
    if ($tag->name !== 'pesel') return $result;

    // ograniczenie do max 11 cyfr jeszcze przed walidacją
    $pesel = preg_replace('/\D/', '', $_POST['pesel'] ?? '');
    $pesel = substr($pesel, 0, 11);
    $_POST['pesel'] = $pesel;

    return validate_pesel_logic($result, $tag, $pesel);
}

/* ===========================
   WALIDACJA PESEL – WooCommerce (billing_city)
   =========================== */
add_action('woocommerce_checkout_process', 'wc_validate_pesel_city');
add_action('woocommerce_checkout_process', 'wc_validate_tel');

function wc_validate_pesel_city() {
    if (isset($_POST['billing_city'])) {
        // tylko cyfry i ograniczenie do 11 znaków
        $pesel = preg_replace('/\D/', '', $_POST['billing_city']);
        $pesel = substr($pesel, 0, 11);
        $_POST['billing_city'] = $pesel;

        $result = new stdClass();
        $result = validate_pesel_logic($result, (object)['name'=>'billing_city'], $pesel, true);
    }
}

/* ===========================
   WALIDACJA TELEFONU – WooCommerce
   =========================== */
function wc_validate_tel() {
    if (isset($_POST['billing_phone'])) {
        $phone_raw = $_POST['billing_phone'];
        $phone_clean = preg_replace('/\D/', '', $phone_raw);
        if (strlen($phone_clean) !== 9) {
            wc_add_notice('Numer telefonu musi składać się z dokładnie 9 cyfr.', 'error');
        } else {
            $_POST['billing_phone'] = $phone_clean;
        }
    }
}

/* ===========================
   FUNKCJA WSPÓLNA – PESEL
   =========================== */
function validate_pesel_logic($result, $tag, $pesel, $is_woo=false) {
    if (!preg_match('/^[0-9]{11}$/', $pesel)) {
        $msg = "Numer PESEL musi składać się z dokładnie 11 cyfr.";
        if ($is_woo) wc_add_notice($msg, 'error'); else $result->invalidate($tag, $msg);
        return $result;
    }

    $year  = intval(substr($pesel, 0, 2));
    $month = intval(substr($pesel, 2, 2));
    $day   = intval(substr($pesel, 4, 2));

    $century = 1900;
    if ($month > 80 && $month < 93) { $century = 1800; $month -= 80; }
    elseif ($month > 0 && $month < 13) { $century = 1900; }
    elseif ($month > 20 && $month < 33) { $century = 2000; $month -= 20; }
    elseif ($month > 40 && $month < 53) { $century = 2100; $month -= 40; }
    elseif ($month > 60 && $month < 73) { $century = 2200; $month -= 60; }
    else {
        $msg = "Numer PESEL zawiera nieprawidłowy miesiąc urodzenia.";
        if ($is_woo) wc_add_notice($msg, 'error'); else $result->invalidate($tag, $msg);
        return $result;
    }

    $fullYear = $century + $year;

    if (!checkdate($month, $day, $fullYear)) {
        $msg = "Numer PESEL zawiera nieprawidłową datę urodzenia.";
        if ($is_woo) wc_add_notice($msg, 'error'); else $result->invalidate($tag, $msg);
        return $result;
    }

    $weights = [1,3,7,9,1,3,7,9,1,3];
    $sum = 0;
    for ($i=0; $i<10; $i++) {
        $sum += $weights[$i] * intval($pesel[$i]);
    }
    $checksum = (10 - ($sum % 10)) % 10;

    if ($checksum !== intval($pesel[10])) {
        $msg = "Podany numer PESEL jest nieprawidłowy.";
        if ($is_woo) wc_add_notice($msg, 'error'); else $result->invalidate($tag, $msg);
    }

    return $result;
}

/* ===========================
   JS DO FORMATOWANIA PÓL
   =========================== */
add_action('wp_footer', function() { ?>
<script>
document.addEventListener('input', function(e) {
    const field = e.target;

    // KOD POCZTOWY – CF7 00-000
    if (field.name === 'kodp') {
        let val = field.value.replace(/\D/g, '');
        if (val.length > 2) field.value = val.slice(0,2)+'-'+val.slice(2,5);
        else field.value = val;
    }

    // PESEL – max 11 cyfr podczas wpisywania (CF7 i Woo)
    if (field.name === 'pesel' || field.name === 'billing_city') {
        let val = field.value.replace(/\D/g, '');
        field.value = val.slice(0,11);
    }

    // TELEFON – Woo i CF7 wyświetlanie
    if (field.name === 'tel' || field.name === 'billing_phone') {
        let val = field.value.replace(/\D/g, '');
        val = val.slice(0, 9);
        if (val.length > 6) field.value = val.slice(0,3)+' '+val.slice(3,6)+' '+val.slice(6);
        else if (val.length > 3) field.value = val.slice(0,3)+' '+val.slice(3);
        else field.value = val;
    }
});
</script>
<?php });

/* Koniec walidacji */

/* Zabezpieczenie dla 2 różnych kategorii w koszyku */
add_action( 'wp_footer', 'add_classes_if_both_categories_in_cart', 50 );
function add_classes_if_both_categories_in_cart() {

    if ( ! is_cart() && ! is_checkout() ) {
        return;
    }

    $has_szczepienia = false;
    $has_badanie     = false;

    if ( WC()->cart && ! WC()->cart->is_empty() ) {
        foreach ( WC()->cart->get_cart() as $cart_item ) {
            $product_id = $cart_item['product_id'];

            if ( has_term( 'szczepienia', 'product_cat', $product_id ) ) {
                $has_szczepienia = true;
            }

            if ( has_term( 'badanie-laboratoryjne', 'product_cat', $product_id ) ) {
                $has_badanie = true;
            }
        }
    }

    $should_activate = ( $has_szczepienia && $has_badanie );
    ?>

    <script>
        (function($){
            function activateClassesIfReady() {
                if (<?php echo $should_activate ? 'true' : 'false'; ?>) {
                    $('.dalej').addClass('wzw2');
                    $('.dk').addClass('dk-acti');
                } else {
                    $('.dalej').removeClass('wzw2');
                    $('.dk').removeClass('dk-acti');
                }
            }

            $(document).ready(function(){
                setTimeout(activateClassesIfReady, 400);
            });

            $(document.body).on('updated_cart_totals updated_checkout', function(){
                setTimeout(activateClassesIfReady, 400);
            });

        })(jQuery);
    </script>

    <?php
}

add_action('wp_footer', function () {
    if (!is_cart()) return;
    ?>
    <script>
    (function($){

        $(document).ready(function() {
            if (typeof $.scroll_to_notices === 'function') {
                $.scroll_to_notices = function() {
                    return false;
                };
            }
        });

        let blockScroll = false;

        $(document).on('click', 'a.product-remove', function() {
            blockScroll = true;
        });

        $(document.body).on('updated_wc_div', function() {
            if (!blockScroll) return;

            const current = window.scrollY;

            setTimeout(function(){
                window.scrollTo(0, current);
                blockScroll = false;
            }, 50);
        });

    })(jQuery);
    </script>
    <?php
});

/* ZESTAW PODRÓŻNIK */
function podroznik_bundle_products() {
    return [5253, 5272, 5257, 5263];
}

function podroznik_cart_state() {

    if ( ! function_exists('WC') || ! WC()->cart ) {
        return [
            'has_bundle'     => false,
            'has_any_bundle' => false,
            'missing_items'  => [],
        ];
    }

    $bundle_products = podroznik_bundle_products();
    $cart_products   = [];

    foreach ( WC()->cart->get_cart() as $item ) {
        $cart_products[] = (int) $item['product_id'];
    }

    $present = array_intersect( $bundle_products, $cart_products );
    $missing = array_diff( $bundle_products, $cart_products );

    $missing_items = [];

    foreach ( $missing as $product_id ) {
        $product = wc_get_product( $product_id );
        if ( $product ) {
            $missing_items[] = [
                'id'   => $product_id,
                'name' => $product->get_name(),
                'url'  => '?add-to-cart=' . $product_id,
            ];
        }
    }

    return [
        'has_bundle'     => empty( $missing ),
        'has_any_bundle' => ! empty( $present ),
        'missing_items'  => $missing_items,
    ];
}

add_action( 'woocommerce_cart_calculate_fees', function ( $cart ) {
    if ( is_admin() && ! defined('DOING_AJAX') ) return;

    if ( podroznik_cart_state()['has_bundle'] ) {
        $cart->add_fee( 'Zniżka', -90 );
    }
});

add_action( 'wp_ajax_add_podroznik_bundle', 'add_podroznik_bundle' );
add_action( 'wp_ajax_nopriv_add_podroznik_bundle', 'add_podroznik_bundle' );

function add_podroznik_bundle() {

    if ( ! function_exists('WC') ) return;

    if ( ! WC()->session ) WC()->initialize_session();
    if ( ! WC()->cart ) WC()->initialize_cart();

    foreach ( podroznik_bundle_products() as $product_id ) {
        WC()->cart->add_to_cart( $product_id, 1 );
    }

    wp_send_json_success();
}

add_action( 'wp_ajax_get_podroznik_state', 'get_podroznik_state' );
add_action( 'wp_ajax_nopriv_get_podroznik_state', 'get_podroznik_state' );

function get_podroznik_state() {
    wp_send_json_success( podroznik_cart_state() );
}

add_action( 'wp_footer', function () {

    if ( ! function_exists('WC') ) return;
    ?>
    <script>
    jQuery(function ($) {

        const $podr  = $('.podr');
        const $podr2 = $('.podr2');
        const $list  = $podr2.find('.podr-u');

        // Jeśli na stronie nie ma elementów Podróżnika — przerwij od razu.
        // Bez tego fetchState() wysyłał zbędne zapytanie AJAX na KAŻDEJ stronie.
        if ( ! $podr.length && ! $podr2.length && ! $('#podroznik').length ) {
            return;
        }

        function render(state) {

            $podr.removeClass('podr-a');
            $podr2.removeClass('podr-a');
            $list.empty();

            if (state.has_any_bundle && state.missing_items.length === 0) {
                $podr.addClass('podr-a');
            }

            if (state.has_any_bundle && state.missing_items.length > 0) {
                $podr2.addClass('podr-a');

                const links = state.missing_items.map(item =>
                    `<a href="${item.url}" class="podr-link">${item.name}</a>`
                );

                $list.html(links.join(', '));
            }
        }

        function fetchState() {
            $.post(ajaxurl, {
                action: 'get_podroznik_state'
            }, function (response) {
                if (response.success) {
                    render(response.data);
                }
            });
        }

        $('#podroznik').on('click', function (e) {
            e.preventDefault();

            $.post(ajaxurl, {
                action: 'add_podroznik_bundle'
            }, function () {

                window.dataLayer = window.dataLayer || [];
                dataLayer.push({
                    event: 'add_to_cart',
                    ecommerce: {
                        currency: 'PLN',
                        items: [
                            { item_id: '5253', quantity: 1 },
                            { item_id: '5272', quantity: 1 },
                            { item_id: '5257', quantity: 1 },
                            { item_id: '5263', quantity: 1 }
                        ]
                    }
                });

                window.location.href = '/koszyk/';
            });
        });

        fetchState();

        $(document.body).on(
            'removed_from_cart updated_cart_totals wc_fragments_refreshed',
            fetchState
        );

    });
    </script>
    <?php
});

// Ogranicz każdy produkt do 1 sztuki w koszyku
add_filter('woocommerce_add_to_cart_quantity', function($quantity, $product_id) {
    return 1;
}, 10, 2);

add_filter('woocommerce_cart_item_quantity', function($product_quantity, $cart_item_key, $cart_item) {
    return '1';
}, 10, 3);

add_filter('woocommerce_is_sold_individually', function($sold_individually, $product) {
    return true;
}, 10, 2);

// Usuń słowo "rozliczeniowy" ze wszystkich błędów WooCommerce
add_filter('woocommerce_add_error', function($error) {
    return str_replace(' rozliczeniowy', '', $error);
}, 10, 1);

add_action('wp_ajax_get_cart_items', 'ajax_get_cart_items');
add_action('wp_ajax_nopriv_get_cart_items', 'ajax_get_cart_items');
function ajax_get_cart_items() {
    $product_ids = [];
    foreach (WC()->cart->get_cart() as $cart_item) {
        $product_ids[] = $cart_item['product_id'];
    }
    wp_send_json_success($product_ids);
}

// Dodajemy atrybut "defer" dla wybranych skryptów (GSAP + ScrollTrigger + własny)
function add_defer_attribute($tag, $handle) {
    $scripts_to_defer = array('gsap', 'gsap-scrolltrigger', 'custom-animations');
    if (in_array($handle, $scripts_to_defer)) {
        return str_replace(' src', ' defer src', $tag);
    }
    return $tag;
}
add_filter('script_loader_tag', 'add_defer_attribute', 10, 2);

add_filter( 'gettext', 'moje_tlumaczenia_woocommerce_login', 20, 3 );
function moje_tlumaczenia_woocommerce_login( $translated_text, $text, $domain ) {
    if ( $domain === 'woocommerce' ) {
        switch ( $text ) {
            case 'Create an account':
                $translated_text = 'Utwórz konto';
                break;
            case 'Remember me':
                $translated_text = 'Zapamiętaj mnie';
                break;
            case 'Lost your password?':
                $translated_text = 'Nie pamiętasz hasła?';
                break;
        }
    }
    return $translated_text;
}

function my_custom_checkout_button_text() {
    return 'Rezerwuj';
}
add_filter( 'woocommerce_order_button_text', 'my_custom_checkout_button_text' );


add_action( 'woocommerce_before_calculate_totals', 'apply_discount_for_wizyta_tagged_products', 999 );
function apply_discount_for_wizyta_tagged_products( $cart ) {
    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;
    if ( empty( $cart->get_cart() ) ) return;

    $discount_tag      = 'szczepienie-znizka';
    $target_product_id = 4987;
    $discount_price    = 70;
    $price_to_discount = 150;

    $found_wizyta       = false;
    $found_discount_tag = false;
    $wizyta_product     = null;
    $current_price      = null;
    $discount_applied   = false;

    foreach ( $cart->get_cart() as $cart_item ) {
        $product    = $cart_item['data'];
        $product_id = $product->get_id();

        if ( $product_id === $target_product_id ) {
            $found_wizyta   = true;
            $wizyta_product = $product;
            $current_price  = (float) $product->get_price();
            continue;
        }

        $product_tags = wp_get_post_terms( $product_id, 'product_tag', [ 'fields' => 'slugs' ] );

        if ( in_array( $discount_tag, $product_tags, true ) ) {
            $found_discount_tag = true;
        }
    }

    if (
        $found_wizyta &&
        $found_discount_tag &&
        $wizyta_product &&
        abs( $current_price - $price_to_discount ) < 0.01
    ) {
        $wizyta_product->set_price( $discount_price );
        $discount_applied = true;
    }

    if ( did_action( 'wp_footer' ) || did_action( 'woocommerce_after_cart' ) ) {
        wc_enqueue_js( "
            jQuery(function($){
                if (" . ( $discount_applied ? 'true' : 'false' ) . ") {
                    $('.zer').addClass('przyznano');
                } else {
                    $('.zer').removeClass('przyznano');
                }
            });
        " );
    } else {
        add_action( 'wp_footer', function() use ( $discount_applied ) {
            wc_enqueue_js( "
                jQuery(function($){
                    if (" . ( $discount_applied ? 'true' : 'false' ) . ") {
                        $('.zer').addClass('przyznano');
                    } else {
                        $('.zer').removeClass('przyznano');
                    }
                });
            " );
        });
    }
}

// ============================================
// PROBLEM 1: FORCE LOAD WOOCOMMERCE CART
// Ograniczone tylko do stron koszyka/checkout/konta
// ============================================
add_action( 'wp_loaded', function() {
    if ( function_exists( 'WC' ) && WC()->cart === null && !is_admin() ) {
        if ( is_cart() || is_checkout() || is_account_page() ) {
            wc_load_cart();
        }
    }
});

// ============================================
// Kod działa TYLKO poza stroną /koszyk/
// ============================================
add_action('wp_footer', function() {
    ?>
    <script>
    jQuery(function($) {

        // Usunięcie produktu 4987 → przekierowanie na /koszyk/
        $(document).on('click', 'a.remove', function() {
            const productId = $(this).data('product_id');
            if (parseInt(productId, 10) === 4987) {
                setTimeout(() => {
                    window.location.href = '/koszyk/';
                }, 500);
            }
        });

        // Klik w przycisk restore-item → zawsze reload
        $(document).on('click', 'a.restore-item', function() {
            setTimeout(() => {
                location.reload();
            }, 500);
        });

    });
    </script>
    <?php
});


// ============================================
// SHORTCODE
// ============================================
function elementor_cart_shortcode() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        return '<p>WooCommerce nie jest aktywne.</p>';
    }

    ob_start(); ?>
    <div class="elementor-cart-widget">
        <?php echo elementor_cart_content(); ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode( 'elementor_cart', 'elementor_cart_shortcode' );


// ============================================
// GENERATOR TREŚCI KOSZYKA
// ============================================
function elementor_cart_content() {
    if ( ! WC()->cart ) return '';
    
    if ( WC()->cart->is_empty() ) {
        return '<p class="cart-empty">Twój koszyk jest pusty.</p>';
    }
    
    $user_id = get_current_user_id();
    $cache_key = 'elementor_cart_' . ($user_id ?: WC()->session->get_customer_id());
    $cached = get_transient($cache_key);
    
    if ($cached !== false) {
        return $cached;
    }
    
    ob_start(); ?>
    <ul class="cart-items">
        <?php foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) :
            $product    = $cart_item['data'];
            $quantity   = $cart_item['quantity'];
            $price      = WC()->cart->get_product_price( $product );
            $permalink  = $product->is_visible() ? $product->get_permalink() : '';
            $remove_url = wc_get_cart_remove_url( $cart_item_key );
        ?>
            <li class="cart-item">
                <div class="cart-details">
                    <a href="<?php echo esc_url( $permalink ); ?>">
                        <?php echo esc_html( $product->get_name() ); ?>
                    </a>
                    <span class="cart-quantity">x<?php echo esc_html( $quantity ); ?></span>
                    <span class="cart-price"><?php echo $price; ?></span>
                    <a href="<?php echo esc_url( $remove_url ); ?>"
                       class="remove-item"
                       aria-label="Usuń ten produkt z koszyka">&times;</a>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
    <div class="cart-total">
        <strong>Razem:</strong> <?php echo WC()->cart->get_cart_total(); ?>
    </div>
    <?php
    $output = ob_get_clean();
    set_transient($cache_key, $output, 60);
    return $output;
}

// Wyczyść cache koszyka gdy zmienia się zawartość
add_action('woocommerce_cart_updated', function() {
    $user_id = get_current_user_id();
    $cache_key = 'elementor_cart_' . ($user_id ?: WC()->session->get_customer_id());
    delete_transient($cache_key);
});


// ============================================
// AJAX — odświeżanie koszyka
// ============================================
add_action( 'wp_ajax_refresh_elementor_cart', 'refresh_elementor_cart' );
add_action( 'wp_ajax_nopriv_refresh_elementor_cart', 'refresh_elementor_cart' );

function refresh_elementor_cart() {
    echo elementor_cart_content();
    wp_die();
}


// ============================================
// FOOTER — główny refreshCart()
// ============================================
add_action( 'wp_footer', function() {
    if ( ! is_admin() ) : ?>
        <script type="text/javascript">
        jQuery(function($){

            $(document.body).on('removed_from_cart', function(event, fragments, cart_hash, button){

                let productId = button?.data('product_id');

                window.dataLayer = window.dataLayer || [];
                dataLayer.push({
                    event: 'remove_from_cart',
                    ecommerce: {
                        items: productId ? [{
                            item_id: productId,
                            quantity: 1
                        }] : []
                    }
                });
            });

            function refreshCart() {
                $.post(
                    '<?php echo admin_url("admin-ajax.php"); ?>',
                    { action: 'refresh_elementor_cart' },
                    function(response){
                        let widget = $('.elementor-cart-widget');
                        if (widget.length > 0) {
                            widget.first().html(response);
                        }
                    }
                );
            }

            $(document.body).on('added_to_cart', refreshCart);
            $(document.body).on('removed_from_cart', refreshCart);

            $(document).on('click', '.elementor-cart-widget .remove-item', function(e){
                e.preventDefault();
                $.get($(this).attr('href'), refreshCart);
            });

        });
        </script>
    <?php endif;
});

//** Enable upload for webp image files.*/
function webp_upload_mimes($existing_mimes) {
    $existing_mimes['webp'] = 'image/webp';
    return $existing_mimes;
}
add_filter('mime_types', 'webp_upload_mimes');

/** Enable preview / thumbnail for webp image files.*/
function webp_is_displayable($result, $path) {
    if ($result === false) {
        $displayable_image_types = array( IMAGETYPE_WEBP );
        $info = @getimagesize( $path );

        if (empty($info)) {
            $result = false;
        } elseif (!in_array($info[2], $displayable_image_types)) {
            $result = false;
        } else {
            $result = true;
        }
    }

    return $result;
}
add_filter('file_is_displayable_image', 'webp_is_displayable', 10, 2);

////////////////////////////////////
/* Kod od Dawida */
////////////////////////////////////
// functions.php
add_action('wp_footer', function() {
    ?><script>var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';</script><?php
});

add_action('wp_footer', function () {
    ?>
    <script>
    // === SUPER MOCNA OCHRONA PRZED DUPLIKATAMI ===
    if (window.cf7LambdaProcessedForms === undefined) {
        window.cf7LambdaProcessedForms = {};
        console.log('Inicjalizacja CF7 Lambda handlera – tylko raz');
    }

    if (!window.cf7LambdaListenerAdded) {
        window.cf7LambdaListenerAdded = true;

        document.addEventListener('wpcf7mailsent', function handler(event) {
            const formId = event.detail.contactFormId;
            const unitTag = event.detail.unit_tag;

            if (window.cf7LambdaProcessedForms[unitTag]) {
                console.log('Ten formularz (' + unitTag + ') już przetworzony – blokuję drugi request');
                return;
            }

            window.cf7LambdaProcessedForms[unitTag] = true;

            console.log('wpcf7mailsent – sukces, przetwarzam formularz:', formId, unitTag);

            const allowedIds = [4158, 9891, 12205, 19066, 19077, 19082];
            if (!allowedIds.includes(formId)) return;

            const isPillDayAfter = formId === 12205;

            let container = event.detail.container ||
                            (unitTag ? document.querySelector('.' + unitTag) : null) ||
                            document.querySelector('.wpcf7-form.sent')?.closest('.wpcf7');

            if (!container) {
                console.error('Kontener nie znaleziony');
                return;
            }

            // Blokujemy pola
            container.querySelectorAll('input, textarea, select, button')
                .forEach(el => el.disabled = true);

            // Wysyłka do Lambdy – TYLKO JEDEN RAZ
            const formData = new FormData();
            event.detail.inputs.forEach(input => formData.append(input.name, input.value));

            const declarationIds = [4158, 19066, 19077, 19082];

            let action = declarationIds.includes(formId) ? 'save_declaration' :
                         formId === 9891 ? 'process_declaration' :
                         'process_pill_day_after';

            formData.append('action', action);

            console.log('Wysyłam pojedynczy request do Lambdy (action: ' + action + ')');

            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(r => r.ok ? r.json() : Promise.reject())
            .then(data => {
                const status = data?.success && data?.data?.status ? data.data.status : 'error';
                let url = '/potwierdzenie-deklaracji/?status=';
                if (formId === 9891) url = '/wgrano-deklaracje/?status=';
                if (isPillDayAfter) url = '/potwierdzenie-tabletka/?status=';
                window.location.href = url + encodeURIComponent(status);
            })
            .catch(err => {
                console.error('Błąd Lambdy:', err);
                window.location.href = '/potwierdzenie-deklaracji/?status=error';
            });

        }, false);

        console.log('Listener wpcf7mailsent dodany – tylko jeden');
    }
    </script>
    <?php
}, 20);

/* --------------------------------------------------------------
   2. AJAX – SAVE DECLARATION (STEP 1) → Send ALL form fields as JSON
   -------------------------------------------------------------- */
add_action('wp_ajax_save_declaration', 'save_declaration_handler');
add_action('wp_ajax_nopriv_save_declaration', 'save_declaration_handler');
function save_declaration_handler() {
    $data = [];
    foreach ($_POST as $key => $value) {
        if ($key === 'action' || $key === '_wpcf7_unit_tag') continue;
        $data[$key] = is_array($value) ? array_map('sanitize_text_field', $value) : sanitize_text_field($value);
    }

    error_log('SAVE DECLARATION PAYLOAD: ' . json_encode($data));

    $response = wp_remote_post(
        'https://f9umlne3n6.execute-api.eu-north-1.amazonaws.com/v1/saveDataFromDeclarations',
        [
            'headers' => [
                'Content-Type' => 'application/json',
                'x-api-key' => '8XXS5t05ps23xV43u7B8B4Dd9SnFYNy826NN7rlj',
            ],
            'body' => json_encode($data),
            'timeout' => 30,
        ]
    );

    if (is_wp_error($response)) {
        error_log('SAVE LAMBDA ERROR: ' . $response->get_error_message());
        wp_send_json_success(['status' => 'error']);
    }

    $body = json_decode(wp_remote_retrieve_body($response), true) ?: [];
    $statusToRead = $body['statusToRead'] ?? '';

    $statusMap = [
        'already_patient' => 'already_patient',
        'success' => 'success',
        'error' => 'error',
    ];

    $status = $statusMap[$statusToRead] ?? 'error';

    wp_send_json_success(['status' => $status]);
}

/* --------------------------------------------------------------
   3. AJAX – PROCESS DECLARATION (STEP 2)
   -------------------------------------------------------------- */
add_action('wp_ajax_process_declaration', 'process_declaration_handler');
add_action('wp_ajax_nopriv_process_declaration', 'process_declaration_handler');
function process_declaration_handler() {

    if (empty($_FILES['plik']) || $_FILES['plik']['error'] !== UPLOAD_ERR_OK) {
        error_log('PROCESS: No file uploaded or upload error');
        wp_send_json_success(['status' => 'error']);
    }

    if (empty($_POST['sign_file_approve'])) {
        error_log('PROCESS: Missing sign_file_approve');
        wp_send_json_success(['status' => 'error']);
    }

    $file_path = $_FILES['plik']['tmp_name'];
    $file_content = file_get_contents($file_path);
    if ($file_content === false) {
        error_log('PROCESS: Failed to read file');
        wp_send_json_success(['status' => 'error']);
    }

    $base64 = base64_encode($file_content);

    $payload = [
        'plik' => [
            'filename' => sanitize_file_name($_FILES['plik']['name']),
            'content'  => $base64,
            'mime'     => $_FILES['plik']['type'] ?? 'application/octet-stream'
        ],
        'sign_file_approve' => sanitize_text_field($_POST['sign_file_approve'])
    ];

    error_log('PROCESS DECLARATION PAYLOAD: ' . json_encode($payload));

    $response = wp_remote_post(
        'https://f9umlne3n6.execute-api.eu-north-1.amazonaws.com/v1/processDeclarations',
        [
            'headers' => [
                'Content-Type' => 'application/json',
                'x-api-key' => '8XXS5t05ps23xV43u7B8B4Dd9SnFYNy826NN7rlj',
            ],
            'body' => json_encode($payload),
            'timeout' => 60,
        ]
    );

    if (is_wp_error($response)) {
        error_log('PROCESS LAMBDA WP ERROR: ' . $response->get_error_message());
        wp_send_json_success(['status' => 'error']);
    }

    $http_code = wp_remote_retrieve_response_code($response);
    $body_raw = wp_remote_retrieve_body($response);
    error_log("LAMBDA HTTP: $http_code | BODY: $body_raw");

    $body = json_decode($body_raw, true) ?: [];
    $statusToRead = $body['statusToRead'] ?? '';

    if ($statusToRead === 'already_patient') {
        $status = 'already_patient';
    } elseif ($statusToRead === 'signature_incorrect') {
        $status = 'signature_incorrect';
    } else {
        $statusMap = ['success' => 'success', 'error' => 'error'];
        $status = $statusMap[$statusToRead] ?? 'error';
    }

    wp_send_json_success(['status' => $status]);
}

/* --------------------------------------------------------------
   4. AJAX – PROCESS EMERGENCY CONTRACEPTION (Tabletka dzień po)
   -------------------------------------------------------------- */
add_action('wp_ajax_process_pill_day_after', 'process_pill_day_after_handler');
add_action('wp_ajax_nopriv_process_pill_day_after', 'process_pill_day_after_handler');

function process_pill_day_after_handler() {
    error_log('PILL DAY AFTER: Handler wywołany');

    $data = [];
    foreach ($_POST as $key => $value) {
        if ($key === 'action' || $key === '_wpcf7_unit_tag') continue;
        $data[$key] = is_array($value)
            ? array_map('sanitize_text_field', $value)
            : sanitize_text_field($value);
    }

    $required_fields = ['imie', 'nazwisko', 'pesel', 'miasto'];
    foreach ($required_fields as $field) {
        if (empty($data[$field])) {
            error_log("PILL DAY AFTER: Brak wymaganego pola: $field");
            wp_send_json_success(['status' => 'error']);
        }
    }

    error_log('PILL DAY AFTER → LAMBDA PAYLOAD: ' . json_encode($data, JSON_UNESCAPED_UNICODE));

    $response = wp_remote_post(
        'https://f9umlne3n6.execute-api.eu-north-1.amazonaws.com/v1/pillDayAfter',
        [
            'headers' => [
                'Content-Type' => 'application/json',
                'x-api-key'    => '8XXS5t05ps23xV43u7B8B4Dd9SnFYNy826NN7rlj',
            ],
            'body'    => json_encode($data, JSON_UNESCAPED_UNICODE),
            'timeout' => 60,
        ]
    );

    if (is_wp_error($response)) {
        error_log('PILL DAY AFTER → LAMBDA WP_ERROR: ' . $response->get_error_message());
        wp_send_json_success(['status' => 'error']);
    }

    $http_code = wp_remote_retrieve_response_code($response);
    $body_raw  = wp_remote_retrieve_body($response);
    error_log("PILL DAY AFTER ← LAMBDA HTTP: $http_code | RAW BODY: $body_raw");

    if ($http_code !== 200) {
        wp_send_json_success(['status' => 'error']);
    }

    $body = json_decode($body_raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        wp_send_json_success(['status' => 'error']);
    }

    if (!empty($body['statusToRead']) && $body['statusToRead'] === 'no_declaration') {
        $status = 'no_declaration';
    } elseif (!empty($body['success']) && $body['success'] === true) {
        $status = 'success';
    } else {
        $status = 'error';
    }

    wp_send_json_success(['status' => $status]);
}

/* ============================================================
   Dr Teraz — Booknetic Full-Page Layout
   На сторінках з шорткодом/віджетом [booknetic]:
   ховає хедер і футер + додає відступи панелі бронювання.
   Вставити В КІНЕЦЬ functions.php теми.
   ============================================================ */
if ( ! function_exists( 'drteraz_is_booknetic_page' ) ) {
	function drteraz_is_booknetic_page() {
		static $cache = null;
		if ( $cache !== null ) {
			return $cache;
		}
		$cache = false;
		if ( ! is_singular() ) {
			return $cache;
		}
		$post = get_post();
		if ( ! $post ) {
			return $cache;
		}
		if ( strpos( (string) $post->post_content, '[booknetic' ) !== false ) {
			$cache = true;
			return $cache;
		}
		$ed = get_post_meta( $post->ID, '_elementor_data', true );
		if ( $ed && strpos( $ed, '"widgetType":"booknetic"' ) !== false ) {
			$cache = true;
		}
		return $cache;
	}

	add_filter( 'body_class', function ( $classes ) {
		if ( drteraz_is_booknetic_page() ) {
			$classes[] = 'booknetic-fullpage';
		}
		return $classes;
	} );

	add_action( 'wp_head', function () {
		if ( ! drteraz_is_booknetic_page() ) {
			return;
		}
		echo '<style id="dr-booknetic-fullpage">'
			. 'body.booknetic-fullpage header.elementor-location-header,'
			. 'body.booknetic-fullpage footer.elementor-location-footer{display:none !important;}'
			. 'body.booknetic-fullpage .booknetic_appointment{margin-top:70px !important;margin-bottom:40px !important;}'
			. '</style>' . "\n";
	}, 99 );
}

/**
 * Widget Elementora: Lista szczepień (dynamiczna tabela produktów).
 * Rejestracja stylów/skryptów + samego widgetu — wyłącznie w motywie.
 */
add_action( 'wp_enqueue_scripts', function () {
	wp_register_style(
		'tz-vaccine-list',
		get_stylesheet_directory_uri() . '/css/vaccine-list.css',
		[],
		'1.4'
	);
	wp_register_script(
		'tz-vaccine-list',
		get_stylesheet_directory_uri() . '/js/vaccine-list.js',
		[],
		'1.3',
		true
	);
} );

add_action( 'elementor/widgets/register', function ( $widgets_manager ) {
	require_once get_stylesheet_directory() . '/inc/class-tz-vaccine-list-widget.php';
	$widgets_manager->register( new \TZ_Vaccine_List_Widget() );
} );

// Import oferty z xlsx (Narzędzia -> Import oferty (TZ)) - tylko w adminie.
if ( is_admin() ) {
	require_once get_stylesheet_directory() . '/inc/tz-offer-import.php';
}

/**
 * Booknetic preselect: na stronach miast szczepień (/szczepienia/{miasto})
 * wszystkie linki do /rezerwuj/ dostają parametry preselect, które Booknetic
 * czyta z URL (?location= i ?service= - patrz BookneticShortcode).
 *
 * Łódź i Gdańsk mają po jednej placówce -> preselect lokalizacji + usługi;
 * Warszawa i Poznań mają kilka placówek -> preselect tylko usługi.
 */
function tz_booknetic_preselect_params() {
	if ( ! is_singular( 'product' ) ) {
		return [];
	}
	$map = [
		'lodz'     => [ 'location' => 3, 'service' => 17 ], // Łódź Śródmieście / Szczepienie z konsultacją
		'gdansk'   => [ 'location' => 7, 'service' => 17 ], // Gdańsk Morena / Szczepienie z konsultacją
		'warszawa' => [ 'service' => 12 ],                  // Szczepienie wraz z konsultacją
		'poznan'   => [ 'service' => 22 ],                  // Szczepienie w Poznaniu
	];
	$post = get_queried_object();
	return ( $post && isset( $post->post_name, $map[ $post->post_name ] ) ) ? $map[ $post->post_name ] : [];
}

function tz_booknetic_preselect_rewrite( $content ) {
	$params = tz_booknetic_preselect_params();
	if ( ! $params || ! is_string( $content ) || strpos( $content, 'rezerwuj' ) === false ) {
		return $content;
	}
	return preg_replace_callback(
		'~href=("|\')([^"\']*/rezerwuj/?[^"\']*)\1~i',
		function ( $m ) use ( $params ) {
			$url = html_entity_decode( $m[2] );
			foreach ( $params as $key => $value ) {
				$url = add_query_arg( $key, $value, $url );
			}
			return 'href=' . $m[1] . esc_url( $url ) . $m[1];
		},
		$content
	);
}
add_filter( 'the_content', 'tz_booknetic_preselect_rewrite', 99 );
add_filter( 'elementor/frontend/the_content', 'tz_booknetic_preselect_rewrite', 99 );
