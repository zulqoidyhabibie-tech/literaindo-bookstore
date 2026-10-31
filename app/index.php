<?php
// Literaindo Press: pintu masuk semua halaman.

declare(strict_types=1);

require __DIR__ . '/lib/inti.php';
require __DIR__ . '/lib/tampilan.php';
require __DIR__ . '/lib/berkas.php';
require __DIR__ . '/lib/skema.php';
require __DIR__ . '/lib/harga.php';
require __DIR__ . '/halaman/publik.php';
require __DIR__ . '/halaman/akun.php';
require __DIR__ . '/halaman/pengaju.php';
require __DIR__ . '/halaman/admin.php';
require __DIR__ . '/halaman/pasang.php';
require __DIR__ . '/halaman/notifikasi.php';
require __DIR__ . '/halaman/pesanan.php';
require __DIR__ . '/halaman/keuangan.php';

date_default_timezone_set('Asia/Jakarta');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');

$path = '/' . trim(substr((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), strlen(base())), '/');
$post = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';

mulai_sesi();

if (!config()) {
	$path === '/pasang' ? halaman_pasang($post) : arahkan('/pasang');
	exit;
}
pastikan_skema();
if ($post) {
	cek_csrf();
}

$rute = [
	'#^/$#'                                 => 'halaman_katalog',
	'#^/buku/([a-z0-9-]+)$#'                => 'halaman_buku',
	'#^/media/sampul/([a-zA-Z0-9._-]+)$#'   => 'media_sampul',
	'#^/masuk$#'                            => 'halaman_masuk',
	'#^/daftar$#'                           => 'halaman_daftar',
	'#^/keluar$#'                           => 'halaman_keluar',
	'#^/akun$#'                             => 'pengaju_daftar',
	'#^/akun/profil$#'                      => 'pengaju_profil',
	'#^/akun/pesanan$#'                     => 'pesanan_saya',
	'#^/pesan/([a-z0-9-]+)$#'               => 'halaman_pesan_buku',
	'#^/pesanan$#'                          => 'halaman_cek_pesanan',
	'#^/pesanan/([A-Z0-9-]+)$#'             => 'halaman_pesanan',
	'#^/pesanan/([A-Z0-9-]+)/bukti$#'       => 'unduh_bukti_pesanan',
	'#^/pesanan/([A-Z0-9-]+)/ebook$#'       => 'unduh_ebook_pesanan',
	'#^/admin/pesanan$#'                    => 'admin_pesanan',
	'#^/admin/pesanan/(\d+)$#'              => 'admin_pesanan_detail',
	'#^/akun/pengajuan/baru$#'              => 'pengaju_baru',
	'#^/akun/pengajuan/(\d+)$#'             => 'pengaju_detail',
	'#^/akun/pengajuan/(\d+)/ubah$#'        => 'pengaju_ubah',
	'#^/berkas/(\d+)$#'                     => 'unduh_berkas',
	'#^/notifikasi$#'                       => 'halaman_notifikasi',
	'#^/notifikasi/jumlah$#'                => 'notifikasi_jumlah',
	'#^/notifikasi/(\d+)$#'                 => 'notifikasi_buka',
	'#^/keuangan$#'                         => 'keuangan_transaksi',
	'#^/keuangan/unduh$#'                   => 'keuangan_unduh',
	'#^/admin$#'                            => 'admin_dasbor',
	'#^/admin/pengajuan$#'                  => 'admin_pengajuan',
	'#^/admin/pengajuan/(\d+)$#'            => 'admin_pengajuan_detail',
	'#^/admin/buku$#'                       => 'admin_buku',
	'#^/admin/buku/baru$#'                  => 'admin_buku_form',
	'#^/admin/buku/(\d+)$#'                 => 'admin_buku_form',
	'#^/admin/pengguna$#'                   => 'admin_pengguna',
	'#^/admin/pengaturan$#'                 => 'admin_pengaturan',
	'#^/admin/harga$#'                      => 'admin_harga',
	'#^/pasang$#'                           => 'tidak_ditemukan',
	'#^/([a-z0-9-]+)$#'                     => 'halaman_konten',
];

foreach ($rute as $pola => $fungsi) {
	if (preg_match($pola, $path, $m)) {
		$fungsi($post, ...array_slice($m, 1));
		exit;
	}
}
tidak_ditemukan();
