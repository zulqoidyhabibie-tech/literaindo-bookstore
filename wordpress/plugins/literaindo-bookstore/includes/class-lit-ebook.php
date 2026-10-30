<?php
/**
 * Perlindungan e-book dan aturan buku cetak vs e-book di checkout.
 *
 * - Pengaturan unduhan WooCommerce yang aman (lihat recommended_options()).
 * - Pembeli e-book wajib punya akun, karena unduhan hanya untuk pengguna yang login.
 * - Penjelasan singkat cara menerima e-book di halaman produk.
 *
 * Ongkos kirim otomatis hanya dihitung untuk buku cetak: e-book adalah produk
 * Virtual, dan WooCommerce tidak meminta alamat/ongkir untuk produk virtual.
 *
 * @package literaindo-bookstore
 */

defined( 'ABSPATH' ) || exit;

class Lit_Ebook {

	public static function init() {
		add_filter( 'woocommerce_checkout_registration_required', array( __CLASS__, 'require_account_for_ebooks' ) );
		add_filter( 'woocommerce_checkout_registration_enabled', array( __CLASS__, 'enable_registration_for_ebooks' ) );
		add_action( 'woocommerce_single_product_summary', array( __CLASS__, 'delivery_note' ), 35 );
	}

	/**
	 * Pengaturan unduhan yang direkomendasikan: opsi => [nilai, label].
	 */
	public static function recommended_options() {
		return array(
			'woocommerce_file_download_method'               => array( 'force', __( 'Metode unduhan: Paksa unduh (file tidak bisa diakses lewat URL langsung)', 'literaindo-bookstore' ) ),
			'woocommerce_downloads_require_login'            => array( 'yes', __( 'Unduhan hanya untuk pelanggan yang login', 'literaindo-bookstore' ) ),
			'woocommerce_downloads_grant_access_after_payment' => array( 'yes', __( 'Akses unduhan dibuka setelah pembayaran dikonfirmasi', 'literaindo-bookstore' ) ),
			'woocommerce_downloads_redirect_fallback_allowed' => array( 'no', __( 'Tidak ada pengalihan ke URL file asli bila unduhan gagal', 'literaindo-bookstore' ) ),
			'woocommerce_downloads_add_hash_to_filename'     => array( 'yes', __( 'Nama file e-book ditambah kode acak saat diunggah', 'literaindo-bookstore' ) ),
		);
	}

	/**
	 * @return array<string, bool> opsi => sudah sesuai rekomendasi?
	 */
	public static function option_status() {
		$status = array();
		foreach ( self::recommended_options() as $option => $rule ) {
			$status[ $option ] = get_option( $option ) === $rule[0];
		}
		return $status;
	}

	public static function apply_recommended_options() {
		foreach ( self::recommended_options() as $option => $rule ) {
			update_option( $option, $rule[0] );
		}
	}

	public static function cart_has_ebook() {
		if ( ! WC()->cart ) {
			return false;
		}
		foreach ( WC()->cart->get_cart() as $item ) {
			if ( ! empty( $item['data'] ) && $item['data']->is_downloadable() ) {
				return true;
			}
		}
		return false;
	}

	public static function require_account_for_ebooks( $required ) {
		return self::cart_has_ebook() ? true : $required;
	}

	public static function enable_registration_for_ebooks( $enabled ) {
		return self::cart_has_ebook() ? true : $enabled;
	}

	public static function delivery_note() {
		global $product;
		if ( ! $product || Lit_Book_Meta::is_upcoming( $product ) ) {
			return;
		}
		$formats = Lit_Book_Format::detect_formats( $product );
		if ( ! in_array( 'ebook', $formats, true ) ) {
			return;
		}
		echo '<p class="lit-delivery-note">' . esc_html__( 'E-book tersedia di menu Akun → Unduhan setelah pembayaran dikonfirmasi. Pembelian e-book memerlukan akun, tanpa ongkos kirim.', 'literaindo-bookstore' ) . '</p>';
	}
}
