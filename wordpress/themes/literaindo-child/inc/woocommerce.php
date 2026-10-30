<?php
/**
 * Penyesuaian tampilan WooCommerce: kartu buku, halaman detail, breadcrumb.
 *
 * @package literaindo-child
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WooCommerce' ) ) {
	return;
}

/**
 * Ukuran gambar sampul. Sampul buku berorientasi potret, jadi lebar cukup 360px
 * di grid; detail produk memakai 560px.
 */
function literaindo_child_woocommerce_args( $args ) {
	$args['thumbnail_image_width'] = 360;
	$args['single_image_width']    = 560;
	$args['product_grid']          = array(
		'default_columns' => 4,
		'default_rows'    => 3,
		'min_columns'     => 2,
		'max_columns'     => 5,
		'min_rows'        => 1,
	);
	return $args;
}
add_filter( 'storefront_woocommerce_args', 'literaindo_child_woocommerce_args' );

/**
 * Nama penulis di bawah judul pada kartu buku.
 */
function literaindo_child_loop_author() {
	global $product;
	$author = literaindo_child_book_author( $product );
	if ( $author ) {
		echo '<p class="lit-card__author">' . esc_html( $author ) . '</p>';
	}
}
add_action( 'woocommerce_shop_loop_item_title', 'literaindo_child_loop_author', 15 );

/**
 * Nama penulis di bawah judul pada halaman detail.
 */
function literaindo_child_single_author() {
	global $product;
	$author = literaindo_child_book_author( $product );
	if ( $author ) {
		printf(
			'<p class="lit-single__author">%s <span>%s</span></p>',
			esc_html__( 'Penulis:', 'literaindo-child' ),
			esc_html( $author )
		);
	}
}
add_action( 'woocommerce_single_product_summary', 'literaindo_child_single_author', 6 );

/**
 * Tab "Informasi Buku" berisi metadata bibliografis. Tab ini jadi tab pertama
 * karena halaman detail juga dipakai sebagai tautan publikasi resmi buku.
 */
function literaindo_child_book_tab( $tabs ) {
	global $product;
	if ( ! $product || ! literaindo_child_book_details( $product ) ) {
		return $tabs;
	}
	$tabs['literaindo_book'] = array(
		'title'    => __( 'Informasi Buku', 'literaindo-child' ),
		'priority' => 5,
		'callback' => 'literaindo_child_book_tab_content',
	);
	return $tabs;
}
add_filter( 'woocommerce_product_tabs', 'literaindo_child_book_tab' );

function literaindo_child_book_tab_content() {
	global $product;
	$details = literaindo_child_book_details( $product );
	?>
	<h2><?php esc_html_e( 'Informasi Buku', 'literaindo-child' ); ?></h2>
	<table class="lit-book-details">
		<tbody>
			<?php foreach ( $details as $row ) : ?>
				<tr>
					<th scope="row"><?php echo esc_html( $row['label'] ); ?></th>
					<td><?php echo esc_html( $row['value'] ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php
}

/**
 * Breadcrumb berbahasa Indonesia.
 */
function literaindo_child_breadcrumb_defaults( $defaults ) {
	$defaults['home'] = __( 'Beranda', 'literaindo-child' );
	return $defaults;
}
add_filter( 'woocommerce_breadcrumb_defaults', 'literaindo_child_breadcrumb_defaults' );

/**
 * Placeholder pencarian di header: judul, penulis, atau ISBN.
 */
function literaindo_child_search_placeholder( $form ) {
	return str_replace(
		esc_attr__( 'Search products&hellip;', 'woocommerce' ),
		esc_attr__( 'Cari judul, penulis, atau ISBN…', 'literaindo-child' ),
		$form
	);
}
add_filter( 'get_product_search_form', 'literaindo_child_search_placeholder' );
