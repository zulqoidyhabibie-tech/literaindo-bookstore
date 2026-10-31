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
	'dasbor'    => '<rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/>',
	'naskah'    => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h6"/>',
	'buku'      => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20V3H6.5A2.5 2.5 0 0 0 4 5.5z"/><path d="M4 19.5A2.5 2.5 0 0 0 6.5 22H20v-5"/>',
	'keranjang' => '<circle cx="9" cy="20" r="1.3"/><circle cx="18" cy="20" r="1.3"/><path d="M2 3h3l2.6 12.4a2 2 0 0 0 2 1.6h8.2a2 2 0 0 0 2-1.6L21 7H6"/>',
	'uang'      => '<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6 12h.01M18 12h.01"/>',
	'harga'     => '<path d="M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0L2 12V2h10l8.6 8.6a2 2 0 0 1 0 2.8z"/><circle cx="7" cy="7" r="1.5"/>',
	'orang'     => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8"/>',
	'atur'      => '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M4.9 4.9 7 7M17 17l2.1 2.1M2 12h3M19 12h3M4.9 19.1 7 17M17 7l2.1-2.1"/>',
	'keluar'    => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
	'tambah'    => '<circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/>',
	'lainnya'   => '<circle cx="5" cy="12" r="1.4"/><circle cx="12" cy="12" r="1.4"/><circle cx="19" cy="12" r="1.4"/>',
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
		? '<a class="navigasi__akun" href="' . e(url(beranda_peran($u['peran']))) . '">' . ikon('akun') . '<span>' . e(['admin' => 'Panel Admin', 'keuangan' => 'Panel Keuangan'][$u['peran']] ?? 'Akun Saya') . '</span></a>'
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
	$css = url('/assets/css/gaya.css') . '?v=16';

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
' . ($panel !== '' ? '<div class="tata-panel">' . $panel . '<div class="tata-panel__isi">' . $pesan . $isi . '</div></div>' : $pesan . $isi) . '
</main>
<footer class="kaki">
	<div class="wadah kaki__kolom">
		<section><h2>' . e($SITUS['nama']) . '</h2><p>' . e($SITUS['deskripsi']) . '</p></section>
		<section><h2>Tautan</h2><ul>' . $tautan . '</ul></section>
		<section><h2>Kontak Kami</h2><p>' . e($SITUS['alamat']) . '<br>WhatsApp: <a href="' . e(wa_link()) . '">' . e($SITUS['whatsapp_tampil']) . '</a><br>Email: <a href="mailto:' . e($SITUS['email']) . '">' . e($SITUS['email']) . '</a></p></section>
	</div>
	<p class="kaki__hak">© ' . $tahun . ' ' . e($SITUS['nama']) . '. Seluruh hak cipta dilindungi.</p>
</footer>
' . ($u ? '<script src="' . e(url('/assets/js/lonceng.js')) . '" defer></script><script src="' . e(url('/assets/js/rupiah.js')) . '?v=1" defer></script>' : '') . '
</body>
</html>
';
}

/** Hitung angka tugas untuk lencana menu; gagal diam-diam agar menu tetap tampil. */
function hitung_tugas(string $sql, array $param = []): int
{
	try {
		return (int) (satu($sql, $param)['n'] ?? 0);
	} catch (Throwable) {
		return 0;
	}
}

/**
 * Menu panel: sidebar kiri di layar lebar, menu bawah ala aplikasi di HP.
 * $grup: ['Judul grup' => [[path, label, ikon, angka], ...]] (judul '' = tanpa judul).
 * $utama: path yang tampil di menu bawah HP (maks. 4); sisanya di "Lainnya".
 */
