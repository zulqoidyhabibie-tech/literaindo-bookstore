<?php
/**
 * Metadata buku: tab "Data Buku" di editor produk, status terbit, data untuk
 * tema (filter literaindo_book_author / literaindo_book_details), structured
 * data schema.org/Book, dan pencarian berdasarkan penulis atau ISBN.
 *
 * @package literaindo-bookstore
 */

defined( 'ABSPATH' ) || exit;

class Lit_Book_Meta {

	const STATUS_PUBLISHED = 'terbit';
	const STATUS_UPCOMING  = 'segera_terbit';

	/**
	 * Kunci meta => [label, tipe input, placeholder].
	 */
	public static function fields() {
		return array(
			'_lit_author'     => array( __( 'Penulis', 'literaindo-bookstore' ), 'text', __( 'Pisahkan beberapa penulis dengan koma', 'literaindo-bookstore' ) ),
			'_lit_editor'     => array( __( 'Editor', 'literaindo-bookstore' ), 'text', '' ),
			'_lit_isbn'       => array( __( 'ISBN (cetak)', 'literaindo-bookstore' ), 'text', '978-623-xxxx-xx-x' ),
			'_lit_eisbn'      => array( __( 'ISBN (e-book / PDF)', 'literaindo-bookstore' ), 'text', '978-623-xxxx-xx-x' ),
			'_lit_edition'    => array( __( 'Edisi / cetakan', 'literaindo-bookstore' ), 'text', __( 'Cetakan pertama', 'literaindo-bookstore' ) ),
			'_lit_year'       => array( __( 'Tahun terbit', 'literaindo-bookstore' ), 'number', '2026' ),
			'_lit_pages'      => array( __( 'Jumlah halaman', 'literaindo-bookstore' ), 'text', 'xii + 248' ),
			'_lit_dimensions' => array( __( 'Ukuran buku', 'literaindo-bookstore' ), 'text', '15,5 × 23 cm' ),
			'_lit_language'   => array( __( 'Bahasa', 'literaindo-bookstore' ), 'text', 'Indonesia' ),
		);
	}

	public static function init() {
		add_filter( 'woocommerce_product_data_tabs', array( __CLASS__, 'add_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( __CLASS__, 'render_panel' ) );
		add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'save' ) );
		add_action( 'admin_notices', array( __CLASS__, 'isbn_notice' ) );

		add_filter( 'literaindo_book_author', array( __CLASS__, 'filter_author' ), 10, 2 );
		add_filter( 'literaindo_book_details', array( __CLASS__, 'filter_details' ), 10, 2 );

		add_filter( 'woocommerce_is_purchasable', array( __CLASS__, 'block_upcoming_purchase' ), 10, 2 );
		add_filter( 'woocommerce_variation_is_purchasable', array( __CLASS__, 'block_upcoming_purchase' ), 10, 2 );
		add_action( 'woocommerce_single_product_summary', array( __CLASS__, 'upcoming_notice' ), 25 );
		add_action( 'woocommerce_before_shop_loop_item_title', array( __CLASS__, 'upcoming_badge' ), 9 );

		add_filter( 'woocommerce_structured_data_product', array( __CLASS__, 'structured_data' ), 10, 2 );

		add_filter( 'posts_search', array( __CLASS__, 'extend_search' ), 10, 2 );
	}

	/* ---------- Data ---------- */

	public static function get( $product, $key ) {
		$product = $product instanceof WC_Product ? $product : wc_get_product( $product );
		if ( ! $product ) {
			return '';
		}
		if ( $product->is_type( 'variation' ) ) {
			$product = wc_get_product( $product->get_parent_id() );
		}
		return $product ? (string) $product->get_meta( $key ) : '';
	}

	public static function status( $product ) {
		$status = self::get( $product, '_lit_status' );
		return self::STATUS_UPCOMING === $status ? self::STATUS_UPCOMING : self::STATUS_PUBLISHED;
	}

	public static function is_upcoming( $product ) {
		return self::STATUS_UPCOMING === self::status( $product );
	}

	/* ---------- Admin ---------- */

	public static function add_tab( $tabs ) {
		$tabs['literaindo_book'] = array(
			'label'    => __( 'Data Buku', 'literaindo-bookstore' ),
			'target'   => 'literaindo_book_data',
			'class'    => array(),
			'priority' => 15,
		);
		return $tabs;
	}

