<?php
// Panel pengaju (penulis): daftar pengajuan, formulir, detail, pembayaran.

declare(strict_types=1);

const URUTAN_STATUS = ['DRAF', 'MENUNGGU_BAYAR_1', 'VERIFIKASI_BAYAR_1', 'DITINJAU', 'PROSES_ISBN', 'MENUNGGU_BAYAR_2', 'VERIFIKASI_BAYAR_2', 'LUNAS', 'SELESAI'];

const TAHAP_TAMPIL = [
	['Kirim naskah', ['DRAF']],
	['Biaya awal', ['MENUNGGU_BAYAR_1', 'VERIFIKASI_BAYAR_1']],
	['Redaksi', ['DITINJAU', 'PERLU_REVISI']],
	['Proses ISBN', ['PROSES_ISBN']],
	['Pelunasan', ['MENUNGGU_BAYAR_2', 'VERIFIKASI_BAYAR_2']],
	['Cetak & kirim', ['LUNAS', 'SELESAI']],
];


function pengajuan_milik(int $id, array $u): array
{
	$p = satu('SELECT * FROM pengajuan WHERE id = ?', [$id]);
	if (!$p || ((int) $p['pengguna_id'] !== (int) $u['id'] && $u['peran'] !== 'admin')) {
		tidak_ditemukan();
	}
	return $p;
}

function pengaju_daftar(bool $post): void
{
	$u = wajib_masuk();
	if ($u['peran'] !== 'pengaju') {
		arahkan(beranda_peran($u['peran']));
	}
	$daftar = semua('SELECT * FROM pengajuan WHERE pengguna_id = ? ORDER BY diubah DESC', [$u['id']]);
	if (!$daftar) {
		$isi = '<section class="panel kosong"><h1>Belum ada pengajuan</h1><p>Ajukan naskah buku Anda untuk diterbitkan dan diurus ISBN-nya oleh Literaindo Press.</p><p><a class="tombol" href="' . e(url('/akun/pengajuan/baru')) . '">Ajukan Buku Baru</a></p></section>';
	} else {
		$baris = '';
		foreach ($daftar as $p) {
			$giliran = STATUS[$p['status']][2] === 'pengaju';
			$baris .= '<li class="daftar-pengajuan__item' . ($giliran ? ' perlu-tindakan' : '') . '">
				<a href="' . e(url('/akun/pengajuan/' . $p['id'])) . '"><strong>' . e($p['judul']) . '</strong></a>
				' . label_status($p['status']) . '
				<span class="catatan">' . e(STATUS[$p['status']][1]) . ' · diperbarui ' . e(tanggal($p['diubah'])) . '</span>
			</li>';
		}
		$isi = '<section class="panel"><div class="judul-baris"><h1>Pengajuan Saya</h1><a class="tombol" href="' . e(url('/akun/pengajuan/baru')) . '">Ajukan Buku Baru</a></div><ul class="daftar-pengajuan">' . $baris . '</ul></section>';
	}
	tampil('Pengajuan Saya', $isi, ['panel' => menu_pengaju('/akun')]);
}

function pengaju_baru(bool $post): void
{
	$u = wajib_masuk(['pengaju', 'admin']);
	formulir_pengajuan($u, null, $post);
}

function pengaju_ubah(bool $post, string $id): void
{
	$u = wajib_masuk();
	$p = pengajuan_milik((int) $id, $u);
	if (!in_array($p['status'], ['DRAF', 'PERLU_REVISI'], true)) {
		pesan('Pengajuan ini sedang diproses dan tidak dapat diubah.', 'info');
		arahkan('/akun/pengajuan/' . $p['id']);
	}
	formulir_pengajuan($u, $p, $post);
}

