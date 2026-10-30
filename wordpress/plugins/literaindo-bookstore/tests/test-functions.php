<?php
/**
 * Uji fungsi bantu tanpa WordPress: php tests/test-functions.php
 */

define( 'ABSPATH', __DIR__ );
require __DIR__ . '/../includes/functions.php';

$failures = 0;
function check( $label, $actual, $expected ) {
	global $failures;
	if ( $actual === $expected ) {
		echo "ok   - $label\n";
		return;
	}
	$failures++;
	echo "FAIL - $label: expected " . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) . "\n";
}

check( 'normalisasi ISBN dengan tanda hubung', literaindo_normalize_isbn( '978-602-8519-93-9' ), '9786028519939' );
check( 'ISBN-13 valid', literaindo_is_valid_isbn( '978-602-8519-93-9' ), true );
check( 'ISBN-13 valid (0-306-40615-2 versi 13)', literaindo_is_valid_isbn( '9780306406157' ), true );
check( 'ISBN-13 angka kontrol salah', literaindo_is_valid_isbn( '9780306406158' ), false );
check( 'ISBN-10 valid', literaindo_is_valid_isbn( '0-306-40615-2' ), true );
check( 'ISBN-10 dengan X', literaindo_is_valid_isbn( '0-8044-2957-X' ), true );
check( 'ISBN terlalu pendek', literaindo_is_valid_isbn( '12345' ), false );
check( 'kata kunci ISBN dikenali', literaindo_looks_like_isbn( '978-602-8519-93-9' ), true );
check( 'judul bukan ISBN', literaindo_looks_like_isbn( 'Metodologi Penelitian' ), false );
check( 'tahun bukan ISBN', literaindo_looks_like_isbn( '2026' ), false );
check( 'format rupiah', literaindo_format_rupiah( 95000 ), 'Rp95.000' );
check( 'format rupiah jutaan', literaindo_format_rupiah( 1250000.0 ), 'Rp1.250.000' );

exit( $failures ? 1 : 0 );
