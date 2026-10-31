<?php
// Pemesanan buku lewat website: formulir pesanan, halaman pesanan pribadi
// (link rahasia), pembayaran QRIS + bukti, verifikasi keuangan, resi, dan
// unduhan e-book berbatas waktu.

declare(strict_types=1);

const EBOOK_HARI = 7;
const STATUS_PESANAN = [
	'MENUNGGU_ONGKIR'  => ['Menghitung ongkir', 'Kami sedang menghitung ongkos kirim ke alamat Anda. Anda akan diberi tahu lewat email setelah total pembayaran siap.', 'proses'],
	'MENUNGGU_BAYAR'   => ['Menunggu pembayaran', 'Silakan bayar tepat sesuai total melalui QRIS, lalu unggah bukti bayar di halaman ini.', 'giliran'],
	'VERIFIKASI_BAYAR' => ['Pembayaran diperiksa', 'Bukti bayar Anda sedang dicek bagian keuangan.', 'proses'],
	'DIPROSES'         => ['Dikemas', 'Pembayaran diterima. Pesanan sedang dikemas.', 'proses'],
	'DIKIRIM'          => ['Dikirim', 'Pesanan sudah dikirim. Lacak dengan nomor resi di bawah.', 'proses'],
	'SELESAI'          => ['Selesai', 'Pesanan selesai. Terima kasih.', 'selesai'],
	'DIBATALKAN'       => ['Dibatalkan', 'Pesanan ini dibatalkan.', 'tolak'],
];
const FORMAT_PESANAN = ['cetak' => 'Buku cetak', 'ebook' => 'E-book (PDF)'];

function label_pesanan(string $s): string
{
	return '<span class="lencana lencana--' . (STATUS_PESANAN[$s][2] ?? 'proses') . '">' . e(STATUS_PESANAN[$s][0] ?? $s) . '</span>';
}

function link_pesanan(array $o, bool $absolut = false): string
{
	$path = '/pesanan/' . $o['kode'] . '?t=' . $o['token'];
	return $absolut ? url_absolut($path) : url($path);
}

/** Simpan file e-book dari formulir katalog admin (DATA_DIR/berkas/ebook). */
function simpan_ebook(int $buku_id): void
{
	$f = $_FILES['ebook'] ?? null;
	if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
		return;
	}
	if ($f['error'] !== UPLOAD_ERR_OK || $f['size'] > 30 * 1024 * 1024) {
		throw new RuntimeException('File e-book gagal diunggah. Maksimal 30 MB.');
	}
	$mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']) ?: '';
	if (strtolower(pathinfo((string) $f['name'], PATHINFO_EXTENSION)) !== 'pdf' || $mime !== 'application/pdf') {
		throw new RuntimeException('File e-book harus berformat PDF.');
	}
	$folder = DATA_DIR . '/berkas/ebook';
	if (!is_dir($folder) && !mkdir($folder, 0750, true)) {
		throw new RuntimeException('Folder e-book tidak dapat dibuat.');
	}
	$nama = $buku_id . '-' . bin2hex(random_bytes(8)) . '.pdf';
	if (!move_uploaded_file($f['tmp_name'], "$folder/$nama")) {
		throw new RuntimeException('File e-book tidak dapat disimpan.');
	}
	q('UPDATE buku SET ebook_path = ? WHERE id = ?', ['ebook/' . $nama, $buku_id]);
}

/** Taksiran berat kiriman (gram): A5/B5/A4, HVS 75 gr, soft cover, + kemasan 100 g. */
function berat_pesanan(array $buku, int $jumlah): int
{
	$luas = ['A5' => 0.148 * 0.21, 'B5' => 0.176 * 0.25, 'A4' => 0.21 * 0.297][kode_ukuran((string) $buku['ukuran'])] ?? 0.148 * 0.21;
	$hal = hitung_halaman((string) $buku['halaman']);
	if ($hal < 1) {
		return 0;
	}
	$per_buku = ceil($hal / 2) * $luas * 75 + 2 * $luas * 260 + 5;
	return (int) round($per_buku * $jumlah + 100);
}

/** Email ke pembeli, selalu berisi link halaman pesanannya. */
function email_pesanan(array $o, string $judul, string $isi): void
{
	kirim_email($o['email'], $judul . ' · ' . $o['kode'], "Halo {$o['nama']},\n\n$isi\n\nLihat pesanan Anda (link pribadi, jangan dibagikan):\n" . link_pesanan($o, true) . "\n\nSalam,\nLiteraindo Press");
}

function ubah_status_pesanan(array $o, string $baru, string $info = ''): void
{
	q('UPDATE pesanan SET status = ?, diubah = ? WHERE id = ?', [$baru, sekarang(), $o['id']]);
	email_pesanan($o, 'Status pesanan: ' . STATUS_PESANAN[$baru][0], STATUS_PESANAN[$baru][1] . ($info !== '' ? "\n\n" . $info : ''));
	if ($o['pengguna_id']) {
		notif((int) $o['pengguna_id'], 'Pesanan ' . $o['kode'] . ': ' . STATUS_PESANAN[$baru][0], '/pesanan/' . $o['kode']);
	}
}