function formulir_pengajuan(array $u, ?array $p, bool $post): void
{
	$revisi = $p && $p['status'] === 'PERLU_REVISI';
	$v = $p ?: ['judul' => '', 'subjudul' => '', 'penulis' => $u['nama'], 'kategori' => '', 'sinopsis' => '', 'perkiraan_halaman' => '', 'jumlah_cetak_diminta' => CETAK_MIN, 'jenis_karya' => 'asli'] + cetak_bawaan();
	if ($p) { // pengajuan lama yang belum punya spesifikasi cetak
		foreach (cetak_bawaan() as $k => $bawaan) {
			$v[$k] = isset(PILIHAN_CETAK[$k][1][$v[$k] ?? '']) ? $v[$k] : $bawaan;
		}
	}
	$galat = [];

	if ($post) {
		$v = array_merge($v, [
			'judul' => input('judul'),
			'subjudul' => input('subjudul'),
			'penulis' => trim(preg_replace("/\r\n?/", "\n", input('penulis'))),
			'kategori' => input('kategori'),
			'sinopsis' => input('sinopsis'),
			'perkiraan_halaman' => (int) input('perkiraan_halaman'),
			'jumlah_cetak_diminta' => max(0, (int) input('jumlah_cetak_diminta')),
			'jenis_karya' => isset(JENIS_KARYA[input('jenis_karya')]) ? input('jenis_karya') : 'asli',
		]);
		foreach (PILIHAN_CETAK as $k => [, $opsi]) {
			$v[$k] = isset($opsi[input($k)]) ? input($k) : array_key_first($opsi);
		}
		$kirim = ($_POST['aksi'] ?? '') === 'kirim';
		if (mb_strlen($v['judul']) < 3) {
			$galat[] = 'Isi judul buku.';
		}
		if ($kirim) {
			foreach (['penulis' => 'nama penulis', 'kategori' => 'kategori', 'sinopsis' => 'sinopsis'] as $k => $label) {
				if ($v[$k] === '') {
					$galat[] = "Isi $label.";
				}
			}
			if ($v['jumlah_cetak_diminta'] < CETAK_MIN) {
				$galat[] = 'Jumlah cetak minimal ' . CETAK_MIN . ' eksemplar (2 Perpusnas RI, 1 Perpusda, 1 arsip penerbit, 1 penulis).';
			}
		}

		if (!$galat) {
			$kolom_cetak = array_keys(PILIHAN_CETAK);
			$nilai_cetak = array_map(fn ($k) => $v[$k], $kolom_cetak);
			db()->beginTransaction();
			try {
				if ($p) {
					q('UPDATE pengajuan SET judul=?, subjudul=?, penulis=?, kategori=?, sinopsis=?, perkiraan_halaman=?, ' . implode('=?, ', $kolom_cetak) . '=?, jumlah_cetak_diminta=?, jenis_karya=?, diubah=? WHERE id=?',
						[$v['judul'], $v['subjudul'], $v['penulis'], $v['kategori'], $v['sinopsis'], $v['perkiraan_halaman'], ...$nilai_cetak, ...[$v['jumlah_cetak_diminta'], $v['jenis_karya'], sekarang(), $p['id']]]);
					$id = (int) $p['id'];
				} else {
					q('INSERT INTO pengajuan (pengguna_id, judul, subjudul, penulis, kategori, sinopsis, perkiraan_halaman, ' . implode(', ', $kolom_cetak) . ', jumlah_cetak_diminta, jenis_karya, status, dibuat, diubah) VALUES (' . implode(',', array_fill(0, 12 + count($kolom_cetak), '?')) . ')',
						[$u['id'], $v['judul'], $v['subjudul'], $v['penulis'], $v['kategori'], $v['sinopsis'], $v['perkiraan_halaman'], ...$nilai_cetak, ...[$v['jumlah_cetak_diminta'], $v['jenis_karya'], 'DRAF', sekarang(), sekarang()]]);
					$id = (int) db()->lastInsertId();
					catat($id, (int) $u['id'], 'Pengajuan dibuat.', true);
				}
				$baru_naskah = simpan_unggahan('naskah', 'naskah', $id);
				simpan_unggahan('pernyataan', 'pernyataan', $id);
				simpan_unggahan('sampul', 'sampul', $id);
				$tambahan = JENIS_KARYA[$v['jenis_karya']][1];
				if ($tambahan) {
					simpan_unggahan($tambahan, $tambahan, $id);
				}
				db()->commit();
			} catch (RuntimeException $ex) {
				db()->rollBack();
				$galat[] = $ex->getMessage();
				$id = $p ? (int) $p['id'] : 0;
			}
		}

		if (!$galat && $kirim) {
			$p = satu('SELECT * FROM pengajuan WHERE id = ?', [$id]);
			if (!berkas_terakhir($id, 'naskah')) {
				$galat[] = 'Unggah berkas naskah sebelum mengirim.';
			}
			if (!berkas_terakhir($id, 'pernyataan')) {
				$galat[] = 'Unggah surat pernyataan keaslian sebelum mengirim.';
			}
			$tambahan = JENIS_KARYA[$v['jenis_karya']][1];
			if ($tambahan && !berkas_terakhir($id, $tambahan)) {
				$galat[] = 'Unggah ' . mb_strtolower(JENIS_BERKAS[$tambahan][0]) . ' (wajib untuk ' . mb_strtolower(JENIS_KARYA[$v['jenis_karya']][0]) . ').';
			}
			if ($revisi && empty($baru_naskah)) {
				$galat[] = 'Unggah naskah hasil revisi sebelum mengirim ulang.';
			}
			if (!$galat) {
				if ($revisi) {
					ubah_status($p, 'DITINJAU', 'Naskah revisi dikirim.');
					pesan('Naskah revisi terkirim. Redaksi akan meninjaunya kembali.');
				} else {
					ubah_status($p, 'MENUNGGU_BAYAR_1', 'Pengajuan baru dikirim.');
					pesan('Pengajuan terkirim. Langkah berikutnya: bayar biaya awal.');
				}
				arahkan('/akun/pengajuan/' . $id);
			}
			// Data & berkas sudah tersimpan sebagai draf: lanjutkan di halaman ubah
			// agar pengiriman ulang tidak membuat pengajuan ganda.
			foreach ($galat as $g) {
				pesan($g, 'galat');
			}
			pesan('Data sudah tersimpan sebagai draf. Lengkapi kekurangannya lalu kirim lagi.', 'info');
			arahkan('/akun/pengajuan/' . $id . '/ubah');
		} elseif (!$galat) {
			pesan('Draf tersimpan.');
			arahkan('/akun/pengajuan/' . $id . '/ubah');
		}
	}

	$berkas_ada = '';
	if ($p) {
		foreach (['naskah', 'pernyataan', 'sampul', 'izin_terjemah', 'pengalihan', 'keterangan_pd'] as $j) {
			$b = berkas_terakhir((int) $p['id'], $j);
			$berkas_ada .= $b ? '<p class="berkas-ada">' . e(JENIS_BERKAS[$j][0]) . ' tersimpan: <a href="' . e(url('/berkas/' . $b['id'])) . '">' . e($b['nama_asli']) . '</a></p>' : '';
		}
	}
	$pilihan_cetak = '';
	foreach (PILIHAN_CETAK as $k => [$judul, $opsi]) {
		$pilihan_cetak .= pilih_kartu($k, $judul, $opsi, (string) $v[$k]);
	}
	$kategori = array_column(semua("SELECT DISTINCT kategori FROM buku WHERE kategori <> '' ORDER BY kategori"), 'kategori');
	$daftar_kategori = implode('', array_map(fn ($k) => '<option value="' . e($k) . '">', array_unique(array_merge($kategori, ['Pendidikan', 'Metodologi Penelitian', 'Matematika dan Statistika', 'Pendidikan Digital', 'Sosial dan Humaniora', 'Agama', 'Kesehatan', 'Ekonomi dan Manajemen']))));
	$daftar_galat = $galat ? '<ul class="pesan pesan--galat" role="alert">' . implode('', array_map(fn ($g) => '<li>' . e($g) . '</li>', $galat)) . '</ul>' : '';
	$judul = $revisi ? 'Kirim Naskah Revisi' : ($p ? 'Lengkapi Pengajuan' : 'Ajukan Buku Baru');

	$isi = '<section class="panel">
	<h1>' . e($judul) . '</h1>
	' . ($revisi ? '<p class="pesan pesan--info">Perbaiki data bila perlu, lalu unggah <strong>naskah hasil revisi</strong> dan tekan Kirim Revisi.</p>' : '<p class="catatan">Isi data buku dan unggah berkasnya. Anda bisa menyimpan draf dan melanjutkannya nanti. Setelah dikirim, Anda akan diminta membayar biaya awal ' . rupiah(pengaturan('biaya_awal', '250000')) . '.</p>') . '
	' . $daftar_galat . '
	<form method="post" enctype="multipart/form-data" class="formulir formulir--lebar">' . csrf_field() . '
		<fieldset><legend>Data buku</legend>
			<label for="judul">Judul buku *</label>
			<input id="judul" name="judul" required maxlength="255" value="' . e($v['judul']) . '">
			<label for="subjudul">Subjudul</label>
			<input id="subjudul" name="subjudul" maxlength="255" value="' . e($v['subjudul']) . '">
			<label for="penulis">Nama penulis beserta gelar * <span class="catatan">(satu nama per baris)</span></label>
			<textarea id="penulis" name="penulis" rows="3">' . e($v['penulis']) . '</textarea>
			<div class="kolom-2">
				<div><label for="kategori">Kategori *</label><input id="kategori" name="kategori" list="daftar-kategori" value="' . e($v['kategori']) . '"><datalist id="daftar-kategori">' . $daftar_kategori . '</datalist></div>
				<div><label for="perkiraan_halaman">Perkiraan jumlah halaman</label><input type="number" min="0" id="perkiraan_halaman" name="perkiraan_halaman" value="' . e($v['perkiraan_halaman'] ?: '') . '"></div>
			</div>
			<label for="sinopsis">Sinopsis / deskripsi buku *</label>
			<textarea id="sinopsis" name="sinopsis" rows="6">' . e($v['sinopsis']) . '</textarea>
		</fieldset>
		<fieldset><legend>Spesifikasi cetak</legend>
			<p class="catatan">Pilih spesifikasi buku cetak Anda. Gambar di setiap pilihan adalah ilustrasi untuk membantu membedakannya. Biaya pelunasan dihitung redaksi berdasarkan pilihan ini dan jumlah eksemplar.</p>
			' . $pilihan_cetak . '
			<label for="jumlah_cetak_diminta">Jumlah eksemplar dicetak * <span class="catatan">(minimal ' . CETAK_MIN . ')</span></label>
			<input type="number" min="' . CETAK_MIN . '" id="jumlah_cetak_diminta" name="jumlah_cetak_diminta" class="input-pendek" value="' . e($v['jumlah_cetak_diminta'] ?: '') . '">
			<div class="rincian-wajib">
				<p><strong>Minimal ' . CETAK_MIN . ' eksemplar</strong>, karena buku ber-ISBN wajib diserahkan sebagai berikut:</p>
				<ul>
					<li><span>2</span> Perpustakaan Nasional RI</li>
					<li><span>1</span> Perpustakaan Daerah (Perpusda)</li>
					<li><span>1</span> Arsip penerbit (Literaindo Press)</li>
					<li><span>1</span> Untuk penulis</li>
				</ul>
				<p class="catatan">Jumlah di atas ' . CETAK_MIN . ' eksemplar menjadi tambahan untuk penulis.</p>
			</div>
		</fieldset>
		<fieldset><legend>Berkas</legend>
			' . $berkas_ada . '
			<label for="naskah">' . ($revisi ? 'Naskah hasil revisi *' : 'Naskah *') . ' <span class="catatan">(PDF/DOC/DOCX, maks. 30 MB)</span></label>
			<input type="file" id="naskah" name="naskah" accept=".pdf,.doc,.docx">
			<label for="pernyataan">Surat pernyataan keaslian naskah * <span class="catatan">(PDF/JPG/PNG, maks. 5 MB)</span></label>
			<p class="contoh-unduh">' . ikon('unduh') . '<span>Belum punya suratnya? Unduh contoh: <a href="' . e(url('/assets/unduh/surat-pernyataan-keaslian-naskah.docx')) . '" download>Word (bisa diisi)</a> · <a href="' . e(url('/assets/unduh/surat-pernyataan-keaslian-naskah.pdf')) . '" download>PDF (untuk dicetak)</a>. Format resmi Perpusnas. Isi data Anda, tanda tangani kolom <strong>Penulis</strong> di atas meterai Rp10.000 (setiap penulis menandatangani), lalu pindai/foto dan unggah di sini. Kolom Penanggung jawab Penerbit akan ditandatangani oleh Literaindo Press.</span></p>
			<input type="file" id="pernyataan" name="pernyataan" accept=".pdf,.jpg,.jpeg,.png">
			<label for="jenis_karya">Jenis karya *</label>
			<select id="jenis_karya" name="jenis_karya" data-jenis-karya>' . implode('', array_map(fn ($k, $j) => '<option value="' . $k . '"' . ($k === $v['jenis_karya'] ? ' selected' : '') . '>' . e($j[0]) . '</option>', array_keys(JENIS_KARYA), JENIS_KARYA)) . '</select>
			' . implode('', array_map(fn ($k, $j) => $j[1] ? '<div class="berkas-tambahan" data-untuk="' . $k . '"' . ($k === $v['jenis_karya'] ? '' : ' hidden') . '><label for="' . $j[1] . '">' . e(JENIS_BERKAS[$j[1]][0]) . ' * <span class="catatan">(PDF/JPG/PNG, maks. 5 MB)</span></label><p class="catatan">' . e($j[2]) . '</p><input type="file" id="' . $j[1] . '" name="' . $j[1] . '" accept=".pdf,.jpg,.jpeg,.png"></div>' : '', array_keys(JENIS_KARYA), JENIS_KARYA)) . '
			<label for="sampul">Desain sampul <span class="catatan">(opsional, JPG/PNG/WebP, maks. 10 MB)</span></label>
			<input type="file" id="sampul" name="sampul" accept=".jpg,.jpeg,.png,.webp">
		</fieldset>
		<div class="formulir__tombol">
			<button type="submit" name="aksi" value="kirim" class="tombol">' . ($revisi ? 'Kirim Revisi' : 'Kirim Pengajuan') . '</button>
			' . ($revisi ? '' : '<button type="submit" name="aksi" value="draf" class="tombol tombol--kedua">Simpan Draf</button>') . '
		</div>
	</form>
</section>';
	$isi .= '<script>document.querySelectorAll("[data-jenis-karya]").forEach(function(s){function f(){document.querySelectorAll(".berkas-tambahan").forEach(function(d){d.hidden=d.dataset.untuk!==s.value;});}s.addEventListener("change",f);f();});</script>';
	tampil($judul, $isi, ['panel' => menu_pengaju($p ? '' : '/akun/pengajuan/baru')]);
}