function menu_panel(array $grup, array $utama, string $aktif): string
{
	$lencana = fn (int $n) => $n > 0 ? '<span class="menu-angka" aria-label="' . $n . ' perlu tindakan">' . ($n > 99 ? '99+' : $n) . '</span>' : '';
	$tautan = function (array $i, string $kelas) use ($aktif, $lencana) {
		[$path, $label, $ik, $n] = $i + [3 => 0];
		return '<a class="' . $kelas . '" href="' . e(url($path)) . '"' . ($path === $aktif ? ' aria-current="page"' : '') . '>' . ikon($ik) . '<span>' . e($label) . '</span>' . $lencana((int) $n) . '</a>';
	};
	$keluar = '<form method="post" action="' . e(url('/keluar')) . '">' . csrf_field() . '<button type="submit" class="menu-item menu-item--keluar">' . ikon('keluar') . '<span>Keluar</span></button></form>';

	$daftar = '';
	$semua = [];
	foreach ($grup as $judul => $item) {
		$daftar .= ($judul !== '' ? '<p class="menu-grup">' . e($judul) . '</p>' : '');
		foreach ($item as $i) {
			$daftar .= $tautan($i, 'menu-item');
			$semua[$i[0]] = $i;
		}
	}
	$daftar .= '<hr class="menu-garis">' . $keluar;

	$bawah = '';
	$sisa_aktif = $aktif !== '' && isset($semua[$aktif]) && !in_array($aktif, $utama, true);
	$sisa_angka = 0;
	foreach ($semua as $path => $i) {
		if (in_array($path, $utama, true)) {
			$bawah .= $tautan($i, 'menu-bawah__item');
		} else {
			$sisa_angka += (int) ($i[3] ?? 0);
		}
	}
	$bawah .= '<details class="menu-bawah__lainnya"><summary class="menu-bawah__item"' . ($sisa_aktif ? ' aria-current="page"' : '') . '>' . ikon('lainnya') . '<span>Lainnya</span>' . $lencana($sisa_angka) . '</summary>'
		. '<div class="menu-lembar" role="menu">' . $daftar . '</div></details>';

	return '<nav class="menu-samping" aria-label="Menu panel">' . $daftar . '</nav>'
		. '<nav class="menu-bawah" aria-label="Menu panel (HP)">' . $bawah . '</nav>';
}

function menu_pengaju(string $aktif): string
{
	$uid = (int) (pengguna()['id'] ?? 0);
	$giliran = "'" . implode("','", array_keys(array_filter(STATUS, fn ($s) => $s[2] === 'pengaju'))) . "'";
	$naskah = hitung_tugas("SELECT COUNT(*) AS n FROM pengajuan WHERE pengguna_id = ? AND status IN ($giliran) AND status <> 'DRAF'", [$uid]);
	$pesanan = hitung_tugas("SELECT COUNT(*) AS n FROM pesanan WHERE pengguna_id = ? AND status = 'MENUNGGU_BAYAR'", [$uid]);
	return menu_panel([
		'' => [['/akun', 'Pengajuan Saya', 'naskah', $naskah], ['/akun/pengajuan/baru', 'Ajukan Buku', 'tambah'], ['/akun/pesanan', 'Pesanan Buku', 'keranjang', $pesanan], ['/akun/profil', 'Profil', 'akun']],
	], ['/akun', '/akun/pengajuan/baru', '/akun/pesanan', '/akun/profil'], $aktif);
}

function menu_admin(string $aktif): string
{
	$naskah = hitung_tugas("SELECT COUNT(*) AS n FROM pengajuan WHERE status IN ('DITINJAU','PROSES_ISBN','LUNAS')");
	$pesanan = hitung_tugas("SELECT COUNT(*) AS n FROM pesanan WHERE status IN ('MENUNGGU_ONGKIR','DIPROSES')");
	$bayar = jumlah_verifikasi();
	return menu_panel([
		'' => [['/admin', 'Dasbor', 'dasbor']],
		'Naskah' => [['/admin/pengajuan', 'Pengajuan', 'naskah', $naskah], ['/admin/buku', 'Katalog', 'buku']],
		'Penjualan' => [['/admin/pesanan', 'Pesanan', 'keranjang', $pesanan], ['/keuangan', 'Keuangan', 'uang', $bayar], ['/admin/harga', 'Harga Cetak', 'harga']],
		'Pengaturan' => [['/admin/halaman', 'Halaman Website', 'halaman'], ['/admin/pengguna', 'Pengguna', 'orang'], ['/admin/pengaturan', 'Pengaturan', 'atur']],
	], ['/admin', '/admin/pengajuan', '/admin/pesanan', '/keuangan'], $aktif);
}

function menu_keuangan(string $aktif): string
{
	return menu_panel([
		'' => [['/keuangan', 'Transaksi', 'uang', jumlah_verifikasi()], ['/akun/profil', 'Profil', 'akun']],
	], ['/keuangan', '/akun/profil'], $aktif);
}

/** Bukti bayar (pengajuan + pesanan) yang menunggu diperiksa keuangan. */
function jumlah_verifikasi(): int
{
	return hitung_tugas("SELECT COUNT(*) AS n FROM pembayaran WHERE status = 'menunggu'")
		+ hitung_tugas("SELECT COUNT(*) AS n FROM pesanan WHERE status = 'VERIFIKASI_BAYAR'");
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
