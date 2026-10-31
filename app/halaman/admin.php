<?php
// Panel admin: dasbor, pengajuan, katalog, pengguna, pengaturan.

declare(strict_types=1);

function admin_dasbor(bool $post): void
{
	wajib_masuk('admin');
	$hitung = [];
	foreach (semua('SELECT status, COUNT(*) AS n FROM pengajuan GROUP BY status') as $r) {
		$hitung[$r['status']] = (int) $r['n'];
	}
	$kotak = '';
	foreach (STATUS as $kode => [$label, , $giliran]) {
		if ($kode === 'DRAF') {
			continue;
		}
		$n = $hitung[$kode] ?? 0;
		$kotak .= '<a class="kotak-status' . ($giliran === 'admin' && $n ? ' kotak-status--tindakan' : '') . '" href="' . e(url('/admin/pengajuan?status=' . $kode)) . '"><span class="kotak-status__angka">' . $n . '</span><span>' . e($label) . '</span></a>';
	}
	$tindakan = semua("SELECT p.*, u.nama FROM pengajuan p JOIN pengguna u ON u.id = p.pengguna_id WHERE p.status IN ('VERIFIKASI_BAYAR_1','DITINJAU','PROSES_ISBN','VERIFIKASI_BAYAR_2','LUNAS') ORDER BY p.diubah ASC");
	$isi = '<section class="panel"><h1>Dasbor Admin</h1>
	<div class="kotak-status-grid">' . $kotak . '</div>
	<h2>Menunggu tindakan Anda (' . count($tindakan) . ')</h2>' . tabel_pengajuan($tindakan) . '
	<p class="catatan">Jumlah buku di katalog: ' . (int) satu('SELECT COUNT(*) AS n FROM buku')['n'] . ' · Pengguna terdaftar: ' . (int) satu('SELECT COUNT(*) AS n FROM pengguna')['n'] . '</p></section>';
	tampil('Dasbor Admin', $isi, ['panel' => menu_admin('/admin')]);
}

function tabel_pengajuan(array $daftar): string
{
	if (!$daftar) {
		return '<p class="catatan">Tidak ada pengajuan.</p>';
	}
	$baris = '';
	foreach ($daftar as $p) {
		$baris .= '<tr><td><a href="' . e(url('/admin/pengajuan/' . $p['id'])) . '"><strong>' . e($p['judul']) . '</strong></a></td><td>' . e($p['nama']) . '</td><td>' . label_status($p['status']) . '</td><td class="angka">' . e(tanggal($p['diubah'])) . '</td></tr>';
	}
	return '<div class="tabel-wadah"><table class="tabel"><thead><tr><th>Judul</th><th>Pengaju</th><th>Status</th><th>Diperbarui</th></tr></thead><tbody>' . $baris . '</tbody></table></div>';
}

function admin_pengajuan(bool $post): void
{
	wajib_masuk('admin');
	$filter = (string) ($_GET['status'] ?? '');
	$cari = trim((string) ($_GET['cari'] ?? ''));
	$sql = 'SELECT p.*, u.nama FROM pengajuan p JOIN pengguna u ON u.id = p.pengguna_id WHERE 1=1';
	$par = [];
	if (isset(STATUS[$filter])) {
		$sql .= ' AND p.status = ?';
		$par[] = $filter;
	}
	if ($cari !== '') {
		$sql .= ' AND (p.judul LIKE ? OR u.nama LIKE ?)';
		$par[] = "%$cari%";
		$par[] = "%$cari%";
	}
	$daftar = semua($sql . ' ORDER BY p.diubah DESC', $par);
	$opsi = '<option value="">Semua status</option>';
	foreach (STATUS as $k => [$label]) {
		$opsi .= '<option value="' . $k . '"' . ($k === $filter ? ' selected' : '') . '>' . e($label) . '</option>';
	}
	$isi = '<section class="panel"><h1>Pengajuan</h1>
	<form method="get" class="filter filter--admin"><label class="filter__isian"><span class="tersembunyi">Status</span><select name="status" id="f-status">' . $opsi . '</select></label><label class="filter__isian"><span class="tersembunyi">Cari</span><input type="search" name="cari" id="f-cari" placeholder="Judul atau nama pengaju" value="' . e($cari) . '"></label><div class="filter__tombol"><button class="tombol" type="submit">Tampilkan</button></div></form>
	' . tabel_pengajuan($daftar) . '</section>';
	tampil('Pengajuan', $isi, ['panel' => menu_admin('/admin/pengajuan')]);
}

