<?php
// Daftar harga cetak (diedit admin di /admin/harga) dan taksiran biaya cetak per buku.

declare(strict_types=1);

// Daftar harga awal dari Literaindo Press (31 Oktober 2026).
// Kertas: [ukuran, kertas, warna, sisi, gramasi, harga per lembar/halaman]
const HARGA_KERTAS_AWAL = [
	['A5', 'hvs', 'hitam_putih', '1', 70, 100], ['A5', 'hvs', 'hitam_putih', '1', 80, 130], ['A5', 'hvs', 'hitam_putih', '1', 100, 230],
	['A5', 'hvs', 'hitam_putih', '2', 75, 160], ['A5', 'hvs', 'hitam_putih', '2', 80, 250], ['A5', 'hvs', 'hitam_putih', '2', 100, 450],
	['A5', 'bookpaper', 'hitam_putih', '1', 57, 100], ['A5', 'bookpaper', 'hitam_putih', '1', 72, 110], ['A5', 'bookpaper', 'hitam_putih', '1', 90, 170],
	['A5', 'bookpaper', 'hitam_putih', '2', 57, 160], ['A5', 'bookpaper', 'hitam_putih', '2', 72, 400], ['A5', 'bookpaper', 'hitam_putih', '2', 90, 280],
	['B5', 'hvs', 'hitam_putih', '1', 75, 140], ['B5', 'hvs', 'hitam_putih', '1', 80, 250], ['B5', 'hvs', 'hitam_putih', '1', 100, 450],
	['B5', 'hvs', 'hitam_putih', '2', 75, 275], ['B5', 'hvs', 'hitam_putih', '2', 80, 450], ['B5', 'hvs', 'hitam_putih', '2', 100, 550],
	['B5', 'bookpaper', 'hitam_putih', '1', 57, 140], ['B5', 'bookpaper', 'hitam_putih', '1', 72, 145], ['B5', 'bookpaper', 'hitam_putih', '1', 90, 205],
	['B5', 'bookpaper', 'hitam_putih', '2', 57, 275], ['B5', 'bookpaper', 'hitam_putih', '2', 72, 280], ['B5', 'bookpaper', 'hitam_putih', '2', 90, 340],
	['A4', 'hvs', 'hitam_putih', '1', 75, 160], ['A4', 'hvs', 'hitam_putih', '1', 80, 400], ['A4', 'hvs', 'hitam_putih', '1', 100, 550],
	['A4', 'hvs', 'hitam_putih', '2', 75, 320], ['A4', 'hvs', 'hitam_putih', '2', 80, 550], ['A4', 'hvs', 'hitam_putih', '2', 100, 650],
	['A4', 'bookpaper', 'hitam_putih', '1', 57, 160], ['A4', 'bookpaper', 'hitam_putih', '1', 72, 250], ['A4', 'bookpaper', 'hitam_putih', '1', 90, 250],
	['A4', 'bookpaper', 'hitam_putih', '2', 57, 320], ['A4', 'bookpaper', 'hitam_putih', '2', 72, 400], ['A4', 'bookpaper', 'hitam_putih', '2', 90, 460],
	['A5', 'hvs', 'warna', '1', 80, 450], ['A5', 'hvs', 'warna', '1', 100, 550],
	['A5', 'hvs', 'warna', '2', 80, 900], ['A5', 'hvs', 'warna', '2', 100, 1100],
	['A5', 'bookpaper', 'warna', '1', 72, 300], ['A5', 'bookpaper', 'warna', '1', 90, 360],
	['A5', 'bookpaper', 'warna', '2', 72, 600], ['A5', 'bookpaper', 'warna', '2', 90, 660],
	['B5', 'hvs', 'warna', '1', 80, 550], ['B5', 'hvs', 'warna', '1', 100, 650],
	['B5', 'hvs', 'warna', '2', 80, 1100], ['B5', 'hvs', 'warna', '2', 100, 1300],
	['B5', 'bookpaper', 'warna', '1', 72, 400], ['B5', 'bookpaper', 'warna', '1', 90, 460],
	['B5', 'bookpaper', 'warna', '2', 72, 800], ['B5', 'bookpaper', 'warna', '2', 90, 860],
	['A4', 'hvs', 'warna', '1', 80, 650], ['A4', 'hvs', 'warna', '1', 100, 750],
	['A4', 'hvs', 'warna', '2', 80, 1300], ['A4', 'hvs', 'warna', '2', 100, 1500],
	['A4', 'bookpaper', 'warna', '1', 72, 500], ['A4', 'bookpaper', 'warna', '1', 90, 560],
	['A4', 'bookpaper', 'warna', '2', 72, 100], ['A4', 'bookpaper', 'warna', '2', 90, 1060],
];
// Cover: [ukuran, jenis cover, jilid, laminasi, harga per buku]
const HARGA_COVER_AWAL = [
	['A5', 'hard', 'lem', 'glossy', 23000], ['A5', 'hard', 'lem', 'doff', 23000], ['A5', 'hard', 'spiral', 'doff', 25100], ['A5', 'hard', 'spiral', 'glossy', 25100],
	['A5', 'soft', 'lem', 'glossy', 13000], ['A5', 'soft', 'lem', 'doff', 13000], ['A5', 'soft', 'spiral', 'doff', 15500], ['A5', 'soft', 'spiral', 'glossy', 15500],
	['B5', 'hard', 'lem', 'glossy', 25000], ['B5', 'hard', 'lem', 'doff', 25000], ['B5', 'hard', 'spiral', 'doff', 29000], ['B5', 'hard', 'spiral', 'glossy', 29000],
	['B5', 'soft', 'lem', 'glossy', 15500], ['B5', 'soft', 'lem', 'doff', 15500], ['B5', 'soft', 'spiral', 'doff', 18000], ['B5', 'soft', 'spiral', 'glossy', 18000],
	['A4', 'hard', 'lem', 'glossy', 26000], ['A4', 'hard', 'lem', 'doff', 26000], ['A4', 'hard', 'spiral', 'doff', 31000], ['A4', 'hard', 'spiral', 'glossy', 31000],
	['A4', 'soft', 'lem', 'glossy', 17000], ['A4', 'soft', 'lem', 'doff', 17000], ['A4', 'soft', 'spiral', 'doff', 20000], ['A4', 'soft', 'spiral', 'glossy', 20000],
];