/** Terima atau tolak bukti bayar pesanan buku (keuangan, cadangan admin). */
function verifikasi_pesanan(array $o, bool $terima, string $catatan = ''): void
{
	if ($o['status'] !== 'VERIFIKASI_BAYAR') {
		throw new RuntimeException('Pembayaran pesanan ini sudah diproses.');
	}
	$u = pengguna();
	if ($terima) {
		q("UPDATE pesanan SET bayar_status = 'diterima', diverifikasi = ?, pemeriksa_id = ? WHERE id = ?", [sekarang(), $u['id'] ?? null, $o['id']]);
		if ($o['format'] === 'ebook') {
			q('UPDATE pesanan SET ebook_sampai = ? WHERE id = ?', [date('Y-m-d H:i:s', strtotime('+' . EBOOK_HARI . ' days')), $o['id']]);
			ubah_status_pesanan($o, 'SELESAI', 'E-book dapat diunduh dari halaman pesanan selama ' . EBOOK_HARI . ' hari.');
		} else {
			ubah_status_pesanan($o, 'DIPROSES');
			notif_admin('Pesanan ' . $o['kode'] . ' sudah dibayar. Siap dikemas dan dikirim.', '/admin/pesanan/' . $o['id']);
		}
	} else {
		if (trim($catatan) === '') {
			throw new RuntimeException('Tulis alasan penolakan bukti bayar.');
		}
		q("UPDATE pesanan SET bayar_status = 'ditolak', catatan_bayar = ?, diverifikasi = ?, pemeriksa_id = ? WHERE id = ?", [$catatan, sekarang(), $u['id'] ?? null, $o['id']]);
		ubah_status_pesanan($o, 'MENUNGGU_BAYAR', 'Bukti bayar ditolak: ' . $catatan . "\nSilakan unggah ulang bukti yang benar.");
	}
}

/** Apakah pengunjung boleh membuka pesanan ini? (link rahasia, pemilik akun, atau staf) */
function boleh_buka_pesanan(array $o): bool
{
	mulai_sesi();
	$t = (string) ($_GET['t'] ?? '');
	if ($t !== '' && hash_equals($o['token'], $t)) {
		$_SESSION['pesanan'][$o['kode']] = true;
		return true;
	}
	if (!empty($_SESSION['pesanan'][$o['kode']])) {
		return true;
	}
	$u = pengguna();
	return $u && (in_array($u['peran'], ['admin', 'keuangan'], true) || (int) $o['pengguna_id'] === (int) $u['id']);
}

function ambil_pesanan(string $kode): array
{
	$o = satu('SELECT o.*, b.judul, b.slug, b.ebook_path, b.ukuran, b.halaman FROM pesanan o JOIN buku b ON b.id = o.buku_id WHERE o.kode = ?', [$kode]);
	if (!$o || !boleh_buka_pesanan($o)) {
		tidak_ditemukan();
	}
	return $o;
}

// ---------------------------------------------------------------------------
// Pembeli

