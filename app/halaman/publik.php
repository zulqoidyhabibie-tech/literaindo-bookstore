<?php
// Halaman publik: katalog, detail buku, halaman konten, sampul.

declare(strict_types=1);

function penulis_daftar(string $s): array
{
	return array_values(array_filter(array_map('trim', preg_split('/[\n;]+/', $s) ?: [])));
}

function buku_url(array $b): string
{
	return '/buku/' . $b['slug'] . '/';
}

function sampul_html(array $b, int $lebar, int $tinggi, string $lazy = ''): string
{
	if ($b['sampul'] === '') {
		return '<span class="kartu__tanpa-sampul">' . e($b['judul']) . '</span>';
	}
	return '<img src="' . e(url($b['sampul'])) . '" alt="Sampul ' . e($b['judul']) . '" width="' . $lebar . '" height="' . $tinggi . '"' . ($lazy ? ' loading="lazy"' : '') . '>';
}

function pilihan_filter(string $nama, string $label, array $nilai): string
{
	$nilai = array_values(array_unique(array_filter($nilai, fn ($v) => $v !== '')));
	sort($nilai, SORT_NATURAL | SORT_FLAG_CASE);
	$opsi = '';
	foreach ($nilai as $v) {
		$opsi .= '<option value="' . e($v) . '">' . e($v) . '</option>';
	}
	return '<label class="filter__isian"><span class="tersembunyi">' . e($label) . '</span><select name="' . $nama . '" id="f-' . $nama . '"><option value="">' . e($label) . '</option>' . $opsi . '</select></label>';
}

function halaman_katalog(bool $post): void
{
	$buku = semua('SELECT * FROM buku WHERE tampil = 1 ORDER BY dibuat DESC, id DESC');
	$kartu = '';
	$data = [];
	$semua_penulis = [];
	foreach ($buku as $b) {
		$penulis = penulis_daftar($b['penulis']);
		$semua_penulis = array_merge($semua_penulis, $penulis);
		$data[] = [
			'slug' => $b['slug'],
			'judul' => mb_strtolower($b['judul'] . ($b['subjudul'] ? ': ' . $b['subjudul'] : '')),
			'isbn' => preg_replace('/[^0-9xX ]/', '', $b['isbn'] . ' ' . $b['eisbn']),
			'kategori' => $b['kategori'],
			'tahun' => $b['tahun'],
			'bahasa' => $b['bahasa'],
			'penulis' => $penulis,
		];
		$kartu .= '<li class="kartu" data-buku="' . e($b['slug']) . '">
	<a class="kartu__tautan" href="' . e(url(buku_url($b))) . '">
		<span class="kartu__sampul">' . sampul_html($b, 200, 300, 'lazy') . '</span>
		' . ($b['status'] === 'segera_terbit' ? '<span class="label label--segera">Segera Terbit</span>' : '') . '
		<span class="kartu__judul">' . e($b['judul']) . '</span>
	</a>
	<span class="kartu__kategori">' . e($b['kategori']) . '</span>
	<span class="kartu__penulis">' . e(implode(', ', $penulis)) . '</span>
</li>';
	}

	$isi = '<section class="panel" id="katalog" aria-labelledby="judul-katalog">
	<h1 id="judul-katalog">Katalog</h1>
	<form class="filter" role="search" action="' . e(url('/')) . '" method="get">
		<label class="filter__isian"><span class="tersembunyi">Judul buku</span><input type="search" name="judul" id="f-judul" placeholder="Judul buku"></label>
		<label class="filter__isian"><span class="tersembunyi">ISBN</span><input type="search" name="isbn" id="f-isbn" placeholder="ISBN" inputmode="numeric"></label>
		' . pilihan_filter('kategori', 'Semua Kategori', array_column($buku, 'kategori'))
		. pilihan_filter('tahun', 'Semua Tahun', array_column($buku, 'tahun'))
		. pilihan_filter('bahasa', 'Semua Bahasa', array_column($buku, 'bahasa'))
		. pilihan_filter('penulis', 'Semua Penulis', $semua_penulis) . '
		<div class="filter__tombol">
			<button type="submit" class="tombol">Cari Buku</button>
			<button type="reset" class="tombol tombol--ikon" aria-label="Atur ulang pencarian" title="Atur ulang">' . ikon('ulang') . '</button>
		</div>
	</form>
	<p class="katalog__jumlah" aria-live="polite">Menampilkan ' . count($buku) . ' buku</p>
	<ul class="katalog">' . $kartu . '</ul>
	<p class="katalog__kosong"' . ($buku ? ' hidden' : '') . '>' . ($buku ? 'Buku tidak ditemukan. Coba kata kunci lain atau atur ulang pencarian.' : 'Katalog sedang disiapkan.') . '</p>
</section>
<script type="application/json" id="data-katalog">' . str_replace('<', '<', (string) json_encode($data, JSON_UNESCAPED_UNICODE)) . '</script>
<script src="' . e(url('/assets/js/katalog.js')) . '" defer></script>';

	tampil('', $isi, ['path' => '/', 'kelas' => 'beranda']);
}