function skema_harga(string $driver): array
{
	$id = $driver === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';
	$akhir = $driver === 'sqlite' ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
	return [
		"CREATE TABLE IF NOT EXISTS harga_kertas (
			id $id,
			ukuran VARCHAR(5) NOT NULL,
			kertas VARCHAR(20) NOT NULL,
			warna VARCHAR(20) NOT NULL,
			sisi VARCHAR(2) NOT NULL,
			gramasi INT NOT NULL,
			harga INT NOT NULL
		)$akhir",
		"CREATE TABLE IF NOT EXISTS harga_cover (
			id $id,
			ukuran VARCHAR(5) NOT NULL,
			sampul VARCHAR(20) NOT NULL,
			jilid VARCHAR(20) NOT NULL,
			laminasi VARCHAR(20) NOT NULL,
			harga INT NOT NULL
		)$akhir",
	];
}

function isi_harga_awal(): void
{
	if (!(int) satu('SELECT COUNT(*) AS n FROM harga_kertas')['n']) {
		foreach (HARGA_KERTAS_AWAL as $r) {
			q('INSERT INTO harga_kertas (ukuran, kertas, warna, sisi, gramasi, harga) VALUES (?,?,?,?,?,?)', $r);
		}
	}
	if (!(int) satu('SELECT COUNT(*) AS n FROM harga_cover')['n']) {
		foreach (HARGA_COVER_AWAL as $r) {
			q('INSERT INTO harga_cover (ukuran, sampul, jilid, laminasi, harga) VALUES (?,?,?,?,?)', $r);
		}
	}
}

/** "A5 (14,8 × 21 cm)" → "A5" */
function kode_ukuran(string $ukuran): string
{
	return (string) strtok($ukuran, ' ');
}

/** true: harga kertas 2 sisi dihitung per lembar (halaman ÷ 2). */
function harga_per_lembar(): bool
{
	return pengaturan('harga_kertas_per', 'lembar') === 'lembar';
}

function data_harga(): array
{
	$int = fn (array $baris, array $kolom) => array_map(function ($r) use ($kolom) {
		foreach ($kolom as $k) {
			$r[$k] = (int) $r[$k];
		}
		return $r;
	}, $baris);
	return [
		'kertas' => $int(semua("SELECT * FROM harga_kertas ORDER BY CASE ukuran WHEN 'A5' THEN 1 WHEN 'B5' THEN 2 ELSE 3 END, warna, kertas, sisi, gramasi"), ['id', 'gramasi', 'harga']),
		'cover' => $int(semua("SELECT * FROM harga_cover ORDER BY CASE ukuran WHEN 'A5' THEN 1 WHEN 'B5' THEN 2 ELSE 3 END, sampul DESC, jilid, laminasi"), ['id', 'harga']),
		'per_lembar' => harga_per_lembar(),
	];
}