function halaman_pesan_buku(bool $post, string $slug): void
{
	$b = satu("SELECT * FROM buku WHERE slug = ? AND tampil = 1 AND status = 'terbit'", [$slug]);
	if (!$b) {
		tidak_ditemukan();
	}
	$format = array_filter(['cetak' => (int) $b['harga_cetak'], 'ebook' => $b['ebook_path'] !== '' ? (int) $b['harga_ebook'] : 0]);
	if (!$format) {
		pesan('Buku ini belum dijual.', 'info');
		arahkan('/buku/' . $b['slug'] . '/');
	}
	$u = pengguna();
	$v = ['format' => (string) array_key_first($format), 'jumlah' => 1, 'nama' => $u['nama'] ?? '', 'email' => $u['email'] ?? '', 'whatsapp' => $u['whatsapp'] ?? '', 'alamat' => '', 'kode_pos' => '', 'catatan' => ''];
	$galat = [];
	if ($post) {
		foreach (['nama', 'email', 'whatsapp', 'alamat', 'kode_pos', 'catatan'] as $k) {
			$v[$k] = mb_substr(input($k), 0, $k === 'alamat' || $k === 'catatan' ? 1000 : 190);
		}
		$v['format'] = isset($format[input('format')]) ? input('format') : $v['format'];
		$v['jumlah'] = $v['format'] === 'ebook' ? 1 : max(1, min(100, (int) input('jumlah', '1')));
		if (mb_strlen($v['nama']) < 3) {
			$galat[] = 'Isi nama lengkap.';
		}
		if (!filter_var($v['email'], FILTER_VALIDATE_EMAIL)) {
			$galat[] = 'Isi email yang aktif. Link halaman pesanan dikirim ke email ini.';
		}
		if (strlen((string) preg_replace('/\D/', '', $v['whatsapp'])) < 9) {
			$galat[] = 'Isi nomor WhatsApp yang aktif.';
		}
		if ($v['format'] === 'cetak' && mb_strlen($v['alamat']) < 15) {
			$galat[] = 'Isi alamat pengiriman lengkap (jalan, kelurahan, kecamatan, kota/kabupaten, provinsi).';
		}
		if (!$galat) {
			$harga = $format[$v['format']];
			$subtotal = $harga * $v['jumlah'];
			do {
				$kode = 'LP' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
			} while (satu('SELECT id FROM pesanan WHERE kode = ?', [$kode]));
			$token = bin2hex(random_bytes(16));
			$status = $v['format'] === 'ebook' ? 'MENUNGGU_BAYAR' : 'MENUNGGU_ONGKIR';
			q('INSERT INTO pesanan (kode, token, pengguna_id, buku_id, format, jumlah, harga_satuan, subtotal, ongkir, total, nama, email, whatsapp, alamat, kode_pos, catatan, status, dibuat, diubah) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
				[$kode, $token, $u['id'] ?? null, $b['id'], $v['format'], $v['jumlah'], $harga, $subtotal, 0, $subtotal, $v['nama'], mb_strtolower($v['email']), $v['whatsapp'], $v['format'] === 'cetak' ? $v['alamat'] : '', $v['kode_pos'], $v['catatan'], $status, sekarang(), sekarang()]);
			$o = satu('SELECT * FROM pesanan WHERE kode = ?', [$kode]);
			email_pesanan($o, 'Pesanan diterima', "Pesanan {$b['judul']} ({$v['jumlah']} × " . FORMAT_PESANAN[$v['format']] . ") sudah kami terima.\n" . STATUS_PESANAN[$status][1]);
			notif_admin('Pesanan baru ' . $kode . ': ' . $b['judul'] . ($status === 'MENUNGGU_ONGKIR' ? '. Isi ongkos kirim.' : '.'), '/admin/pesanan/' . $o['id']);
			mulai_sesi();
			$_SESSION['pesanan'][$kode] = true;
			pesan('Pesanan ' . $kode . ' dibuat. Simpan link halaman ini; kami juga mengirimkannya ke email Anda.');
			arahkan('/pesanan/' . $kode . '?t=' . $token);
		}
	}

	$daftar_galat = $galat ? '<ul class="pesan pesan--galat" role="alert">' . implode('', array_map(fn ($g) => '<li>' . e($g) . '</li>', $galat)) . '</ul>' : '';
	$opsi = '';
	foreach ($format as $k => $h) {
		$opsi .= '<label class="pilih-format"><input type="radio" name="format" value="' . $k . '"' . ($k === $v['format'] ? ' checked' : '') . ' data-format> <strong>' . e(FORMAT_PESANAN[$k]) . '</strong> <span>' . rupiah($h) . '</span></label>';
	}
	$isi = '<section class="panel panel--sempit">
	<p><a href="' . e(url('/buku/' . $b['slug'] . '/')) . '">← ' . e($b['judul']) . '</a></p>
	<h1>Pesan Buku</h1>
	<p class="catatan">Data Anda hanya dipakai untuk memproses pesanan ini. Setelah pesanan dibuat, Anda mendapat <strong>halaman pesanan pribadi</strong> berisi total pembayaran, kode QRIS, dan status pengiriman.</p>
	' . $daftar_galat . '
	<form method="post" class="formulir">' . csrf_field() . '
		<fieldset><legend>' . e($b['judul']) . '</legend>' . $opsi . '
			<div data-hanya-cetak><label for="jumlah">Jumlah buku</label><input type="number" id="jumlah" name="jumlah" min="1" max="100" value="' . (int) $v['jumlah'] . '" class="input-pendek"></div>
		</fieldset>
		<fieldset><legend>Data pemesan</legend>
			<label for="nama">Nama lengkap *</label><input id="nama" name="nama" required autocomplete="name" value="' . e($v['nama']) . '">
			<label for="email">Email *</label><input type="email" id="email" name="email" required autocomplete="email" value="' . e($v['email']) . '">
			<label for="whatsapp">Nomor WhatsApp *</label><input id="whatsapp" name="whatsapp" required inputmode="tel" autocomplete="tel" value="' . e($v['whatsapp']) . '">
			<div data-hanya-cetak>
				<label for="alamat">Alamat pengiriman lengkap *</label><textarea id="alamat" name="alamat" rows="3" autocomplete="street-address" placeholder="Jalan, RT/RW, kelurahan, kecamatan, kota/kabupaten, provinsi">' . e($v['alamat']) . '</textarea>
				<label for="kode_pos">Kode pos</label><input id="kode_pos" name="kode_pos" inputmode="numeric" autocomplete="postal-code" class="input-pendek" value="' . e($v['kode_pos']) . '">
			</div>
			<label for="catatan">Catatan (opsional)</label><textarea id="catatan" name="catatan" rows="2">' . e($v['catatan']) . '</textarea>
		</fieldset>
		<p class="catatan" data-hanya-cetak>Untuk buku cetak, ongkos kirim kami hitung dulu sesuai alamat. Total pembayaran dan QRIS muncul di halaman pesanan setelah ongkir diisi (biasanya pada jam kerja yang sama).</p>
		<div class="formulir__tombol"><button type="submit" class="tombol">Buat Pesanan</button></div>
	</form>
</section>
<script>(function(){var r=document.querySelectorAll("[data-format]");function f(){var c=document.querySelector("[data-format]:checked");var cetak=!c||c.value==="cetak";document.querySelectorAll("[data-hanya-cetak]").forEach(function(d){d.hidden=!cetak;});var a=document.getElementById("alamat");if(a)a.required=cetak;}r.forEach(function(x){x.addEventListener("change",f);});f();})();</script>';
	tampil('Pesan ' . $b['judul'], $isi, ['kelas' => 'halaman-pesan']);
}