function admin_pengajuan_detail(bool $post, string $id): void
{
	$admin = wajib_masuk('admin');
	$p = satu('SELECT * FROM pengajuan WHERE id = ?', [(int) $id]);
	if (!$p) {
		tidak_ditemukan();
	}
	$pid = (int) $p['id'];
	$aid = (int) $admin['id'];

	if ($post) {
		$aksi = $_POST['aksi'] ?? '';
		$catatan = mb_substr(input('catatan'), 0, 2000);
		$s = $p['status'];
		try {
			if ($aksi === 'catatan' && input('isi') !== '') {
				catat($pid, $aid, mb_substr(input('isi'), 0, 3000));
				notif_pengajuan($p, 'Pesan dari admin: ' . mb_substr(input('isi'), 0, 200));
				q('UPDATE pengajuan SET diubah = ? WHERE id = ?', [sekarang(), $pid]);
				$pemilik = satu('SELECT * FROM pengguna WHERE id = ?', [$p['pengguna_id']]);
				kirim_email($pemilik['email'], 'Pesan baru untuk pengajuan "' . $p['judul'] . '"', "Halo {$pemilik['nama']},\n\nAdmin Literaindo Press mengirim pesan:\n\n" . input('isi') . "\n\nBalas di: " . url_absolut("/akun/pengajuan/$pid"));
				pesan('Pesan terkirim.');
			} elseif (in_array($aksi, ['terima_bayar', 'tolak_bayar'], true) && in_array($s, ['VERIFIKASI_BAYAR_1', 'VERIFIKASI_BAYAR_2'], true)) {
				$tahap = $s === 'VERIFIKASI_BAYAR_1' ? 1 : 2;
				$bayar = satu("SELECT * FROM pembayaran WHERE pengajuan_id = ? AND tahap = ? AND status = 'menunggu' ORDER BY id DESC LIMIT 1", [$pid, $tahap]);
				if ($aksi === 'terima_bayar') {
					if ($bayar) {
						q("UPDATE pembayaran SET status = 'diterima', diverifikasi = ? WHERE id = ?", [sekarang(), $bayar['id']]);
					}
					ubah_status($p, $tahap === 1 ? 'DITINJAU' : 'LUNAS', 'Pembayaran tahap ' . $tahap . ' diterima.');
				} else {
					if ($catatan === '') {
						throw new RuntimeException('Tulis alasan penolakan bukti bayar.');
					}
					if ($bayar) {
						q("UPDATE pembayaran SET status = 'ditolak', catatan = ?, diverifikasi = ? WHERE id = ?", [$catatan, sekarang(), $bayar['id']]);
					}
					ubah_status($p, 'MENUNGGU_BAYAR_' . $tahap, 'Bukti bayar ditolak: ' . $catatan);
				}
				pesan('Status pembayaran diperbarui.');
			} elseif ($aksi === 'revisi' && $s === 'DITINJAU') {
				if ($catatan === '') {
					throw new RuntimeException('Tulis catatan revisi untuk penulis.');
				}
				ubah_status($p, 'PERLU_REVISI', $catatan);
				pesan('Permintaan revisi dikirim ke penulis.');
			} elseif ($aksi === 'tagihan' && in_array($s, ['DITINJAU', 'PROSES_ISBN', 'MENUNGGU_BAYAR_2'], true)) {
				$jumlah = (int) input('jumlah_cetak');
				$tagihan = (int) preg_replace('/\D/', '', input('tagihan_2'));
				if ($jumlah < 1 || $tagihan < 1) {
					throw new RuntimeException('Isi jumlah cetak dan nominal pelunasan.');
				}
				q('UPDATE pengajuan SET jumlah_cetak = ?, halaman_final = ?, tagihan_2 = ?, rincian_tagihan_2 = ?, diubah = ? WHERE id = ?', [$jumlah, input('halaman_final'), $tagihan, input('rincian_tagihan_2'), sekarang(), $pid]);
				if ($s === 'DITINJAU') {
					$bid = buat_buku_dari_pengajuan(satu('SELECT * FROM pengajuan WHERE id = ?', [$pid]), input('slug'), (int) preg_replace('/\D/', '', input('harga_cetak')));
					q('UPDATE pengajuan SET buku_id = ? WHERE id = ?', [$bid, $pid]);
					ubah_status($p, 'PROSES_ISBN', 'Tagihan pelunasan ' . rupiah($tagihan) . ' untuk ' . $jumlah . ' eksemplar ditetapkan. Halaman buku dibuat di katalog.');
					pesan('Tagihan ditetapkan dan halaman buku dibuat. Gunakan link halaman buku untuk pengajuan ISBN.');
				} else {
					catat($pid, $aid, 'Tagihan pelunasan diperbarui menjadi ' . rupiah($tagihan) . ' (' . $jumlah . ' eksemplar).', true);
					pesan('Tagihan diperbarui.');
				}
			} elseif ($aksi === 'isbn' && $s === 'PROSES_ISBN') {
				$isbn = input('isbn');
				if (!preg_match('/^[0-9Xx-]{10,17}$/', $isbn)) {
					throw new RuntimeException('Nomor ISBN tidak valid. Contoh: 978-623-00-1234-5');
				}
				if ($p['buku_id']) {
					q("UPDATE buku SET isbn = ?, eisbn = ?, status = 'terbit' WHERE id = ?", [$isbn, input('eisbn'), $p['buku_id']]);
				}
				ubah_status($p, 'MENUNGGU_BAYAR_2', 'ISBN terbit: ' . $isbn . '. Silakan bayar pelunasan ' . rupiah($p['tagihan_2']) . '.');
				pesan('ISBN tersimpan. Buku berstatus Sudah Terbit dan tagihan pelunasan dibuka.');
			} elseif ($aksi === 'selesai' && $s === 'LUNAS') {
				q('UPDATE pengajuan SET resi = ? WHERE id = ?', [input('resi'), $pid]);
				ubah_status($p, 'SELESAI', input('resi') ? 'Nomor resi: ' . input('resi') : null);
				pesan('Pengajuan ditandai selesai.');
			} elseif ($aksi === 'tolak' && !in_array($s, ['SELESAI', 'DITOLAK'], true)) {
				if ($catatan === '') {
					throw new RuntimeException('Tulis alasan penolakan pengajuan.');
				}
				ubah_status($p, 'DITOLAK', $catatan);
				pesan('Pengajuan ditolak.');
			}
		} catch (RuntimeException $ex) {
			pesan($ex->getMessage(), 'galat');
		}
		arahkan('/admin/pengajuan/' . $pid);
	}

	$pengaju = satu('SELECT * FROM pengguna WHERE id = ?', [$p['pengguna_id']]);
	$buku = $p['buku_id'] ? satu('SELECT * FROM buku WHERE id = ?', [$p['buku_id']]) : null;
	$biaya_awal = (int) pengaturan('biaya_awal', '250000');
	$form = fn (string $aksi, string $isi, string $tombol, string $kelas = 'tombol') => '<form method="post" class="formulir">' . csrf_field() . '<input type="hidden" name="aksi" value="' . $aksi . '">' . $isi . '<button type="submit" class="' . $kelas . '">' . $tombol . '</button></form>';

	$form_tagihan = function (bool $buat_buku) use ($p, $form) {
		$isi = '<div class="kolom-2">
			<div><label for="jumlah_cetak">Jumlah eksemplar dicetak</label><input type="number" min="1" id="jumlah_cetak" name="jumlah_cetak" required value="' . e((string) ($p['jumlah_cetak'] ?: $p['jumlah_cetak_diminta'])) . '"></div>
			<div><label for="halaman_final">Jumlah halaman final</label><input id="halaman_final" name="halaman_final" placeholder="mis. viii + 180" value="' . e($p['halaman_final']) . '"></div>
			<div><label for="tagihan_2">Nominal pelunasan (Rp)</label><input id="tagihan_2" name="tagihan_2" inputmode="numeric" required value="' . e($p['tagihan_2'] ?: '') . '"></div>
			<div><label for="rincian_tagihan_2">Rincian untuk penulis</label><input id="rincian_tagihan_2" name="rincian_tagihan_2" placeholder="mis. 50 eks × Rp45.000 − biaya awal" value="' . e($p['rincian_tagihan_2']) . '"></div>';
		if ($buat_buku) {
			$isi .= '<div><label for="slug">Link halaman buku</label><input id="slug" name="slug" value="' . e(slugify($p['judul'])) . '" pattern="[a-z0-9-]+"><span class="catatan">bookstore.literaindo.com/buku/<em>link</em>/ — tidak boleh diubah setelah dikirim ke Perpusnas</span></div>
			<div><label for="harga_cetak">Harga jual cetak di katalog (Rp, opsional)</label><input id="harga_cetak" name="harga_cetak" inputmode="numeric"></div>';
		}
		return $form('tagihan', $isi . '</div>', $buat_buku ? 'Tetapkan Tagihan &amp; Buat Halaman Buku' : 'Perbarui Tagihan');
	};
	$catatan_input = fn (string $label, string $id) => '<label for="' . $id . '">' . $label . '</label><textarea id="' . $id . '" name="catatan" rows="3"></textarea>';

	$bukti = function (int $tahap) use ($pid) {
		$b = satu('SELECT py.*, b.id AS bid, b.nama_asli FROM pembayaran py LEFT JOIN berkas b ON b.id = py.berkas_id WHERE py.pengajuan_id = ? AND py.tahap = ? ORDER BY py.id DESC LIMIT 1', [$pid, $tahap]);
		return $b ? '<p>Nominal: <strong>' . rupiah($b['nominal']) . '</strong> · diunggah ' . e(tanggal($b['dibuat'])) . '</p><p><a class="tombol tombol--kedua" href="' . e(url('/berkas/' . $b['bid'])) . '" target="_blank">' . ikon('unduh') . ' Lihat bukti bayar</a></p>' : '';
	};

	$aksi = match ($p['status']) {
		'DRAF' => '<p>Penulis belum mengirim pengajuan.</p>',
		'MENUNGGU_BAYAR_1' => '<p>Menunggu penulis membayar biaya awal ' . rupiah($biaya_awal) . '.</p>',
		'MENUNGGU_BAYAR_2' => '<p>Menunggu penulis membayar pelunasan ' . rupiah($p['tagihan_2']) . '.</p><details><summary>Ubah tagihan</summary>' . $form_tagihan(false) . '</details>',
		'VERIFIKASI_BAYAR_1', 'VERIFIKASI_BAYAR_2' => $bukti($p['status'] === 'VERIFIKASI_BAYAR_1' ? 1 : 2)
			. '<p class="catatan">Cocokkan dengan mutasi QRIS sebelum menerima.</p><div class="aksi-baris">' . $form('terima_bayar', '', 'Terima Pembayaran')
			. '<details><summary>Tolak bukti bayar</summary>' . $form('tolak_bayar', $catatan_input('Alasan (dikirim ke penulis)', 'c-tolak-bayar'), 'Tolak Bukti', 'tombol tombol--bahaya') . '</details></div>',
		'DITINJAU' => '<h3>Setelah naskah siap: tetapkan tagihan pelunasan</h3><p class="catatan">Halaman buku akan dibuat di katalog (Segera Terbit) agar linknya bisa dipakai untuk pengajuan ISBN. Penulis membayar pelunasan setelah ISBN terbit.</p>' . $form_tagihan(true)
			. '<details><summary>Minta revisi ke penulis</summary>' . $form('revisi', $catatan_input('Catatan revisi', 'c-revisi'), 'Kirim Permintaan Revisi', 'tombol tombol--kedua') . '</details>',
		'PERLU_REVISI' => '<p>Menunggu penulis mengirim naskah revisi.</p>',
		'PROSES_ISBN' => ($buku ? '<p>Link untuk Perpusnas: <a href="' . e(url(buku_url($buku))) . '" target="_blank">' . e(url_absolut(buku_url($buku))) . '</a> · <a href="' . e(url('/admin/buku/' . $buku['id'])) . '">ubah data buku</a></p>' : '')
			. '<h3>ISBN sudah terbit?</h3>' . $form('isbn', '<div class="kolom-2"><div><label for="isbn">ISBN</label><input id="isbn" name="isbn" required placeholder="978-623-xx-xxxx-x"></div><div><label for="eisbn">e-ISBN (opsional)</label><input id="eisbn" name="eisbn"></div></div>', 'Simpan ISBN &amp; Buka Tagihan Pelunasan')
			. '<details><summary>Ubah tagihan</summary>' . $form_tagihan(false) . '</details>',
		'LUNAS' => '<p>Lunas. Setelah buku dicetak dan dikirim ke penulis, tandai selesai.</p>' . $form('selesai', '<label for="resi">Nomor resi pengiriman (opsional)</label><input id="resi" name="resi">', 'Tandai Selesai'),
		'SELESAI' => '<p>Selesai.' . ($p['resi'] ? ' Resi: ' . e($p['resi']) : '') . '</p>',
		'DITOLAK' => '<p>Pengajuan ditolak.</p>',
		default => '',
	};
	if (!in_array($p['status'], ['SELESAI', 'DITOLAK'], true)) {
		$aksi .= '<details class="bahaya"><summary>Tolak pengajuan</summary>' . $form('tolak', $catatan_input('Alasan penolakan (dikirim ke penulis)', 'c-tolak'), 'Tolak Pengajuan', 'tombol tombol--bahaya') . '</details>';
	}

	$isi = '<section class="panel">
	<p><a href="' . e(url('/admin/pengajuan')) . '">← Semua pengajuan</a></p>
	<div class="judul-baris"><h1>' . e($p['judul']) . '</h1>' . label_status($p['status']) . '</div>
	' . ($p['status'] !== 'DITOLAK' ? stepper($p['status']) : '') . '
	<div class="kotak-langkah kotak-langkah--admin"><h2>Tindakan</h2>' . $aksi . '</div>
	<div class="dua-kolom">
		<section><h2>Pengaju</h2><dl class="data-ringkas">
			<div><dt>Nama</dt><dd>' . e($pengaju['nama']) . '</dd></div>
			<div><dt>Email</dt><dd>' . e($pengaju['email']) . '</dd></div>
			<div><dt>WhatsApp</dt><dd><a href="https://wa.me/' . e(preg_replace('/^0/', '62', (string) preg_replace('/\D/', '', $pengaju['whatsapp']))) . '" target="_blank">' . e($pengaju['whatsapp']) . '</a></dd></div>
		</dl>
		<h2>Data buku</h2><dl class="data-ringkas">
			<div><dt>Subjudul</dt><dd>' . e($p['subjudul']) . '</dd></div>
			<div><dt>Penulis</dt><dd>' . nl2br(e($p['penulis'])) . '</dd></div>
			<div><dt>Kategori</dt><dd>' . e($p['kategori']) . '</dd></div>
			<div><dt>Ukuran</dt><dd>' . e($p['ukuran']) . '</dd></div>
			<div><dt>Perkiraan halaman</dt><dd>' . e((string) $p['perkiraan_halaman']) . '</dd></div>
			<div><dt>Cetak diminta</dt><dd>' . e((string) $p['jumlah_cetak_diminta']) . ' eksemplar</dd></div>
			' . ($p['tagihan_2'] ? '<div><dt>Pelunasan</dt><dd>' . rupiah($p['tagihan_2']) . ' (' . e((string) $p['jumlah_cetak']) . ' eks.)</dd></div>' : '') . '
		</dl>
		<h3>Sinopsis</h3><p>' . nl2br(e($p['sinopsis'])) . '</p>
		<h2>Berkas</h2>' . daftar_berkas($pid) . '</section>
		<section><h2>Riwayat &amp; pesan</h2>' . form_catatan() . riwayat($pid) . '</section>
	</div>
</section>';
	tampil($p['judul'], $isi, ['panel' => menu_admin('/admin/pengajuan')]);
}

