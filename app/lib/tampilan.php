<?php
// Kerangka halaman (header biru, isi, footer gelap) dan ikon.

declare(strict_types=1);

const IKON = [
	'penulis'   => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/>',
	'editor'    => '<path d="M4 20h4L19 9l-4-4L4 16z"/>',
	'penerbit'  => '<path d="M4 21V5l8-3 8 3v16"/><path d="M9 21v-5h6v5M8 9h2M14 9h2M8 13h2M14 13h2"/>',
	'isbn'      => '<path d="M4 9h16M4 15h16M10 3 8 21M16 3l-2 18"/>',
	'tahun'     => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
	'halaman'   => '<path d="M6 2h9l5 5v15H6z"/><path d="M14 2v6h6"/>',
	'ukuran'    => '<path d="M3 21 21 3M14 3h7v7M10 21H3v-7"/>',
	'bahasa'    => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c3 3 3 15 0 18M12 3c-3 3-3 15 0 18"/>',
	'kategori'  => '<path d="M3 12V3h9l9 9-9 9z"/><circle cx="8" cy="8" r="1.5"/>',
	'edisi'     => '<path d="M4 4h12l4 4v12H4z"/><path d="M8 12h8M8 16h5"/>',
	'status'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/>',
	'deskripsi' => '<path d="M4 20h4L19 9l-4-4L4 16z"/><path d="M13 7l4 4"/>',
	'cari'      => '<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>',
	'ulang'     => '<path d="M20 11a8 8 0 1 0-2.3 5.7"/><path d="M20 4v7h-7"/>',
	'kembali'   => '<path d="m11 6-6 6 6 6M5 12h14"/>',
	'wa'        => '<path d="M4 20l1.3-4A8 8 0 1 1 8 18.7z"/><path d="M9 9c0 3 3 6 6 6l1-1.5-2-1-1 1c-1-.5-2-1.5-2.5-2.5l1-1-1-2z"/>',
	'akun'      => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/>',
	'lonceng'   => '<path d="M6 16V11a6 6 0 0 1 12 0v5l2 2H4z"/><path d="M10 20a2 2 0 0 0 4 0"/>',
	'unduh'     => '<path d="M12 3v12M7 10l5 5 5-5M5 21h14"/>',
];

function ikon(string $nama): string
{
	return '<svg class="ikon" viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . IKON[$nama] . '</svg>';
}

function label_status(string $s): string
{
	$jenis = STATUS[$s][2] === 'pengaju' ? 'giliran' : ($s === 'SELESAI' ? 'selesai' : ($s === 'DITOLAK' ? 'tolak' : 'proses'));
	return '<span class="lencana lencana--' . $jenis . '">' . e(STATUS[$s][0]) . '</span>';
}

/**
 * Cetak halaman lengkap.
 * $opsi: path (untuk menu aktif & canonical), deskripsi, kelas, kepala (HTML tambahan di <head>), panel (menu panel).
 */
