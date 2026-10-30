<?php
/**
 * Pengaturan beranda yang bisa diubah admin lewat Tampilan → Sesuaikan → Beranda Literaindo.
 *
 * @package literaindo-child
 */

defined( 'ABSPATH' ) || exit;

/**
 * Nilai bawaan pengaturan beranda.
 */
function literaindo_child_home_defaults() {
	return array(
		'literaindo_hero_eyebrow'   => __( 'Toko Resmi Literaindo Press', 'literaindo-child' ),
		'literaindo_hero_title'     => __( 'Buku Berkualitas. Pengetahuan Tanpa Batas.', 'literaindo-child' ),
		'literaindo_hero_text'      => __( 'Buku pendidikan dan referensi akademik untuk dosen, mahasiswa, guru, peneliti, dan pembaca. Tersedia dalam format cetak dan e-book.', 'literaindo-child' ),
		'literaindo_hero_button'    => __( 'Jelajahi Buku', 'literaindo-child' ),
		'literaindo_about_title'    => __( 'Tentang Literaindo Press', 'literaindo-child' ),
		'literaindo_about_text'     => __( 'Literaindo Press menerbitkan buku pendidikan, metodologi penelitian, matematika dan statistika, serta pendidikan digital. Setiap judul di toko ini adalah terbitan resmi kami.', 'literaindo-child' ),
		'literaindo_about_page'     => 0,
	);
}

/**
 * Ambil satu pengaturan beranda dengan nilai bawaannya.
 */
function literaindo_child_home_mod( $key ) {
	$defaults = literaindo_child_home_defaults();
	return get_theme_mod( $key, isset( $defaults[ $key ] ) ? $defaults[ $key ] : '' );
}

function literaindo_child_customize_register( $wp_customize ) {
	$defaults = literaindo_child_home_defaults();

	$wp_customize->add_section(
		'literaindo_home',
		array(
			'title'    => __( 'Beranda Literaindo', 'literaindo-child' ),
			'priority' => 25,
		)
	);

	$text_fields = array(
		'literaindo_hero_eyebrow' => array( __( 'Label kecil di atas judul', 'literaindo-child' ), 'text' ),
		'literaindo_hero_title'   => array( __( 'Judul banner', 'literaindo-child' ), 'text' ),
		'literaindo_hero_text'    => array( __( 'Teks banner', 'literaindo-child' ), 'textarea' ),
		'literaindo_hero_button'  => array( __( 'Label tombol banner', 'literaindo-child' ), 'text' ),
		'literaindo_about_title'  => array( __( 'Judul bagian Tentang', 'literaindo-child' ), 'text' ),
		'literaindo_about_text'   => array( __( 'Teks bagian Tentang', 'literaindo-child' ), 'textarea' ),
	);

	foreach ( $text_fields as $key => $field ) {
		$wp_customize->add_setting(
			$key,
			array(
				'default'           => $defaults[ $key ],
				'sanitize_callback' => 'textarea' === $field[1] ? 'sanitize_textarea_field' : 'sanitize_text_field',
			)
		);
		$wp_customize->add_control(
			$key,
			array(
				'label'   => $field[0],
				'section' => 'literaindo_home',
				'type'    => $field[1],
			)
		);
	}

	$wp_customize->add_setting(
		'literaindo_about_page',
		array(
			'default'           => 0,
			'sanitize_callback' => 'absint',
		)
	);
	$wp_customize->add_control(
		'literaindo_about_page',
		array(
			'label'   => __( 'Halaman "Tentang Kami" (tautan selengkapnya)', 'literaindo-child' ),
			'section' => 'literaindo_home',
			'type'    => 'dropdown-pages',
		)
	);
}
add_action( 'customize_register', 'literaindo_child_customize_register' );
