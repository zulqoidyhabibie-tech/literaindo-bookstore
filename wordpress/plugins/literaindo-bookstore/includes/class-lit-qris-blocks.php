<?php
/**
 * Dukungan checkout berbasis blok (bawaan WooCommerce baru) untuk QRIS BTN.
 * Tanpa ini, metode QRIS hanya muncul di checkout klasik [woocommerce_checkout].
 *
 * @package literaindo-bookstore
 */

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class Lit_QRIS_Blocks extends AbstractPaymentMethodType {

	protected $name = 'literaindo_qris';

	public function initialize() {
		$this->settings = (array) get_option( 'woocommerce_literaindo_qris_settings', array() );
	}

	public function is_active() {
		return 'yes' === ( $this->settings['enabled'] ?? 'no' ) && ! empty( $this->settings['qris_image'] );
	}

	public function get_payment_method_script_handles() {
		wp_register_script(
			'literaindo-qris-blocks',
			LITERAINDO_BOOKSTORE_URL . 'assets/js/qris-blocks.js',
			array( 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities' ),
			LITERAINDO_BOOKSTORE_VERSION,
			true
		);
		return array( 'literaindo-qris-blocks' );
	}

	public function get_payment_method_data() {
		return array(
			'title'       => $this->settings['title'] ?? __( 'QRIS', 'literaindo-bookstore' ),
			'description' => $this->settings['description'] ?? '',
			'supports'    => array( 'products' ),
		);
	}
}
