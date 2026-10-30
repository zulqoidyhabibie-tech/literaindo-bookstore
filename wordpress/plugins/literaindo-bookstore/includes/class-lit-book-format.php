<?php
/**
 * Taksonomi "Format" (cetak / e-book) dengan URL /format/cetak/ dan /format/ebook/.
 *
 * Format diisi otomatis saat produk disimpan: produk (atau variasi) yang
 * virtual + bisa diunduh dianggap e-book, sisanya cetak.
 *
 * @package literaindo-bookstore
 */

defined( 'ABSPATH' ) || exit;

class Lit_Book_Format {

	const TAXONOMY = 'book_format';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_taxonomy' ) );
		add_action( 'woocommerce_after_product_object_save', array( __CLASS__, 'sync_product_format' ) );
		add_action( 'woocommerce_after_product_variation_object_save', array( __CLASS__, 'sync_product_format' ) );
	}

	public static function register_taxonomy() {
		register_taxonomy(
			self::TAXONOMY,
			'product',
			array(
				'labels'            => array(
					'name'          => __( 'Format', 'literaindo-bookstore' ),
					'singular_name' => __( 'Format', 'literaindo-bookstore' ),
					'menu_name'     => __( 'Format Buku', 'literaindo-bookstore' ),
				),
				'public'            => true,
				'hierarchical'      => true,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'rewrite'           => array( 'slug' => 'format', 'with_front' => false ),
				// Diisi otomatis; admin tidak perlu mencentang manual.
				'meta_box_cb'       => false,
			)
		);
	}

	public static function ensure_terms() {
		if ( ! term_exists( 'cetak', self::TAXONOMY ) ) {
			wp_insert_term( __( 'Buku Cetak', 'literaindo-bookstore' ), self::TAXONOMY, array( 'slug' => 'cetak' ) );
		}
		if ( ! term_exists( 'ebook', self::TAXONOMY ) ) {
			wp_insert_term( __( 'E-book', 'literaindo-bookstore' ), self::TAXONOMY, array( 'slug' => 'ebook' ) );
		}
	}

	/**
	 * Slug format yang dimiliki produk: 'cetak', 'ebook', atau keduanya.
	 *
	 * @param WC_Product $product
	 * @return string[]
	 */
	public static function detect_formats( $product ) {
		$items = array( $product );
		if ( $product->is_type( 'variable' ) ) {
			$items = array_filter( array_map( 'wc_get_product', $product->get_children() ) );
		}

		$formats = array();
		foreach ( $items as $item ) {
			$formats[] = ( $item->is_virtual() && $item->is_downloadable() ) ? 'ebook' : 'cetak';
		}
		return array_values( array_unique( $formats ) );
	}

	/**
	 * @param WC_Product $product
	 */
	public static function sync_product_format( $product ) {
		if ( $product->is_type( 'variation' ) ) {
			$parent = wc_get_product( $product->get_parent_id() );
			if ( $parent ) {
				self::sync_product_format( $parent );
			}
			return;
		}
		if ( ! $product->get_id() ) {
			return;
		}
		self::ensure_terms();
		wp_set_object_terms( $product->get_id(), self::detect_formats( $product ), self::TAXONOMY );
	}

	/**
	 * Label format yang mudah dibaca, misalnya "Cetak, E-book".
	 *
	 * @param WC_Product $product
	 * @return string
	 */
	public static function label( $product ) {
		$terms = get_the_terms( $product->get_id(), self::TAXONOMY );
		if ( ! $terms || is_wp_error( $terms ) ) {
			return '';
		}
		return implode( ', ', wp_list_pluck( $terms, 'name' ) );
	}
}