function slug_unik(string $slug, int $kecuali = 0): string
{
	$dasar = slugify($slug);
	$coba = $dasar;
	for ($i = 2; satu('SELECT id FROM buku WHERE slug = ? AND id <> ?', [$coba, $kecuali]); $i++) {
		$coba = "$dasar-$i";
	}
	return $coba;
}

function buat_buku_dari_pengajuan(array $p, string $slug, int $harga_cetak): int
{
	q('INSERT INTO buku (slug, judul, subjudul, penulis, kategori, tahun, status, halaman, ukuran, bahasa, harga_cetak, sampul, deskripsi, tampil, pengajuan_id, dibuat) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [
		slug_unik($slug !== '' ? $slug : $p['judul']), $p['judul'], $p['subjudul'], $p['penulis'], $p['kategori'], date('Y'), 'segera_terbit',
		$p['halaman_final'], $p['ukuran'], 'Indonesia', $harga_cetak, terbitkan_sampul((int) $p['id']), $p['sinopsis'], 1, $p['id'], sekarang(),
	]);
	return (int) db()->lastInsertId();
}

function admin_buku(bool $post): void
{
	wajib_masuk('admin');
	$baris = '';
	foreach (semua('SELECT * FROM buku ORDER BY dibuat DESC, id DESC') as $b) {
		$baris .= '<tr><td><a href="' . e(url('/admin/buku/' . $b['id'])) . '"><strong>' . e($b['judul']) . '</strong></a>' . ($b['tampil'] ? '' : ' <span class="lencana lencana--tolak">Disembunyikan</span>') . '</td><td>' . e(implode(', ', penulis_daftar($b['penulis']))) . '</td><td>' . ($b['status'] === 'terbit' ? 'Sudah Terbit' : 'Segera Terbit') . '</td><td>' . e($b['isbn']) . '</td><td><a href="' . e(url(buku_url($b))) . '" target="_blank">Lihat</a></td></tr>';
	}
	$isi = '<section class="panel"><div class="judul-baris"><h1>Katalog</h1><a class="tombol" href="' . e(url('/admin/buku/baru')) . '">Tambah Buku</a></div>
	<div class="tabel-wadah"><table class="tabel"><thead><tr><th>Judul</th><th>Penulis</th><th>Status</th><th>ISBN</th><th></th></tr></thead><tbody>' . $baris . '</tbody></table></div></section>';
	tampil('Katalog', $isi, ['panel' => menu_admin('/admin/buku')]);
}