/** Pilihan bergambar (radio) untuk spesifikasi cetak. */
function pilih_kartu(string $nama, string $judul, array $opsi, string $terpilih): string
{
	$out = '<fieldset class="pilih-kartu"><legend>' . e($judul) . ' *</legend><div class="pilih-kartu__grid">';
	foreach ($opsi as $nilai => [$label, $ket, $gambar]) {
		$out .= '<label class="kartu-opsi"><input type="radio" name="' . e($nama) . '" value="' . e((string) $nilai) . '"' . ((string) $nilai === $terpilih ? ' checked' : '') . '>'
			. '<img src="' . e(url('/assets/img/cetak/' . $gambar . '.svg')) . '" alt="" width="160" height="120" loading="lazy">'
			. '<strong>' . e($label) . '</strong><span>' . e($ket) . '</span></label>';
	}
	return $out . '</div></fieldset>';
}

function stepper(string $status): string
{
	$posisi = 0;
	foreach (TAHAP_TAMPIL as $i => [, $daftar]) {
		if (in_array($status, $daftar, true)) {
			$posisi = $i;
		}
	}
	if ($status === 'SELESAI') {
		$posisi = count(TAHAP_TAMPIL);
	}
	$out = '<ol class="stepper">';
	foreach (TAHAP_TAMPIL as $i => [$label]) {
		$kelas = $i < $posisi ? 'selesai' : ($i === $posisi ? 'sekarang' : '');
		$out .= '<li class="' . $kelas . '"' . ($i === $posisi ? ' aria-current="step"' : '') . '>' . e($label) . '</li>';
	}
	return $out . '</ol>';
}