	public static function render_panel() {
		global $product_object;
		?>
		<div id="literaindo_book_data" class="panel woocommerce_options_panel hidden">
			<div class="options_group">
				<?php
				woocommerce_wp_select(
					array(
						'id'          => '_lit_status',
						'label'       => __( 'Status terbit', 'literaindo-bookstore' ),
						'value'       => self::status( $product_object ),
						'options'     => array(
							self::STATUS_PUBLISHED => __( 'Sudah terbit (bisa dibeli)', 'literaindo-bookstore' ),
							self::STATUS_UPCOMING  => __( 'Segera terbit (tampil, belum bisa dibeli)', 'literaindo-bookstore' ),
						),
						'desc_tip'    => true,
						'description' => __( 'Pakai "Segera terbit" untuk buku yang halamannya dibutuhkan sebagai tautan publikasi saat mengajukan ISBN.', 'literaindo-bookstore' ),
					)
				);
				?>
			</div>
			<div class="options_group">
				<?php
				foreach ( self::fields() as $key => $field ) {
					woocommerce_wp_text_input(
						array(
							'id'          => $key,
							'label'       => $field[0],
							'type'        => $field[1],
							'placeholder' => $field[2],
							'value'       => $product_object->get_meta( $key ),
						)
					);
				}
				?>
				<p class="form-field"><em><?php esc_html_e( 'ISBN boleh dikosongkan selama masih dalam proses pengajuan.', 'literaindo-bookstore' ); ?></em></p>
			</div>
		</div>
		<?php
	}

	/**
	 * @param WC_Product $product
	 */
	public static function save( $product ) {
		// Nonce sudah diperiksa WooCommerce sebelum hook ini dipanggil.
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$status = isset( $_POST['_lit_status'] ) ? sanitize_key( wp_unslash( $_POST['_lit_status'] ) ) : self::STATUS_PUBLISHED;
		$product->update_meta_data( '_lit_status', self::STATUS_UPCOMING === $status ? self::STATUS_UPCOMING : self::STATUS_PUBLISHED );

		$invalid = array();
		foreach ( array_keys( self::fields() ) as $key ) {
			$value = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
			$product->update_meta_data( $key, $value );

			if ( in_array( $key, array( '_lit_isbn', '_lit_eisbn' ), true ) ) {
				// Salinan tanpa tanda hubung untuk pencarian.
				$product->update_meta_data( $key . '_search', literaindo_normalize_isbn( $value ) );
				if ( '' !== $value && ! literaindo_is_valid_isbn( $value ) ) {
					$invalid[] = $value;
				}
			}
		}
		// phpcs:enable

		if ( $invalid ) {
			set_transient( 'lit_isbn_invalid_' . get_current_user_id(), $invalid, 60 );
		}
	}

	public static function isbn_notice() {
		$key     = 'lit_isbn_invalid_' . get_current_user_id();
		$invalid = get_transient( $key );
		if ( ! $invalid ) {
			return;
		}
		delete_transient( $key );
		printf(
			'<div class="notice notice-warning is-dismissible"><p>%s <strong>%s</strong></p></div>',
			esc_html__( 'Periksa kembali ISBN berikut, angka kontrolnya tidak valid:', 'literaindo-bookstore' ),
			esc_html( implode( ', ', (array) $invalid ) )
		);
	}

	/* ---------- Tampilan (dipakai child theme) ---------- */

	public static function filter_author( $author, $product ) {
		$value = self::get( $product, '_lit_author' );
		return '' !== $value ? $value : $author;
	}

	public static function filter_details( $details, $product ) {
		$isbn_pending = __( 'Dalam proses pengajuan', 'literaindo-bookstore' );
		$upcoming     = self::is_upcoming( $product );
		$city         = Lit_Settings::publisher_city();
		$publisher    = Lit_Settings::publisher_name() . ( $city ? ', ' . $city : '' );
		$isbn         = self::get( $product, '_lit_isbn' );
		$eisbn        = self::get( $product, '_lit_eisbn' );

		$rows = array(
			array( 'label' => __( 'Judul', 'literaindo-bookstore' ), 'value' => $product->get_name() ),
			array( 'label' => __( 'Penulis', 'literaindo-bookstore' ), 'value' => self::get( $product, '_lit_author' ) ),
			array( 'label' => __( 'Editor', 'literaindo-bookstore' ), 'value' => self::get( $product, '_lit_editor' ) ),
			array( 'label' => __( 'Penerbit', 'literaindo-bookstore' ), 'value' => $publisher ),
			array( 'label' => __( 'Status', 'literaindo-bookstore' ), 'value' => $upcoming ? __( 'Segera Terbit', 'literaindo-bookstore' ) : __( 'Sudah Terbit', 'literaindo-bookstore' ) ),
			array( 'label' => __( 'ISBN', 'literaindo-bookstore' ), 'value' => '' !== $isbn ? $isbn : ( $upcoming ? $isbn_pending : '' ) ),
			array( 'label' => __( 'e-ISBN', 'literaindo-bookstore' ), 'value' => $eisbn ),
			array( 'label' => __( 'Edisi', 'literaindo-bookstore' ), 'value' => self::get( $product, '_lit_edition' ) ),
			array( 'label' => __( 'Tahun terbit', 'literaindo-bookstore' ), 'value' => self::get( $product, '_lit_year' ) ),
			array( 'label' => __( 'Jumlah halaman', 'literaindo-bookstore' ), 'value' => self::get( $product, '_lit_pages' ) ),
			array( 'label' => __( 'Ukuran', 'literaindo-bookstore' ), 'value' => self::get( $product, '_lit_dimensions' ) ),
			array( 'label' => __( 'Bahasa', 'literaindo-bookstore' ), 'value' => self::get( $product, '_lit_language' ) ),
			array( 'label' => __( 'Format', 'literaindo-bookstore' ), 'value' => Lit_Book_Format::label( $product ) ),
		);

		return array_merge( (array) $details, $rows );
	}