function admin_buku_form(bool $post, string $id = ''): void
{
	wajib_masuk('admin');
	$b = $id !== '' ? satu('SELECT * FROM buku WHERE id = ?', [(int) $id]) : null;
	if ($id !== '' && !$b) {
		tidak_ditemukan();
	}
	$kolom = ['judul', 'subjudul', 'penulis', 'editor', 'kategori', 'tahun', 'isbn', 'eisbn', 'edisi', 'halaman', 'ukuran', 'bahasa', 'deskripsi'];
	$v = $b ?: array_fill_keys($kolom, '') + ['slug' => '', 'status' => 'segera_terbit', 'harga_cetak' => 0, 'harga_ebook' => 0, 'sampul' => '', 'tampil' => 1, 'bahasa' => 'Indonesia', 'tahun' => date('Y')];

	if ($post) {
		foreach ($kolom as $k) {
			$v[$k] = $k === 'penulis' || $k === 'deskripsi' ? trim(preg_replace("/\r\n?/", "\n", input($k))) : input($k);
		}
		$v['status'] = input('status') === 'terbit' ? 'terbit' : 'segera_terbit';
		$v['harga_cetak'] = (int) preg_replace('/\D/', '', input('harga_cetak'));
		$v['harga_ebook'] = (int) preg_replace('/\D/', '', input('harga_ebook'));
		$v['tampil'] = empty($_POST['tampil']) ? 0 : 1;
		try {
			if (mb_strlen($v['judul']) < 2) {
				throw new RuntimeException('Isi judul buku.');
			}
			$sampul = unggah_sampul_katalog('sampul');
			if ($sampul !== '') {
				$v['sampul'] = $sampul;
			}
			if ($b) {
				q('UPDATE buku SET judul=?, subjudul=?, penulis=?, editor=?, kategori=?, tahun=?, isbn=?, eisbn=?, edisi=?, halaman=?, ukuran=?, bahasa=?, deskripsi=?, status=?, harga_cetak=?, harga_ebook=?, sampul=?, tampil=? WHERE id=?',
					[...array_map(fn ($k) => $v[$k], $kolom), $v['status'], $v['harga_cetak'], $v['harga_ebook'], $v['sampul'], $v['tampil'], $b['id']]);
				$bid = (int) $b['id'];
			} else {
				$slug = slug_unik(input('slug') !== '' ? input('slug') : $v['judul']);
				q('INSERT INTO buku (judul, subjudul, penulis, editor, kategori, tahun, isbn, eisbn, edisi, halaman, ukuran, bahasa, deskripsi, status, harga_cetak, harga_ebook, sampul, tampil, slug, dibuat) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
					[...array_map(fn ($k) => $v[$k], $kolom), $v['status'], $v['harga_cetak'], $v['harga_ebook'], $v['sampul'], $v['tampil'], $slug, sekarang()]);
				$bid = (int) db()->lastInsertId();
			}
			pesan('Data buku tersimpan.');
			arahkan('/admin/buku/' . $bid);
		} catch (RuntimeException $ex) {
			pesan($ex->getMessage(), 'galat');
		}
	}

	$f = fn (string $k, string $label, string $extra = '') => '<div><label for="' . $k . '">' . $label . '</label><input id="' . $k . '" name="' . $k . '" value="' . e((string) $v[$k]) . '"' . $extra . '></div>';
	$isi = '<section class="panel"><p><a href="' . e(url('/admin/buku')) . '">← Katalog</a></p>
	<h1>' . ($b ? 'Ubah Buku' : 'Tambah Buku') . '</h1>
	' . ($b ? '<p>Link: <a href="' . e(url(buku_url($b))) . '" target="_blank">' . e(url_absolut(buku_url($b))) . '</a></p>' : '') . '
	<form method="post" enctype="multipart/form-data" class="formulir formulir--lebar">' . csrf_field() . '
		<div class="kolom-2">
			' . $f('judul', 'Judul *', ' required') . $f('subjudul', 'Subjudul') . '
			' . ($b ? '' : $f('slug', 'Link (kosongkan agar dibuat dari judul)', ' pattern="[a-z0-9-]*"')) . '
			' . $f('editor', 'Editor') . $f('kategori', 'Kategori') . $f('tahun', 'Tahun terbit') . '
			<div><label for="status">Status</label><select id="status" name="status"><option value="segera_terbit"' . ($v['status'] === 'segera_terbit' ? ' selected' : '') . '>Segera Terbit (belum bisa dipesan)</option><option value="terbit"' . ($v['status'] === 'terbit' ? ' selected' : '') . '>Sudah Terbit</option></select></div>
			' . $f('isbn', 'ISBN') . $f('eisbn', 'e-ISBN') . $f('edisi', 'Edisi') . $f('halaman', 'Jumlah halaman') . $f('ukuran', 'Ukuran') . $f('bahasa', 'Bahasa') . '
			' . $f('harga_cetak', 'Harga cetak (Rp, 0 = tidak dijual)', ' inputmode="numeric"') . $f('harga_ebook', 'Harga e-book (Rp, 0 = tidak dijual)', ' inputmode="numeric"') . '
		</div>
		<label for="penulis">Penulis (satu nama per baris)</label><textarea id="penulis" name="penulis" rows="3">' . e($v['penulis']) . '</textarea>
		<label for="deskripsi">Deskripsi (pisahkan paragraf dengan baris kosong)</label><textarea id="deskripsi" name="deskripsi" rows="8">' . e((string) $v['deskripsi']) . '</textarea>
		<label for="sampul">Sampul ' . ($v['sampul'] ? '(sudah ada, unggah untuk mengganti)' : '') . '</label>
		' . ($v['sampul'] ? '<img class="pratinjau-sampul" src="' . e(url($v['sampul'])) . '" alt="Sampul saat ini">' : '') . '
		<input type="file" id="sampul" name="sampul" accept=".jpg,.jpeg,.png,.webp">
		<label class="centang"><input type="checkbox" id="tampil" name="tampil" value="1"' . ($v['tampil'] ? ' checked' : '') . '> Tampilkan di katalog</label>
		<div class="formulir__tombol"><button type="submit" class="tombol">Simpan</button></div>
	</form></section>';
	tampil($b ? 'Ubah Buku' : 'Tambah Buku', $isi, ['panel' => menu_admin('/admin/buku')]);
}

