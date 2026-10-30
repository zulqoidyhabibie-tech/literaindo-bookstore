<?php
/**
 * Plugin Name:       Literaindo Bookstore
 * Plugin URI:        https://github.com/zulqoidyhabibie-tech/literaindo-bookstore
 * Description:       Fungsi khusus Literaindo Press Bookstore: metadata buku (penulis, ISBN, status terbit), format cetak/e-book, pembayaran QRIS BTN dengan unggah bukti bayar, perlindungan e-book, dan daftar periksa Persiapan Toko.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * WC requires at least: 8.0
 * Author:            Literaindo Press
 * License:           GPL-2.0-or-later
 * Text Domain:       literaindo-bookstore
 *
 * @package literaindo-bookstore
 */

defined( 'ABSPATH' ) || exit;

define( 'LITERAINDO_BOOKSTORE_VERSION', '0.1.0' );
define( 'LITERAINDO_BOOKSTORE_FILE', __FILE__ );
define( 'LITERAINDO_BOOKSTORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'LITERAINDO_BOOKSTORE_URL', plugin_dir_url( __FILE__ ) );

require_once LITERAINDO_BOOKSTORE_DIR . 'includes/functions.php';

/**
 * Kompatibel dengan High-Performance Order Storage (HPOS): semua akses pesanan
 * memakai API WC_Order, bukan post meta langsung.
 */
add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

/**
 * Muat modul setelah WooCommerce siap.
 */
function literaindo_bookstore_init() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action(
			'admin_notices',
			static function () {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'Literaindo Bookstore membutuhkan WooCommerce yang aktif.', 'literaindo-bookstore' ) . '</p></div>';
			}
		);
		return;
	}

	require_once LITERAINDO_BOOKSTORE_DIR . 'includes/class-lit-settings.php';
	require_once LITERAINDO_BOOKSTORE_DIR . 'includes/class-lit-book-format.php';
	require_once LITERAINDO_BOOKSTORE_DIR . 'includes/class-lit-book-meta.php';
	require_once LITERAINDO_BOOKSTORE_DIR . 'includes/class-lit-qris-gateway.php';
	require_once LITERAINDO_BOOKSTORE_DIR . 'includes/class-lit-qris-proof.php';
	require_once LITERAINDO_BOOKSTORE_DIR . 'includes/class-lit-ebook.php';
	require_once LITERAINDO_BOOKSTORE_DIR . 'includes/class-lit-setup.php';

	Lit_Settings::init();
	Lit_Book_Format::init();
	Lit_Book_Meta::init();
	Lit_QRIS_Proof::init();
	Lit_Ebook::init();
	if ( is_admin() ) {
		Lit_Setup::init();
	}

	add_filter( 'woocommerce_payment_gateways', 'literaindo_bookstore_register_gateway' );
	add_action( 'woocommerce_blocks_payment_method_type_registration', 'literaindo_bookstore_register_blocks_gateway' );
	add_action( 'wp_enqueue_scripts', 'literaindo_bookstore_enqueue_assets' );
}
add_action( 'plugins_loaded', 'literaindo_bookstore_init', 20 );

function literaindo_bookstore_register_gateway( $gateways ) {
	$gateways[] = 'Lit_QRIS_Gateway';
	return $gateways;
}

/**
 * @param Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $registry
 */
function literaindo_bookstore_register_blocks_gateway( $registry ) {
	require_once LITERAINDO_BOOKSTORE_DIR . 'includes/class-lit-qris-blocks.php';
	$registry->register( new Lit_QRIS_Blocks() );
}

function literaindo_bookstore_enqueue_assets() {
	if ( ! is_woocommerce() && ! is_cart() && ! is_checkout() && ! is_account_page() && ! is_front_page() ) {
		return;
	}
	wp_enqueue_style(
		'literaindo-bookstore',
		LITERAINDO_BOOKSTORE_URL . 'assets/css/frontend.css',
		array(),
		LITERAINDO_BOOKSTORE_VERSION
	);
}

/**
 * Aktivasi: buat istilah format, atur permalink /produk/ dan /kategori/ bila
 * masih bawaan, lalu siapkan folder privat untuk bukti bayar.
 */
function literaindo_bookstore_activate() {
	require_once LITERAINDO_BOOKSTORE_DIR . 'includes/class-lit-book-format.php';
	require_once LITERAINDO_BOOKSTORE_DIR . 'includes/class-lit-qris-proof.php';

	Lit_Book_Format::register_taxonomy();
	Lit_Book_Format::ensure_terms();

	$permalinks = (array) get_option( 'woocommerce_permalinks', array() );
	if ( empty( $permalinks['product_base'] ) ) {
		$permalinks['product_base'] = '/produk';
	}
	if ( empty( $permalinks['category_base'] ) ) {
		$permalinks['category_base'] = 'kategori';
	}
	update_option( 'woocommerce_permalinks', $permalinks );

	Lit_QRIS_Proof::protect_upload_dir();

	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'literaindo_bookstore_activate' );

register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );
