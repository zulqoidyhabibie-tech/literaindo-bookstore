<?php
/**
 * Fungsi tampilan yang dipakai template beranda dan WooCommerce.
 *
 * Kontrak dengan plugin literaindo-bookstore:
 * - `literaindo_book_author` (string)  : nama penulis untuk sebuah produk.
 * - `literaindo_book_details` (array)  : daftar [ 'label' => ..., 'value' => ... ]
 *   untuk tabel "Informasi Buku" (ISBN, tahun terbit, halaman, dsb.).
 * Tanpa plugin, keduanya kosong dan bagian terkait tidak ditampilkan.
 *
 * @package literaindo-child
 */

defined( 'ABSPATH' ) || exit;

/**
 * @param WC_Product $product
 * @return string
 */
function literaindo_child_book_author( $product ) {
	return (string) apply_filters( 'literaindo_book_author', '', $product );
}

/**
 * @param WC_Product $product
 * @return array<int, array{label: string, value: string}>
 */
function literaindo_child_book_details( $product ) {
	$details = apply_filters( 'literaindo_book_details', array(), $product );
	return array_values(
		array_filter(
			(array) $details,
			static function ( $row ) {
				return is_array( $row ) && '' !== trim( (string) ( $row['value'] ?? '' ) );
			}
		)
	);
}

/**
 * Kartu kategori untuk beranda.
 *
 * @param WP_Term $term
 */
function literaindo_child_category_card( $term ) {
	$link = get_term_link( $term );
	if ( is_wp_error( $link ) ) {
		return;
	}
	?>
	<li class="lit-category">
		<a class="lit-category__link" href="<?php echo esc_url( $link ); ?>">
			<span class="lit-category__name"><?php echo esc_html( $term->name ); ?></span>
			<span class="lit-category__count">
				<?php
				/* translators: %d: jumlah judul */
				echo esc_html( sprintf( _n( '%d judul', '%d judul', $term->count, 'literaindo-child' ), $term->count ) );
				?>
			</span>
		</a>
	</li>
	<?php
}

/**
 * Judul bagian beranda dengan tautan opsional di kanan.
 */
function literaindo_child_section_heading( $title, $link_url = '', $link_label = '' ) {
	?>
	<div class="lit-section__head">
		<h2 class="lit-section__title"><?php echo esc_html( $title ); ?></h2>
		<?php if ( $link_url ) : ?>
			<a class="lit-section__more" href="<?php echo esc_url( $link_url ); ?>"><?php echo esc_html( $link_label ); ?></a>
		<?php endif; ?>
	</div>
	<?php
}
