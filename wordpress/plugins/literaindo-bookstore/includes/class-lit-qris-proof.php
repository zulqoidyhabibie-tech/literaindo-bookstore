<?php
/**
 * Bukti bayar QRIS: unggah oleh pelanggan, tinjau dan konfirmasi oleh admin.
 *
 * File bukti disimpan di wp-content/uploads/literaindo-proofs/ dengan nama
 * acak dan hanya bisa dibuka lewat admin (cek hak akses), bukan URL langsung.
 *
 * @package literaindo-bookstore
 */

defined( 'ABSPATH' ) || exit;

class Lit_QRIS_Proof {

	const META     = '_lit_qris_proof';
	const DIR      = 'literaindo-proofs';
	const MAX_SIZE = 3145728; // 3 MB.

	public static function init() {
		add_action( 'admin_post_lit_qris_upload', array( __CLASS__, 'handle_upload' ) );
		add_action( 'admin_post_nopriv_lit_qris_upload', array( __CLASS__, 'handle_upload' ) );
		add_action( 'admin_post_lit_qris_view', array( __CLASS__, 'handle_view' ) );
		add_action( 'admin_post_lit_qris_confirm', array( __CLASS__, 'handle_confirm' ) );

		add_action( 'woocommerce_view_order', array( __CLASS__, 'view_order_box' ), 5 );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
	}

	/* ---------- Penyimpanan ---------- */

	public static function dir() {
		$uploads = wp_upload_dir( null, false );
		return trailingslashit( $uploads['basedir'] ) . self::DIR;
	}

