<!DOCTYPE html>
<?php
/*
 * @package HelloElementor
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<?php $viewport_content = apply_filters( 'hello_elementor_viewport_content', 'width=device-width, initial-scale=1' ); ?>
	<meta name="viewport" content="<?php echo esc_attr( $viewport_content ); ?>">
	<meta name="msapplication-navbutton-color" content="#050505" />
	<meta name="apple-mobile-web-app-status-bar-style" content="#050505" />
	<!-- link rel="stylesheet" href="/wp-content/themes/hello-elementor-child/style.css?nocache <?php //echo date('h:i:s'); ?>"-->
	<!-- script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script --> 
	<!-- script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/gsap.min.js"></script -->
	<!-- script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/ScrollTrigger.min.js"></script -->
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap" onload="this.onload=null;this.rel='stylesheet'" />
	<noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap" /></noscript>

<!-- Google Tag Manager -->
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','GTM-5P7KVSBG');</script>
<!-- End Google Tag Manager -->	
	
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>> 
<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-5P7KVSBG"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->
	
<?php hello_elementor_body_open();	
if ( ! function_exists( 'elementor_theme_do_location' ) || ! elementor_theme_do_location( 'header' ) ) {get_template_part( 'template-parts/header' );} ?>