function daftar_berkas(int $id): string
{
	$out = '';
	foreach (semua('SELECT * FROM berkas WHERE pengajuan_id = ? ORDER BY id DESC', [$id]) as $b) {
		$out .= '<li><a href="' . e(url('/berkas/' . $b['id'])) . '">' . ikon('unduh') . e(JENIS_BERKAS[$b['jenis']][0] ?? $b['jenis']) . '</a> <span class="catatan">' . e($b['nama_asli']) . ' · ' . ukuran_berkas((int) $b['ukuran']) . ' · ' . e(tanggal($b['dibuat'])) . '</span></li>';
	}
	return $out ? '<ul class="daftar-berkas">' . $out . '</ul>' : '<p class="catatan">Belum ada berkas.</p>';
}

function riwayat(int $id): string
{
	$out = '';
	foreach (semua('SELECT c.*, u.nama, u.peran FROM catatan c LEFT JOIN pengguna u ON u.id = c.pengguna_id WHERE c.pengajuan_id = ? ORDER BY c.id DESC', [$id]) as $c) {
		$siapa = $c['sistem'] ? 'Sistem' : (['admin' => 'Admin Literaindo', 'keuangan' => 'Keuangan Literaindo'][$c['peran']] ?? $c['nama']);
		$out .= '<li class="' . ($c['sistem'] ? 'riwayat--sistem' : ($c['peran'] === 'admin' ? 'riwayat--admin' : '')) . '"><span class="riwayat__siapa">' . e((string) $siapa) . ' · ' . e(tanggal($c['dibuat'])) . '</span><p>' . nl2br(e($c['isi'])) . '</p></li>';
	}
	return '<ul class="riwayat">' . $out . '</ul>';
}

