<?php
/**
 * Pengaturan umum: WooCommerce → Pengaturan → Produk → Literaindo.
 *
 * @package literaindo-bookstore
 */

defined( 'ABSPATH' ) || exit;

class Lit_Settings {

	const SECTION = 'literaindo';

	public static function init() {
		add_filter( 'woocommerce_get_sections_products', array( __CLASS__, 'add_section' ) );
		add_filter( 'woocommerce_get_settings_products', array( __CLASS__, 'settings' ), 10, 2 );
	}

	public static function publisher_name() {
		return (string) get_option( 'literaindo_publisher_name', 'Literaindo Press' );
	}

	public static function publisher_city() {
		return (string) get_option( 'literaindo_publisher_city', '' );
	}

	public static function add_section( $sections ) {
		$sections[ self::SECTION ] = __( 'Literaindo', 'literaindo-bookstore' );
		return $sections;
	}

	public static function settings( $settings, $current_section ) {
		if ( self::SECTION !== $current_section ) {
			return $settings;
		}

		return array(
			array(
				'title' => __( 'Data penerbit', 'literaindo-bookstore' ),
				'type'  => 'title',
				'desc'  => __( 'Ditampilkan di tab "Informasi Buku" setiap judul.', 'literaindo-bookstore' ),
				'id'    => 'literaindo_publisher',
			),
			array(
				'title'   => __( 'Nama penerbit', 'literaindo-bookstore' ),
				'id'      => 'literaindo_publisher_name',
				'type'    => 'text',
				'default' => 'Literaindo Press',
			),
			array(
				'title'   => __( 'Kota terbit', 'literaindo-bookstore' ),
				'id'      => 'literaindo_publisher_city',
				'type'    => 'text',
				'default' => '',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'literaindo_publisher',
			),
		);
	}
}