/** Cari pesanan dengan kode + email (untuk pembeli yang kehilangan link). */
function halaman_cek_pesanan(bool $post): void
{
	$galat = '';
	if ($post) {
		$o = satu('SELECT * FROM pesanan WHERE kode = ?', [strtoupper(trim(input('kode')))]);
		if ($o && hash_equals($o['email'], mb_strtolower(trim(input('email'))))) {
			mulai_sesi();
			$_SESSION['pesanan'][$o['kode']] = true;
			arahkan('/pesanan/' . $o['kode']);
		}
		$galat = '<p class="pesan pesan--galat" role="alert">Kode pesanan dan email tidak cocok.</p>';
	}
	$isi = '<section class="panel panel--sempit"><h1>Cek Pesanan</h1>
	<p class="catatan">Masukkan kode pesanan (contoh: LP261031-A1B2C3) dan email yang Anda pakai saat memesan.</p>' . $galat . '
	<form method="post" class="formulir">' . csrf_field() . '
		<label for="kode">Kode pesanan</label><input id="kode" name="kode" required autocapitalize="characters">
		<label for="email">Email</label><input type="email" id="email" name="email" required>
		<button type="submit" class="tombol">Buka Pesanan</button>
	</form></section>';
	tampil('Cek Pesanan', $isi);
}

function halaman_pesanan(bool $post, string $kode): void
{
	$o = ambil_pesanan($kode);
	if ($post && ($_POST['aksi'] ?? '') === 'bukti') {
		try {
			if ($o['status'] !== 'MENUNGGU_BAYAR') {
				throw new RuntimeException('Pesanan ini tidak sedang menunggu pembayaran.');
			}
			$path = simpan_bukti_pesanan($o);
			q("UPDATE pesanan SET bukti_path = ?, bukti_dibuat = ?, bayar_status = 'menunggu', catatan_bayar = '', status = 'VERIFIKASI_BAYAR', diubah = ? WHERE id = ?", [$path, sekarang(), sekarang(), $o['id']]);
			notif_keuangan('Bukti bayar pesanan ' . $o['kode'] . ' (' . rupiah($o['total']) . ') perlu diverifikasi.');
			pesan('Bukti bayar terkirim. Bagian keuangan akan memeriksanya.');
		} catch (RuntimeException $ex) {
			pesan($ex->getMessage(), 'galat');
		}
		arahkan('/pesanan/' . $o['kode']);
	}

	$s = $o['status'];
	$langkah = '<p>' . e(STATUS_PESANAN[$s][1]) . '</p>';
	if ($s === 'MENUNGGU_BAYAR') {
		$langkah .= ($o['bayar_status'] === 'ditolak' ? '<p class="pesan pesan--galat">Bukti bayar sebelumnya ditolak: ' . e($o['catatan_bayar']) . '</p>' : '')
			. '<div class="tagihan"><div><p>Total pembayaran</p><p class="tagihan__nominal">' . rupiah($o['total']) . '</p>
			<form method="post" enctype="multipart/form-data" class="formulir">' . csrf_field() . '<input type="hidden" name="aksi" value="bukti">
				<label for="bukti">Unggah bukti bayar <span class="catatan">(JPG/PNG/PDF, maks. 5 MB)</span></label>
				<input type="file" id="bukti" name="bukti" accept=".jpg,.jpeg,.png,.pdf" required>
				<button type="submit" class="tombol">Kirim Bukti Bayar</button>
			</form></div>' . blok_qris((int) $o['total']) . '</div>';
	} elseif ($s === 'DIKIRIM' || ($s === 'SELESAI' && $o['format'] === 'cetak')) {
		$langkah .= '<p>Kurir: <strong>' . e($o['kurir']) . '</strong> · No. resi: <strong>' . e($o['resi']) . '</strong></p>';
	} elseif ($s === 'SELESAI' && $o['format'] === 'ebook') {
		$aktif = $o['ebook_sampai'] && strtotime($o['ebook_sampai']) > time();
		$langkah .= $aktif
			? '<p><a class="tombol" href="' . e(url('/pesanan/' . $o['kode'] . '/ebook')) . '">' . ikon('unduh') . ' Unduh E-book (PDF)</a></p><p class="catatan">Link berlaku sampai ' . e(tanggal($o['ebook_sampai'])) . '. E-book hanya untuk penggunaan pribadi.</p>'
			: '<p class="catatan">Masa unduh e-book sudah berakhir. Hubungi kami bila Anda perlu mengunduh ulang.</p>';
	}

	$isi = '<section class="panel panel--sempit">
	<div class="judul-baris"><h1>Pesanan ' . e($o['kode']) . '</h1>' . label_pesanan($s) . '</div>
	<div class="kotak-langkah">' . $langkah . '</div>
	<h2>Rincian</h2>
	<dl class="data-ringkas">
		<div><dt>Buku</dt><dd><a href="' . e(url('/buku/' . $o['slug'] . '/')) . '">' . e($o['judul']) . '</a></dd></div>
		<div><dt>Format</dt><dd>' . e(FORMAT_PESANAN[$o['format']]) . '</dd></div>
		<div><dt>Jumlah</dt><dd>' . (int) $o['jumlah'] . ' × ' . rupiah($o['harga_satuan']) . '</dd></div>
		<div><dt>Subtotal</dt><dd>' . rupiah($o['subtotal']) . '</dd></div>
		' . ($o['format'] === 'cetak' ? '<div><dt>Ongkos kirim</dt><dd>' . ($s === 'MENUNGGU_ONGKIR' ? 'sedang dihitung' : rupiah($o['ongkir'])) . '</dd></div>' : '') . '
		<div><dt>Total</dt><dd><strong>' . ($s === 'MENUNGGU_ONGKIR' ? '-' : rupiah($o['total'])) . '</strong></dd></div>
		<div><dt>Pemesan</dt><dd>' . e($o['nama']) . '<br>' . e($o['email']) . '<br>' . e($o['whatsapp']) . '</dd></div>
		' . ($o['format'] === 'cetak' ? '<div><dt>Alamat</dt><dd>' . nl2br(e($o['alamat'])) . ($o['kode_pos'] ? ' ' . e($o['kode_pos']) : '') . '</dd></div>' : '') . '
		<div><dt>Dipesan</dt><dd>' . e(tanggal($o['dibuat'])) . '</dd></div>
	</dl>
	<p class="catatan">Halaman ini pribadi. Jangan bagikan link-nya. Bila link hilang, buka <a href="' . e(url('/pesanan')) . '">Cek Pesanan</a> dengan kode pesanan dan email Anda. Ada pertanyaan? <a href="' . e(wa_link('Halo Literaindo Press, saya ingin menanyakan pesanan ' . $o['kode'] . '.')) . '">Hubungi kami via WhatsApp</a>.</p>
</section>';
	tampil('Pesanan ' . $o['kode'], $isi, ['kelas' => 'halaman-pesanan', 'kepala' => '<meta name="robots" content="noindex">']);
}