function form_catatan(): string
{
	return '<form method="post" class="formulir formulir--catatan">' . csrf_field() . '<input type="hidden" name="aksi" value="catatan"><label for="isi-catatan">Kirim pesan</label><textarea id="isi-catatan" name="isi" rows="3" required></textarea><button type="submit" class="tombol tombol--kedua">Kirim Pesan</button></form>';
}

function form_bukti(int $tahap): string
{
	return '<form method="post" enctype="multipart/form-data" class="formulir">' . csrf_field() . '
		<input type="hidden" name="aksi" value="bukti">
		<label for="bukti">Unggah bukti pembayaran <span class="catatan">(foto/screenshot JPG/PNG atau PDF, maks. 5 MB)</span></label>
		<input type="file" id="bukti" name="bukti" accept=".jpg,.jpeg,.png,.pdf" required>
		<button type="submit" class="tombol">Kirim Bukti Bayar</button>
	</form>';
}

function pembayaran_ditolak(int $id, int $tahap): string
{
	$b = satu('SELECT * FROM pembayaran WHERE pengajuan_id = ? AND tahap = ? ORDER BY id DESC LIMIT 1', [$id, $tahap]);
	return $b && $b['status'] === 'ditolak' ? '<p class="pesan pesan--galat">Bukti bayar sebelumnya ditolak: ' . e($b['catatan'] ?: 'tidak sesuai') . '. Silakan unggah ulang.</p>' : '';
}

