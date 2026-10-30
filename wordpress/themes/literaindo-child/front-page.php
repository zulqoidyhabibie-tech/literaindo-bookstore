<?php
/**
 * Beranda Literaindo Press Bookstore.
 *
 * Urutan sesuai blueprint 4.1: banner + pencarian, kategori unggulan,
 * buku terbaru, buku terlaris, tentang penerbit.
 *
 * @package literaindo-child
 */

defined( 'ABSPATH' ) || exit;

get_header();

$has_wc   = class_exists( 'WooCommerce' );
$shop_url = $has_wc ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
?>

<div id="primary" class="content-area lit-home">
	<main id="main" class="site-main">

		<section class="lit-hero" aria-labelledby="lit-hero-title">
			<div class="lit-hero__inner">
				<div class="lit-hero__copy">
					<p class="lit-eyebrow"><?php echo esc_html( literaindo_child_home_mod( 'literaindo_hero_eyebrow' ) ); ?></p>
					<h1 id="lit-hero-title" class="lit-hero__title"><?php echo esc_html( literaindo_child_home_mod( 'literaindo_hero_title' ) ); ?></h1>
					<p class="lit-hero__text"><?php echo esc_html( literaindo_child_home_mod( 'literaindo_hero_text' ) ); ?></p>

					<?php if ( $has_wc ) : ?>
						<div class="lit-hero__search">
							<?php the_widget( 'WC_Widget_Product_Search', array( 'title' => '' ) ); ?>
						</div>
					<?php endif; ?>

					<a class="button lit-button--primary" href="<?php echo esc_url( $shop_url ); ?>">
						<?php echo esc_html( literaindo_child_home_mod( 'literaindo_hero_button' ) ); ?>
					</a>
				</div>

				<?php
				$hero_books = $has_wc ? wc_get_products(
					array(
						'status'  => 'publish',
						'limit'   => 3,
						'orderby' => 'date',
						'order'   => 'DESC',
						'visibility' => 'catalog',
					)
				) : array();
				$hero_books = array_filter(
					$hero_books,
					static function ( $book ) {
						return (bool) $book->get_image_id();
					}
				);
				?>
				<div class="lit-hero__covers" aria-hidden="true">
					<?php if ( $hero_books ) : ?>
						<?php foreach ( $hero_books as $book ) : ?>
							<div class="lit-hero__cover">
								<?php echo wp_get_attachment_image( $book->get_image_id(), 'woocommerce_thumbnail', false, array( 'loading' => 'eager' ) ); ?>
							</div>
						<?php endforeach; ?>
					<?php else : ?>
						<div class="lit-hero__cover lit-hero__cover--placeholder"><span>Literaindo<br>Press</span></div>
						<div class="lit-hero__cover lit-hero__cover--placeholder"><span>Cetak</span></div>
						<div class="lit-hero__cover lit-hero__cover--placeholder"><span>E-book</span></div>
					<?php endif; ?>
				</div>
			</div>
		</section>

		<?php
		if ( $has_wc ) :
			$categories = get_terms(
				array(
					'taxonomy'   => 'product_cat',
					'hide_empty' => false,
					'parent'     => 0,
					'number'     => 4,
					'orderby'    => 'menu_order',
					'exclude'    => array( (int) get_option( 'default_product_cat' ) ),
				)
			);
			if ( ! is_wp_error( $categories ) && $categories ) :
				?>
				<section class="lit-section lit-section--categories" aria-labelledby="lit-cat-title">
					<div class="lit-section__inner">
						<div class="lit-section__head">
							<h2 id="lit-cat-title" class="lit-section__title"><?php esc_html_e( 'Kategori Unggulan', 'literaindo-child' ); ?></h2>
						</div>
						<ul class="lit-categories">
							<?php array_map( 'literaindo_child_category_card', $categories ); ?>
						</ul>
					</div>
				</section>
				<?php
			endif;

			$has_products = (bool) wc_get_products( array( 'status' => 'publish', 'limit' => 1, 'return' => 'ids' ) );

			if ( $has_products ) :
				?>
				<section class="lit-section">
					<div class="lit-section__inner">
						<?php literaindo_child_section_heading( __( 'Buku Terbaru', 'literaindo-child' ), $shop_url, __( 'Lihat semua buku', 'literaindo-child' ) ); ?>
						<?php echo do_shortcode( '[products limit="8" columns="4" orderby="date" order="DESC"]' ); ?>
					</div>
				</section>

				<section class="lit-section lit-section--alt">
					<div class="lit-section__inner">
						<?php literaindo_child_section_heading( __( 'Buku Terlaris', 'literaindo-child' ) ); ?>
						<?php echo do_shortcode( '[products limit="4" columns="4" best_selling="true"]' ); ?>
					</div>
				</section>
				<?php
			else :
				?>
				<section class="lit-section">
					<div class="lit-section__inner">
						<p class="lit-empty"><?php esc_html_e( 'Katalog buku sedang disiapkan. Silakan kembali lagi segera.', 'literaindo-child' ); ?></p>
					</div>
				</section>
				<?php
			endif;
		endif;
		?>

		<section class="lit-section lit-about" aria-labelledby="lit-about-title">
			<div class="lit-section__inner lit-about__inner">
				<div>
					<h2 id="lit-about-title" class="lit-section__title"><?php echo esc_html( literaindo_child_home_mod( 'literaindo_about_title' ) ); ?></h2>
					<p><?php echo esc_html( literaindo_child_home_mod( 'literaindo_about_text' ) ); ?></p>
					<?php
					$about_page = (int) literaindo_child_home_mod( 'literaindo_about_page' );
					if ( $about_page ) :
						?>
						<a class="lit-section__more" href="<?php echo esc_url( get_permalink( $about_page ) ); ?>"><?php esc_html_e( 'Selengkapnya tentang kami', 'literaindo-child' ); ?></a>
					<?php endif; ?>
				</div>
				<ul class="lit-about__points">
					<li><strong><?php esc_html_e( 'Terbitan resmi', 'literaindo-child' ); ?></strong><span><?php esc_html_e( 'Semua judul diterbitkan langsung oleh Literaindo Press.', 'literaindo-child' ); ?></span></li>
					<li><strong><?php esc_html_e( 'Cetak dan e-book', 'literaindo-child' ); ?></strong><span><?php esc_html_e( 'Pilih format yang paling sesuai dengan kebutuhan Anda.', 'literaindo-child' ); ?></span></li>
					<li><strong><?php esc_html_e( 'Bayar dengan QRIS', 'literaindo-child' ); ?></strong><span><?php esc_html_e( 'Pindai QRIS dari aplikasi bank atau dompet digital apa pun.', 'literaindo-child' ); ?></span></li>
				</ul>
			</div>
		</section>

	</main>
</div>

<?php
get_footer();