function simpan_bukti_pesanan(array $o): string
{
	$f = $_FILES['bukti'] ?? null;
	if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || $f['size'] > 5 * 1024 * 1024) {
		throw new RuntimeException('Bukti bayar gagal diunggah. Maksimal 5 MB.');
	}
	$ext = strtolower(pathinfo((string) $f['name'], PATHINFO_EXTENSION));
	$mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']) ?: '';
	if (!in_array($ext, ['jpg', 'jpeg', 'png', 'pdf'], true) || !in_array($mime, MIME_DIIZINKAN[$ext], true)) {
		throw new RuntimeException('Bukti bayar harus berupa JPG, PNG, atau PDF.');
	}
	$folder = DATA_DIR . '/berkas/pesanan/' . (int) $o['id'];
	if (!is_dir($folder) && !mkdir($folder, 0750, true)) {
		throw new RuntimeException('Folder penyimpanan tidak dapat dibuat.');
	}
	$nama = 'bukti-' . bin2hex(random_bytes(8)) . '.' . $ext;
	if (!move_uploaded_file($f['tmp_name'], "$folder/$nama")) {
		throw new RuntimeException('Bukti bayar tidak dapat disimpan.');
	}
	return 'pesanan/' . (int) $o['id'] . '/' . $nama;
}

function unduh_bukti_pesanan(bool $post, string $kode): void
{
	$o = ambil_pesanan($kode);
	if ($o['bukti_path'] === '') {
		tidak_ditemukan();
	}
	kirim_berkas($o['bukti_path'], 'bukti-' . $o['kode'] . '.' . pathinfo($o['bukti_path'], PATHINFO_EXTENSION), true);
}

function unduh_ebook_pesanan(bool $post, string $kode): void
{
	$o = ambil_pesanan($kode);
	$staf = in_array(pengguna()['peran'] ?? '', ['admin'], true);
	if (!$staf && ($o['format'] !== 'ebook' || $o['status'] !== 'SELESAI' || !$o['ebook_sampai'] || strtotime($o['ebook_sampai']) < time())) {
		tidak_ditemukan();
	}
	if ($o['ebook_path'] === '') {
		tidak_ditemukan();
	}
	q('UPDATE pesanan SET ebook_unduh = ebook_unduh + 1 WHERE id = ?', [$o['id']]);
	kirim_berkas($o['ebook_path'], slugify($o['judul']) . '.pdf');
}