function pengaju_detail(bool $post, string $id): void
{
	$u = wajib_masuk();
	if ($u['peran'] === 'admin') {
		arahkan('/admin/pengajuan/' . (int) $id);
	}
	if ($u['peran'] !== 'pengaju') {
		arahkan(beranda_peran($u['peran']));
	}
	$p = pengajuan_milik((int) $id, $u);
	$pid = (int) $p['id'];

	if ($post) {
		$aksi = $_POST['aksi'] ?? '';
		if ($aksi === 'catatan' && input('isi') !== '') {
			catat($pid, (int) $u['id'], mb_substr(input('isi'), 0, 3000));
			notif_pengajuan($p, 'Pesan baru: ' . mb_substr(input('isi'), 0, 200));
			q('UPDATE pengajuan SET diubah = ? WHERE id = ?', [sekarang(), $pid]);
			pesan('Pesan terkirim ke admin.');
		} elseif ($aksi === 'bukti' && in_array($p['status'], ['MENUNGGU_BAYAR_1', 'MENUNGGU_BAYAR_2'], true)) {
			$tahap = $p['status'] === 'MENUNGGU_BAYAR_1' ? 1 : 2;
			try {
				$bid = simpan_unggahan('bukti', 'bukti_' . $tahap, $pid);
				if (!$bid) {
					throw new RuntimeException('Pilih berkas bukti pembayaran.');
				}
				$nominal = $tahap === 1 ? (int) pengaturan('biaya_awal', '250000') : (int) $p['tagihan_2'];
				q('INSERT INTO pembayaran (pengajuan_id, tahap, nominal, berkas_id, status, dibuat) VALUES (?, ?, ?, ?, ?, ?)', [$pid, $tahap, $nominal, $bid, 'menunggu', sekarang()]);
				ubah_status($p, 'VERIFIKASI_BAYAR_' . $tahap, 'Bukti bayar ' . rupiah($nominal) . ' diunggah.');
				notif_keuangan('Bukti bayar ' . (TAHAP_BAYAR[$tahap] ?? '') . ' ' . rupiah($nominal) . ' dari ' . $u['nama'] . ' ("' . $p['judul'] . '") perlu diverifikasi.');
				pesan('Bukti bayar terkirim. Bagian keuangan akan memverifikasinya.');
			} catch (RuntimeException $ex) {
				pesan($ex->getMessage(), 'galat');
			}
		}
		arahkan('/akun/pengajuan/' . $pid);
	}

	$biaya_awal = (int) pengaturan('biaya_awal', '250000');
	$buku = $p['buku_id'] ? satu('SELECT * FROM buku WHERE id = ?', [$p['buku_id']]) : null;
	$langkah = match ($p['status']) {
		'DRAF' => '<p>Lengkapi data dan berkas, lalu kirim pengajuan.</p><p><a class="tombol" href="' . e(url("/akun/pengajuan/$pid/ubah")) . '">Lengkapi &amp; Kirim</a></p>',
		'MENUNGGU_BAYAR_1' => pembayaran_ditolak($pid, 1) . '<div class="tagihan"><div><p>Biaya awal penerbitan</p><p class="tagihan__nominal">' . rupiah($biaya_awal) . '</p><p class="catatan">Pindai QRIS dari aplikasi bank atau dompet digital apa pun, bayar tepat sesuai nominal, lalu unggah bukti bayarnya. Biaya ini tidak dapat dikembalikan.</p>' . form_bukti(1) . '</div>' . blok_qris($biaya_awal) . '</div>',
		'VERIFIKASI_BAYAR_1', 'VERIFIKASI_BAYAR_2' => '<p>Bukti pembayaran Anda sedang dicek bagian keuangan, biasanya dalam 1×24 jam kerja.</p>',
		'DITINJAU' => '<p>Naskah sedang diproses redaksi: penyuntingan, tata letak, dan desain sampul. Admin akan menghubungi Anda bila ada catatan.</p>',
		'PERLU_REVISI' => '<p>Redaksi meminta perbaikan naskah. Baca catatan di bagian Riwayat, lalu kirim naskah revisi.</p><p><a class="tombol" href="' . e(url("/akun/pengajuan/$pid/ubah")) . '">Kirim Naskah Revisi</a></p>',
		'PROSES_ISBN' => '<p>Naskah selesai diproses dan sedang diajukan ISBN ke Perpustakaan Nasional.</p>' . ($buku ? '<p>Halaman buku Anda: <a href="' . e(url(buku_url($buku))) . '">' . e(url_absolut(buku_url($buku))) . '</a></p>' : '')
			. ($p['tagihan_2'] ? '<p class="catatan">Tagihan pelunasan ' . rupiah($p['tagihan_2']) . ' dibayar setelah ISBN terbit.</p>' : ''),
		'MENUNGGU_BAYAR_2' => pembayaran_ditolak($pid, 2) . '<div class="tagihan"><div><p>Pelunasan biaya cetak</p><p class="tagihan__nominal">' . rupiah($p['tagihan_2']) . '</p><p class="catatan">' . e($p['jumlah_cetak'] . ' eksemplar' . ($p['rincian_tagihan_2'] ? ' · ' . $p['rincian_tagihan_2'] : '')) . '</p><p class="catatan">Spesifikasi: ' . e(ringkas_cetak($p)) . '</p>' . form_bukti(2) . '</div>' . blok_qris((int) $p['tagihan_2']) . '</div>',
		'LUNAS' => '<p>Pembayaran lunas. Buku Anda masuk antrean cetak.</p>',
		'SELESAI' => '<p>Buku sudah dicetak dan dikirim.' . ($p['resi'] ? ' Nomor resi: <strong>' . e($p['resi']) . '</strong>' : '') . '</p>' . ($buku ? '<p>Halaman buku: <a href="' . e(url(buku_url($buku))) . '">' . e(url_absolut(buku_url($buku))) . '</a></p>' : ''),
		'DITOLAK' => '<p>Pengajuan ini tidak dapat diproses. Alasannya ada di bagian Riwayat.</p>',
		default => '',
	};

	$isbn = $buku && $buku['isbn'] ? '<div><dt>ISBN</dt><dd>' . e($buku['isbn']) . '</dd></div>' : '';
	$isi = '<section class="panel">
	<p><a href="' . e(url('/akun')) . '">← Pengajuan Saya</a></p>
	<div class="judul-baris"><h1>' . e($p['judul']) . '</h1>' . label_status($p['status']) . '</div>
	' . ($p['status'] !== 'DITOLAK' ? stepper($p['status']) : '') . '
	<div class="kotak-langkah"><h2>Langkah berikutnya</h2>' . $langkah . '</div>
	<div class="dua-kolom">
		<section><h2>Data buku</h2><dl class="data-ringkas">
			<div><dt>Penulis</dt><dd>' . nl2br(e($p['penulis'])) . '</dd></div>
			<div><dt>Kategori</dt><dd>' . e($p['kategori']) . '</dd></div>
			' . data_cetak($p) . '
			<div><dt>Jumlah cetak</dt><dd>' . e((string) ($p['jumlah_cetak'] ?: $p['jumlah_cetak_diminta'])) . ' eksemplar</dd></div>' . $isbn . '
		</dl>
		<h2>Berkas</h2>' . daftar_berkas($pid) . '</section>
		<section><h2>Riwayat &amp; pesan</h2>' . form_catatan() . riwayat($pid) . '</section>
	</div>
</section>';
	tampil($p['judul'], $isi, ['panel' => menu_pengaju('')]);
}