function admin_pengguna(bool $post): void
{
	$admin = wajib_masuk('admin');
	if ($post) {
		$uid = (int) input('uid');
		$target = satu('SELECT * FROM pengguna WHERE id = ?', [$uid]);
		if ($target) {
			if (($_POST['aksi'] ?? '') === 'peran' && $uid !== (int) $admin['id']) {
				q('UPDATE pengguna SET peran = ? WHERE id = ?', [input('peran') === 'admin' ? 'admin' : 'pengaju', $uid]);
				pesan('Peran ' . $target['nama'] . ' diperbarui.');
			} elseif (($_POST['aksi'] ?? '') === 'sandi') {
				$baru = (string) ($_POST['sandi'] ?? '');
				if (strlen($baru) < 8) {
					pesan('Kata sandi minimal 8 karakter.', 'galat');
				} else {
					q('UPDATE pengguna SET sandi = ? WHERE id = ?', [password_hash($baru, PASSWORD_DEFAULT), $uid]);
					pesan('Kata sandi ' . $target['nama'] . ' diganti. Beri tahu pengguna lewat WhatsApp.');
				}
			}
		}
		arahkan('/admin/pengguna');
	}
	$baris = '';
	foreach (semua('SELECT u.*, (SELECT COUNT(*) FROM pengajuan p WHERE p.pengguna_id = u.id) AS jml FROM pengguna u ORDER BY u.dibuat DESC') as $r) {
		$diri = (int) $r['id'] === (int) $admin['id'];
		$baris .= '<tr><td><strong>' . e($r['nama']) . '</strong><br><span class="catatan">' . e($r['email']) . ' · ' . e($r['whatsapp']) . '</span></td><td class="angka">' . (int) $r['jml'] . '</td><td>'
			. ($diri ? 'Admin (Anda)' : '<form method="post" class="baris-form">' . csrf_field() . '<input type="hidden" name="aksi" value="peran"><input type="hidden" name="uid" value="' . $r['id'] . '"><label class="tersembunyi" for="peran-' . $r['id'] . '">Peran</label><select id="peran-' . $r['id'] . '" name="peran"><option value="pengaju"' . ($r['peran'] === 'pengaju' ? ' selected' : '') . '>Pengaju</option><option value="admin"' . ($r['peran'] === 'admin' ? ' selected' : '') . '>Admin</option></select><button class="tombol tombol--kecil" type="submit">Simpan</button></form>')
			. '</td><td><details><summary>Atur ulang sandi</summary><form method="post" class="baris-form">' . csrf_field() . '<input type="hidden" name="aksi" value="sandi"><input type="hidden" name="uid" value="' . $r['id'] . '"><label class="tersembunyi" for="sandi-' . $r['id'] . '">Sandi baru</label><input type="text" id="sandi-' . $r['id'] . '" name="sandi" minlength="8" placeholder="Sandi baru"><button class="tombol tombol--kecil" type="submit">Ganti</button></form></details></td></tr>';
	}
	$isi = '<section class="panel"><h1>Pengguna</h1><div class="tabel-wadah"><table class="tabel"><thead><tr><th>Nama</th><th>Pengajuan</th><th>Peran</th><th>Kata sandi</th></tr></thead><tbody>' . $baris . '</tbody></table></div></section>';
	tampil('Pengguna', $isi, ['panel' => menu_admin('/admin/pengguna')]);
}