/** "xxix + 265" → 294; "180" → 180. Angka Romawi dan angka biasa dijumlahkan. */
function hitung_halaman(string $teks): int
{
	$total = 0;
	$nilai = ['i' => 1, 'v' => 5, 'x' => 10, 'l' => 50, 'c' => 100, 'd' => 500, 'm' => 1000];
	foreach (preg_split('/[^0-9a-z]+/i', mb_strtolower($teks), -1, PREG_SPLIT_NO_EMPTY) as $t) {
		if (ctype_digit($t)) {
			$total += (int) $t;
		} elseif (preg_match('/^[ivxlcdm]+$/', $t)) {
			$n = 0;
			for ($i = 0, $len = strlen($t); $i < $len; $i++) {
				$v = $nilai[$t[$i]];
				$n += ($i + 1 < $len && $nilai[$t[$i + 1]] > $v) ? -$v : $v;
			}
			$total += $n;
		}
	}
	return $total;
}

/**
 * Taksiran biaya cetak satu buku dari daftar harga.
 * Hasil: [isi, cover, per_buku, lembar, harga_kertas, catatan[]]
 */
function taksiran_cetak(array $p, int $halaman, int $gramasi): array
{
	$uk = kode_ukuran((string) $p['ukuran']);
	$catatan = [];
	$kertas = $gramasi ? satu('SELECT harga FROM harga_kertas WHERE ukuran=? AND kertas=? AND warna=? AND sisi=? AND gramasi=?', [$uk, $p['jenis_kertas'], $p['warna_isi'], $p['sisi_cetak'], $gramasi]) : null;
	if (!$kertas) {
		$catatan[] = 'Harga kertas untuk spesifikasi ini belum ada di daftar harga.';
	}
	$cover = satu('SELECT harga FROM harga_cover WHERE ukuran=? AND sampul=? AND jilid=? AND laminasi=?', [$uk, $p['jenis_sampul'], $p['jilid'], $p['laminasi']]);
	if (!$cover) {
		$catatan[] = 'Harga cover untuk spesifikasi ini belum ada di daftar harga.';
	}
	$lembar = ($p['sisi_cetak'] === '2' && harga_per_lembar()) ? (int) ceil($halaman / 2) : $halaman;
	$isi = $kertas ? $lembar * (int) $kertas['harga'] : 0;
	$c = $cover ? (int) $cover['harga'] : 0;
	return ['isi' => $isi, 'cover' => $c, 'per_buku' => $isi + $c, 'lembar' => $lembar, 'harga_kertas' => $kertas ? (int) $kertas['harga'] : 0, 'catatan' => $catatan];
}

// ---------------------------------------------------------------------------
// Paket penerbitan. Literaindo hanya menerima naskah siap terbit (tanpa editing/layout/desain).
// [nama, eksemplar wajib, termasuk HKI, kunci pengaturan harga, harga bawaan, ringkasan]
const PAKET = [
	'aksara' => ['Paket Aksara', 5, false, 'harga_paket_aksara', 350000, 'Penerbitan ber-ISBN + cetak 5 eksemplar'],
	'pustaka' => ['Paket Pustaka', 10, true, 'harga_paket_pustaka', 750000, 'Penerbitan ber-ISBN + cetak 10 eksemplar + sertifikat HKI'],
	'digital' => ['Paket Digital', 0, false, 'harga_paket_digital', 250000, 'Penerbitan e-book ber-ISBN (PDF), tanpa cetak'],
];

/** Jenis ISBN yang diajukan: [label, keterangan]. Perpusnas memberi ISBN terpisah untuk cetak dan e-book. */
const FORMAT_TERBIT = [
	'cetak' => ['Buku cetak', 'ISBN untuk buku fisik. Pilih Paket Aksara atau Pustaka.'],
	'ebook' => ['E-book saja (PDF)', 'ISBN elektronik tanpa cetak. Memakai Paket Digital; berkas PDF diserahkan ke Perpusnas.'],
	'keduanya' => ['Cetak + E-book', 'Dua ISBN: buku cetak dan e-book. Harga sama dengan paket cetak yang dipilih, tanpa tambahan.'],
];