function pengaju_profil(bool $post): void
{
	$u = wajib_masuk();
	if ($post) {
		$nama = input('nama');
		$wa = preg_replace('/[^0-9+]/', '', input('whatsapp'));
		if (mb_strlen($nama) >= 3) {
			q('UPDATE pengguna SET nama = ?, whatsapp = ? WHERE id = ?', [$nama, $wa, $u['id']]);
		}
		$lama = (string) ($_POST['sandi_lama'] ?? '');
		$baru = (string) ($_POST['sandi_baru'] ?? '');
		if ($baru !== '') {
			if (!password_verify($lama, $u['sandi'])) {
				pesan('Kata sandi lama salah. Kata sandi tidak diubah.', 'galat');
				arahkan('/akun/profil');
			}
			if (strlen($baru) < 8) {
				pesan('Kata sandi baru minimal 8 karakter.', 'galat');
				arahkan('/akun/profil');
			}
			q('UPDATE pengguna SET sandi = ? WHERE id = ?', [password_hash($baru, PASSWORD_DEFAULT), $u['id']]);
		}
		pesan('Profil tersimpan.');
		arahkan('/akun/profil');
	}
	$isi = '<section class="panel panel--sempit"><h1>Profil</h1>
	<form method="post" class="formulir">' . csrf_field() . '
		<label for="nama">Nama lengkap</label><input id="nama" name="nama" value="' . e($u['nama']) . '" required>
		<label for="email">Email</label><input id="email" value="' . e($u['email']) . '" disabled>
		<label for="whatsapp">Nomor WhatsApp</label><input id="whatsapp" name="whatsapp" value="' . e($u['whatsapp']) . '">
		<fieldset><legend>Ganti kata sandi (opsional)</legend>
			<label for="sandi_lama">Kata sandi lama</label><input type="password" id="sandi_lama" name="sandi_lama" autocomplete="current-password">
			<label for="sandi_baru">Kata sandi baru</label><input type="password" id="sandi_baru" name="sandi_baru" minlength="8" autocomplete="new-password">
		</fieldset>
		<button type="submit" class="tombol">Simpan</button>
	</form></section>';
	tampil('Profil', $isi, ['panel' => menu_peran($u, '/akun/profil')]);
}

function unduh_berkas(bool $post, string $id): void
{
	$u = wajib_masuk();
	$b = satu('SELECT b.*, p.pengguna_id FROM berkas b JOIN pengajuan p ON p.id = b.pengajuan_id WHERE b.id = ?', [(int) $id]);
	$boleh = $b && ($u['peran'] === 'admin'
		|| (int) $b['pengguna_id'] === (int) $u['id']
		|| ($u['peran'] === 'keuangan' && in_array($b['jenis'], ['bukti_1', 'bukti_2'], true)));
	if (!$boleh) {
		tidak_ditemukan();
	}
	$inline = in_array(strtolower(pathinfo($b['path'], PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp', 'pdf'], true);
	kirim_berkas($b['path'], $b['nama_asli'], $inline);
}