	/* ---------- Status "Segera Terbit" ---------- */

	public static function block_upcoming_purchase( $purchasable, $product ) {
		return self::is_upcoming( $product ) ? false : $purchasable;
	}

	public static function upcoming_notice() {
		global $product;
		if ( $product && self::is_upcoming( $product ) ) {
			echo '<p class="lit-upcoming-notice">' . esc_html__( 'Buku ini segera terbit dan belum bisa dipesan. Silakan kunjungi halaman ini kembali.', 'literaindo-bookstore' ) . '</p>';
		}
	}

	public static function upcoming_badge() {
		global $product;
		if ( $product && self::is_upcoming( $product ) ) {
			echo '<span class="lit-badge lit-badge--upcoming">' . esc_html__( 'Segera Terbit', 'literaindo-bookstore' ) . '</span>';
		}
	}

	/* ---------- Structured data ---------- */

	/**
	 * Tandai halaman sebagai Product + Book dengan ISBN, penulis, dan penerbit.
	 */
	public static function structured_data( $markup, $product ) {
		$markup['@type'] = array( 'Product', 'Book' );

		$author = self::get( $product, '_lit_author' );
		if ( $author ) {
			$markup['author'] = array_map(
				static function ( $name ) {
					return array( '@type' => 'Person', 'name' => trim( $name ) );
				},
				array_filter( explode( ',', $author ) )
			);
		}

		$markup['publisher'] = array(
			'@type' => 'Organization',
			'name'  => Lit_Settings::publisher_name(),
		);

		$isbn = literaindo_normalize_isbn( self::get( $product, '_lit_isbn' ) );
		if ( $isbn ) {
			$markup['isbn'] = $isbn;
		}
		$year = self::get( $product, '_lit_year' );
		if ( $year ) {
			$markup['datePublished'] = $year;
		}
		$pages = (int) self::get( $product, '_lit_pages' );
		if ( $pages ) {
			$markup['numberOfPages'] = $pages;
		}
		$language = self::get( $product, '_lit_language' );
		if ( $language ) {
			$markup['inLanguage'] = $language;
		}
		return $markup;
	}

	/* ---------- Pencarian ---------- */

	/**
	 * Pencarian produk juga mencocokkan penulis dan ISBN (dengan atau tanpa tanda hubung).
	 *
	 * @param string   $search
	 * @param WP_Query $query
	 */
	public static function extend_search( $search, $query ) {
		global $wpdb;

		if ( '' === $search || is_admin() || ! $query->is_search() ) {
			return $search;
		}
		$post_type = (array) $query->get( 'post_type' );
		if ( ! in_array( 'product', $post_type, true ) ) {
			return $search;
		}

		$term = trim( (string) $query->get( 's' ) );
		if ( '' === $term ) {
			return $search;
		}

		if ( literaindo_looks_like_isbn( $term ) ) {
			$ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key IN ('_lit_isbn_search', '_lit_eisbn_search') AND meta_value = %s",
					literaindo_normalize_isbn( $term )
				)
			);
		} else {
			$ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_lit_author' AND meta_value LIKE %s",
					'%' . $wpdb->esc_like( $term ) . '%'
				)
			);
		}

		$ids = array_filter( array_map( 'absint', $ids ) );
		if ( ! $ids ) {
			return $search;
		}

		$where = preg_replace( '/^\s*AND\s*/i', '', $search );
		return " AND ( {$wpdb->posts}.ID IN (" . implode( ',', $ids ) . ") OR ( {$where} ) ) ";
	}
}