function tampil(string $judul, string $isi, array $opsi = []): void
{
	global $SITUS;
	$path = $opsi['path'] ?? strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
	$deskripsi = $opsi['deskripsi'] ?? $SITUS['deskripsi'];
	$judul_tab = $judul ? "$judul | {$SITUS['nama']}" : "{$SITUS['nama']} | {$SITUS['tagline']}";
	$u = function_exists('pengguna') && config() ? pengguna() : null;

	$menu = '';
	foreach ($SITUS['menu'] as $m) {
		$aktif = $m['url'] === $path || ($m['url'] === '/' && str_starts_with((string) $path, '/buku/'));
		$menu .= '<li><a href="' . e(url($m['url'])) . '"' . ($aktif ? ' aria-current="page"' : '') . '>' . e($m['label']) . '</a></li>';
	}
	$akun = $u
		? '<a class="navigasi__akun" href="' . e(url($u['peran'] === 'admin' ? '/admin' : '/akun')) . '">' . ikon('akun') . '<span>' . e($u['peran'] === 'admin' ? 'Panel Admin' : 'Akun Saya') . '</span></a>'
		: '<a class="navigasi__akun" href="' . e(url('/masuk')) . '">' . ikon('akun') . '<span>Masuk</span></a>';

	if ($u) {
		$n = jumlah_notif((int) $u['id']);
		$akun .= '<a class="navigasi__lonceng" href="' . e(url('/notifikasi')) . '" aria-label="Notifikasi' . ($n ? ", $n belum dibaca" : '') . '" title="Notifikasi" data-jumlah-url="' . e(url('/notifikasi/jumlah')) . '">' . ikon('lonceng')
			. '<span class="lonceng__angka"' . ($n ? '' : ' hidden') . '>' . ($n > 99 ? '99+' : $n) . '</span></a>';
	}

	$tautan = '';
	foreach ($SITUS['tautan_terkait'] as $t) {
		$href = str_starts_with($t['url'], '/') ? url($t['url']) : $t['url'];
		$tautan .= '<li><a href="' . e($href) . '">' . e($t['label']) . '</a></li>';
	}

	$pesan = session_status() === PHP_SESSION_ACTIVE ? ambil_pesan() : '';
	$panel = $opsi['panel'] ?? '';
	$tahun = date('Y');
	$css = url('/assets/css/gaya.css') . '?v=6';

	echo '<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>' . e($judul_tab) . '</title>
<meta name="description" content="' . e($deskripsi) . '">
<link rel="canonical" href="' . e(url_absolut((string) $path)) . '">
<meta property="og:site_name" content="' . e($SITUS['nama']) . '">
<meta property="og:title" content="' . e($judul ?: $SITUS['nama']) . '">
<meta property="og:description" content="' . e($deskripsi) . '">
<link rel="icon" href="' . e(url('/favicon.png')) . '" type="image/png">
<link rel="apple-touch-icon" href="' . e(url('/assets/img/apple-touch-icon.png')) . '">
<meta name="theme-color" content="' . e($SITUS['warna_utama']) . '">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="' . e($css) . '">
<style>:root{--warna-utama:' . e($SITUS['warna_utama']) . '}</style>
' . ($opsi['kepala'] ?? '') . '</head>
<body class="' . e($opsi['kelas'] ?? '') . '">
<a class="lewati" href="#isi">Langsung ke isi</a>
<header class="kepala">
	<div class="wadah kepala__merek">
		<a class="merek" href="' . e(url('/')) . '">
			<img class="merek__logo" src="' . e(url('/assets/img/logo-literaindo.png')) . '" alt="' . e($SITUS['nama']) . '" width="800" height="235">
			<span class="merek__tagline">' . e($SITUS['tagline']) . '</span>
		</a>
	</div>
	<nav class="navigasi" aria-label="Menu utama">
		<div class="wadah navigasi__isi">
			<details class="navigasi__lipat"><summary>Menu</summary><ul>' . $menu . '</ul></details>
			<ul class="navigasi__menu">' . $menu . '</ul>
			' . $akun . '
			<a class="navigasi__cari" href="' . e(url('/#katalog')) . '" aria-label="Cari buku">' . ikon('cari') . '</a>
		</div>
	</nav>
</header>
<main id="isi" class="wadah">
' . $panel . $pesan . $isi . '
</main>
<footer class="kaki">
	<div class="wadah kaki__kolom">
		<section><h2>' . e($SITUS['nama']) . '</h2><p>' . e($SITUS['deskripsi']) . '</p></section>
		<section><h2>Tautan</h2><ul>' . $tautan . '</ul></section>
		<section><h2>Kontak Kami</h2><p>' . e($SITUS['alamat']) . '<br>WhatsApp: <a href="' . e(wa_link()) . '">' . e($SITUS['whatsapp_tampil']) . '</a><br>Email: <a href="mailto:' . e($SITUS['email']) . '">' . e($SITUS['email']) . '</a></p></section>
	</div>
	<p class="kaki__hak">© ' . $tahun . ' ' . e($SITUS['nama']) . '. Seluruh hak cipta dilindungi.</p>
</footer>
' . ($u ? '<script src="' . e(url('/assets/js/lonceng.js')) . '" defer></script>' : '') . '
</body>
</html>
';
}

/** Menu tab untuk panel pengaju/admin. */
function menu_panel(array $item, string $aktif): string
{
	$out = '<nav class="panel-menu" aria-label="Menu panel"><ul>';
	foreach ($item as $path => $label) {
		$out .= '<li><a href="' . e(url($path)) . '"' . ($path === $aktif ? ' aria-current="page"' : '') . '>' . e($label) . '</a></li>';
	}
	$out .= '<li class="panel-menu__keluar"><form method="post" action="' . e(url('/keluar')) . '">' . csrf_field() . '<button type="submit" class="tautan">Keluar</button></form></li>';
	return $out . '</ul></nav>';
}

function menu_pengaju(string $aktif): string
{
	return menu_panel(['/akun' => 'Pengajuan Saya', '/akun/pengajuan/baru' => 'Ajukan Buku Baru', '/akun/profil' => 'Profil'], $aktif);
}

function menu_admin(string $aktif): string
{
	return menu_panel(['/admin' => 'Dasbor', '/admin/pengajuan' => 'Pengajuan', '/admin/buku' => 'Katalog', '/admin/pengguna' => 'Pengguna', '/admin/pengaturan' => 'Pengaturan'], $aktif);
}

/** Blok QRIS dengan nominal yang harus dibayar. */
function blok_qris(int $nominal = 0): string
{
	global $SITUS;
	$gambar = pengaturan_aman('qris_gambar', $SITUS['qris_gambar']);
	return '<figure class="qris"><img src="' . e(url($gambar)) . '" alt="Kode QRIS ' . e($SITUS['qris_nama_merchant']) . '" width="300">'
		. '<figcaption>QRIS a.n. ' . e($SITUS['qris_nama_merchant']) . ($nominal ? '<br><span class="qris__nominal">Bayar tepat ' . rupiah($nominal) . '</span>' : '') . '</figcaption></figure>';
}

function pengaturan_aman(string $k, string $bawaan): string
{
	try {
		return config() ? pengaturan($k, $bawaan) : $bawaan;
	} catch (Throwable) {
		return $bawaan;
	}
}
