<?php
/**
 * Literaindo Child: child theme Storefront untuk Literaindo Press Bookstore.
 *
 * Tema hanya mengurus tampilan. Data buku (penulis, ISBN, dsb.) disediakan
 * oleh plugin literaindo-bookstore melalui filter `literaindo_book_details`.
 *
 * @package literaindo-child
 */

defined( 'ABSPATH' ) || exit;

define( 'LITERAINDO_CHILD_VERSION', '0.1.0' );

require_once get_stylesheet_directory() . '/inc/customizer.php';
require_once get_stylesheet_directory() . '/inc/template-tags.php';
require_once get_stylesheet_directory() . '/inc/woocommerce.php';

/**
 * Font dan stylesheet utama. Prioritas 40 agar dimuat setelah gaya Storefront
 * (termasuk CSS inline dari Customizer) sehingga token warna kita yang menang.
 */
function literaindo_child_enqueue_assets() {
	wp_enqueue_style(
		'literaindo-fonts',
		'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Noto+Serif:wght@600;700&display=swap',
		array(),
		null
	);

	wp_enqueue_style(
		'literaindo-main',
		get_stylesheet_directory_uri() . '/assets/css/main.css',
		array( 'storefront-style' ),
		LITERAINDO_CHILD_VERSION
	);
}
add_action( 'wp_enqueue_scripts', 'literaindo_child_enqueue_assets', 40 );

/**
 * Preconnect ke Google Fonts supaya font tidak memperlambat LCP.
 */
function literaindo_child_resource_hints( $urls, $relation_type ) {
	if ( 'preconnect' === $relation_type ) {
		$urls[] = array( 'href' => 'https://fonts.gstatic.com', 'crossorigin' );
	}
	return $urls;
}
add_filter( 'wp_resource_hints', 'literaindo_child_resource_hints', 10, 2 );

/**
 * Samakan warna bawaan Customizer Storefront dengan palet blueprint.
 */
function literaindo_child_storefront_defaults( $defaults ) {
	return array_merge(
		$defaults,
		array(
			'storefront_heading_color'           => '#173B57',
			'storefront_text_color'              => '#2B2F33',
			'storefront_accent_color'            => '#173B57',
			'storefront_hero_heading_color'      => '#173B57',
			'storefront_hero_text_color'         => '#2B2F33',
			'storefront_header_background_color' => '#FFFFFF',
			'storefront_header_text_color'       => '#2B2F33',
			'storefront_header_link_color'       => '#173B57',
			'storefront_footer_background_color' => '#173B57',
			'storefront_footer_heading_color'    => '#FFFFFF',
			'storefront_footer_text_color'       => '#D9E2EA',
			'storefront_footer_link_color'       => '#FFFFFF',
			'storefront_button_background_color' => '#173B57',
			'storefront_button_text_color'       => '#FFFFFF',
			'storefront_button_alt_background_color' => '#873E4A',
			'storefront_button_alt_text_color'   => '#FFFFFF',
			'background_color'                   => 'F7F3EA',
		)
	);
}
add_filter( 'storefront_setting_default_values', 'literaindo_child_storefront_defaults' );

/**
 * Footer: ganti kredit Storefront dengan nama penerbit.
 */
add_filter( 'storefront_credit_link', '__return_false' );

function literaindo_child_copyright( $text ) {
	return sprintf(
		/* translators: 1: tahun, 2: nama situs */
		esc_html__( '© %1$s %2$s. Seluruh hak cipta dilindungi.', 'literaindo-child' ),
		esc_html( wp_date( 'Y' ) ),
		esc_html( get_bloginfo( 'name' ) )
	);
}
add_filter( 'storefront_copyright_text', 'literaindo_child_copyright' );

/**
 * Menu tambahan di footer untuk halaman bantuan dan kebijakan.
 */
function literaindo_child_register_menus() {
	register_nav_menus(
		array(
			'literaindo-footer' => __( 'Footer: Bantuan & Kebijakan', 'literaindo-child' ),
		)
	);
}
add_action( 'after_setup_theme', 'literaindo_child_register_menus' );

function literaindo_child_footer_menu() {
	if ( ! has_nav_menu( 'literaindo-footer' ) ) {
		return;
	}
	wp_nav_menu(
		array(
			'theme_location'  => 'literaindo-footer',
			'container'       => 'nav',
			'container_class' => 'literaindo-footer-menu',
			'container_aria_label' => __( 'Bantuan dan kebijakan', 'literaindo-child' ),
			'depth'           => 1,
		)
	);
}
add_action( 'storefront_footer', 'literaindo_child_footer_menu', 15 );

/**
 * Beranda tanpa sidebar.
 */
function literaindo_child_body_classes( $classes ) {
	if ( is_front_page() ) {
		$classes[] = 'storefront-full-width-content';
		$classes[] = 'lit-is-home';
	}
	return $classes;
}
add_filter( 'body_class', 'literaindo_child_body_classes' );