	/**
	 * Folder privat: tolak akses langsung (Apache) dan cegah daftar isi folder.
	 * Untuk Nginx, lihat README plugin.
	 */
	public static function protect_upload_dir() {
		$dir = self::dir();
		if ( ! wp_mkdir_p( $dir ) ) {
			return false;
		}
		if ( ! file_exists( $dir . '/.htaccess' ) ) {
			file_put_contents( $dir . '/.htaccess', "Require all denied\nDeny from all\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
		if ( ! file_exists( $dir . '/index.php' ) ) {
			file_put_contents( $dir . '/index.php', "<?php // Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
		return true;
	}

	/**
	 * @param WC_Order $order
	 * @return array{file?: string, uploaded?: int}
	 */
	public static function get_proof( $order ) {
		$proof = $order->get_meta( self::META );
		return is_array( $proof ) ? $proof : array();
	}

	public static function accepts_proof( $order ) {
		return $order
			&& Lit_QRIS_Gateway::ID === $order->get_payment_method()
			&& $order->has_status( array( 'on-hold', 'pending' ) );
	}

	/* ---------- Pelanggan ---------- */

	/**
	 * Formulir unggah bukti. Dipanggil dari kotak pembayaran QRIS.
	 *
	 * @param WC_Order $order
	 * @param string   $context 'received' (halaman terima kasih) atau 'view' (Akun → Pesanan).
	 */
	public static function render_form( $order, $context = 'received' ) {
		if ( ! self::accepts_proof( $order ) ) {
			return;
		}
		$proof  = self::get_proof( $order );
		$status = isset( $_GET['lit_proof'] ) ? sanitize_key( wp_unslash( $_GET['lit_proof'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$messages = array(
			'uploaded' => array( 'message', __( 'Terima kasih, bukti bayar sudah kami terima. Pesanan akan diproses setelah pembayaran diverifikasi.', 'literaindo-bookstore' ) ),
			'invalid'  => array( 'error', __( 'File tidak dapat diterima. Gunakan JPG, PNG, atau PDF maksimal 3 MB.', 'literaindo-bookstore' ) ),
			'failed'   => array( 'error', __( 'Unggahan gagal. Silakan coba lagi atau hubungi kami.', 'literaindo-bookstore' ) ),
		);
		?>
		<div class="lit-qris__proof">
			<h3><?php esc_html_e( 'Unggah bukti bayar', 'literaindo-bookstore' ); ?></h3>

			<?php if ( isset( $messages[ $status ] ) ) : ?>
				<div class="woocommerce-<?php echo esc_attr( $messages[ $status ][0] ); ?>" role="status"><?php echo esc_html( $messages[ $status ][1] ); ?></div>
			<?php elseif ( ! empty( $proof['uploaded'] ) ) : ?>
				<p class="lit-qris__received">
					<?php
					/* translators: %s: tanggal unggah */
					printf( esc_html__( 'Bukti bayar diterima pada %s dan sedang diverifikasi. Anda bisa mengganti file jika keliru.', 'literaindo-bookstore' ), esc_html( wp_date( 'j F Y, H:i', (int) $proof['uploaded'] ) ) );
					?>
				</p>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
				<input type="hidden" name="action" value="lit_qris_upload">
				<input type="hidden" name="order_id" value="<?php echo esc_attr( $order->get_id() ); ?>">
				<input type="hidden" name="order_key" value="<?php echo esc_attr( $order->get_order_key() ); ?>">
				<input type="hidden" name="context" value="<?php echo esc_attr( 'view' === $context ? 'view' : 'received' ); ?>">
				<?php wp_nonce_field( 'lit_qris_upload_' . $order->get_id(), 'lit_qris_nonce' ); ?>
				<p>
					<label for="lit_qris_file"><?php esc_html_e( 'File bukti bayar (JPG, PNG, atau PDF, maks. 3 MB)', 'literaindo-bookstore' ); ?></label>
					<input type="file" id="lit_qris_file" name="lit_qris_file" accept="image/jpeg,image/png,application/pdf" required>
				</p>
				<button type="submit" class="button alt"><?php esc_html_e( 'Kirim bukti bayar', 'literaindo-bookstore' ); ?></button>
			</form>
		</div>
		<?php
	}

	public static function view_order_box( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! self::accepts_proof( $order ) ) {
			return;
		}
		$gateways = WC()->payment_gateways()->payment_gateways();
		if ( isset( $gateways[ Lit_QRIS_Gateway::ID ] ) ) {
			// Kotak yang sama dengan halaman terima kasih, tetapi kembali ke Akun → Pesanan.
			$gateways[ Lit_QRIS_Gateway::ID ]->render_payment_box( $order, 'view' );
		}
	}

	public static function handle_upload() {
		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$order    = $order_id ? wc_get_order( $order_id ) : false;
		$key      = isset( $_POST['order_key'] ) ? wc_clean( wp_unslash( $_POST['order_key'] ) ) : '';
		$context  = ( isset( $_POST['context'] ) && 'view' === $_POST['context'] ) ? 'view' : 'received';

		if (
			! $order
			|| ! hash_equals( $order->get_order_key(), (string) $key )
			|| ! isset( $_POST['lit_qris_nonce'] )
			|| ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['lit_qris_nonce'] ) ), 'lit_qris_upload_' . $order_id )
		) {
			wp_die( esc_html__( 'Permintaan tidak valid.', 'literaindo-bookstore' ), '', array( 'response' => 403 ) );
		}

		$back = 'view' === $context ? $order->get_view_order_url() : $order->get_checkout_order_received_url();

		if ( ! self::accepts_proof( $order ) ) {
			wp_safe_redirect( $back );
			exit;
		}

		$result = self::store_file( $order, isset( $_FILES['lit_qris_file'] ) ? $_FILES['lit_qris_file'] : array() ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		wp_safe_redirect( add_query_arg( 'lit_proof', $result, $back ) );
		exit;
	}

	/**
	 * Validasi dan simpan file. Mengembalikan 'uploaded', 'invalid', atau 'failed'.
	 *
	 * @param WC_Order $order
	 * @param array    $file Satu entri dari $_FILES.
	 * @return string
	 */
	private static function store_file( $order, $file ) {
		if ( empty( $file['tmp_name'] ) || UPLOAD_ERR_OK !== (int) $file['error'] || ! is_uploaded_file( $file['tmp_name'] ) ) {
			return 'failed';
		}
		if ( (int) $file['size'] > self::MAX_SIZE ) {
			return 'invalid';
		}

		$allowed = array(
			'jpg|jpeg' => 'image/jpeg',
			'png'      => 'image/png',
			'pdf'      => 'application/pdf',
		);
		$check   = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], $allowed );
		if ( empty( $check['ext'] ) || empty( $check['type'] ) ) {
			return 'invalid';
		}

		if ( ! self::protect_upload_dir() ) {
			return 'failed';
		}

		$name = $order->get_id() . '-' . wp_generate_password( 20, false ) . '.' . $check['ext'];
		if ( ! move_uploaded_file( $file['tmp_name'], self::dir() . '/' . $name ) ) {
			return 'failed';
		}

		$previous = self::get_proof( $order );
		if ( ! empty( $previous['file'] ) ) {
			wp_delete_file( self::dir() . '/' . basename( $previous['file'] ) );
		}

		$order->update_meta_data(
			self::META,
			array(
				'file'     => $name,
				'type'     => $check['type'],
				'uploaded' => time(),
			)
		);
		$order->add_order_note( __( 'Pelanggan mengunggah bukti bayar QRIS. Cocokkan dengan mutasi rekening BTN, lalu konfirmasi pembayaran.', 'literaindo-bookstore' ) );
		$order->save();

		self::notify_admin( $order );

		return 'uploaded';
	}

	private static function notify_admin( $order ) {
		$to = get_option( 'woocommerce_email_from_address', get_option( 'admin_email' ) );
		/* translators: %s: nomor pesanan */
		$subject = sprintf( __( '[Literaindo] Bukti bayar QRIS untuk pesanan #%s', 'literaindo-bookstore' ), $order->get_order_number() );
		$body    = sprintf(
			/* translators: 1: nomor pesanan, 2: total, 3: URL admin */
			__( "Pesanan #%1\$s (total %2\$s) sudah mengunggah bukti bayar QRIS.\n\nPeriksa dan konfirmasi di: %3\$s", 'literaindo-bookstore' ),
			$order->get_order_number(),
			literaindo_format_rupiah( $order->get_total() ),
			$order->get_edit_order_url()
		);
		wp_mail( $to, $subject, $body );
	}

	/* ---------- Admin ---------- */

	public static function add_meta_box() {
		$screen = function_exists( 'wc_get_page_screen_id' ) ? wc_get_page_screen_id( 'shop-order' ) : 'shop_order';
		add_meta_box( 'lit-qris-proof', __( 'Pembayaran QRIS', 'literaindo-bookstore' ), array( __CLASS__, 'render_meta_box' ), $screen, 'side', 'high' );
	}

	/**
	 * @param WP_Post|WC_Order $post_or_order
	 */
	public static function render_meta_box( $post_or_order ) {
		$order = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order( $post_or_order->ID );
		if ( ! $order || Lit_QRIS_Gateway::ID !== $order->get_payment_method() ) {
			echo '<p>' . esc_html__( 'Pesanan ini tidak memakai QRIS.', 'literaindo-bookstore' ) . '</p>';
			return;
		}

		$proof = self::get_proof( $order );
		echo '<p><strong>' . esc_html__( 'Total:', 'literaindo-bookstore' ) . '</strong> ' . esc_html( literaindo_format_rupiah( $order->get_total() ) ) . '</p>';

		if ( empty( $proof['file'] ) ) {
			echo '<p>' . esc_html__( 'Pelanggan belum mengunggah bukti bayar.', 'literaindo-bookstore' ) . '</p>';
		} else {
			$view = wp_nonce_url( admin_url( 'admin-post.php?action=lit_qris_view&order_id=' . $order->get_id() ), 'lit_qris_view_' . $order->get_id() );
			if ( 0 === strpos( (string) ( $proof['type'] ?? '' ), 'image/' ) ) {
				printf( '<p><a href="%1$s" target="_blank" rel="noopener"><img src="%1$s" alt="%2$s" style="max-width:100%%;height:auto;border:1px solid #ddd;"></a></p>', esc_url( $view ), esc_attr__( 'Bukti bayar', 'literaindo-bookstore' ) );
			} else {
				printf( '<p><a href="%s" target="_blank" rel="noopener">%s</a></p>', esc_url( $view ), esc_html__( 'Buka bukti bayar (PDF)', 'literaindo-bookstore' ) );
			}
			/* translators: %s: tanggal */
			echo '<p>' . esc_html( sprintf( __( 'Diunggah %s', 'literaindo-bookstore' ), wp_date( 'j M Y, H:i', (int) $proof['uploaded'] ) ) ) . '</p>';
		}

		if ( $order->has_status( array( 'on-hold', 'pending' ) ) ) {
			$confirm = wp_nonce_url( admin_url( 'admin-post.php?action=lit_qris_confirm&order_id=' . $order->get_id() ), 'lit_qris_confirm_' . $order->get_id() );
			printf(
				'<p><a class="button button-primary" href="%s">%s</a></p><p class="description">%s</p>',
				esc_url( $confirm ),
				esc_html__( 'Konfirmasi pembayaran', 'literaindo-bookstore' ),
				esc_html__( 'Tekan hanya setelah dana terlihat di mutasi rekening BTN. Pesanan berubah ke Diproses dan akses e-book dibuka.', 'literaindo-bookstore' )
			);
		} elseif ( $order->is_paid() ) {
			echo '<p>' . esc_html__( 'Pembayaran sudah dikonfirmasi.', 'literaindo-bookstore' ) . '</p>';
		}
	}

	public static function handle_view() {
		$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
		check_admin_referer( 'lit_qris_view_' . $order_id );
		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_die( esc_html__( 'Anda tidak punya akses.', 'literaindo-bookstore' ), '', array( 'response' => 403 ) );
		}

		$order = wc_get_order( $order_id );
		$proof = $order ? self::get_proof( $order ) : array();
		$path  = empty( $proof['file'] ) ? '' : self::dir() . '/' . basename( $proof['file'] );
		if ( ! $path || ! is_readable( $path ) ) {
			wp_die( esc_html__( 'File tidak ditemukan.', 'literaindo-bookstore' ), '', array( 'response' => 404 ) );
		}

		nocache_headers();
		header( 'Content-Type: ' . ( $proof['type'] ?? 'application/octet-stream' ) );
		header( 'Content-Length: ' . filesize( $path ) );
		header( 'Content-Disposition: inline; filename="bukti-bayar-' . $order_id . '.' . pathinfo( $path, PATHINFO_EXTENSION ) . '"' );
		header( 'X-Content-Type-Options: nosniff' );
		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}

	public static function handle_confirm() {
		$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
		check_admin_referer( 'lit_qris_confirm_' . $order_id );
		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_die( esc_html__( 'Anda tidak punya akses.', 'literaindo-bookstore' ), '', array( 'response' => 403 ) );
		}

		$order = wc_get_order( $order_id );
		if ( $order && ! $order->is_paid() ) {
			$user = wp_get_current_user();
			$order->add_order_note(
				/* translators: %s: nama admin */
				sprintf( __( 'Pembayaran QRIS dikonfirmasi oleh %s.', 'literaindo-bookstore' ), $user->display_name )
			);
			// Menandai lunas: status Diproses (atau Selesai untuk pesanan e-book saja), unduhan dibuka.
			$order->payment_complete();
		}

		wp_safe_redirect( $order ? $order->get_edit_order_url() : admin_url() );
		exit;
	}
}