function admin_pengaturan(bool $post): void
{
	global $SITUS;
	wajib_masuk('admin');
	if ($post) {
		try {
			$biaya = (int) preg_replace('/\D/', '', input('biaya_awal'));
			if ($biaya < 1) {
				throw new RuntimeException('Isi biaya awal.');
			}
			simpan_pengaturan('biaya_awal', (string) $biaya);
			$qris = unggah_sampul_katalog('qris');
			if ($qris !== '') {
				simpan_pengaturan('qris_gambar', $qris);
			}
			pesan('Pengaturan tersimpan.');
		} catch (RuntimeException $ex) {
			pesan($ex->getMessage(), 'galat');
		}
		arahkan('/admin/pengaturan');
	}
	$isi = '<section class="panel panel--sempit"><h1>Pengaturan</h1>
	<form method="post" enctype="multipart/form-data" class="formulir">' . csrf_field() . '
		<label for="biaya_awal">Biaya awal pengajuan (Rp)</label>
		<input id="biaya_awal" name="biaya_awal" inputmode="numeric" value="' . e(pengaturan('biaya_awal', '250000')) . '">
		<label for="qris">Gambar QRIS (unggah untuk mengganti)</label>
		' . blok_qris() . '
		<input type="file" id="qris" name="qris" accept=".jpg,.jpeg,.png,.webp">
		<button type="submit" class="tombol">Simpan</button>
	</form>
	<p class="catatan">Nama, alamat, kontak, dan menu website diatur di file <code>app/data/situs.json</code> di GitHub.</p></section>';
	tampil('Pengaturan', $isi, ['panel' => menu_admin('/admin/pengaturan')]);
}
