<?php
/**
 * Fungsi bantu tanpa ketergantungan WooCommerce (bisa diuji tanpa WordPress).
 *
 * @package literaindo-bookstore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Buang tanda hubung dan spasi dari ISBN. "978-623-1234-56-7" → "9786231234567".
 *
 * @param string $isbn
 * @return string
 */
function literaindo_normalize_isbn( $isbn ) {
	return strtoupper( preg_replace( '/[^0-9Xx]/', '', (string) $isbn ) );
}

/**
 * Validasi checksum ISBN-13 (atau ISBN-10 untuk buku lama).
 *
 * @param string $isbn
 * @return bool
 */
function literaindo_is_valid_isbn( $isbn ) {
	$isbn = literaindo_normalize_isbn( $isbn );

	if ( 13 === strlen( $isbn ) && ctype_digit( $isbn ) ) {
		$sum = 0;
		for ( $i = 0; $i < 12; $i++ ) {
			$sum += (int) $isbn[ $i ] * ( 0 === $i % 2 ? 1 : 3 );
		}
		return ( 10 - $sum % 10 ) % 10 === (int) $isbn[12];
	}

	if ( 10 === strlen( $isbn ) && preg_match( '/^\d{9}[\dX]$/', $isbn ) ) {
		$sum = 0;
		for ( $i = 0; $i < 10; $i++ ) {
			$digit = 'X' === $isbn[ $i ] ? 10 : (int) $isbn[ $i ];
			$sum  += $digit * ( 10 - $i );
		}
		return 0 === $sum % 11;
	}

	return false;
}

/**
 * Apakah kata kunci pencarian terlihat seperti ISBN (minimal 10 digit)?
 *
 * @param string $term
 * @return bool
 */
function literaindo_looks_like_isbn( $term ) {
	return (bool) preg_match( '/^[0-9Xx\-\s]{10,17}$/', trim( (string) $term ) )
		&& strlen( literaindo_normalize_isbn( $term ) ) >= 10;
}

/**
 * Format rupiah tanpa desimal: 95000 → "Rp95.000".
 *
 * @param float|int $amount
 * @return string
 */
function literaindo_format_rupiah( $amount ) {
	return 'Rp' . number_format( (float) $amount, 0, ',', '.' );
}