/** Paket yang cocok untuk jenis ISBN tertentu. */
function paket_untuk_format(string $format): array
{
	return $format === 'ebook' ? ['digital'] : ['aksara', 'pustaka'];
}
const SPEK_STANDAR = ['ukuran' => 'A5 (14,8 × 21 cm)', 'sisi_cetak' => '2', 'warna_isi' => 'hitam_putih', 'jenis_kertas' => 'hvs', 'jenis_sampul' => 'soft', 'jilid' => 'lem', 'laminasi' => 'doff'];
const EKS_TIDAK_UNTUK_PENULIS = 4; // 2 Perpusnas, 1 Perpusda, 1 arsip penerbit

function harga_paket(string $k): int
{
	return isset(PAKET[$k]) ? (int) pengaturan(PAKET[$k][3], (string) PAKET[$k][4]) : 0;
}

function margin_cetak(): int
{
	return (int) pengaturan('margin_cetak', '30');
}

function batas_halaman_paket(): int
{
	return (int) pengaturan('batas_halaman_paket', '200');
}

/** Harga cetak per buku (sudah termasuk margin) untuk buku standar setebal batas halaman paket. */
function per_buku_standar(): int
{
	$gram = satu('SELECT MIN(gramasi) AS g FROM harga_kertas WHERE ukuran=? AND kertas=? AND warna=? AND sisi=?', ['A5', 'hvs', 'hitam_putih', '2']);
	$t = taksiran_cetak(SPEK_STANDAR, batas_halaman_paket(), (int) ($gram['g'] ?? 0));
	return (int) round($t['per_buku'] * (100 + margin_cetak()) / 100);
}

/** Data paket untuk taksiran.js dan tampilan. */
function data_paket(): array
{
	$out = [];
	foreach (PAKET as $k => [$nama, $eks, $hki, , , $ringkas]) {
		$out[$k] = ['nama' => $nama, 'eks' => $eks, 'hki' => $hki, 'harga' => harga_paket($k), 'ringkas' => $ringkas];
	}
	return $out;
}

/** Kartu paket untuk halaman publik dan formulir. $nama_input kosong = hanya tampilan. */
function kartu_paket(string $nama_input = '', string $terpilih = ''): string
{
	$batas = batas_halaman_paket();
	$out = '<div class="paket-grid">';
	foreach (data_paket() as $k => $p) {
		$digital = $p['eks'] === 0;
		$isi = '<span class="paket__nama">' . e($p['nama']) . '</span><span class="paket__harga">' . rupiah($p['harga']) . '</span>'
			. ($digital
				? '<ul class="paket__isi"><li>Diterbitkan Literaindo Press sebagai e-book, dengan ISBN dari Perpustakaan Nasional RI (gratis)</li><li>Tanpa cetak; berkas PDF diserahkan ke Perpusnas sebagai wajib serah digital</li><li>Halaman buku di katalog dan bisa dijual sebagai e-book</li></ul>'
					. '<span class="paket__catatan">Untuk naskah e-book siap terbit (PDF). Ingin sekaligus versi cetak? Pilih jenis ISBN Cetak + E-book dengan Paket Aksara atau Pustaka, tanpa biaya tambahan.</span>'
				: '<ul class="paket__isi"><li>Diterbitkan Literaindo Press, dengan ISBN dari Perpustakaan Nasional RI (gratis)</li><li>Cetak ' . $p['eks'] . ' eksemplar (' . ($p['eks'] - EKS_TIDAK_UNTUK_PENULIS) . ' untuk penulis, ' . EKS_TIDAK_UNTUK_PENULIS . ' untuk wajib serah dan arsip)</li>'
					. ($p['hki'] ? '<li>Sertifikat HKI (hak cipta)</li>' : '')
					. '<li>Halaman buku di katalog Literaindo Press</li><li>Bisa sekaligus terbit sebagai e-book tanpa biaya tambahan</li></ul>'
					. '<span class="paket__catatan">Untuk buku A5 hingga ' . $batas . ' halaman, HVS hitam putih, soft cover. Halaman lebih banyak atau spesifikasi lain menyesuaikan daftar harga cetak. Belum termasuk ongkos kirim.</span>');
		$out .= $nama_input
			? '<label class="paket"' . ($digital ? ' data-untuk-ebook' : ' data-untuk-cetak') . '><input type="radio" name="' . e($nama_input) . '" value="' . $k . '" data-eks="' . $p['eks'] . '"' . ($k === $terpilih ? ' checked' : '') . '>' . $isi . '</label>'
			: '<div class="paket">' . $isi . '</div>';
	}
	return $out . '</div>';
}
