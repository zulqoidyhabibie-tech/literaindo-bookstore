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
			if ($aksi === 'berkas_isbn') {
				$n = 0;
				foreach (['permohonan_isbn', 'halaman_awal', 'naskah_watermark'] as $j) {
					$n += simpan_unggahan($j, $j, $pid) ? 1 : 0;
				}
				if ($n) {
					catat($pid, $aid, $n . ' berkas ISBN diunggah admin.', true);
				}
				pesan($n ? 'Berkas ISBN tersimpan.' : 'Pilih minimal satu berkas untuk diunggah.', $n ? 'ok' : 'info');
			} elseif ($aksi === 'catatan' && input('isi') !== '') {
				catat($pid, $aid, mb_substr(input('isi'), 0, 3000));
				notif_pengajuan($p, 'Pesan dari admin: ' . mb_substr(input('isi'), 0, 200));
				q('UPDATE pengajuan SET diubah = ? WHERE id = ?', [sekarang(), $pid]);
				$pemilik = satu('SELECT * FROM pengguna WHERE id = ?', [$p['pengguna_id']]);
				kirim_email($pemilik['email'], 'Pesan baru untuk pengajuan "' . $p['judul'] . '"', "Halo {$pemilik['nama']},\n\nAdmin Literaindo Press mengirim pesan:\n\n" . input('isi') . "\n\nBalas di: " . url_absolut("/akun/pengajuan/$pid"));
				pesan('Pesan terkirim.');
			} elseif (in_array($aksi, ['terima_bayar', 'tolak_bayar'], true) && in_array($s, ['VERIFIKASI_BAYAR_1', 'VERIFIKASI_BAYAR_2'], true)) {
				verifikasi_bayar($p, $aksi === 'terima_bayar', $catatan);
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
				$paket = isset(PAKET[input('paket')]) ? input('paket') : '';
				$min = $paket ? PAKET[$paket][1] : CETAK_MIN;
				if ($jumlah < $min || $tagihan < 1) {
					throw new RuntimeException('Isi nominal pelunasan dan jumlah cetak (minimal ' . $min . ' eksemplar).');
				}
				$spek = [];
				foreach (PILIHAN_CETAK as $k => [, $opsi]) {
					$spek[$k] = isset($opsi[input($k)]) ? input($k) : (string) ($p[$k] ?? '');
				}
				$per_buku = (int) preg_replace('/\D/', '', input('harga_per_buku'));
				$sebelum = ringkas_cetak($p);
				$kolom = array_keys($spek);
				q('UPDATE pengajuan SET paket = ? WHERE id = ?', [$paket, $pid]);
				q('UPDATE pengajuan SET jumlah_cetak = ?, halaman_final = ?, halaman_cetak = ?, gramasi = ?, harga_per_buku = ?, tagihan_2 = ?, rincian_tagihan_2 = ?, ' . implode(' = ?, ', $kolom) . ' = ?, diubah = ? WHERE id = ?',
					[$jumlah, input('halaman_final'), max(0, (int) input('halaman_cetak')), (int) input('gramasi'), $per_buku, $tagihan, input('rincian_tagihan_2'), ...array_values($spek), ...[sekarang(), $pid]]);
				$sesudah = ringkas_cetak($spek);
				if ($sesudah !== $sebelum) {
					catat($pid, $aid, 'Spesifikasi cetak disesuaikan redaksi menjadi: ' . $sesudah . '.', true);
				}
				$harga_jual = (int) preg_replace('/\D/', '', input('harga_cetak'));
				if ($p['buku_id'] && $harga_jual > 0) {
					q('UPDATE buku SET harga_cetak = ? WHERE id = ?', [$harga_jual, $p['buku_id']]);
				}
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

	$form_tagihan = function (bool $buat_buku) use ($p, $form, $buku, $biaya_awal) {
		$spek = '';
		$nilai_spek = [];
		foreach (PILIHAN_CETAK as $k => [$judul, $opsi]) {
			$nilai = isset($opsi[$p[$k] ?? '']) ? (string) $p[$k] : (string) array_key_first($opsi);
			$nilai_spek[$k] = $nilai;
			$spek .= '<div><label for="spek_' . $k . '">' . e($judul) . '</label><select id="spek_' . $k . '" name="' . $k . '" data-spek="' . $k . '">'
				. implode('', array_map(fn ($n, $o) => '<option value="' . e((string) $n) . '"' . ((string) $n === $nilai ? ' selected' : '') . '>' . e($o[0]) . '</option>', array_keys($opsi), $opsi))
				. '</select></div>';
		}
		// Pilihan gramasi untuk kombinasi kertas ini (diperbarui otomatis oleh taksiran.js)
		$gram = array_column(semua('SELECT gramasi FROM harga_kertas WHERE ukuran=? AND kertas=? AND warna=? AND sisi=? ORDER BY gramasi', [kode_ukuran($nilai_spek['ukuran']), $nilai_spek['jenis_kertas'], $nilai_spek['warna_isi'], $nilai_spek['sisi_cetak']]), 'gramasi');
		$gram_pilih = in_array((int) $p['gramasi'], array_map('intval', $gram), true) ? (int) $p['gramasi'] : (int) ($gram[0] ?? 0);
		$opsi_gram = implode('', array_map(fn ($g) => '<option value="' . (int) $g . '"' . ((int) $g === $gram_pilih ? ' selected' : '') . '>' . (int) $g . ' gr</option>', $gram)) ?: '<option value="0">belum ada di daftar harga</option>';
		$halaman = (int) ($p['halaman_cetak'] ?: hitung_halaman((string) $p['halaman_final']) ?: $p['perkiraan_halaman']);
		$t = taksiran_cetak($nilai_spek + $p, $halaman, $gram_pilih);
		$jumlah = (int) ($p['jumlah_cetak'] ?: $p['jumlah_cetak_diminta']);
		$margin = margin_cetak();
		$per_buku = (int) ($p['harga_per_buku'] ?: round($t['per_buku'] * (100 + $margin) / 10000) * 100);
		$data = data_harga();
		$data['biaya_awal'] = $biaya_awal;
		$data['paket'] = data_paket();
		$data['margin'] = $margin;
		$data['per_buku_std'] = per_buku_standar();
		$paket = isset(PAKET[$p['paket'] ?? '']) ? $p['paket'] : 'aksara'; // pengajuan lama (sebelum ada paket) dianggap Paket Aksara
		$opsi_paket = '';
		foreach ($data['paket'] as $k => $pk) {
			$opsi_paket .= '<option value="' . $k . '"' . ($k === $paket ? ' selected' : '') . '>' . e($pk['nama'] . ' · ' . rupiah($pk['harga']) . ' · ' . $pk['eks'] . ' eks') . '</option>';
		}
		$opsi_paket .= '<option value=""' . ($paket === '' ? ' selected' : '') . '>Tanpa paket (hitung per buku)</option>';
		$min = $paket ? PAKET[$paket][1] : CETAK_MIN;

		$isi = '<div data-taksiran>
		<h4 class="subjudul-form">1. Spesifikasi cetak</h4><p class="catatan">Sesuai pilihan penulis: <strong>' . e(ringkas_cetak($p)) . '</strong>. Ubah bila disepakati lain dengan penulis.</p>
		<div class="kolom-2">' . $spek . '<div><label for="gramasi">Berat kertas isi</label><select id="gramasi" name="gramasi" data-gramasi>' . $opsi_gram . '</select></div></div>

		<h4 class="subjudul-form">2. Taksiran biaya cetak per buku</h4>
		<div class="kolom-2">
			<div><label for="halaman_final">Jumlah halaman final <span class="catatan">(tertulis di buku)</span></label><input id="halaman_final" name="halaman_final" placeholder="mis. xxix + 265" value="' . e($p['halaman_final']) . '" data-halaman-final></div>
			<div><label for="halaman_cetak">Total halaman dicetak</label><input type="number" min="0" id="halaman_cetak" name="halaman_cetak" value="' . ($halaman ?: '') . '" data-halaman><span class="catatan">Terisi otomatis dari halaman final (Romawi + angka).</span></div>
		</div>
		<table class="taksiran">
			<tr><th>Kertas isi</th><td data-t="rumus">' . e($t['lembar'] . ' ' . ($nilai_spek['sisi_cetak'] === '2' && $data['per_lembar'] ? 'lembar' : 'halaman') . ' × ' . rupiah($t['harga_kertas'])) . '</td><td data-t="isi">' . rupiah($t['isi']) . '</td></tr>
			<tr><th>Cover &amp; jilid</th><td data-t="rumus-cover">' . e(label_cetak('jenis_sampul', $nilai_spek['jenis_sampul']) . ', ' . label_cetak('jilid', $nilai_spek['jilid']) . ', ' . label_cetak('laminasi', $nilai_spek['laminasi'])) . '</td><td data-t="cover">' . rupiah($t['cover']) . '</td></tr>
			<tr class="taksiran__total"><th colspan="2">Taksiran harga cetak per buku</th><td data-t="per_buku">' . rupiah($t['per_buku']) . '</td></tr>
		</table>
		<table class="taksiran taksiran--berat">
			<tr><th>Berat per buku</th><td>kertas isi + cover</td><td data-b="buku">-</td></tr>
			<tr><th>Total cetak</th><td data-b="n-total">-</td><td data-b="total">-</td></tr>
			<tr><th>Kiriman ke penulis</th><td data-b="n-penulis">-</td><td data-b="penulis">-</td></tr>
			<tr><th>Wajib serah ke Perpusnas</th><td>2 eks + kemasan</td><td data-b="perpusnas">-</td></tr>
			<tr><th>Wajib serah ke Perpusda</th><td>1 eks + kemasan</td><td data-b="perpusda">-</td></tr>
		</table>
		<p class="catatan">Berat adalah taksiran untuk ongkir: kertas (luas × gramasi), cover, dan kemasan ±100 g per kiriman. Kurir membulatkan ke atas per kg. Buku arsip penerbit tidak dikirim.</p>
		<p class="pesan pesan--info taksiran__catatan" data-t="catatan"' . ($t['catatan'] ? '' : ' hidden') . '>' . e(implode(' ', $t['catatan'])) . ' Isi harga per buku secara manual atau lengkapi <a href="' . e(url('/admin/harga')) . '">daftar harga</a>.</p>

		<h4 class="subjudul-form">3. Paket, harga, dan tagihan pelunasan</h4>
		<div class="kolom-2">
			<div><label for="paket">Paket penerbitan</label><select id="paket" name="paket" data-paket>' . $opsi_paket . '</select></div>
			<div><label for="jumlah_cetak">Jumlah eksemplar dicetak <span class="catatan">(min. <span data-min-eks>' . $min . '</span>)</span></label><input type="number" min="' . $min . '" id="jumlah_cetak" name="jumlah_cetak" required value="' . $jumlah . '" data-jumlah></div>
			<div><label for="harga_per_buku">Harga cetak per buku (Rp) <span class="catatan">(taksiran + margin ' . $margin . '%)</span></label><input id="harga_per_buku" name="harga_per_buku" inputmode="numeric" value="' . ($per_buku ?: '') . '" data-per-buku' . ($p['harga_per_buku'] ? ' data-manual="1"' : '') . '><button type="button" class="tautan-tombol" data-pakai-taksiran>Pakai taksiran</button></div>
			<div><label for="tagihan_2">Nominal pelunasan (Rp)</label><input id="tagihan_2" name="tagihan_2" inputmode="numeric" required value="' . e($p['tagihan_2'] ?: '') . '" data-tagihan' . ($p['tagihan_2'] ? ' data-manual="1"' : '') . '>
				<label class="centang" data-hanya-tanpa-paket><input type="checkbox" data-potong> Kurangi biaya awal ' . rupiah($biaya_awal) . '</label></div>
			<div><label for="rincian_tagihan_2">Rincian untuk penulis</label><input id="rincian_tagihan_2" name="rincian_tagihan_2" placeholder="mis. 5 eks × Rp45.000" value="' . e($p['rincian_tagihan_2']) . '" data-rincian' . ($p['rincian_tagihan_2'] ? ' data-manual="1"' : '') . '></div>
			<div><label for="harga_cetak">Harga jual di katalog (Rp' . ($buat_buku ? ', opsional' : '') . ')</label><input id="harga_cetak" name="harga_cetak" inputmode="numeric" value="' . e((string) (($buku['harga_cetak'] ?? 0) ?: '')) . '"></div>';
		if ($buat_buku) {
			$isi .= '<div><label for="slug">Link halaman buku</label><input id="slug" name="slug" value="' . e(slugify($p['judul'])) . '" pattern="[a-z0-9-]+"><span class="catatan">bookstore.literaindo.com/buku/<em>link</em>/ — tidak boleh diubah setelah dikirim ke Perpusnas</span></div>';
		}
		$isi .= '</div>
		<table class="taksiran taksiran--paket" data-rincian-paket>
			<tr><th data-p="nama">Harga paket</th><td></td><td data-p="harga">-</td></tr>
			<tr><th>Selisih spesifikasi</th><td data-p="ket-selisih">-</td><td data-p="selisih">-</td></tr>
			<tr><th>Eksemplar tambahan</th><td data-p="ket-tambah">-</td><td data-p="tambah">-</td></tr>
			<tr><th>Total</th><td></td><td data-p="total">-</td></tr>
			<tr><th>Uang muka (biaya awal)</th><td>sudah dibayar</td><td data-p="dp">-</td></tr>
			<tr class="taksiran__total"><th colspan="2">Pelunasan</th><td data-p="pelunasan">-</td></tr>
		</table>
		<p class="catatan">Selisih spesifikasi muncul bila harga cetak per buku lebih mahal dari buku standar paket (A5, ' . batas_halaman_paket() . ' halaman, HVS hitam putih, soft cover) yaitu ' . rupiah($data['per_buku_std']) . ' per buku. Nominal pelunasan dan rinciannya terisi otomatis. Periksa, ubah bila perlu, lalu simpan untuk mengesahkan.</p></div>
		<script type="application/json" id="data-harga">' . json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) . '</script>
		<script src="' . e(url('/assets/js/taksiran.js')) . '?v=5" defer></script>';
		return $form('tagihan', $isi, $buat_buku ? 'Tetapkan Tagihan &amp; Buat Halaman Buku' : 'Perbarui Tagihan');
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
		'VERIFIKASI_BAYAR_1', 'VERIFIKASI_BAYAR_2' => '<p>Bukti bayar sedang menunggu verifikasi oleh <strong>bagian keuangan</strong> di <a href="' . e(url('/keuangan')) . '">Panel Keuangan</a>.</p>' . $bukti($p['status'] === 'VERIFIKASI_BAYAR_1' ? 1 : 2)
			. '<details><summary>Verifikasi sendiri sebagai admin</summary><p class="catatan">Gunakan hanya jika bagian keuangan berhalangan. Cocokkan dengan mutasi rekening/QRIS sebelum menerima.</p><div class="aksi-baris">' . $form('terima_bayar', '', 'Terima Pembayaran')
			. '<details><summary>Tolak bukti bayar</summary>' . $form('tolak_bayar', $catatan_input('Alasan (dikirim ke penulis)', 'c-tolak-bayar'), 'Tolak Bukti', 'tombol tombol--bahaya') . '</details></div></details>',
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
	// Daftar periksa berkas ISBN sesuai ketentuan Perpusnas.
	$jk = JENIS_KARYA[$p['jenis_karya'] ?? 'asli'] ?? JENIS_KARYA['asli'];
	$cek = [
		['a', 'Surat permohonan ISBN (kop surat, tanda tangan pimpinan, stempel)', 'permohonan_isbn'],
		['b', 'Halaman awal: judul, balik judul, kata pengantar, daftar isi', 'halaman_awal'],
		['c', 'Naskah akhir terbitan ber-watermark', 'naskah_watermark'],
		['d', 'Surat pernyataan keaslian karya bermeterai', 'pernyataan'],
	];
	foreach (['izin_terjemah' => 'e', 'pengalihan' => 'f', 'keterangan_pd' => 'g'] as $j => $huruf) {
		if ($jk[1] === $j) {
			$cek[] = [$huruf, JENIS_BERKAS[$j][0], $j];
		}
	}
	$li = '';
	foreach ($cek as [$huruf, $label, $j]) {
		$b = berkas_terakhir($pid, $j);
		$li .= '<li class="' . ($b ? 'ada' : 'belum') . '"><span class="periksa__tanda" aria-hidden="true">' . ($b ? '✓' : '') . '</span><span><strong>' . $huruf . '.</strong> ' . e($label)
			. ($b ? ' · <a href="' . e(url('/berkas/' . $b['id'])) . '">' . e($b['nama_asli']) . '</a>' : ' · <em>belum ada</em>') . '</span></li>';
	}
	$li .= '<li class="' . ($buku ? 'ada' : 'belum') . '"><span class="periksa__tanda" aria-hidden="true">' . ($buku ? '✓' : '') . '</span><span><strong>h.</strong> Link katalog buku di website'
		. ($buku ? ' · <a href="' . e(url(buku_url($buku))) . '" target="_blank">' . e(url_absolut(buku_url($buku))) . '</a>' : ' · <em>dibuat saat tagihan pelunasan ditetapkan</em>') . '</span></li>';
	$kotak_isbn = $p['status'] === 'DRAF' ? '' : '<div class="kotak-langkah"><h2>Berkas ISBN</h2>
		<p class="catatan">Jenis karya: <strong>' . e($jk[0]) . '</strong>. Lengkapi semua butir sebelum mengajukan ke isbn.perpusnas.go.id.</p>
		<ul class="periksa">' . $li . '</ul>
		<details><summary>Unggah berkas a, b, c</summary><form method="post" enctype="multipart/form-data" class="formulir">' . csrf_field() . '<input type="hidden" name="aksi" value="berkas_isbn">
			<label for="permohonan_isbn">a. Surat permohonan ISBN <span class="catatan">(PDF/JPG/PNG, maks. 5 MB)</span></label><input type="file" id="permohonan_isbn" name="permohonan_isbn" accept=".pdf,.jpg,.jpeg,.png">
			<label for="halaman_awal">b. Halaman awal <span class="catatan">(PDF/DOC/DOCX, maks. 20 MB)</span></label><input type="file" id="halaman_awal" name="halaman_awal" accept=".pdf,.doc,.docx">
			<label for="naskah_watermark">c. Naskah akhir ber-watermark <span class="catatan">(PDF, maks. 30 MB)</span></label><input type="file" id="naskah_watermark" name="naskah_watermark" accept=".pdf">
			<button type="submit" class="tombol">Simpan Berkas</button></form></details></div>';

	if (!in_array($p['status'], ['SELESAI', 'DITOLAK'], true)) {
		$aksi .= '<details class="bahaya"><summary>Tolak pengajuan</summary>' . $form('tolak', $catatan_input('Alasan penolakan (dikirim ke penulis)', 'c-tolak'), 'Tolak Pengajuan', 'tombol tombol--bahaya') . '</details>';
	}

	$isi = '<section class="panel">
	<p><a href="' . e(url('/admin/pengajuan')) . '">← Semua pengajuan</a></p>
	<div class="judul-baris"><h1>' . e($p['judul']) . '</h1>' . label_status($p['status']) . '</div>
	' . ($p['status'] !== 'DITOLAK' ? stepper($p['status']) : '') . '
	<div class="kotak-langkah kotak-langkah--admin"><h2>Tindakan</h2>' . $aksi . '</div>
	' . $kotak_isbn . '
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
			<div><dt>Jenis karya</dt><dd>' . e($jk[0]) . '</dd></div>
			<div><dt>Paket</dt><dd>' . e(PAKET[$p['paket']][0] ?? '-') . '</dd></div>
			' . data_cetak($p) . '
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
			simpan_ebook($bid);
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
		<label for="ebook">File e-book (PDF, maks. 30 MB) ' . (($b['ebook_path'] ?? '') !== '' ? '<span class="catatan">(sudah ada: ' . e(basename((string) $b['ebook_path'])) . ', unggah untuk mengganti)</span>' : '<span class="catatan">(wajib bila e-book dijual)</span>') . '</label>
		<input type="file" id="ebook" name="ebook" accept=".pdf">
		<p class="catatan">File e-book tidak bisa dibuka publik. Pembeli hanya mendapat link unduhan pribadi yang berlaku ' . EBOOK_HARI . ' hari setelah pembayarannya diterima.</p>
		<label class="centang"><input type="checkbox" id="tampil" name="tampil" value="1"' . ($v['tampil'] ? ' checked' : '') . '> Tampilkan di katalog</label>
		<div class="formulir__tombol"><button type="submit" class="tombol">Simpan</button></div>
	</form></section>';
	tampil($b ? 'Ubah Buku' : 'Tambah Buku', $isi, ['panel' => menu_admin('/admin/buku')]);
}

function admin_pengguna(bool $post): void
{
	$admin = wajib_masuk('admin');
	if ($post && ($_POST['aksi'] ?? '') === 'tambah') {
		$email = mb_strtolower(input('email'));
		$sandi = (string) ($_POST['sandi'] ?? '');
		$peran = isset(PERAN[input('peran')]) ? input('peran') : 'keuangan';
		if (mb_strlen(input('nama')) < 3 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
			pesan('Isi nama dan email yang benar.', 'galat');
		} elseif (strlen($sandi) < 8) {
			pesan('Kata sandi minimal 8 karakter.', 'galat');
		} elseif (satu('SELECT id FROM pengguna WHERE email = ?', [$email])) {
			pesan('Email ini sudah terdaftar. Ubah perannya di tabel di bawah.', 'galat');
		} else {
			q('INSERT INTO pengguna (nama, email, whatsapp, sandi, peran, dibuat) VALUES (?, ?, ?, ?, ?, ?)', [input('nama'), $email, preg_replace('/[^0-9+]/', '', input('whatsapp')), password_hash($sandi, PASSWORD_DEFAULT), $peran, sekarang()]);
			pesan('Akun ' . PERAN[$peran] . ' untuk ' . input('nama') . ' dibuat. Berikan email dan kata sandinya; pengguna bisa menggantinya di menu Profil.');
		}
		arahkan('/admin/pengguna');
	}
	if ($post) {
		$uid = (int) input('uid');
		$target = satu('SELECT * FROM pengguna WHERE id = ?', [$uid]);
		if ($target) {
			if (($_POST['aksi'] ?? '') === 'peran' && $uid !== (int) $admin['id']) {
				q('UPDATE pengguna SET peran = ? WHERE id = ?', [isset(PERAN[input('peran')]) ? input('peran') : 'pengaju', $uid]);
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
			. ($diri ? 'Admin (Anda)' : '<form method="post" class="baris-form">' . csrf_field() . '<input type="hidden" name="aksi" value="peran"><input type="hidden" name="uid" value="' . $r['id'] . '"><label class="tersembunyi" for="peran-' . $r['id'] . '">Peran</label><select id="peran-' . $r['id'] . '" name="peran">' . implode('', array_map(fn ($k, $l) => '<option value="' . $k . '"' . ($r['peran'] === $k ? ' selected' : '') . '>' . e($l) . '</option>', array_keys(PERAN), PERAN)) . '</select><button class="tombol tombol--kecil" type="submit">Simpan</button></form>')
			. '</td><td><details><summary>Atur ulang sandi</summary><form method="post" class="baris-form">' . csrf_field() . '<input type="hidden" name="aksi" value="sandi"><input type="hidden" name="uid" value="' . $r['id'] . '"><label class="tersembunyi" for="sandi-' . $r['id'] . '">Sandi baru</label><input type="text" id="sandi-' . $r['id'] . '" name="sandi" minlength="8" placeholder="Sandi baru"><button class="tombol tombol--kecil" type="submit">Ganti</button></form></details></td></tr>';
	}
	$isi = '<section class="panel"><h1>Pengguna</h1>
	<details class="kotak-langkah"><summary>Tambah akun (keuangan / admin)</summary>
		<form method="post" class="formulir">' . csrf_field() . '<input type="hidden" name="aksi" value="tambah">
			<div class="kolom-2">
				<div><label for="t-nama">Nama</label><input id="t-nama" name="nama" required></div>
				<div><label for="t-email">Email (untuk masuk)</label><input type="email" id="t-email" name="email" required></div>
				<div><label for="t-wa">Nomor WhatsApp</label><input id="t-wa" name="whatsapp" inputmode="tel"></div>
				<div><label for="t-sandi">Kata sandi awal (min. 8 karakter)</label><input type="text" id="t-sandi" name="sandi" minlength="8" required></div>
				<div><label for="t-peran">Peran</label><select id="t-peran" name="peran"><option value="keuangan">Keuangan: lihat &amp; unduh data transaksi</option><option value="admin">Admin: akses penuh</option></select></div>
			</div>
			<button type="submit" class="tombol">Buat Akun</button>
		</form>
	</details>
	<div class="tabel-wadah"><table class="tabel"><thead><tr><th>Nama</th><th>Pengajuan</th><th>Peran</th><th>Kata sandi</th></tr></thead><tbody>' . $baris . '</tbody></table></div></section>';
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
			foreach (PAKET as [, , , $kunci]) {
				$h = (int) preg_replace('/\D/', '', input($kunci));
				if ($h > 0) {
					simpan_pengaturan($kunci, (string) $h);
				}
			}
			simpan_pengaturan('batas_halaman_paket', (string) max(1, (int) input('batas_halaman_paket', '200')));
			simpan_pengaturan('margin_cetak', (string) max(0, min(500, (int) input('margin_cetak', '30'))));
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
		<p class="catatan">Biaya awal menjadi uang muka dan dipotong dari harga paket saat pelunasan.</p>
		<fieldset><legend>Paket penerbitan</legend>
			' . implode('', array_map(fn ($pk) => '<label for="' . $pk[3] . '">Harga ' . e($pk[0]) . ' (Rp) <span class="catatan">' . e($pk[5]) . '</span></label><input id="' . $pk[3] . '" name="' . $pk[3] . '" inputmode="numeric" value="' . e(pengaturan($pk[3], (string) $pk[4])) . '">', PAKET)) . '
			<label for="batas_halaman_paket">Batas halaman buku standar paket</label>
			<input id="batas_halaman_paket" name="batas_halaman_paket" type="number" min="1" value="' . e((string) batas_halaman_paket()) . '">
			<label for="margin_cetak">Margin harga cetak per buku (%)</label>
			<input id="margin_cetak" name="margin_cetak" type="number" min="0" max="500" value="' . e((string) margin_cetak()) . '">
			<p class="catatan">Harga paket berlaku untuk buku A5, HVS hitam putih, soft cover, sampai batas halaman di atas. Buku yang lebih tebal atau spesifikasinya lain dikenai selisih biaya cetak. Harga cetak per buku = taksiran dari Harga Cetak + margin.</p>
		</fieldset>
		<label for="qris">Gambar QRIS (unggah untuk mengganti)</label>
		' . blok_qris() . '
		<input type="file" id="qris" name="qris" accept=".jpg,.jpeg,.png,.webp">
		<button type="submit" class="tombol">Simpan</button>
	</form>
	<p class="catatan">Nama, alamat, kontak, dan menu website diatur di file <code>app/data/situs.json</code> di GitHub.</p></section>';
	tampil('Pengaturan', $isi, ['panel' => menu_admin('/admin/pengaturan')]);
}

/** Kode harga seperti di daftar harga Excel, mis. A5_HVS_HITAM PUTIH_2SISI_80gr. */
function kode_kertas(array $r): string
{
	return $r['ukuran'] . '_' . ($r['kertas'] === 'hvs' ? 'HVS' : 'BP') . '_' . ($r['warna'] === 'warna' ? 'WARNA' : 'HITAM PUTIH') . '_' . $r['sisi'] . 'SISI_' . (int) $r['gramasi'] . 'gr';
}

function kode_cover(array $r): string
{
	return label_cetak('jenis_sampul', $r['sampul']) . ' ' . $r['ukuran'] . '_' . ucfirst($r['jilid']) . '_' . label_cetak('laminasi', $r['laminasi']);
}

function admin_harga(bool $post): void
{
	wajib_masuk('admin');
	$ukuran = ['A5', 'B5', 'A4'];
	if ($post) {
		$aksi = $_POST['aksi'] ?? '';
		$uang = fn ($v) => (int) preg_replace('/\D/', '', (string) $v);
		try {
			if ($aksi === 'cara_hitung') {
				simpan_pengaturan('harga_kertas_per', input('harga_kertas_per') === 'halaman' ? 'halaman' : 'lembar');
				pesan('Cara menghitung harga kertas disimpan.');
			} elseif ($aksi === 'simpan_kertas' || $aksi === 'simpan_cover') {
				$tabel = $aksi === 'simpan_kertas' ? 'harga_kertas' : 'harga_cover';
				$hapus = array_map('intval', array_keys((array) ($_POST['hapus'] ?? [])));
				$n = 0;
				foreach ((array) ($_POST['harga'] ?? []) as $id => $harga) {
					$id = (int) $id;
					if (in_array($id, $hapus, true)) {
						q("DELETE FROM $tabel WHERE id = ?", [$id]);
						continue;
					}
					if ($tabel === 'harga_kertas') {
						$g = (int) ($_POST['gramasi'][$id] ?? 0);
						$n += q('UPDATE harga_kertas SET harga = ?, gramasi = ? WHERE id = ? AND (harga <> ? OR gramasi <> ?)', [$uang($harga), $g, $id, $uang($harga), $g])->rowCount();
					} else {
						$n += q('UPDATE harga_cover SET harga = ? WHERE id = ? AND harga <> ?', [$uang($harga), $id, $uang($harga)])->rowCount();
					}
				}
				pesan($n . ' harga diperbarui' . ($hapus ? ', ' . count($hapus) . ' baris dihapus' : '') . '.');
			} elseif ($aksi === 'tambah_kertas') {
				$r = [in_array(input('ukuran'), $ukuran, true) ? input('ukuran') : 'A5', input('kertas') === 'bookpaper' ? 'bookpaper' : 'hvs', input('warna') === 'warna' ? 'warna' : 'hitam_putih', input('sisi') === '1' ? '1' : '2', (int) input('gramasi')];
				$harga = $uang(input('harga'));
				if ($r[4] < 1 || $harga < 1) {
					throw new RuntimeException('Isi berat kertas dan harga.');
				}
				$ada = satu('SELECT id FROM harga_kertas WHERE ukuran=? AND kertas=? AND warna=? AND sisi=? AND gramasi=?', $r);
				$ada ? q('UPDATE harga_kertas SET harga = ? WHERE id = ?', [$harga, $ada['id']]) : q('INSERT INTO harga_kertas (ukuran, kertas, warna, sisi, gramasi, harga) VALUES (?,?,?,?,?,?)', [...$r, $harga]);
				pesan(($ada ? 'Harga diperbarui: ' : 'Harga ditambahkan: ') . kode_kertas(['ukuran' => $r[0], 'kertas' => $r[1], 'warna' => $r[2], 'sisi' => $r[3], 'gramasi' => $r[4]]));
			} elseif ($aksi === 'tambah_cover') {
				$r = [in_array(input('ukuran'), $ukuran, true) ? input('ukuran') : 'A5', input('sampul') === 'hard' ? 'hard' : 'soft', isset(PILIHAN_CETAK['jilid'][1][input('jilid')]) ? input('jilid') : 'lem', input('laminasi') === 'glossy' ? 'glossy' : 'doff'];
				$harga = $uang(input('harga'));
				if ($harga < 1) {
					throw new RuntimeException('Isi harga cover.');
				}
				$ada = satu('SELECT id FROM harga_cover WHERE ukuran=? AND sampul=? AND jilid=? AND laminasi=?', $r);
				$ada ? q('UPDATE harga_cover SET harga = ? WHERE id = ?', [$harga, $ada['id']]) : q('INSERT INTO harga_cover (ukuran, sampul, jilid, laminasi, harga) VALUES (?,?,?,?,?)', [...$r, $harga]);
				pesan(($ada ? 'Harga diperbarui: ' : 'Harga ditambahkan: ') . kode_cover(['ukuran' => $r[0], 'sampul' => $r[1], 'jilid' => $r[2], 'laminasi' => $r[3]]));
			}
		} catch (RuntimeException $ex) {
			pesan($ex->getMessage(), 'galat');
		}
		arahkan('/admin/harga' . ($aksi && str_contains($aksi, 'cover') ? '#cover' : '#kertas'));
	}

	$data = data_harga();
	$opsi = fn (array $pilihan, string $nama) => '<select name="' . $nama . '">' . implode('', array_map(fn ($v, $l) => '<option value="' . e((string) $v) . '">' . e($l) . '</option>', array_keys($pilihan), $pilihan)) . '</select>';
	$uk = array_combine($ukuran, $ukuran);

	$baris_kertas = '';
	$grup = '';
	foreach ($data['kertas'] as $r) {
		$g = $r['ukuran'] . ' · ' . label_cetak('warna_isi', $r['warna']);
		if ($g !== $grup) {
			$baris_kertas .= '<tr class="tabel-harga__grup"><th colspan="6">' . e($g) . '</th></tr>';
			$grup = $g;
		}
		$id = (int) $r['id'];
		$baris_kertas .= '<tr><td class="tabel-harga__kode">' . e(kode_kertas($r)) . '</td><td>' . e(label_cetak('jenis_kertas', $r['kertas'])) . '</td><td>' . e($r['sisi']) . ' sisi</td>'
			. '<td><input class="isian-kecil" name="gramasi[' . $id . ']" inputmode="numeric" value="' . $r['gramasi'] . '" aria-label="Berat kertas ' . e(kode_kertas($r)) . '"> gr</td>'
			. '<td><input class="isian-harga" name="harga[' . $id . ']" inputmode="numeric" value="' . $r['harga'] . '" aria-label="Harga ' . e(kode_kertas($r)) . '"></td>'
			. '<td><label class="hapus-baris"><input type="checkbox" name="hapus[' . $id . ']" value="1"> Hapus</label></td></tr>';
	}
	$baris_cover = '';
	$grup = '';
	foreach ($data['cover'] as $r) {
		if ($r['ukuran'] !== $grup) {
			$baris_cover .= '<tr class="tabel-harga__grup"><th colspan="4">' . e($r['ukuran']) . '</th></tr>';
			$grup = $r['ukuran'];
		}
		$id = (int) $r['id'];
		$baris_cover .= '<tr><td class="tabel-harga__kode">' . e(kode_cover($r)) . '</td><td>' . e(label_cetak('jilid', $r['jilid'])) . '</td>'
			. '<td><input class="isian-harga" name="harga[' . $id . ']" inputmode="numeric" value="' . $r['harga'] . '" aria-label="Harga ' . e(kode_cover($r)) . '"></td>'
			. '<td><label class="hapus-baris"><input type="checkbox" name="hapus[' . $id . ']" value="1"> Hapus</label></td></tr>';
	}
	$f = fn (string $aksi) => '<input type="hidden" name="aksi" value="' . $aksi . '">' . csrf_field();
	$warna = array_map(fn ($o) => $o[0], PILIHAN_CETAK['warna_isi'][1]);
	$kertas = array_map(fn ($o) => $o[0], PILIHAN_CETAK['jenis_kertas'][1]);
	$per = $data['per_lembar'];

	$isi = '<section class="panel"><h1>Harga Cetak</h1>
	<p class="catatan">Daftar harga ini dipakai untuk menaksir harga cetak per buku di form tagihan pelunasan. Ubah angkanya kapan saja; tagihan yang sudah ditetapkan tidak ikut berubah.</p>

	<form method="post" class="formulir formulir--lebar kotak-cara">' . $f('cara_hitung') . '
		<fieldset><legend>Cara menghitung harga kertas cetak 2 sisi</legend>
			<label class="centang"><input type="radio" name="harga_kertas_per" value="lembar"' . ($per ? ' checked' : '') . '> <strong>Per lembar</strong>: jumlah halaman ÷ 2 × harga 2SISI (buku 200 halaman = 100 lembar)</label>
			<label class="centang"><input type="radio" name="harga_kertas_per" value="halaman"' . ($per ? '' : ' checked') . '> <strong>Per halaman</strong>: jumlah halaman × harga 2SISI</label>
			<p class="catatan">Cetak 1 sisi selalu dihitung per halaman.</p>
		</fieldset>
		<div class="formulir__tombol"><button class="tombol tombol--kedua" type="submit">Simpan cara hitung</button></div>
	</form>

	<h2 id="kertas">Harga kertas isi (per ' . ($per ? 'lembar' : 'halaman') . ')</h2>
	<form method="post" class="formulir formulir--lebar">' . $f('simpan_kertas') . '
		<div class="tabel-wadah"><table class="tabel tabel-harga"><thead><tr><th>Kode</th><th>Kertas</th><th>Sisi</th><th>Berat</th><th>Harga (Rp)</th><th></th></tr></thead><tbody>' . $baris_kertas . '</tbody></table></div>
		<div class="formulir__tombol"><button class="tombol" type="submit">Simpan harga kertas</button></div>
	</form>
	<details class="tambah-harga"><summary>Tambah harga kertas</summary>
		<form method="post" class="formulir formulir--lebar">' . $f('tambah_kertas') . '<div class="baris-tambah">
			<label>Ukuran ' . $opsi($uk, 'ukuran') . '</label><label>Kertas ' . $opsi($kertas, 'kertas') . '</label><label>Warna ' . $opsi($warna, 'warna') . '</label><label>Sisi ' . $opsi(['1' => '1 sisi', '2' => '2 sisi'], 'sisi') . '</label>
			<label>Berat (gr) <input name="gramasi" inputmode="numeric" required></label><label>Harga (Rp) <input name="harga" inputmode="numeric" required></label></div>
			<div class="formulir__tombol"><button class="tombol tombol--kedua" type="submit">Tambah</button></div><p class="catatan">Jika kombinasinya sudah ada, harganya diperbarui.</p></form>
	</details>

	<h2 id="cover">Harga cover dan jilid (per buku)</h2>
	<form method="post" class="formulir formulir--lebar">' . $f('simpan_cover') . '
		<div class="tabel-wadah"><table class="tabel tabel-harga"><thead><tr><th>Jenis cover</th><th>Jilid</th><th>Harga (Rp)</th><th></th></tr></thead><tbody>' . $baris_cover . '</tbody></table></div>
		<div class="formulir__tombol"><button class="tombol" type="submit">Simpan harga cover</button></div>
	</form>
	<details class="tambah-harga"><summary>Tambah harga cover</summary>
		<form method="post" class="formulir formulir--lebar">' . $f('tambah_cover') . '<div class="baris-tambah">
			<label>Ukuran ' . $opsi($uk, 'ukuran') . '</label><label>Cover ' . $opsi(array_map(fn ($o) => $o[0], PILIHAN_CETAK['jenis_sampul'][1]), 'sampul') . '</label><label>Jilid ' . $opsi(array_map(fn ($o) => $o[0], PILIHAN_CETAK['jilid'][1]), 'jilid') . '</label><label>Lapisan ' . $opsi(array_map(fn ($o) => $o[0], PILIHAN_CETAK['laminasi'][1]), 'laminasi') . '</label>
			<label>Harga (Rp) <input name="harga" inputmode="numeric" required></label></div>
			<div class="formulir__tombol"><button class="tombol tombol--kedua" type="submit">Tambah</button></div><p class="catatan">Contoh: tambahkan harga untuk jilid staples bila Anda menyediakannya.</p></form>
	</details>
	</section>';
	tampil('Harga Cetak', $isi, ['panel' => menu_admin('/admin/harga')]);
}
