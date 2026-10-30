<?php
/**
 * WooCommerce → Persiapan Toko: daftar periksa sebelum peluncuran, dengan
 * tombol untuk menerapkan pengaturan yang aman dan membuat draf halaman
 * bantuan/kebijakan.
 *
 * @package literaindo-bookstore
 */

defined( 'ABSPATH' ) || exit;

class Lit_Setup {

	const SLUG = 'literaindo-setup';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 60 );
		add_action( 'admin_post_lit_setup_ebook', array( __CLASS__, 'action_ebook' ) );
		add_action( 'admin_post_lit_setup_currency', array( __CLASS__, 'action_currency' ) );
		add_action( 'admin_post_lit_setup_pages', array( __CLASS__, 'action_pages' ) );
	}

	/**
	 * Halaman yang dibuat sebagai draf: slug => [judul, peran].
	 * Peran 'terms' dan 'privacy' dihubungkan ke pengaturan WooCommerce/WordPress.
	 */
	public static function pages() {
		return array(
			'tentang-kami'            => array( __( 'Tentang Kami', 'literaindo-bookstore' ), '' ),
			'bantuan'                 => array( __( 'Bantuan & FAQ', 'literaindo-bookstore' ), '' ),
			'syarat-transaksi'        => array( __( 'Syarat Transaksi', 'literaindo-bookstore' ), 'terms' ),
			'pengiriman-retur-refund' => array( __( 'Pengiriman, Retur, dan Refund', 'literaindo-bookstore' ), '' ),
			'ketentuan-ebook'         => array( __( 'Ketentuan Penggunaan E-book', 'literaindo-bookstore' ), '' ),
			'kebijakan-privasi'       => array( __( 'Kebijakan Privasi', 'literaindo-bookstore' ), 'privacy' ),
		);
	}

	public static function menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Persiapan Toko Literaindo', 'literaindo-bookstore' ),
			__( 'Persiapan Toko', 'literaindo-bookstore' ),
			'manage_woocommerce',
			self::SLUG,
			array( __CLASS__, 'render' )
		);
	}

	private static function page_url( $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::SLUG ), $args ), admin_url( 'admin.php' ) );
	}

	private static function action_url( $action ) {
		return wp_nonce_url( admin_url( 'admin-post.php?action=' . $action ), $action );
	}

	private static function guard( $action ) {
		check_admin_referer( $action );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Anda tidak punya akses.', 'literaindo-bookstore' ), '', array( 'response' => 403 ) );
		}
	}

	/* ---------- Pemeriksaan ---------- */

	/**
	 * @return array<int, array{label: string, ok: bool, help: string, action?: array{0: string, 1: string}, link?: array{0: string, 1: string}}>
	 */
	public static function checks() {
		$permalinks = (array) get_option( 'woocommerce_permalinks', array() );
		$gateway    = (array) get_option( 'woocommerce_literaindo_qris_settings', array() );
		$ebook_ok   = ! in_array( false, Lit_Ebook::option_status(), true );
		$pages      = self::page_status();
		$proof_dir  = Lit_QRIS_Proof::dir();

		$has_shipping = false;
		foreach ( WC_Shipping_Zones::get_zones() as $zone ) {
			if ( ! empty( $zone['shipping_methods'] ) ) {
				$has_shipping = true;
				break;
			}
		}

		return array(
			array(
				'label' => __( 'Bahasa situs: Bahasa Indonesia', 'literaindo-bookstore' ),
				'ok'    => 'id_ID' === get_locale(),
				'help'  => __( 'Agar tombol dan pesan WooCommerce berbahasa Indonesia.', 'literaindo-bookstore' ),
				'link'  => array( admin_url( 'options-general.php' ), __( 'Pengaturan Umum', 'literaindo-bookstore' ) ),
			),
			array(
				'label'  => __( 'Mata uang Rupiah tanpa desimal (Rp95.000)', 'literaindo-bookstore' ),
				'ok'     => 'IDR' === get_option( 'woocommerce_currency' ) && 0 === (int) get_option( 'woocommerce_price_num_decimals' ) && '.' === get_option( 'woocommerce_price_thousand_sep' ),
				'help'   => __( 'Mata uang IDR, pemisah ribuan titik, 0 desimal.', 'literaindo-bookstore' ),
				'action' => array( 'lit_setup_currency', __( 'Terapkan format Rupiah', 'literaindo-bookstore' ) ),
			),
			array(
				'label' => __( 'URL buku memakai /produk/', 'literaindo-bookstore' ),
				'ok'    => isset( $permalinks['product_base'] ) && '/produk' === untrailingslashit( $permalinks['product_base'] ) && '' !== get_option( 'permalink_structure' ),
				'help'  => __( 'Pengaturan → Permalink: struktur "Nama tulisan", dasar produk kustom /produk/.', 'literaindo-bookstore' ),
				'link'  => array( admin_url( 'options-permalink.php' ), __( 'Permalink', 'literaindo-bookstore' ) ),
			),
			array(
				'label' => __( 'Pembayaran QRIS BTN aktif dan gambar QRIS terpasang', 'literaindo-bookstore' ),
				'ok'    => 'yes' === ( $gateway['enabled'] ?? 'no' ) && ! empty( $gateway['qris_image'] ),
				'help'  => __( 'Unggah gambar QRIS di Media, tempel URL-nya di pengaturan pembayaran.', 'literaindo-bookstore' ),
				'link'  => array( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . Lit_QRIS_Gateway::ID ), __( 'Pengaturan QRIS', 'literaindo-bookstore' ) ),
			),
			array(
				'label' => __( 'Folder bukti bayar terlindungi', 'literaindo-bookstore' ),
				'ok'    => file_exists( $proof_dir . '/.htaccess' ) && file_exists( $proof_dir . '/index.php' ),
				'help'  => __( 'Dibuat saat plugin diaktifkan. Server Nginx perlu aturan tambahan (lihat README plugin).', 'literaindo-bookstore' ),
			),
			array(
				'label'  => __( 'Unduhan e-book aman', 'literaindo-bookstore' ),
				'ok'     => $ebook_ok,
				'help'   => implode( ' · ', wp_list_pluck( Lit_Ebook::recommended_options(), 1 ) ),
				'action' => array( 'lit_setup_ebook', __( 'Terapkan pengaturan e-book', 'literaindo-bookstore' ) ),
			),
			array(
				'label' => __( 'Ongkos kirim buku cetak', 'literaindo-bookstore' ),
				'ok'    => $has_shipping,
				'help'  => __( 'Buat zona "Indonesia" dengan metode Tarif Tetap atau tarif per kelas. E-book (produk Virtual) otomatis tanpa ongkir.', 'literaindo-bookstore' ),
				'link'  => array( admin_url( 'admin.php?page=wc-settings&tab=shipping' ), __( 'Zona pengiriman', 'literaindo-bookstore' ) ),
			),
			array(
				'label'  => __( 'Halaman bantuan dan kebijakan tersedia', 'literaindo-bookstore' ),
				'ok'     => ! in_array( false, wp_list_pluck( $pages, 'exists' ), true ),
				'help'   => __( 'Dibuat sebagai draf berisi teks contoh. Lengkapi bagian [dalam kurung], tinjau, lalu terbitkan.', 'literaindo-bookstore' ),
				'action' => array( 'lit_setup_pages', __( 'Buat draf yang belum ada', 'literaindo-bookstore' ) ),
			),
		);
	}

	/**
	 * @return array<string, array{exists: bool, id: int, status: string}>
	 */
	public static function page_status() {
		$status = array();
		foreach ( array_keys( self::pages() ) as $slug ) {
			$id   = (int) get_option( 'literaindo_page_' . $slug );
			$post = $id ? get_post( $id ) : null;
			if ( ! $post || 'trash' === $post->post_status ) {
				$post = get_page_by_path( $slug );
			}
			$status[ $slug ] = array(
				'exists' => $post && 'trash' !== $post->post_status,
				'id'     => $post ? (int) $post->ID : 0,
				'status' => $post ? $post->post_status : '',
			);
		}
		return $status;
	}

	/* ---------- Tampilan ---------- */

	public static function render() {
		$done = isset( $_GET['lit_done'] ) ? sanitize_key( wp_unslash( $_GET['lit_done'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$done_messages = array(
			'ebook'    => __( 'Pengaturan unduhan e-book diterapkan.', 'literaindo-bookstore' ),
			'currency' => __( 'Format Rupiah diterapkan.', 'literaindo-bookstore' ),
			'pages'    => __( 'Draf halaman dibuat. Lengkapi dan terbitkan dari daftar di bawah.', 'literaindo-bookstore' ),
		);
		$checks = self::checks();
		$ok     = count( array_filter( wp_list_pluck( $checks, 'ok' ) ) );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Persiapan Toko Literaindo', 'literaindo-bookstore' ); ?></h1>
			<?php if ( isset( $done_messages[ $done ] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $done_messages[ $done ] ); ?></p></div>
			<?php endif; ?>

			<p>
				<?php
				/* translators: 1: jumlah selesai, 2: total */
				printf( esc_html__( '%1$d dari %2$d pemeriksaan selesai.', 'literaindo-bookstore' ), (int) $ok, count( $checks ) );
				?>
			</p>

			<table class="widefat striped" style="max-width:960px">
				<tbody>
				<?php foreach ( $checks as $check ) : ?>
					<tr>
						<td style="width:2.5em;font-size:1.3em;text-align:center"><?php echo $check['ok'] ? '<span style="color:#008a20" aria-label="selesai">✓</span>' : '<span style="color:#b32d2e" aria-label="belum">✗</span>'; ?></td>
						<td>
							<strong><?php echo esc_html( $check['label'] ); ?></strong><br>
							<span class="description"><?php echo esc_html( $check['help'] ); ?></span>
						</td>
						<td style="width:16em;text-align:right">
							<?php if ( ! $check['ok'] && ! empty( $check['action'] ) ) : ?>
								<a class="button button-primary" href="<?php echo esc_url( self::action_url( $check['action'][0] ) ); ?>"><?php echo esc_html( $check['action'][1] ); ?></a>
							<?php elseif ( ! empty( $check['link'] ) ) : ?>
								<a class="button" href="<?php echo esc_url( $check['link'][0] ); ?>"><?php echo esc_html( $check['link'][1] ); ?></a>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'Halaman bantuan dan kebijakan', 'literaindo-bookstore' ); ?></h2>
			<table class="widefat striped" style="max-width:960px">
				<tbody>
				<?php
				$labels = array(
					'publish' => __( 'Terbit', 'literaindo-bookstore' ),
					'draft'   => __( 'Draf, perlu ditinjau', 'literaindo-bookstore' ),
				);
				foreach ( self::page_status() as $slug => $page ) :
					$title = self::pages()[ $slug ][0];
					?>
					<tr>
						<td><strong><?php echo esc_html( $title ); ?></strong> <code>/<?php echo esc_html( $slug ); ?>/</code></td>
						<td><?php echo $page['exists'] ? esc_html( $labels[ $page['status'] ] ?? $page['status'] ) : esc_html__( 'Belum ada', 'literaindo-bookstore' ); ?></td>
						<td style="text-align:right">
							<?php if ( $page['exists'] ) : ?>
								<a class="button" href="<?php echo esc_url( get_edit_post_link( $page['id'] ) ); ?>"><?php esc_html_e( 'Edit', 'literaindo-bookstore' ); ?></a>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/* ---------- Aksi ---------- */

	public static function action_ebook() {
		self::guard( 'lit_setup_ebook' );
		Lit_Ebook::apply_recommended_options();
		wp_safe_redirect( self::page_url( array( 'lit_done' => 'ebook' ) ) );
		exit;
	}

	public static function action_currency() {
		self::guard( 'lit_setup_currency' );
		update_option( 'woocommerce_currency', 'IDR' );
		update_option( 'woocommerce_currency_pos', 'left' );
		update_option( 'woocommerce_price_thousand_sep', '.' );
		update_option( 'woocommerce_price_decimal_sep', ',' );
		update_option( 'woocommerce_price_num_decimals', 0 );
		wp_safe_redirect( self::page_url( array( 'lit_done' => 'currency' ) ) );
		exit;
	}

	public static function action_pages() {
		self::guard( 'lit_setup_pages' );
		self::create_pages();
		wp_safe_redirect( self::page_url( array( 'lit_done' => 'pages' ) ) );
		exit;
	}

	/**
	 * Buat halaman yang belum ada sebagai draf, hubungkan Syarat Transaksi dan
	 * Kebijakan Privasi ke pengaturannya, dan isi menu footer bila masih kosong.
	 * Halaman yang sudah ada tidak diubah.
	 */
	public static function create_pages() {
		$status = self::page_status();
		$home   = untrailingslashit( home_url() );

		foreach ( self::pages() as $slug => $page ) {
			$id = $status[ $slug ]['id'];

			if ( ! $status[ $slug ]['exists'] ) {
				$file    = LITERAINDO_BOOKSTORE_DIR . 'templates/pages/' . $slug . '.html';
				$content = file_exists( $file ) ? str_replace( '{{home}}', esc_url( $home ), (string) file_get_contents( $file ) ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions
				$id      = wp_insert_post(
					array(
						'post_type'    => 'page',
						'post_status'  => 'draft',
						'post_title'   => $page[0],
						'post_name'    => $slug,
						'post_content' => $content,
					)
				);
				if ( is_wp_error( $id ) ) {
					continue;
				}
			}

			update_option( 'literaindo_page_' . $slug, (int) $id );

			if ( 'terms' === $page[1] && ! get_option( 'woocommerce_terms_page_id' ) ) {
				update_option( 'woocommerce_terms_page_id', (int) $id );
			}
			if ( 'privacy' === $page[1] ) {
				$current = (int) get_option( 'wp_page_for_privacy_policy' );
				// Ganti halaman privasi bawaan WordPress (biasanya draf berbahasa Inggris).
				if ( ! $current || 'publish' !== get_post_status( $current ) ) {
					update_option( 'wp_page_for_privacy_policy', (int) $id );
				}
			}
		}

		self::maybe_create_footer_menu();
	}

	private static function maybe_create_footer_menu() {
		$location  = 'literaindo-footer';
		$locations = get_nav_menu_locations();
		if ( ! array_key_exists( $location, get_registered_nav_menus() ) || ! empty( $locations[ $location ] ) ) {
			return;
		}

		$menu_id = wp_create_nav_menu( __( 'Bantuan & Kebijakan', 'literaindo-bookstore' ) );
		if ( is_wp_error( $menu_id ) ) {
			return;
		}
		foreach ( array( 'bantuan', 'syarat-transaksi', 'pengiriman-retur-refund', 'ketentuan-ebook', 'kebijakan-privasi' ) as $slug ) {
			$page_id = (int) get_option( 'literaindo_page_' . $slug );
			if ( $page_id ) {
				wp_update_nav_menu_item(
					$menu_id,
					0,
					array(
						'menu-item-object-id' => $page_id,
						'menu-item-object'    => 'page',
						'menu-item-type'      => 'post_type',
						'menu-item-status'    => 'publish',
					)
				);
			}
		}
		$locations[ $location ] = $menu_id;
		set_theme_mod( 'nav_menu_locations', $locations );
	}
}