/** Daftar pesanan milik akun yang masuk. */
function pesanan_saya(bool $post): void
{
	$u = wajib_masuk();
	$daftar = semua('SELECT o.*, b.judul FROM pesanan o JOIN buku b ON b.id = o.buku_id WHERE o.pengguna_id = ? OR o.email = ? ORDER BY o.id DESC', [$u['id'], mb_strtolower($u['email'])]);
	$baris = '';
	foreach ($daftar as $o) {
		mulai_sesi();
		$_SESSION['pesanan'][$o['kode']] = true;
		$baris .= '<tr><td><a href="' . e(url('/pesanan/' . $o['kode'])) . '">' . e($o['kode']) . '</a><br><span class="catatan">' . e(tanggal($o['dibuat'])) . '</span></td><td>' . e($o['judul']) . '<br><span class="catatan">' . (int) $o['jumlah'] . ' × ' . e(FORMAT_PESANAN[$o['format']]) . '</span></td><td class="angka">' . ($o['status'] === 'MENUNGGU_ONGKIR' ? '-' : rupiah($o['total'])) . '</td><td>' . label_pesanan($o['status']) . '</td></tr>';
	}
	$isi = '<section class="panel"><h1>Pesanan Saya</h1>' . ($baris ? '<div class="tabel-wadah"><table class="tabel"><thead><tr><th>Kode</th><th>Buku</th><th>Total</th><th>Status</th></tr></thead><tbody>' . $baris . '</tbody></table></div>' : '<p class="kosong">Belum ada pesanan buku.</p>') . '</section>';
	tampil('Pesanan Saya', $isi, ['panel' => menu_peran($u, '/akun/pesanan')]);
}

// ---------------------------------------------------------------------------
// Admin

function admin_pesanan(bool $post): void
{
	wajib_masuk('admin');
	$filter = isset(STATUS_PESANAN[$_GET['status'] ?? '']) ? $_GET['status'] : '';
	$daftar = semua('SELECT o.*, b.judul FROM pesanan o JOIN buku b ON b.id = o.buku_id' . ($filter ? ' WHERE o.status = ?' : '') . ' ORDER BY o.id DESC LIMIT 300', $filter ? [$filter] : []);
	$hitung = [];
	foreach (semua('SELECT status, COUNT(*) AS n FROM pesanan GROUP BY status') as $r) {
		$hitung[$r['status']] = (int) $r['n'];
	}
	$opsi = '<option value="">Semua status</option>';
	foreach (STATUS_PESANAN as $k => [$label]) {
		$opsi .= '<option value="' . $k . '"' . ($k === $filter ? ' selected' : '') . '>' . e($label) . ' (' . ($hitung[$k] ?? 0) . ')</option>';
	}
	$baris = '';
	foreach ($daftar as $o) {
		$baris .= '<tr><td><a href="' . e(url('/admin/pesanan/' . $o['id'])) . '">' . e($o['kode']) . '</a><br><span class="catatan">' . e(tanggal($o['dibuat'])) . '</span></td><td>' . e($o['nama']) . '<br><span class="catatan">' . e($o['judul']) . ' · ' . (int) $o['jumlah'] . ' × ' . e(FORMAT_PESANAN[$o['format']]) . '</span></td><td class="angka">' . rupiah($o['total']) . '</td><td>' . label_pesanan($o['status']) . '</td></tr>';
	}
	$perlu = ($hitung['MENUNGGU_ONGKIR'] ?? 0) + ($hitung['DIPROSES'] ?? 0);
	$isi = '<section class="panel"><h1>Pesanan Buku</h1>
	' . ($perlu ? '<p class="pesan pesan--info">' . $perlu . ' pesanan perlu tindakan: isi ongkir (' . ($hitung['MENUNGGU_ONGKIR'] ?? 0) . ') atau kirim buku (' . ($hitung['DIPROSES'] ?? 0) . ').</p>' : '') . '
	<form method="get" class="filter filter--admin"><label class="filter__isian"><span class="tersembunyi">Status</span><select name="status">' . $opsi . '</select></label><div class="filter__tombol"><button class="tombol" type="submit">Tampilkan</button></div></form>
	' . ($baris ? '<div class="tabel-wadah"><table class="tabel"><thead><tr><th>Kode</th><th>Pemesan / buku</th><th>Total</th><th>Status</th></tr></thead><tbody>' . $baris . '</tbody></table></div>' : '<p class="kosong">Belum ada pesanan.</p>') . '
	</section>';
	tampil('Pesanan Buku', $isi, ['panel' => menu_admin('/admin/pesanan')]);
}