function halaman_buku(bool $post, string $slug): void
{
	global $SITUS;
	$b = satu('SELECT * FROM buku WHERE slug = ? AND tampil = 1', [$slug]);
	if (!$b) {
		tidak_ditemukan();
	}
	$segera = $b['status'] === 'segera_terbit';
	$penulis = penulis_daftar($b['penulis']);
	$judul_lengkap = $b['judul'] . ($b['subjudul'] ? ': ' . $b['subjudul'] : '');

	$baris = array_filter([
		['penulis', 'Penulis', implode(', ', $penulis)],
		['editor', 'Editor', $b['editor']],
		['penerbit', 'Penerbit', $SITUS['nama']],
		['isbn', 'ISBN', $b['isbn'] ?: ($segera ? 'Dalam proses pengajuan' : '')],
		['isbn', 'e-ISBN', $b['eisbn']],
		['tahun', 'Tahun terbit', $b['tahun']],
		['edisi', 'Edisi', $b['edisi']],
		['halaman', 'Jumlah halaman', $b['halaman']],
		['ukuran', 'Ukuran', $b['ukuran']],
		['bahasa', 'Bahasa', $b['bahasa']],
		['kategori', 'Kategori', $b['kategori']],
		['status', 'Status', $segera ? 'Segera Terbit' : 'Sudah Terbit'],
	], fn ($r) => $r[2] !== '');
	$dl = '';
	foreach ($baris as [$ik, $label, $v]) {
		$dl .= '<div><dt>' . ikon($ik) . e($label) . '</dt><dd>' . e($v) . '</dd></div>';
	}

	$harga = '';
	if ($b['harga_cetak'] > 0) {
		$harga .= '<li><span>Cetak</span><strong>' . rupiah($b['harga_cetak']) . '</strong></li>';
	}
	if ($b['harga_ebook'] > 0) {
		$harga .= '<li><span>E-book (PDF)</span><strong>' . rupiah($b['harga_ebook']) . '</strong></li>';
	}

	$pesan = $segera
		? '<p class="catatan">Buku ini segera terbit dan belum dapat dipesan.</p><a class="tombol tombol--wa" href="' . e(wa_link("Halo {$SITUS['nama']}, saya ingin menanyakan jadwal terbit buku \"{$b['judul']}\".")) . '">' . ikon('wa') . ' Tanya jadwal terbit</a>'
		: (($b['harga_cetak'] > 0 || ($b['harga_ebook'] > 0 && $b['ebook_path'] !== ''))
			? '<a class="tombol" href="' . e(url('/pesan/' . $b['slug'])) . '">Beli Buku Ini</a><p class="catatan">Pesan langsung di website. Total pembayaran dan QRIS tampil di halaman pesanan pribadi Anda. Lihat <a href="' . e(url('/cara-pemesanan/')) . '">cara pemesanan</a>.</p>'
			: '<p class="catatan">Buku ini belum dijual.</p>')
			. '<a class="tautan-wa" href="' . e(wa_link("Halo {$SITUS['nama']}, saya ingin bertanya tentang buku \"{$b['judul']}\".")) . '">' . ikon('wa') . ' Tanya via WhatsApp</a>';

	$deskripsi = '';
	foreach (preg_split('/\n\s*\n/', (string) $b['deskripsi']) ?: [] as $p) {
		if (trim($p) !== '') {
			$deskripsi .= '<p>' . nl2br(e(trim($p))) . '</p>';
		}
	}
	$ringkas = mb_substr(trim((string) preg_replace('/\s+/', ' ', (string) $b['deskripsi'])), 0, 155);

	$schema = array_filter([
		'@context' => 'https://schema.org',
		'@type' => 'Book',
		'name' => $judul_lengkap,
		'url' => url_absolut(buku_url($b)),
		'author' => array_map(fn ($n) => ['@type' => 'Person', 'name' => $n], $penulis),
		'publisher' => ['@type' => 'Organization', 'name' => $SITUS['nama']],
		'inLanguage' => $b['bahasa'] === 'Indonesia' ? 'id' : $b['bahasa'],
		'isbn' => $b['isbn'],
		'datePublished' => $b['tahun'],
		'image' => $b['sampul'] ? url_absolut($b['sampul']) : '',
		'description' => $ringkas,
	]);

	$isi = '<article class="panel buku">
	<div class="buku__atas">
		<figure class="buku__sampul">' . sampul_html($b, 400, 600) . '</figure>
		<div class="buku__info">
			' . ($segera ? '<span class="label label--segera">Segera Terbit</span>' : '') . '
			<h1>' . e($b['judul']) . ($b['subjudul'] ? '<span class="buku__subjudul">' . e($b['subjudul']) . '</span>' : '') . '</h1>
			<dl class="buku__data">' . $dl . '</dl>
			' . ($harga ? '<ul class="buku__harga">' . $harga . '</ul>' : '') . '
			<div class="buku__pesan">' . $pesan . '</div>
		</div>
	</div>
	' . ($deskripsi ? '<section class="buku__deskripsi" aria-labelledby="judul-deskripsi"><h2 id="judul-deskripsi">' . ikon('deskripsi') . 'Deskripsi</h2>' . $deskripsi . '</section>' : '') . '
	<a class="tombol tombol--kembali" href="' . e(url('/#katalog')) . '">' . ikon('kembali') . ' Kembali ke Katalog</a>
</article>';

	tampil($judul_lengkap, $isi, [
		'path' => buku_url($b),
		'deskripsi' => $ringkas ?: "$judul_lengkap, diterbitkan {$SITUS['nama']}.",
		'kelas' => 'halaman-buku',
		'kepala' => '<meta property="og:type" content="book">' . ($b['sampul'] ? '<meta property="og:image" content="' . e(url_absolut($b['sampul'])) . '">' : '')
			. '<script type="application/ld+json">' . str_replace('<', '<', (string) json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '</script>',
	]);
}

function halaman_konten(bool $post, string $slug): void
{
	global $SITUS;
	$file = APP_DIR . '/konten/' . $slug . '.html';
	if (!is_file($file)) {
		tidak_ditemukan();
	}
	$mentah = (string) file_get_contents($file);
	preg_match('/<!--\s*judul:\s*(.+?)\s*-->/', $mentah, $m);
	$judul = $m[1] ?? $slug;
	$ganti = [
		'badan_usaha' => e($SITUS['badan_usaha']),
		'alamat' => e($SITUS['alamat']),
		'email' => e($SITUS['email']),
		'whatsapp_tampil' => e($SITUS['whatsapp_tampil']),
		'jam_layanan' => e($SITUS['jam_layanan']),
		'qris_nama_merchant' => e($SITUS['qris_nama_merchant']),
		'wa_link' => e(wa_link()),
		'qris' => blok_qris(),
		'biaya_awal' => rupiah(pengaturan('biaya_awal', '250000')),
		'paket' => kartu_paket(),
		'url_daftar' => e(url('/daftar')),
		'url_akun' => e(url('/akun')),
	];
	$html = preg_replace_callback('/\{\{(\w+)\}\}/', fn ($x) => $ganti[$x[1]] ?? $x[0], (string) preg_replace('/<!--\s*judul:.*?-->\s*/', '', $mentah));
	tampil($judul, '<article class="panel konten"><h1>' . e($judul) . "</h1>\n" . $html . '</article>', ['path' => "/$slug/", 'kelas' => 'halaman-konten']);
}

function media_sampul(bool $post, string $nama): void
{
	kirim_berkas('sampul/' . basename($nama), $nama, true, 86400 * 30);
}
