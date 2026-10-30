<?php
/**
 * Metode pembayaran QRIS (statis) Bank BTN dengan konfirmasi manual.
 *
 * Alur: pelanggan membuat pesanan → status "Ditahan" (on-hold) dan stok
 * dicadangkan → halaman terima kasih + email menampilkan QRIS dan nominal →
 * pelanggan mengunggah bukti bayar → admin mencocokkan dengan mutasi BTN lalu
 * menekan "Konfirmasi pembayaran" (lihat Lit_QRIS_Proof).
 *
 * Akses e-book baru diberikan WooCommerce setelah pembayaran dikonfirmasi,
 * karena pesanan on-hold tidak membuka unduhan.
 *
 * @package literaindo-bookstore
 */

defined( 'ABSPATH' ) || exit;

class Lit_QRIS_Gateway extends WC_Payment_Gateway {

	const ID = 'literaindo_qris';

	/** @var string */
	public $merchant_name = '';

	/** @var string */
	public $qris_image = '';

	/** @var int */
	public $deadline = 24;

	/** @var string */
	public $instructions = '';

	public function __construct() {
		$this->id                 = self::ID;
		$this->has_fields         = false;
		$this->method_title       = __( 'QRIS BTN (konfirmasi manual)', 'literaindo-bookstore' );
		$this->method_description = __( 'Pelanggan memindai QRIS statis Bank BTN, lalu mengunggah bukti bayar. Admin mengonfirmasi setelah dana masuk.', 'literaindo-bookstore' );
		$this->supports           = array( 'products' );

		$this->init_form_fields();
		$this->init_settings();

		$this->title         = $this->get_option( 'title' );
		$this->description   = $this->get_option( 'description' );
		$this->merchant_name = $this->get_option( 'merchant_name' );
		$this->qris_image    = $this->get_option( 'qris_image' );
		$this->deadline      = absint( $this->get_option( 'deadline_hours', 24 ) );
		$this->instructions  = $this->get_option( 'instructions' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
		add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
	}

	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'        => array(
				'title'   => __( 'Aktifkan', 'literaindo-bookstore' ),
				'type'    => 'checkbox',
				'label'   => __( 'Aktifkan pembayaran QRIS BTN', 'literaindo-bookstore' ),
				'default' => 'no',
			),
			'title'          => array(
				'title'   => __( 'Judul', 'literaindo-bookstore' ),
				'type'    => 'text',
				'default' => __( 'QRIS (semua bank & e-wallet)', 'literaindo-bookstore' ),
			),
			'description'    => array(
				'title'   => __( 'Deskripsi di checkout', 'literaindo-bookstore' ),
				'type'    => 'textarea',
				'default' => __( 'Bayar dengan memindai kode QRIS dari aplikasi bank atau dompet digital. Kode QRIS dan total pembayaran ditampilkan setelah pesanan dibuat.', 'literaindo-bookstore' ),
			),
			'merchant_name'  => array(
				'title'       => __( 'Nama merchant QRIS', 'literaindo-bookstore' ),
				'type'        => 'text',
				'description' => __( 'Nama yang muncul di aplikasi pembeli saat memindai, misalnya nama CV Anda. Membantu pembeli memastikan tujuan pembayaran benar.', 'literaindo-bookstore' ),
				'default'     => '',
			),
			'qris_image'     => array(
				'title'       => __( 'URL gambar QRIS', 'literaindo-bookstore' ),
				'type'        => 'text',
				'description' => __( 'Unggah gambar QRIS BTN di Media → Tambah Baru, lalu tempel URL file-nya di sini.', 'literaindo-bookstore' ),
				'default'     => '',
			),
			'deadline_hours' => array(
				'title'       => __( 'Batas waktu bayar (jam)', 'literaindo-bookstore' ),
				'type'        => 'number',
				'description' => __( 'Ditampilkan kepada pelanggan sebagai tenggat pembayaran.', 'literaindo-bookstore' ),
				'default'     => 24,
			),
			'instructions'   => array(
				'title'   => __( 'Instruksi tambahan', 'literaindo-bookstore' ),
				'type'    => 'textarea',
				'default' => __( 'Bayar tepat sesuai total pesanan, lalu unggah tangkapan layar bukti pembayaran di halaman ini. Pesanan diproses setelah pembayaran kami verifikasi.', 'literaindo-bookstore' ),
			),
		);
	}

	public function is_available() {
		return parent::is_available() && '' !== (string) $this->qris_image;
	}

	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		$order->update_status( 'on-hold', __( 'Menunggu pembayaran QRIS BTN dan bukti bayar dari pelanggan.', 'literaindo-bookstore' ) );
		wc_maybe_reduce_stock_levels( $order_id );
		WC()->cart->empty_cart();

		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
	}

	/**
	 * Kotak pembayaran: QRIS, total, tenggat, dan formulir bukti bayar.
	 *
	 * @param WC_Order $order
	 * @param string   $context 'received' atau 'view', lihat Lit_QRIS_Proof::render_form().
	 */
	public function render_payment_box( $order, $context = 'received' ) {
		$deadline = $order->get_date_created() ? $order->get_date_created()->getTimestamp() + $this->deadline * HOUR_IN_SECONDS : 0;
		?>
		<section class="lit-qris" aria-labelledby="lit-qris-title">
			<h2 id="lit-qris-title"><?php esc_html_e( 'Pembayaran QRIS', 'literaindo-bookstore' ); ?></h2>
			<div class="lit-qris__grid">
				<figure class="lit-qris__code">
					<img src="<?php echo esc_url( $this->qris_image ); ?>" alt="<?php esc_attr_e( 'Kode QRIS pembayaran', 'literaindo-bookstore' ); ?>" width="280" height="280">
					<?php if ( $this->merchant_name ) : ?>
						<figcaption><?php echo esc_html( $this->merchant_name ); ?></figcaption>
					<?php endif; ?>
				</figure>
				<div class="lit-qris__info">
					<p class="lit-qris__label"><?php esc_html_e( 'Total yang harus dibayar', 'literaindo-bookstore' ); ?></p>
					<p class="lit-qris__amount"><?php echo esc_html( literaindo_format_rupiah( $order->get_total() ) ); ?></p>
					<p>
						<?php
						/* translators: %s: nomor pesanan */
						printf( esc_html__( 'Nomor pesanan: %s', 'literaindo-bookstore' ), '<strong>' . esc_html( $order->get_order_number() ) . '</strong>' );
						?>
					</p>
					<?php if ( $deadline ) : ?>
						<p>
							<?php
							/* translators: %s: tanggal dan jam */
							printf( esc_html__( 'Bayar sebelum %s', 'literaindo-bookstore' ), '<strong>' . esc_html( wp_date( 'j F Y, H:i', $deadline ) ) . '</strong>' );
							?>
						</p>
					<?php endif; ?>
					<ol class="lit-qris__steps">
						<li><?php esc_html_e( 'Buka aplikasi bank atau dompet digital, pilih Bayar/Scan QRIS.', 'literaindo-bookstore' ); ?></li>
						<li><?php esc_html_e( 'Pindai kode di samping dan masukkan nominal tepat sesuai total.', 'literaindo-bookstore' ); ?></li>
						<li><?php esc_html_e( 'Simpan tangkapan layar bukti bayar, lalu unggah di bawah ini.', 'literaindo-bookstore' ); ?></li>
					</ol>
					<?php if ( $this->instructions ) : ?>
						<p class="lit-qris__note"><?php echo esc_html( $this->instructions ); ?></p>
					<?php endif; ?>
				</div>
			</div>
			<?php Lit_QRIS_Proof::render_form( $order, $context ); ?>
		</section>
		<?php
	}

	public function thankyou_page( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( $order && $order->has_status( array( 'on-hold', 'pending' ) ) ) {
			$this->render_payment_box( $order );
		}
	}

	/**
	 * Email pesanan baru ke pelanggan: QRIS, total, dan tautan unggah bukti.
	 *
	 * @param WC_Order $order
	 * @param bool     $sent_to_admin
	 * @param bool     $plain_text
	 */
	public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
		if ( $sent_to_admin || self::ID !== $order->get_payment_method() || ! $order->has_status( 'on-hold' ) ) {
			return;
		}

		$link   = $order->get_checkout_order_received_url();
		$amount = literaindo_format_rupiah( $order->get_total() );

		if ( $plain_text ) {
			echo esc_html__( 'Pembayaran QRIS', 'literaindo-bookstore' ) . "\n";
			/* translators: %s: total */
			echo esc_html( sprintf( __( 'Total yang harus dibayar: %s', 'literaindo-bookstore' ), $amount ) ) . "\n";
			/* translators: %s: URL */
			echo esc_html( sprintf( __( 'Lihat kode QRIS dan unggah bukti bayar: %s', 'literaindo-bookstore' ), $link ) ) . "\n\n";
			return;
		}
		?>
		<h2><?php esc_html_e( 'Pembayaran QRIS', 'literaindo-bookstore' ); ?></h2>
		<p>
			<?php
			/* translators: %s: total */
			printf( esc_html__( 'Total yang harus dibayar: %s', 'literaindo-bookstore' ), '<strong>' . esc_html( $amount ) . '</strong>' );
			?>
		</p>
		<p><img src="<?php echo esc_url( $this->qris_image ); ?>" alt="<?php esc_attr_e( 'Kode QRIS pembayaran', 'literaindo-bookstore' ); ?>" width="220" style="max-width:220px;height:auto;"></p>
		<p><a href="<?php echo esc_url( $link ); ?>"><?php esc_html_e( 'Unggah bukti bayar', 'literaindo-bookstore' ); ?></a></p>
		<?php
	}
}