function admin_pesanan_detail(bool $post, string $id): void
{
	wajib_masuk('admin');
	$o = satu('SELECT o.*, b.judul, b.slug, b.ebook_path, b.ukuran, b.halaman FROM pesanan o JOIN buku b ON b.id = o.buku_id WHERE o.id = ?', [(int) $id]);
	if (!$o) {
		tidak_ditemukan();
	}
	if ($post) {
		$aksi = $_POST['aksi'] ?? '';
		try {
			if ($aksi === 'ongkir' && in_array($o['status'], ['MENUNGGU_ONGKIR', 'MENUNGGU_BAYAR'], true) && $o['format'] === 'cetak') {
				$ongkir = (int) preg_replace('/\D/', '', input('ongkir'));
				if ($ongkir < 0 || input('ongkir') === '') {
					throw new RuntimeException('Isi ongkos kirim.');
				}
				q('UPDATE pesanan SET ongkir = ?, total = subtotal + ?, kurir = ?, diubah = ? WHERE id = ?', [$ongkir, $ongkir, mb_substr(input('kurir'), 0, 60), sekarang(), $o['id']]);
				$o = satu('SELECT * FROM pesanan WHERE id = ?', [$o['id']]);
				ubah_status_pesanan($o, 'MENUNGGU_BAYAR', 'Ongkos kirim: ' . rupiah($ongkir) . ($o['kurir'] ? ' (' . $o['kurir'] . ')' : '') . "\nTotal pembayaran: " . rupiah($o['total']));
				pesan('Ongkir disimpan. Pembeli sudah diberi tahu total pembayarannya.');
			} elseif (in_array($aksi, ['terima_bayar', 'tolak_bayar'], true)) {
				verifikasi_pesanan($o, $aksi === 'terima_bayar', mb_substr(input('catatan'), 0, 500));
				pesan('Status pembayaran diperbarui.');
			} elseif ($aksi === 'kirim' && $o['status'] === 'DIPROSES') {
				if (input('resi') === '') {
					throw new RuntimeException('Isi nomor resi.');
				}
				q('UPDATE pesanan SET kurir = ?, resi = ?, diubah = ? WHERE id = ?', [mb_substr(input('kurir'), 0, 60), mb_substr(input('resi'), 0, 120), sekarang(), $o['id']]);
				ubah_status_pesanan(satu('SELECT * FROM pesanan WHERE id = ?', [$o['id']]), 'DIKIRIM', 'Kurir: ' . input('kurir') . "\nNo. resi: " . input('resi'));
				pesan('Resi tersimpan. Pembeli sudah diberi tahu.');
			} elseif ($aksi === 'selesai' && $o['status'] === 'DIKIRIM') {
				q('UPDATE pesanan SET status = ?, diubah = ? WHERE id = ?', ['SELESAI', sekarang(), $o['id']]);
				pesan('Pesanan ditandai selesai.');
			} elseif ($aksi === 'perpanjang' && $o['format'] === 'ebook' && $o['status'] === 'SELESAI') {
				q('UPDATE pesanan SET ebook_sampai = ? WHERE id = ?', [date('Y-m-d H:i:s', strtotime('+' . EBOOK_HARI . ' days')), $o['id']]);
				email_pesanan($o, 'Link e-book diperpanjang', 'Link unduhan e-book Anda aktif kembali selama ' . EBOOK_HARI . ' hari.');
				pesan('Link unduhan e-book diperpanjang ' . EBOOK_HARI . ' hari.');
			} elseif ($aksi === 'batal' && in_array($o['status'], ['MENUNGGU_ONGKIR', 'MENUNGGU_BAYAR', 'VERIFIKASI_BAYAR'], true)) {
				ubah_status_pesanan($o, 'DIBATALKAN', input('catatan') !== '' ? 'Alasan: ' . input('catatan') : '');
				pesan('Pesanan dibatalkan.');
			}
		} catch (RuntimeException $ex) {
			pesan($ex->getMessage(), 'galat');
		}
		arahkan('/admin/pesanan/' . $o['id']);
	}

	$form = fn (string $aksi, string $isi, string $tombol, string $kelas = 'tombol') => '<form method="post" class="formulir">' . csrf_field() . '<input type="hidden" name="aksi" value="' . $aksi . '">' . $isi . '<button type="submit" class="' . $kelas . '">' . $tombol . '</button></form>';
	$berat = $o['format'] === 'cetak' ? berat_pesanan($o, (int) $o['jumlah']) : 0;
	$bukti = $o['bukti_path'] !== '' ? '<p><a class="tombol tombol--kedua" href="' . e(url('/pesanan/' . $o['kode'] . '/bukti')) . '" target="_blank">' . ikon('unduh') . ' Lihat bukti bayar</a></p>' : '';
	$aksi = match ($o['status']) {
		'MENUNGGU_ONGKIR' => '<h3>Isi ongkos kirim</h3><p class="catatan">Tujuan: ' . e($o['alamat']) . ($o['kode_pos'] ? ' ' . e($o['kode_pos']) : '') . '. Taksiran berat kiriman: <strong>' . ($berat ? ($berat >= 1000 ? '±' . number_format($berat / 1000, 2, ',', '.') . ' kg' : '±' . $berat . ' g') : 'isi jumlah halaman di data katalog') . '</strong> (HVS 75 gr, soft cover, termasuk kemasan).</p>'
			. $form('ongkir', '<div class="kolom-2"><div><label for="ongkir">Ongkos kirim (Rp)</label><input id="ongkir" name="ongkir" inputmode="numeric" data-rupiah required></div><div><label for="kurir">Kurir dan layanan</label><input id="kurir" name="kurir" placeholder="mis. JNE REG"></div></div>', 'Simpan Ongkir &amp; Kirim Total ke Pembeli'),
		'MENUNGGU_BAYAR' => '<p>Menunggu pembeli membayar ' . rupiah($o['total']) . '.</p>' . ($o['format'] === 'cetak' ? '<details><summary>Ubah ongkir</summary>' . $form('ongkir', '<div class="kolom-2"><div><label for="ongkir">Ongkos kirim (Rp)</label><input id="ongkir" name="ongkir" inputmode="numeric" data-rupiah required value="' . (int) $o['ongkir'] . '"></div><div><label for="kurir">Kurir dan layanan</label><input id="kurir" name="kurir" value="' . e($o['kurir']) . '"></div></div>', 'Simpan') . '</details>' : ''),
		'VERIFIKASI_BAYAR' => '<p>Bukti bayar menunggu verifikasi oleh <strong>bagian keuangan</strong> di <a href="' . e(url('/keuangan')) . '">Panel Keuangan</a>.</p>' . $bukti
			. '<details><summary>Verifikasi sendiri sebagai admin</summary><div class="aksi-baris">' . $form('terima_bayar', '', 'Terima Pembayaran') . '</div>' . $form('tolak_bayar', '<label for="catatan-tolak">Alasan penolakan</label><textarea id="catatan-tolak" name="catatan" rows="2"></textarea>', 'Tolak Bukti', 'tombol tombol--bahaya') . '</details>',
		'DIPROSES' => '<h3>Kemas dan kirim</h3><p class="catatan">Alamat: ' . nl2br(e($o['alamat'])) . ($o['kode_pos'] ? ' ' . e($o['kode_pos']) : '') . ' · ' . e($o['whatsapp']) . '</p>'
			. $form('kirim', '<div class="kolom-2"><div><label for="kurir">Kurir</label><input id="kurir" name="kurir" value="' . e($o['kurir']) . '"></div><div><label for="resi">Nomor resi</label><input id="resi" name="resi" required></div></div>', 'Simpan Resi &amp; Beri Tahu Pembeli'),
		'DIKIRIM' => '<p>Dikirim lewat ' . e($o['kurir']) . ', resi <strong>' . e($o['resi']) . '</strong>.</p>' . $form('selesai', '', 'Tandai Selesai', 'tombol tombol--kedua'),
		'SELESAI' => $o['format'] === 'ebook' ? '<p>Link e-book berlaku sampai ' . e(tanggal($o['ebook_sampai'])) . ' · sudah diunduh ' . (int) $o['ebook_unduh'] . ' kali.</p>' . $form('perpanjang', '', 'Perpanjang Link ' . EBOOK_HARI . ' Hari', 'tombol tombol--kedua') . ($o['ebook_path'] === '' ? '<p class="pesan pesan--galat">File e-book belum diunggah di data katalog buku ini.</p>' : '') : '<p>Pesanan selesai.</p>',
		default => '<p>Pesanan dibatalkan.</p>',
	};
	if (in_array($o['status'], ['MENUNGGU_ONGKIR', 'MENUNGGU_BAYAR', 'VERIFIKASI_BAYAR'], true)) {
		$aksi .= '<details><summary>Batalkan pesanan</summary>' . $form('batal', '<label for="alasan-batal">Alasan (dikirim ke pembeli)</label><textarea id="alasan-batal" name="catatan" rows="2"></textarea>', 'Batalkan Pesanan', 'tombol tombol--bahaya') . '</details>';
	}

	$isi = '<section class="panel">
	<p><a href="' . e(url('/admin/pesanan')) . '">← Pesanan Buku</a></p>
	<div class="judul-baris"><h1>Pesanan ' . e($o['kode']) . '</h1>' . label_pesanan($o['status']) . '</div>
	<div class="dua-kolom">
		<section><h2>Rincian</h2><dl class="data-ringkas">
			<div><dt>Buku</dt><dd><a href="' . e(url('/buku/' . $o['slug'] . '/')) . '" target="_blank">' . e($o['judul']) . '</a></dd></div>
			<div><dt>Format</dt><dd>' . e(FORMAT_PESANAN[$o['format']]) . '</dd></div>
			<div><dt>Jumlah</dt><dd>' . (int) $o['jumlah'] . ' × ' . rupiah($o['harga_satuan']) . '</dd></div>
			<div><dt>Ongkir</dt><dd>' . rupiah($o['ongkir']) . ($o['kurir'] ? ' (' . e($o['kurir']) . ')' : '') . '</dd></div>
			<div><dt>Total</dt><dd><strong>' . rupiah($o['total']) . '</strong></dd></div>
			<div><dt>Pembayaran</dt><dd>' . e(STATUS_BAYAR[$o['bayar_status']] ?? 'Belum ada bukti') . '</dd></div>
			<div><dt>Pemesan</dt><dd>' . e($o['nama']) . '<br>' . e($o['email']) . '<br><a href="https://wa.me/' . e(preg_replace('/^0/', '62', (string) preg_replace('/\D/', '', $o['whatsapp']))) . '" target="_blank">' . e($o['whatsapp']) . '</a></dd></div>
			' . ($o['format'] === 'cetak' ? '<div><dt>Alamat</dt><dd>' . nl2br(e($o['alamat'])) . ' ' . e($o['kode_pos']) . '</dd></div>' : '') . '
			' . ($o['catatan'] !== '' ? '<div><dt>Catatan</dt><dd>' . nl2br(e($o['catatan'])) . '</dd></div>' : '') . '
			<div><dt>Dipesan</dt><dd>' . e(tanggal($o['dibuat'])) . '</dd></div>
		</dl>
		<p class="catatan">Link pribadi pembeli: <a href="' . e(link_pesanan($o)) . '" target="_blank">buka halaman pesanan</a>. Bagikan hanya ke pembeli ini bila ia kehilangan email.</p></section>
		<section class="kotak-tindakan"><h2>Tindakan</h2>' . $aksi . '</section>
	</div>
</section>';
	tampil('Pesanan ' . $o['kode'], $isi, ['panel' => menu_admin('/admin/pesanan')]);
}
