<?php
// Pengajuan HKI (pencatatan hak cipta di DJKI).
// Dua jalur: layanan HKI saja (berbayar) dan HKI yang termasuk Paket Pustaka
// (terhubung ke pengajuan buku, tanpa biaya tambahan).

declare(strict_types=1);

const STATUS_HKI = [
	'DRAF'             => ['Draf', 'Formulir belum dikirim', 'pengaju'],
	'MENUNGGU_BAYAR'   => ['Menunggu pembayaran', 'Silakan bayar biaya layanan HKI', 'pengaju'],
	'VERIFIKASI_BAYAR' => ['Verifikasi pembayaran', 'Bukti bayar sedang dicek bagian keuangan', 'keuangan'],
	'DIPERIKSA'        => ['Berkas diperiksa', 'Admin sedang memeriksa data dan berkas Anda', 'admin'],
	'PERLU_PERBAIKAN'  => ['Perlu perbaikan', 'Ada catatan dari admin; perbaiki data atau berkas lalu kirim ulang', 'pengaju'],
	'DIAJUKAN'         => ['Diajukan ke DJKI', 'Permohonan sudah didaftarkan ke DJKI, menunggu sertifikat terbit', 'djki'],
	'SELESAI'          => ['Sertifikat terbit', 'Sertifikat hak cipta sudah bisa diunduh', ''],
	'DITOLAK'          => ['Ditolak', 'Permohonan tidak dapat diproses', ''],
];

const JENIS_CIPTAAN = [
	'buku'         => 'Buku',
	'ebook'        => 'Buku elektronik (e-book)',
	'modul'        => 'Modul, diktat, atau bahan ajar',
	'panduan'      => 'Buku panduan atau petunjuk',
	'karya_ilmiah' => 'Karya ilmiah, artikel, atau jurnal',
	'novel'        => 'Novel atau cerita',
	'puisi'        => 'Puisi atau antologi',
	'program'      => 'Program komputer atau aplikasi',
	'seni'         => 'Gambar, ilustrasi, atau seni rupa',
	'musik'        => 'Lagu atau musik',
	'video'        => 'Video atau film',
	'foto'         => 'Fotografi',
	'poster'       => 'Poster, infografis, atau desain',
	'lainnya'      => 'Lainnya',
];

const JENIS_BERKAS_HKI = [
	'ktp'        => ['KTP seluruh pencipta', ['pdf', 'jpg', 'jpeg', 'png'], 10],
	'pernyataan' => ['Surat pernyataan hak cipta', ['pdf', 'jpg', 'jpeg', 'png'], 5],
	'contoh'     => ['Contoh ciptaan', ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'], 30],
	'pengalihan' => ['Surat pengalihan hak cipta', ['pdf', 'jpg', 'jpeg', 'png'], 5],
	'bukti'      => ['Bukti bayar layanan HKI', ['jpg', 'jpeg', 'png', 'pdf'], 5],
	'sertifikat' => ['Sertifikat hak cipta', ['pdf', 'jpg', 'jpeg', 'png'], 10],
];

const MAKS_PENCIPTA = 10;
const KOLOM_PENCIPTA = ['nama' => 'Nama lengkap', 'nik' => 'NIK', 'kewarganegaraan' => 'Kewarganegaraan', 'alamat' => 'Alamat lengkap', 'kode_pos' => 'Kode pos', 'email' => 'Email', 'hp' => 'Nomor HP/WhatsApp'];

function harga_hki(): int
{
	return (int) pengaturan('harga_hki', '250000');
}

function label_hki(string $s): string
{
	$giliran = STATUS_HKI[$s][2] ?? '';
	$jenis = $giliran === 'pengaju' ? 'giliran' : ($s === 'SELESAI' ? 'selesai' : ($s === 'DITOLAK' ? 'tolak' : 'proses'));
	return '<span class="lencana lencana--' . $jenis . '">' . e(STATUS_HKI[$s][0] ?? $s) . '</span>';
}

function no_hki(array $h): string
{
	return 'HKI-' . str_pad((string) $h['id'], 4, '0', STR_PAD_LEFT);
}

function pencipta_hki(array $h): array
{
	$d = json_decode((string) ($h['pencipta'] ?? ''), true);
	return is_array($d) ? $d : [];
}

function pemegang_hki(array $h): array
{
	$d = json_decode((string) ($h['pemegang'] ?? ''), true);
	return is_array($d) ? $d : [];
}

/** HKI aktif (bukan ditolak) yang terhubung ke pengajuan buku. */
function hki_pengajuan(int $pengajuan_id): ?array
{
	return satu("SELECT * FROM hki WHERE pengajuan_id = ? AND status <> 'DITOLAK' ORDER BY id DESC LIMIT 1", [$pengajuan_id]);
}

function hki_milik(int $id, array $u): array
{
	$h = satu('SELECT * FROM hki WHERE id = ?', [$id]);
	if (!$h || (int) $h['pengguna_id'] !== (int) $u['id']) {
		tidak_ditemukan();
	}
	return $h;
}

function simpan_unggahan_hki(string $field, string $jenis, int $hki_id): ?int
{
	$hasil = pindahkan_unggahan($field, JENIS_BERKAS_HKI[$jenis], 'hki/' . $hki_id, $jenis);
	if (!$hasil) {
		return null;
	}
	$u = pengguna();
	q('INSERT INTO hki_berkas (hki_id, jenis, path, nama_asli, ukuran, pengunggah_id, dibuat) VALUES (?, ?, ?, ?, ?, ?, ?)',
		[$hki_id, $jenis, $hasil['path'], $hasil['nama_asli'], $hasil['ukuran'], $u ? $u['id'] : null, sekarang()]);
	return (int) db()->lastInsertId();
}

function berkas_hki(int $hki_id, string $jenis): ?array
{
	return satu('SELECT * FROM hki_berkas WHERE hki_id = ? AND jenis = ? ORDER BY id DESC LIMIT 1', [$hki_id, $jenis]);
}

function catat_hki(int $hki_id, ?int $uid, string $isi, bool $sistem = false): void
{
	q('INSERT INTO hki_catatan (hki_id, pengguna_id, isi, sistem, dibuat) VALUES (?, ?, ?, ?, ?)', [$hki_id, $uid, $isi, $sistem ? 1 : 0, sekarang()]);
}

/** Beri tahu pihak lawan: admin bila pemohon yang bertindak, pemohon bila staf yang bertindak. */
function notif_hki(array $h, string $pesan): void
{
	$u = pengguna();
	if ($u && $u['peran'] === 'pengaju') {
		notif_admin('HKI "' . $h['judul'] . '" (' . $u['nama'] . '): ' . $pesan, '/admin/hki/' . $h['id']);
	} else {
		notif((int) $h['pengguna_id'], 'HKI "' . $h['judul'] . '": ' . $pesan, '/akun/hki/' . $h['id']);
	}
}

function ubah_status_hki(array $h, string $baru, ?string $keterangan = null): void
{
	$u = pengguna();
	q('UPDATE hki SET status = ?, diubah = ? WHERE id = ?', [$baru, sekarang(), $h['id']]);
	$teks = STATUS_HKI[$baru][0] . ($keterangan ? '. ' . $keterangan : '');
	catat_hki((int) $h['id'], $u ? (int) $u['id'] : null, 'Status: ' . $teks, true);
	notif_hki($h, $teks);
	$pemilik = satu('SELECT * FROM pengguna WHERE id = ?', [$h['pengguna_id']]);
	if ($pemilik) {
		kirim_email(
			$pemilik['email'],
			'Status HKI "' . $h['judul'] . '": ' . STATUS_HKI[$baru][0],
			"Halo {$pemilik['nama']},\n\nStatus permohonan HKI \"{$h['judul']}\" sekarang: " . STATUS_HKI[$baru][0] . ".\n" . STATUS_HKI[$baru][1] . ".\n"
			. ($keterangan ? "\nCatatan: $keterangan\n" : '')
			. "\nLihat detailnya di: " . url_absolut('/akun/hki/' . $h['id']) . "\n\nSalam,\nLiteraindo Press"
		);
	}
}

/** Terima atau tolak bukti bayar layanan HKI (keuangan, cadangan admin). */
function verifikasi_hki(array $h, bool $terima, string $catatan = ''): void
{
	if ($h['status'] !== 'VERIFIKASI_BAYAR') {
		throw new RuntimeException('Pembayaran HKI ini sudah diproses.');
	}
	$u = pengguna();
	if ($terima) {
		q("UPDATE hki SET bayar_status = 'diterima', diverifikasi = ?, pemeriksa_id = ? WHERE id = ?", [sekarang(), $u['id'] ?? null, $h['id']]);
		ubah_status_hki($h, 'DIPERIKSA', 'Pembayaran ' . rupiah($h['biaya']) . ' diterima.');
		notif_admin('HKI "' . $h['judul'] . '": pembayaran diterima. Berkas siap diperiksa.', '/admin/hki/' . $h['id']);
	} else {
		if (trim($catatan) === '') {
			throw new RuntimeException('Tulis alasan penolakan bukti bayar.');
		}
		q("UPDATE hki SET bayar_status = 'ditolak', catatan_bayar = ?, diverifikasi = ?, pemeriksa_id = ? WHERE id = ?", [$catatan, sekarang(), $u['id'] ?? null, $h['id']]);
		ubah_status_hki($h, 'MENUNGGU_BAYAR', 'Bukti bayar ditolak: ' . $catatan);
	}
}

// ---------------------------------------------------------------------------
// Pemohon (akun penulis)

function hki_daftar(bool $post): void
{
	$u = wajib_masuk();
	if ($u['peran'] !== 'pengaju') {
		arahkan($u['peran'] === 'admin' ? '/admin/hki' : beranda_peran($u['peran']));
	}
	$daftar = semua('SELECT * FROM hki WHERE pengguna_id = ? ORDER BY diubah DESC', [$u['id']]);
	$pengantar = '<p class="catatan">Daftarkan hak cipta karya Anda ke Direktorat Jenderal Kekayaan Intelektual (DJKI). Biaya layanan ' . rupiah(harga_hki()) . ', sudah termasuk biaya pendaftaran. Untuk buku yang terbit dengan <strong>Paket Pustaka</strong>, HKI sudah termasuk paket: isi datanya dari halaman pengajuan buku Anda.</p>';
	if (!$daftar) {
		$isi = '<section class="panel kosong"><h1>Pengajuan HKI</h1>' . $pengantar . '<p><a class="tombol" href="' . e(url('/akun/hki/baru')) . '">Ajukan HKI</a></p></section>';
	} else {
		$baris = '';
		foreach ($daftar as $h) {
			$giliran = (STATUS_HKI[$h['status']][2] ?? '') === 'pengaju';
			$baris .= '<li class="daftar-pengajuan__item' . ($giliran ? ' perlu-tindakan' : '') . '">
				<a href="' . e(url('/akun/hki/' . $h['id'])) . '"><strong>' . e($h['judul']) . '</strong></a>
				' . label_hki($h['status']) . '
				<span class="catatan">' . e(no_hki($h)) . ' · ' . e(JENIS_CIPTAAN[$h['jenis_ciptaan']] ?? $h['jenis_ciptaan']) . ($h['pengajuan_id'] ? ' · dari pengajuan buku' : '') . ' · diperbarui ' . e(tanggal($h['diubah'])) . '</span>
			</li>';
		}
		$isi = '<section class="panel"><div class="judul-baris"><h1>Pengajuan HKI</h1><a class="tombol" href="' . e(url('/akun/hki/baru')) . '">Ajukan HKI</a></div>' . $pengantar . '<ul class="daftar-pengajuan">' . $baris . '</ul></section>';
	}
	tampil('Pengajuan HKI', $isi, ['panel' => menu_pengaju('/akun/hki')]);
}

function hki_baru(bool $post): void
{
	$u = wajib_masuk();
	if ($u['peran'] !== 'pengaju') {
		pesan('Pengajuan HKI dibuat dari akun penulis.', 'info');
		arahkan(beranda_peran($u['peran']));
	}
	$pj = null;
	$pid = (int) ($_GET['pengajuan'] ?? 0);
	if ($pid) {
		$pj = satu('SELECT * FROM pengajuan WHERE id = ? AND pengguna_id = ?', [$pid, $u['id']]);
		if (!$pj || in_array($pj['status'], ['DRAF', 'DITOLAK'], true)) {
			pesan('HKI untuk buku bisa diajukan setelah pengajuan buku dikirim.', 'info');
			arahkan($pj ? '/akun/pengajuan/' . $pid : '/akun/hki');
		}
		$ada = hki_pengajuan($pid);
		if ($ada) {
			arahkan('/akun/hki/' . $ada['id']);
		}
	}
	formulir_hki($u, null, $pj, $post);
}

function hki_ubah(bool $post, string $id): void
{
	$u = wajib_masuk('pengaju');
	$h = hki_milik((int) $id, $u);
	if (!in_array($h['status'], ['DRAF', 'PERLU_PERBAIKAN'], true)) {
		pesan('Permohonan ini sedang diproses dan tidak dapat diubah.', 'info');
		arahkan('/akun/hki/' . $h['id']);
	}
	$pj = $h['pengajuan_id'] ? satu('SELECT * FROM pengajuan WHERE id = ?', [$h['pengajuan_id']]) : null;
	formulir_hki($u, $h, $pj, $post);
}

/** Baca daftar pencipta dari formulir (pencipta[i][kolom]). */
function baca_pencipta(): array
{
	$out = [];
	foreach (array_slice((array) ($_POST['pencipta'] ?? []), 0, MAKS_PENCIPTA) as $baris) {
		if (!is_array($baris)) {
			continue;
		}
		$r = [];
		foreach (array_keys(KOLOM_PENCIPTA) as $k) {
			$r[$k] = mb_substr(trim((string) ($baris[$k] ?? '')), 0, $k === 'alamat' ? 500 : 150);
		}
		$r['nik'] = preg_replace('/\D/', '', $r['nik']);
		$r['hp'] = preg_replace('/[^0-9+]/', '', $r['hp']);
		if (implode('', array_diff_key($r, ['kewarganegaraan' => 1])) !== '') {
			$out[] = $r;
		}
	}
	return $out;
}

function formulir_hki(array $u, ?array $h, ?array $pj, bool $post): void
{
	$perbaikan = $h && $h['status'] === 'PERLU_PERBAIKAN';
	$termasuk_paket = $pj && ($pj['paket'] ?? '') === 'pustaka';
	$v = $h ?: [
		'jenis_ciptaan' => 'buku',
		'judul' => $pj ? trim($pj['judul'] . ($pj['subjudul'] ? ': ' . $pj['subjudul'] : '')) : '',
		'uraian' => $pj['sinopsis'] ?? '',
		'tanggal_umum' => '',
		'tempat_umum' => '',
		'tautan_contoh' => '',
	];
	$pencipta = $h ? pencipta_hki($h) : [];
	$pemegang = $h ? pemegang_hki($h) : [];
	if (!$pencipta) {
		$nama_awal = $pj ? array_filter(array_map('trim', explode("\n", (string) $pj['penulis']))) : [$u['nama']];
		foreach ($nama_awal ?: [$u['nama']] as $i => $nama) {
			$pencipta[] = ['nama' => $nama, 'nik' => '', 'kewarganegaraan' => 'Indonesia', 'alamat' => '', 'kode_pos' => '', 'email' => $i === 0 ? $u['email'] : '', 'hp' => $i === 0 ? $u['whatsapp'] : ''];
		}
	}
	$galat = [];

	if ($post) {
		$v = array_merge($v, [
			'jenis_ciptaan' => isset(JENIS_CIPTAAN[input('jenis_ciptaan')]) ? input('jenis_ciptaan') : 'buku',
			'judul' => mb_substr(input('judul'), 0, 255),
			'uraian' => mb_substr(input('uraian'), 0, 5000),
			'tanggal_umum' => preg_match('/^\d{4}-\d{2}-\d{2}$/', input('tanggal_umum')) ? input('tanggal_umum') : '',
			'tempat_umum' => mb_substr(input('tempat_umum'), 0, 150),
			'tautan_contoh' => mb_substr(input('tautan_contoh'), 0, 255),
		]);
		$pencipta = baca_pencipta();
		$pemegang = input('pemegang_tipe') === 'lain' ? [
			'nama' => mb_substr(input('pemegang_nama'), 0, 150),
			'jenis' => input('pemegang_jenis') === 'badan' ? 'badan' : 'perorangan',
			'kewarganegaraan' => mb_substr(input('pemegang_kewarganegaraan', 'Indonesia'), 0, 60),
			'alamat' => mb_substr(input('pemegang_alamat'), 0, 500),
			'kode_pos' => mb_substr(input('pemegang_kode_pos'), 0, 10),
			'email' => mb_substr(input('pemegang_email'), 0, 150),
			'hp' => preg_replace('/[^0-9+]/', '', input('pemegang_hp')),
		] : [];
		if ($v['tautan_contoh'] !== '' && !preg_match('#^https?://#i', $v['tautan_contoh'])) {
			$galat[] = 'Tautan contoh ciptaan harus diawali https://';
		}
		$kirim = ($_POST['aksi'] ?? '') === 'kirim';
		if (mb_strlen($v['judul']) < 3) {
			$galat[] = 'Isi judul ciptaan.';
		}
		if ($kirim) {
			if (mb_strlen($v['uraian']) < 20) {
				$galat[] = 'Isi uraian singkat ciptaan (minimal satu-dua kalimat).';
			}
			if ($v['tanggal_umum'] === '' || $v['tanggal_umum'] > date('Y-m-d')) {
				$galat[] = 'Isi tanggal pertama kali diumumkan (tidak boleh lewat dari hari ini).';
			}
			if ($v['tempat_umum'] === '') {
				$galat[] = 'Isi kota tempat ciptaan pertama kali diumumkan.';
			}
			if (!$pencipta) {
				$galat[] = 'Isi data minimal satu pencipta.';
			}
			foreach ($pencipta as $i => $p) {
				$ke = count($pencipta) > 1 ? ' pencipta ke-' . ($i + 1) : ' pencipta';
				foreach (['nama', 'alamat', 'email', 'hp'] as $k) {
					if ($p[$k] === '') {
						$galat[] = 'Isi ' . mb_strtolower(KOLOM_PENCIPTA[$k]) . $ke . '.';
					}
				}
				if (mb_strtolower($p['kewarganegaraan'] ?: 'indonesia') === 'indonesia' && strlen($p['nik']) !== 16) {
					$galat[] = 'NIK' . $ke . ' harus 16 angka.';
				}
				if ($p['email'] !== '' && !filter_var($p['email'], FILTER_VALIDATE_EMAIL)) {
					$galat[] = 'Email' . $ke . ' tidak valid.';
				}
			}
			if ($pemegang && ($pemegang['nama'] === '' || $pemegang['alamat'] === '')) {
				$galat[] = 'Isi nama dan alamat pemegang hak cipta.';
			}
			if (empty($_POST['setuju'])) {
				$galat[] = 'Centang pernyataan bahwa data yang diisi benar.';
			}
		}

		if (!$galat) {
			$data = [$v['jenis_ciptaan'], $v['judul'], $v['uraian'], $v['tanggal_umum'], $v['tempat_umum'], json_encode($pencipta, JSON_UNESCAPED_UNICODE), $pemegang ? json_encode($pemegang, JSON_UNESCAPED_UNICODE) : '', $v['tautan_contoh']];
			db()->beginTransaction();
			try {
				if ($h) {
					q('UPDATE hki SET jenis_ciptaan=?, judul=?, uraian=?, tanggal_umum=?, tempat_umum=?, pencipta=?, pemegang=?, tautan_contoh=?, diubah=? WHERE id=?', [...$data, sekarang(), $h['id']]);
					$id = (int) $h['id'];
				} else {
					$biaya = $termasuk_paket ? 0 : harga_hki();
					q('INSERT INTO hki (pengguna_id, pengajuan_id, jenis_ciptaan, judul, uraian, tanggal_umum, tempat_umum, pencipta, pemegang, tautan_contoh, biaya, status, dibuat, diubah) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
						[$u['id'], $pj['id'] ?? null, ...$data, ...[$biaya, 'DRAF', sekarang(), sekarang()]]);
					$id = (int) db()->lastInsertId();
					catat_hki($id, (int) $u['id'], 'Permohonan HKI dibuat' . ($pj ? ' untuk buku "' . $pj['judul'] . '"' : '') . '.', true);
				}
				$baru = 0;
				foreach (['ktp', 'pernyataan', 'contoh', 'pengalihan'] as $j) {
					$baru += simpan_unggahan_hki($j, $j, $id) ? 1 : 0;
				}
				db()->commit();
			} catch (RuntimeException $ex) {
				db()->rollBack();
				$galat[] = $ex->getMessage();
				$id = $h ? (int) $h['id'] : 0;
			}
		}

		if (!$galat && $kirim) {
			$h = satu('SELECT * FROM hki WHERE id = ?', [$id]);
			foreach (['ktp' => 'KTP seluruh pencipta', 'pernyataan' => 'surat pernyataan hak cipta'] as $j => $label) {
				if (!berkas_hki($id, $j)) {
					$galat[] = "Unggah $label.";
				}
			}
			if (!berkas_hki($id, 'contoh') && $h['tautan_contoh'] === '') {
				$galat[] = 'Unggah contoh ciptaan atau isi tautannya.';
			}
			if ($pemegang && !berkas_hki($id, 'pengalihan')) {
				$galat[] = 'Unggah surat pengalihan hak cipta (wajib bila pemegang hak bukan pencipta).';
			}
			if (!$galat) {
				if ($perbaikan) {
					ubah_status_hki($h, 'DIPERIKSA', 'Perbaikan dikirim.');
					pesan('Perbaikan terkirim. Admin akan memeriksanya kembali.');
				} elseif ((int) $h['biaya'] > 0) {
					ubah_status_hki($h, 'MENUNGGU_BAYAR', 'Permohonan dikirim.');
					pesan('Permohonan terkirim. Langkah berikutnya: bayar biaya layanan HKI.');
				} else {
					ubah_status_hki($h, 'DIPERIKSA', 'Permohonan dikirim (termasuk Paket Pustaka).');
					pesan('Permohonan terkirim. Admin akan memeriksa data dan berkas Anda.');
				}
				arahkan('/akun/hki/' . $id);
			}
			foreach ($galat as $g) {
				pesan($g, 'galat');
			}
			pesan('Data sudah tersimpan sebagai draf. Lengkapi kekurangannya lalu kirim lagi.', 'info');
			arahkan('/akun/hki/' . $id . '/ubah');
		} elseif (!$galat) {
			pesan('Draf tersimpan.');
			arahkan('/akun/hki/' . $id . '/ubah');
		}
	}

	$berkas_ada = [];
	if ($h) {
		foreach (array_keys(JENIS_BERKAS_HKI) as $j) {
			$b = berkas_hki((int) $h['id'], $j);
			$berkas_ada[$j] = $b ? '<p class="berkas-ada">Tersimpan: <a href="' . e(url('/hki/berkas/' . $b['id'])) . '">' . e($b['nama_asli']) . '</a></p>' : '';
		}
	}
	$ada = fn (string $j) => $berkas_ada[$j] ?? '';

	$baris_pencipta = function (int $i, array $p): string {
		$f = fn (string $k, string $tipe = 'text', string $extra = '') => '<div><label for="pc-' . $i . '-' . $k . '">' . e(KOLOM_PENCIPTA[$k]) . '</label><input id="pc-' . $i . '-' . $k . '" name="pencipta[' . $i . '][' . $k . ']" type="' . $tipe . '" value="' . e($p[$k] ?? '') . '"' . $extra . '></div>';
		return '<fieldset class="pencipta" data-pencipta><legend>Pencipta <span data-nomor>' . ($i + 1) . '</span></legend>
			<div class="kolom-2">' . $f('nama') . $f('nik', 'text', ' inputmode="numeric" maxlength="16" placeholder="16 angka sesuai KTP"') . $f('email', 'email') . $f('hp', 'tel', ' inputmode="tel"') . $f('kewarganegaraan') . $f('kode_pos', 'text', ' inputmode="numeric" maxlength="10"') . '
			<div class="lebar-penuh"><label for="pc-' . $i . '-alamat">Alamat lengkap <span class="catatan">(jalan, RT/RW, kelurahan, kecamatan, kabupaten/kota, provinsi)</span></label><textarea id="pc-' . $i . '-alamat" name="pencipta[' . $i . '][alamat]" rows="2">' . e($p['alamat'] ?? '') . '</textarea></div></div>
			<button type="button" class="tautan-tombol" data-hapus-pencipta>Hapus pencipta ini</button>
		</fieldset>';
	};
	$daftar_pencipta = '';
	foreach ($pencipta as $i => $p) {
		$daftar_pencipta .= $baris_pencipta($i, $p);
	}
	$templat = $baris_pencipta(999, ['kewarganegaraan' => 'Indonesia']);

	$opsi_jenis = implode('', array_map(fn ($k, $l) => '<option value="' . $k . '"' . ($k === $v['jenis_ciptaan'] ? ' selected' : '') . '>' . e($l) . '</option>', array_keys(JENIS_CIPTAAN), JENIS_CIPTAAN));
	$lain = (bool) $pemegang;
	$pm = $pemegang + ['nama' => '', 'jenis' => 'perorangan', 'kewarganegaraan' => 'Indonesia', 'alamat' => '', 'kode_pos' => '', 'email' => '', 'hp' => ''];
	$daftar_galat = $galat ? '<ul class="pesan pesan--galat" role="alert">' . implode('', array_map(fn ($g) => '<li>' . e($g) . '</li>', $galat)) . '</ul>' : '';
	$judul = $perbaikan ? 'Perbaiki Permohonan HKI' : ($h ? 'Lengkapi Permohonan HKI' : 'Ajukan HKI');
	$biaya = $h ? (int) $h['biaya'] : ($termasuk_paket ? 0 : harga_hki());
	$info_biaya = $pj
		? ($biaya === 0
			? '<p class="pesan pesan--info">HKI untuk buku <strong>' . e($pj['judul']) . '</strong> sudah <strong>termasuk Paket Pustaka</strong>. Tidak ada biaya tambahan.</p>'
			: '<p class="pesan pesan--info">Pencatatan HKI untuk buku <strong>' . e($pj['judul']) . '</strong>. Biaya layanan ' . rupiah($biaya) . ', dibayar setelah formulir dikirim.</p>')
		: '<p class="catatan">Biaya layanan HKI <strong>' . rupiah($biaya) . '</strong>, sudah termasuk biaya pendaftaran ke DJKI. Dibayar melalui QRIS setelah formulir dikirim.</p>';

	$isi = '<section class="panel">
	<p><a href="' . e(url($h ? '/akun/hki/' . $h['id'] : '/akun/hki')) . '">← ' . ($h ? 'Kembali ke permohonan' : 'Pengajuan HKI') . '</a></p>
	<h1>' . e($judul) . '</h1>
	' . ($perbaikan ? '<p class="pesan pesan--info">Baca catatan admin di halaman permohonan, perbaiki data atau unggah berkas pengganti, lalu tekan <strong>Kirim Perbaikan</strong>.</p>' : $info_biaya) . '
	' . $daftar_galat . '
	<form method="post" enctype="multipart/form-data" class="formulir formulir--lebar">' . csrf_field() . '
		<fieldset><legend>Data ciptaan</legend>
			<label for="jenis_ciptaan">Jenis ciptaan *</label>
			<select id="jenis_ciptaan" name="jenis_ciptaan">' . $opsi_jenis . '</select>
			<label for="judul">Judul ciptaan *</label>
			<input id="judul" name="judul" required maxlength="255" value="' . e($v['judul']) . '">
			<label for="uraian">Uraian singkat ciptaan * <span class="catatan">(isi pokok, tujuan, atau gambaran karya)</span></label>
			<textarea id="uraian" name="uraian" rows="5">' . e($v['uraian']) . '</textarea>
			<div class="kolom-2">
				<div><label for="tanggal_umum">Tanggal pertama kali diumumkan *</label><input type="date" id="tanggal_umum" name="tanggal_umum" max="' . date('Y-m-d') . '" value="' . e($v['tanggal_umum']) . '"><span class="catatan">Tanggal karya pertama kali dipublikasikan, diterbitkan, atau dibagikan ke publik.</span></div>
				<div><label for="tempat_umum">Kota tempat pertama kali diumumkan *</label><input id="tempat_umum" name="tempat_umum" maxlength="150" placeholder="mis. Muara Bungo" value="' . e($v['tempat_umum']) . '"></div>
			</div>
		</fieldset>
		<fieldset><legend>Pencipta</legend>
			<p class="catatan">Isi semua orang yang menciptakan karya ini, sesuai KTP. Pencipta pertama dianggap pemohon utama.</p>
			<div data-daftar-pencipta>' . $daftar_pencipta . '</div>
			<template id="templat-pencipta">' . $templat . '</template>
			<button type="button" class="tombol tombol--kedua tombol--kecil" data-tambah-pencipta>' . ikon('tambah') . ' Tambah pencipta</button>
		</fieldset>
		<fieldset><legend>Pemegang hak cipta</legend>
			<label class="centang"><input type="radio" name="pemegang_tipe" value="pencipta"' . ($lain ? '' : ' checked') . ' data-pemegang> Sama dengan pencipta</label>
			<label class="centang"><input type="radio" name="pemegang_tipe" value="lain"' . ($lain ? ' checked' : '') . ' data-pemegang> Pihak lain (mis. instansi, sekolah, perusahaan)</label>
			<div data-pemegang-lain' . ($lain ? '' : ' hidden') . '>
				<div class="kolom-2">
					<div><label for="pemegang_nama">Nama pemegang hak *</label><input id="pemegang_nama" name="pemegang_nama" value="' . e($pm['nama']) . '"></div>
					<div><label for="pemegang_jenis">Jenis</label><select id="pemegang_jenis" name="pemegang_jenis"><option value="perorangan">Perorangan</option><option value="badan"' . ($pm['jenis'] === 'badan' ? ' selected' : '') . '>Badan hukum / instansi</option></select></div>
					<div><label for="pemegang_email">Email</label><input type="email" id="pemegang_email" name="pemegang_email" value="' . e($pm['email']) . '"></div>
					<div><label for="pemegang_hp">Nomor HP</label><input id="pemegang_hp" name="pemegang_hp" inputmode="tel" value="' . e($pm['hp']) . '"></div>
					<div><label for="pemegang_kewarganegaraan">Kewarganegaraan</label><input id="pemegang_kewarganegaraan" name="pemegang_kewarganegaraan" value="' . e($pm['kewarganegaraan']) . '"></div>
					<div><label for="pemegang_kode_pos">Kode pos</label><input id="pemegang_kode_pos" name="pemegang_kode_pos" inputmode="numeric" value="' . e($pm['kode_pos']) . '"></div>
					<div class="lebar-penuh"><label for="pemegang_alamat">Alamat lengkap *</label><textarea id="pemegang_alamat" name="pemegang_alamat" rows="2">' . e($pm['alamat']) . '</textarea></div>
				</div>
				<label for="pengalihan">Surat pengalihan hak cipta * <span class="catatan">(PDF/JPG/PNG, maks. 5 MB)</span></label>
				<p class="contoh-unduh">' . ikon('unduh') . '<span>Unduh contoh: <a href="' . e(url('/assets/unduh/surat-pengalihan-hak-cipta.doc')) . '" download>Word (bisa diisi)</a> · <a href="' . e(url('/assets/unduh/surat-pengalihan-hak-cipta.pdf')) . '" download>PDF (untuk dicetak)</a>. Diisi dan ditandatangani pencipta (Pihak I) dan pemegang hak cipta (Pihak II), dengan meterai Rp10.000, lalu pindai/foto dan unggah di sini.</span></p>
				' . $ada('pengalihan') . '<input type="file" id="pengalihan" name="pengalihan" accept=".pdf,.jpg,.jpeg,.png">
			</div>
		</fieldset>
		<fieldset><legend>Berkas</legend>
			<label for="ktp">Scan KTP seluruh pencipta * <span class="catatan">(gabungkan dalam satu PDF bila lebih dari satu orang; PDF/JPG/PNG, maks. 10 MB)</span></label>
			' . $ada('ktp') . '<input type="file" id="ktp" name="ktp" accept=".pdf,.jpg,.jpeg,.png">
			<label for="pernyataan">Surat pernyataan hak cipta * <span class="catatan">(format DJKI, ditandatangani di atas meterai Rp10.000; PDF/JPG/PNG, maks. 5 MB)</span></label>
			<p class="contoh-unduh">' . ikon('unduh') . '<span>Belum punya suratnya? Unduh contoh: <a href="' . e(url('/assets/unduh/surat-pernyataan-hak-cipta.docx')) . '" download>Word (bisa diisi)</a> · <a href="' . e(url('/assets/unduh/surat-pernyataan-hak-cipta.pdf')) . '" download>PDF (untuk dicetak)</a>. Isi nama, kewarganegaraan, alamat, jenis dan judul ciptaan, lalu setiap pemegang hak cipta menandatangani di atas meterai Rp10.000. Pindai/foto dan unggah di sini.</span></p>
			' . $ada('pernyataan') . '<input type="file" id="pernyataan" name="pernyataan" accept=".pdf,.jpg,.jpeg,.png">
			<label for="contoh">Contoh ciptaan * <span class="catatan">(mis. PDF buku lengkap; PDF/DOC/DOCX/JPG/PNG, maks. 30 MB)</span></label>
			' . $ada('contoh') . '<input type="file" id="contoh" name="contoh" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
			<label for="tautan_contoh">Atau tautan contoh ciptaan <span class="catatan">(untuk lagu, video, aplikasi, atau berkas besar: Google Drive, YouTube, dll.)</span></label>
			<input type="url" id="tautan_contoh" name="tautan_contoh" placeholder="https://" value="' . e($v['tautan_contoh']) . '">
		</fieldset>
		<label class="centang siap-terbit"><input type="checkbox" name="setuju" value="1"> Saya menyatakan data dan berkas di atas benar, dan karya ini adalah ciptaan asli para pencipta yang tercantum.</label>
		<div class="formulir__tombol">
			<button type="submit" name="aksi" value="kirim" class="tombol">' . ($perbaikan ? 'Kirim Perbaikan' : 'Kirim Permohonan') . '</button>
			' . ($perbaikan ? '' : '<button type="submit" name="aksi" value="draf" class="tombol tombol--kedua">Simpan Draf</button>') . '
		</div>
	</form>
</section>
<script>(function(){var w=document.querySelector("[data-daftar-pencipta]"),t=document.getElementById("templat-pencipta"),maks=' . MAKS_PENCIPTA . ';
function nomori(){var f=w.querySelectorAll("[data-pencipta]");f.forEach(function(el,i){el.querySelector("[data-nomor]").textContent=i+1;el.querySelector("[data-hapus-pencipta]").hidden=f.length<2;});document.querySelector("[data-tambah-pencipta]").hidden=f.length>=maks;}
var n=w.querySelectorAll("[data-pencipta]").length;
document.querySelector("[data-tambah-pencipta]").addEventListener("click",function(){var h=t.innerHTML.split("999").join(String(n++));w.insertAdjacentHTML("beforeend",h);nomori();});
w.addEventListener("click",function(e){if(e.target.matches("[data-hapus-pencipta]")){e.target.closest("[data-pencipta]").remove();nomori();}});
document.querySelectorAll("[data-pemegang]").forEach(function(r){r.addEventListener("change",function(){document.querySelector("[data-pemegang-lain]").hidden=document.querySelector("[data-pemegang]:checked").value!=="lain";});});
nomori();})();</script>';
	tampil($judul, $isi, ['panel' => menu_pengaju($h ? '' : '/akun/hki')]);
}

function stepper_hki(array $h): string
{
	$tahap = [['Isi data', ['DRAF']]];
	if ((int) $h['biaya'] > 0) {
		$tahap[] = ['Pembayaran', ['MENUNGGU_BAYAR', 'VERIFIKASI_BAYAR']];
	}
	array_push($tahap, ['Pemeriksaan', ['DIPERIKSA', 'PERLU_PERBAIKAN']], ['Pendaftaran DJKI', ['DIAJUKAN']], ['Sertifikat', ['SELESAI']]);
	$posisi = 0;
	foreach ($tahap as $i => [, $daftar]) {
		if (in_array($h['status'], $daftar, true)) {
			$posisi = $i;
		}
	}
	if ($h['status'] === 'SELESAI') {
		$posisi = count($tahap);
	}
	$out = '<ol class="stepper">';
	foreach ($tahap as $i => [$label]) {
		$kelas = $i < $posisi ? 'selesai' : ($i === $posisi ? 'sekarang' : '');
		$out .= '<li class="' . $kelas . '"' . ($i === $posisi ? ' aria-current="step"' : '') . '>' . e($label) . '</li>';
	}
	return $out . '</ol>';
}

function daftar_berkas_hki(int $id, bool $staf): string
{
	$out = '';
	foreach (semua('SELECT * FROM hki_berkas WHERE hki_id = ? ORDER BY id DESC', [$id]) as $b) {
		$out .= '<li><a href="' . e(url('/hki/berkas/' . $b['id'])) . '">' . ikon('unduh') . e(JENIS_BERKAS_HKI[$b['jenis']][0] ?? $b['jenis']) . '</a> <span class="catatan">' . e($b['nama_asli']) . ' · ' . ukuran_berkas((int) $b['ukuran']) . ' · ' . e(tanggal($b['dibuat'])) . '</span></li>';
	}
	return $out ? '<ul class="daftar-berkas">' . $out . '</ul>' : '<p class="catatan">Belum ada berkas.</p>';
}

function riwayat_hki(int $id): string
{
	$out = '';
	foreach (semua('SELECT c.*, u.nama, u.peran FROM hki_catatan c LEFT JOIN pengguna u ON u.id = c.pengguna_id WHERE c.hki_id = ? ORDER BY c.id DESC', [$id]) as $c) {
		$siapa = $c['sistem'] ? 'Sistem' : (['admin' => 'Admin Literaindo', 'keuangan' => 'Keuangan Literaindo'][$c['peran']] ?? $c['nama']);
		$out .= '<li class="' . ($c['sistem'] ? 'riwayat--sistem' : ($c['peran'] === 'admin' ? 'riwayat--admin' : '')) . '"><span class="riwayat__siapa">' . e((string) $siapa) . ' · ' . e(tanggal($c['dibuat'])) . '</span><p>' . nl2br(e($c['isi'])) . '</p></li>';
	}
	return '<ul class="riwayat">' . $out . '</ul>';
}

/** Ringkasan data ciptaan, pencipta, dan pemegang hak. */
function data_hki(array $h): string
{
	$out = '<dl class="data-ringkas">
		<div><dt>No. permohonan</dt><dd>' . e(no_hki($h)) . '</dd></div>
		<div><dt>Jenis ciptaan</dt><dd>' . e(JENIS_CIPTAAN[$h['jenis_ciptaan']] ?? $h['jenis_ciptaan']) . '</dd></div>
		<div><dt>Diumumkan</dt><dd>' . e($h['tanggal_umum'] ? date('d-m-Y', strtotime($h['tanggal_umum'])) : '-') . ($h['tempat_umum'] ? ', ' . e($h['tempat_umum']) : '') . '</dd></div>
		<div><dt>Biaya</dt><dd>' . ((int) $h['biaya'] ? rupiah($h['biaya']) : 'Termasuk Paket Pustaka') . '</dd></div>'
		. ($h['tautan_contoh'] ? '<div><dt>Tautan contoh</dt><dd><a href="' . e($h['tautan_contoh']) . '" target="_blank" rel="noopener">' . e($h['tautan_contoh']) . '</a></dd></div>' : '')
		. ($h['nomor_permohonan'] ? '<div><dt>No. permohonan DJKI</dt><dd>' . e($h['nomor_permohonan']) . ($h['tanggal_permohonan'] ? ' (' . e(date('d-m-Y', strtotime($h['tanggal_permohonan']))) . ')' : '') . '</dd></div>' : '')
		. ($h['nomor_pencatatan'] ? '<div><dt>No. pencatatan</dt><dd><strong>' . e($h['nomor_pencatatan']) . '</strong></dd></div>' : '')
		. '</dl><h3>Uraian</h3><p>' . nl2br(e($h['uraian'])) . '</p><h3>Pencipta</h3>';
	foreach (pencipta_hki($h) as $i => $p) {
		$out .= '<p><strong>' . ($i + 1) . '. ' . e($p['nama']) . '</strong><br><span class="catatan">NIK ' . e($p['nik'] ?: '-') . ' · ' . e($p['kewarganegaraan'] ?: 'Indonesia') . ' · ' . e($p['email']) . ' · ' . e($p['hp']) . '<br>' . e($p['alamat']) . ($p['kode_pos'] ? ' ' . e($p['kode_pos']) : '') . '</span></p>';
	}
	$pm = pemegang_hki($h);
	$out .= '<h3>Pemegang hak cipta</h3>' . ($pm
		? '<p><strong>' . e($pm['nama']) . '</strong> (' . ($pm['jenis'] === 'badan' ? 'badan hukum/instansi' : 'perorangan') . ')<br><span class="catatan">' . e($pm['email']) . ' · ' . e($pm['hp']) . '<br>' . e($pm['alamat']) . ' ' . e($pm['kode_pos']) . '</span></p>'
		: '<p>Sama dengan pencipta.</p>');
	return $out;
}

function hki_detail(bool $post, string $id): void
{
	$u = wajib_masuk();
	if ($u['peran'] === 'admin') {
		arahkan('/admin/hki/' . (int) $id);
	}
	if ($u['peran'] !== 'pengaju') {
		arahkan(beranda_peran($u['peran']));
	}
	$h = hki_milik((int) $id, $u);
	$hid = (int) $h['id'];

	if ($post) {
		$aksi = $_POST['aksi'] ?? '';
		if ($aksi === 'catatan' && input('isi') !== '') {
			catat_hki($hid, (int) $u['id'], mb_substr(input('isi'), 0, 3000));
			notif_hki($h, 'Pesan baru: ' . mb_substr(input('isi'), 0, 200));
			q('UPDATE hki SET diubah = ? WHERE id = ?', [sekarang(), $hid]);
			pesan('Pesan terkirim ke admin.');
		} elseif ($aksi === 'bukti' && $h['status'] === 'MENUNGGU_BAYAR') {
			try {
				$bid = simpan_unggahan_hki('bukti', 'bukti', $hid);
				if (!$bid) {
					throw new RuntimeException('Pilih berkas bukti pembayaran.');
				}
				q("UPDATE hki SET bayar_status = 'menunggu', bukti_berkas_id = ?, bukti_dibuat = ?, catatan_bayar = '', diverifikasi = NULL, pemeriksa_id = NULL WHERE id = ?", [$bid, sekarang(), $hid]);
				ubah_status_hki($h, 'VERIFIKASI_BAYAR', 'Bukti bayar ' . rupiah($h['biaya']) . ' diunggah.');
				notif_keuangan('Bukti bayar layanan HKI ' . rupiah($h['biaya']) . ' dari ' . $u['nama'] . ' ("' . $h['judul'] . '") perlu diverifikasi.');
				pesan('Bukti bayar terkirim. Bagian keuangan akan memverifikasinya.');
			} catch (RuntimeException $ex) {
				pesan($ex->getMessage(), 'galat');
			}
		}
		arahkan('/akun/hki/' . $hid);
	}

	$pj = $h['pengajuan_id'] ? satu('SELECT * FROM pengajuan WHERE id = ?', [$h['pengajuan_id']]) : null;
	$sertifikat = berkas_hki($hid, 'sertifikat');
	$langkah = match ($h['status']) {
		'DRAF' => '<p>Lengkapi data dan berkas, lalu kirim permohonan.</p><p><a class="tombol" href="' . e(url("/akun/hki/$hid/ubah")) . '">Lengkapi &amp; Kirim</a></p>',
		'MENUNGGU_BAYAR' => ($h['bayar_status'] === 'ditolak' ? '<p class="pesan pesan--galat">Bukti bayar sebelumnya ditolak: ' . e($h['catatan_bayar'] ?: 'tidak sesuai') . '. Silakan unggah ulang.</p>' : '')
			. '<div class="tagihan"><div><p>Biaya layanan HKI</p><p class="tagihan__nominal">' . rupiah($h['biaya']) . '</p><p class="catatan">Pindai QRIS dari aplikasi bank atau dompet digital apa pun, bayar tepat sesuai nominal, lalu unggah bukti bayarnya.</p>' . form_bukti(0) . '</div>' . blok_qris((int) $h['biaya']) . '</div>',
		'VERIFIKASI_BAYAR' => '<p>Bukti pembayaran Anda sedang dicek bagian keuangan, biasanya dalam 1×24 jam kerja.</p>',
		'DIPERIKSA' => '<p>Admin sedang memeriksa data dan berkas Anda sebelum didaftarkan ke DJKI.' . ($pj && !in_array($pj['status'], ['MENUNGGU_BAYAR_2', 'VERIFIKASI_BAYAR_2', 'LUNAS', 'SELESAI'], true) ? ' HKI buku didaftarkan setelah buku terbit.' : '') . '</p>',
		'PERLU_PERBAIKAN' => '<p>Admin meminta perbaikan. Baca catatannya di bagian Riwayat, lalu kirim perbaikan.</p><p><a class="tombol" href="' . e(url("/akun/hki/$hid/ubah")) . '">Perbaiki Permohonan</a></p>',
		'DIAJUKAN' => '<p>Permohonan sudah didaftarkan ke DJKI' . ($h['nomor_permohonan'] ? ' dengan nomor <strong>' . e($h['nomor_permohonan']) . '</strong>' : '') . '. Kami akan memberi tahu Anda setelah sertifikat terbit.</p>',
		'SELESAI' => '<p>Sertifikat hak cipta sudah terbit' . ($h['nomor_pencatatan'] ? ' dengan nomor pencatatan <strong>' . e($h['nomor_pencatatan']) . '</strong>' : '') . '.</p>'
			. ($sertifikat ? '<p><a class="tombol" href="' . e(url('/hki/berkas/' . $sertifikat['id'])) . '">' . ikon('unduh') . ' Unduh Sertifikat</a></p>' : ''),
		'DITOLAK' => '<p>Permohonan ini tidak dapat diproses. Alasannya ada di bagian Riwayat.</p>',
		default => '',
	};

	$isi = '<section class="panel">
	<p><a href="' . e(url('/akun/hki')) . '">← Pengajuan HKI</a></p>
	<div class="judul-baris"><h1>' . e($h['judul']) . '</h1>' . label_hki($h['status']) . '</div>
	' . ($pj ? '<p class="catatan">Terhubung dengan pengajuan buku <a href="' . e(url('/akun/pengajuan/' . $pj['id'])) . '">' . e($pj['judul']) . '</a>' . ((int) $h['biaya'] === 0 ? ' (termasuk Paket Pustaka)' : '') . '.</p>' : '') . '
	' . ($h['status'] !== 'DITOLAK' ? stepper_hki($h) : '') . '
	<div class="kotak-langkah"><h2>Langkah berikutnya</h2>' . $langkah . '</div>
	<div class="dua-kolom">
		<section><h2>Data HKI</h2>' . data_hki($h) . '<h2>Berkas</h2>' . daftar_berkas_hki($hid, false) . '</section>
		<section><h2>Riwayat &amp; pesan</h2>' . form_catatan() . riwayat_hki($hid) . '</section>
	</div>
</section>';
	tampil($h['judul'], $isi, ['panel' => menu_pengaju('/akun/hki')]);
}

/** Kotak HKI di halaman pengajuan buku (penulis). */
function kotak_hki_pengajuan(array $p): string
{
	if (in_array($p['status'], ['DRAF', 'DITOLAK'], true)) {
		return '';
	}
	$h = hki_pengajuan((int) $p['id']);
	if ($h) {
		return '<div class="kotak-langkah"><h2>HKI (hak cipta)</h2><p>' . label_hki($h['status']) . ' ' . e(STATUS_HKI[$h['status']][1]) . '. <a href="' . e(url('/akun/hki/' . $h['id'])) . '">Lihat permohonan HKI</a></p></div>';
	}
	if (($p['paket'] ?? '') === 'pustaka') {
		return '<div class="kotak-langkah"><h2>HKI (hak cipta)</h2><p>Paket Pustaka sudah termasuk pencatatan hak cipta di DJKI. Isi data pencipta dan unggah berkasnya agar kami bisa mendaftarkannya setelah buku terbit.</p><p><a class="tombol" href="' . e(url('/akun/hki/baru?pengajuan=' . $p['id'])) . '">Isi Data HKI</a></p></div>';
	}
	return '<div class="kotak-langkah"><h2>HKI (hak cipta)</h2><p>Lindungi buku Anda dengan pencatatan hak cipta di DJKI. Biaya layanan ' . rupiah(harga_hki()) . '.</p><p><a class="tombol tombol--kedua" href="' . e(url('/akun/hki/baru?pengajuan=' . $p['id'])) . '">Ajukan HKI untuk Buku Ini</a></p></div>';
}

function unduh_berkas_hki(bool $post, string $id): void
{
	$u = wajib_masuk();
	$b = satu('SELECT b.*, h.pengguna_id FROM hki_berkas b JOIN hki h ON h.id = b.hki_id WHERE b.id = ?', [(int) $id]);
	$boleh = $b && ($u['peran'] === 'admin'
		|| (int) $b['pengguna_id'] === (int) $u['id']
		|| ($u['peran'] === 'keuangan' && $b['jenis'] === 'bukti'));
	if (!$boleh) {
		tidak_ditemukan();
	}
	$inline = in_array(strtolower(pathinfo($b['path'], PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'pdf'], true);
	kirim_berkas($b['path'], $b['nama_asli'], $inline);
}

// ---------------------------------------------------------------------------
// Admin

function admin_hki(bool $post): void
{
	wajib_masuk('admin');
	$filter = (string) ($_GET['status'] ?? '');
	$cari = trim((string) ($_GET['cari'] ?? ''));
	$sql = "SELECT h.*, u.nama FROM hki h JOIN pengguna u ON u.id = h.pengguna_id WHERE h.status <> 'DRAF'";
	$par = [];
	if (isset(STATUS_HKI[$filter])) {
		$sql = 'SELECT h.*, u.nama FROM hki h JOIN pengguna u ON u.id = h.pengguna_id WHERE h.status = ?';
		$par[] = $filter;
	}
	if ($cari !== '') {
		$sql .= ' AND (h.judul LIKE ? OR u.nama LIKE ?)';
		array_push($par, "%$cari%", "%$cari%");
	}
	$daftar = semua($sql . ' ORDER BY h.diubah DESC', $par);
	$hitung = [];
	foreach (semua('SELECT status, COUNT(*) AS n FROM hki GROUP BY status') as $r) {
		$hitung[$r['status']] = (int) $r['n'];
	}
	$kotak = '';
	foreach (['VERIFIKASI_BAYAR', 'DIPERIKSA', 'PERLU_PERBAIKAN', 'DIAJUKAN', 'SELESAI'] as $k) {
		$n = $hitung[$k] ?? 0;
		$kotak .= '<a class="kotak-status' . ($k === 'DIPERIKSA' && $n ? ' kotak-status--tindakan' : '') . '" href="' . e(url('/admin/hki?status=' . $k)) . '"><span class="kotak-status__angka">' . $n . '</span><span>' . e(STATUS_HKI[$k][0]) . '</span></a>';
	}
	$opsi = '<option value="">Semua (kecuali draf)</option>';
	foreach (STATUS_HKI as $k => [$label]) {
		$opsi .= '<option value="' . $k . '"' . ($k === $filter ? ' selected' : '') . '>' . e($label) . '</option>';
	}
	$baris = '';
	foreach ($daftar as $h) {
		$baris .= '<tr><td><a href="' . e(url('/admin/hki/' . $h['id'])) . '"><strong>' . e($h['judul']) . '</strong></a><br><span class="catatan">' . e(no_hki($h)) . ' · ' . e(JENIS_CIPTAAN[$h['jenis_ciptaan']] ?? '') . '</span></td><td>' . e($h['nama']) . '</td><td>' . ($h['pengajuan_id'] ? ((int) $h['biaya'] ? 'Buku · ' . rupiah($h['biaya']) : 'Paket Pustaka') : 'HKI saja · ' . rupiah($h['biaya'])) . '</td><td>' . label_hki($h['status']) . '</td><td class="angka">' . e(tanggal($h['diubah'])) . '</td></tr>';
	}
	$isi = '<section class="panel"><h1>Pengajuan HKI</h1>
	<div class="kotak-status-grid">' . $kotak . '</div>
	<form method="get" class="filter filter--admin"><label class="filter__isian"><span class="tersembunyi">Status</span><select name="status">' . $opsi . '</select></label><label class="filter__isian"><span class="tersembunyi">Cari</span><input type="search" name="cari" placeholder="Judul atau nama pemohon" value="' . e($cari) . '"></label><div class="filter__tombol"><button class="tombol" type="submit">Tampilkan</button></div></form>
	' . ($baris ? '<div class="tabel-wadah"><table class="tabel"><thead><tr><th>Judul</th><th>Pemohon</th><th>Jalur</th><th>Status</th><th>Diperbarui</th></tr></thead><tbody>' . $baris . '</tbody></table></div>' : '<p class="catatan">Tidak ada permohonan HKI.</p>') . '
	<p class="catatan">Harga layanan HKI saja: ' . rupiah(harga_hki()) . ' (ubah di <a href="' . e(url('/admin/pengaturan')) . '">Pengaturan</a>).</p></section>';
	tampil('Pengajuan HKI', $isi, ['panel' => menu_admin('/admin/hki')]);
}

/** Teks siap salin untuk mengisi formulir e-Hak Cipta DJKI. */
function teks_djki(array $h): string
{
	$t = "Jenis ciptaan: " . (JENIS_CIPTAAN[$h['jenis_ciptaan']] ?? $h['jenis_ciptaan']) . "\nJudul: {$h['judul']}\nUraian: {$h['uraian']}\n"
		. 'Tanggal diumumkan: ' . ($h['tanggal_umum'] ? date('d-m-Y', strtotime($h['tanggal_umum'])) : '-') . "\nTempat diumumkan: {$h['tempat_umum']}\n";
	foreach (pencipta_hki($h) as $i => $p) {
		$t .= "\nPencipta " . ($i + 1) . "\nNama: {$p['nama']}\nNIK: {$p['nik']}\nKewarganegaraan: {$p['kewarganegaraan']}\nAlamat: {$p['alamat']}\nKode pos: {$p['kode_pos']}\nEmail: {$p['email']}\nHP: {$p['hp']}\n";
	}
	$pm = pemegang_hki($h);
	$t .= "\nPemegang hak cipta: " . ($pm ? "{$pm['nama']} (" . ($pm['jenis'] === 'badan' ? 'badan hukum' : 'perorangan') . ")\nAlamat: {$pm['alamat']} {$pm['kode_pos']}\nEmail: {$pm['email']}\nHP: {$pm['hp']}" : 'sama dengan pencipta') . "\n";
	return $t;
}

function admin_hki_detail(bool $post, string $id): void
{
	$admin = wajib_masuk('admin');
	$h = satu('SELECT * FROM hki WHERE id = ?', [(int) $id]);
	if (!$h) {
		tidak_ditemukan();
	}
	$hid = (int) $h['id'];
	$aid = (int) $admin['id'];

	if ($post) {
		$aksi = $_POST['aksi'] ?? '';
		$catatan = mb_substr(input('catatan'), 0, 2000);
		$s = $h['status'];
		try {
			if ($aksi === 'catatan' && input('isi') !== '') {
				catat_hki($hid, $aid, mb_substr(input('isi'), 0, 3000));
				notif_hki($h, 'Pesan dari admin: ' . mb_substr(input('isi'), 0, 200));
				q('UPDATE hki SET diubah = ? WHERE id = ?', [sekarang(), $hid]);
				$pemilik = satu('SELECT * FROM pengguna WHERE id = ?', [$h['pengguna_id']]);
				kirim_email($pemilik['email'], 'Pesan baru untuk HKI "' . $h['judul'] . '"', "Halo {$pemilik['nama']},\n\nAdmin Literaindo Press mengirim pesan:\n\n" . input('isi') . "\n\nBalas di: " . url_absolut("/akun/hki/$hid"));
				pesan('Pesan terkirim.');
			} elseif (in_array($aksi, ['terima_bayar', 'tolak_bayar'], true) && $s === 'VERIFIKASI_BAYAR') {
				verifikasi_hki($h, $aksi === 'terima_bayar', $catatan);
				pesan('Status pembayaran diperbarui.');
			} elseif ($aksi === 'perbaikan' && in_array($s, ['DIPERIKSA', 'DIAJUKAN'], true)) {
				if ($catatan === '') {
					throw new RuntimeException('Tulis catatan perbaikan untuk pemohon.');
				}
				ubah_status_hki($h, 'PERLU_PERBAIKAN', $catatan);
				pesan('Permintaan perbaikan dikirim ke pemohon.');
			} elseif ($aksi === 'diajukan' && $s === 'DIPERIKSA') {
				$nomor = mb_substr(input('nomor_permohonan'), 0, 60);
				$tgl = preg_match('/^\d{4}-\d{2}-\d{2}$/', input('tanggal_permohonan')) ? input('tanggal_permohonan') : date('Y-m-d');
				if ($nomor === '') {
					throw new RuntimeException('Isi nomor permohonan dari DJKI.');
				}
				q('UPDATE hki SET nomor_permohonan = ?, tanggal_permohonan = ? WHERE id = ?', [$nomor, $tgl, $hid]);
				ubah_status_hki($h, 'DIAJUKAN', 'Nomor permohonan DJKI: ' . $nomor . '.');
				pesan('Permohonan ditandai sudah diajukan ke DJKI.');
			} elseif ($aksi === 'sertifikat' && in_array($s, ['DIAJUKAN', 'SELESAI'], true)) {
				$nomor = mb_substr(input('nomor_pencatatan'), 0, 60);
				if ($nomor === '') {
					throw new RuntimeException('Isi nomor pencatatan pada sertifikat.');
				}
				$bid = simpan_unggahan_hki('sertifikat', 'sertifikat', $hid);
				if (!$bid && !berkas_hki($hid, 'sertifikat')) {
					throw new RuntimeException('Unggah file sertifikat hak cipta.');
				}
				q('UPDATE hki SET nomor_pencatatan = ? WHERE id = ?', [$nomor, $hid]);
				if ($s === 'DIAJUKAN') {
					ubah_status_hki($h, 'SELESAI', 'Nomor pencatatan: ' . $nomor . '. Sertifikat bisa diunduh.');
					pesan('Sertifikat tersimpan. Pemohon sudah diberi tahu.');
				} else {
					catat_hki($hid, $aid, 'Data sertifikat diperbarui (nomor pencatatan ' . $nomor . ').', true);
					pesan('Data sertifikat diperbarui.');
				}
			} elseif ($aksi === 'tolak' && !in_array($s, ['SELESAI', 'DITOLAK'], true)) {
				if ($catatan === '') {
					throw new RuntimeException('Tulis alasan penolakan.');
				}
				ubah_status_hki($h, 'DITOLAK', $catatan);
				pesan('Permohonan HKI ditolak.');
			}
		} catch (RuntimeException $ex) {
			pesan($ex->getMessage(), 'galat');
		}
		arahkan('/admin/hki/' . $hid);
	}

	$pemohon = satu('SELECT * FROM pengguna WHERE id = ?', [$h['pengguna_id']]);
	$pj = $h['pengajuan_id'] ? satu('SELECT * FROM pengajuan WHERE id = ?', [$h['pengajuan_id']]) : null;
	$buku = $pj && $pj['buku_id'] ? satu('SELECT * FROM buku WHERE id = ?', [$pj['buku_id']]) : null;
	$form = fn (string $aksi, string $isi, string $tombol, string $kelas = 'tombol', bool $unggah = false) => '<form method="post" class="formulir"' . ($unggah ? ' enctype="multipart/form-data"' : '') . '>' . csrf_field() . '<input type="hidden" name="aksi" value="' . $aksi . '">' . $isi . '<button type="submit" class="' . $kelas . '">' . $tombol . '</button></form>';
	$catatan_input = fn (string $label, string $id) => '<label for="' . $id . '">' . $label . '</label><textarea id="' . $id . '" name="catatan" rows="3"></textarea>';
	$bukti = $h['bukti_berkas_id'] ? '<p>Nominal: <strong>' . rupiah($h['biaya']) . '</strong> · diunggah ' . e(tanggal($h['bukti_dibuat'])) . '</p><p><a class="tombol tombol--kedua" href="' . e(url('/hki/berkas/' . $h['bukti_berkas_id'])) . '" target="_blank">' . ikon('unduh') . ' Lihat bukti bayar</a></p>' : '';
	$form_sertifikat = fn (string $tombol) => $form('sertifikat', '<div class="kolom-2"><div><label for="nomor_pencatatan">Nomor pencatatan</label><input id="nomor_pencatatan" name="nomor_pencatatan" required value="' . e($h['nomor_pencatatan']) . '" placeholder="mis. 000123456"></div><div><label for="sertifikat">File sertifikat <span class="catatan">(PDF/JPG/PNG, maks. 10 MB)</span></label><input type="file" id="sertifikat" name="sertifikat" accept=".pdf,.jpg,.jpeg,.png"' . (berkas_hki($hid, 'sertifikat') ? '' : ' required') . '></div></div>', $tombol, 'tombol', true);

	$belum_terbit = $pj && !in_array($pj['status'], ['MENUNGGU_BAYAR_2', 'VERIFIKASI_BAYAR_2', 'LUNAS', 'SELESAI'], true);
	$aksi = match ($h['status']) {
		'DRAF' => '<p>Pemohon belum mengirim formulir.</p>',
		'MENUNGGU_BAYAR' => '<p>Menunggu pemohon membayar ' . rupiah($h['biaya']) . '.</p>',
		'VERIFIKASI_BAYAR' => '<p>Bukti bayar menunggu verifikasi <strong>bagian keuangan</strong> di <a href="' . e(url('/keuangan')) . '">Panel Keuangan</a>.</p>' . $bukti
			. '<details><summary>Verifikasi sendiri sebagai admin</summary><p class="catatan">Gunakan hanya jika bagian keuangan berhalangan.</p><div class="aksi-baris">' . $form('terima_bayar', '', 'Terima Pembayaran')
			. '<details><summary>Tolak bukti bayar</summary>' . $form('tolak_bayar', $catatan_input('Alasan (dikirim ke pemohon)', 'c-tolak-bayar'), 'Tolak Bukti', 'tombol tombol--bahaya') . '</details></div></details>',
		'DIPERIKSA' => ($belum_terbit ? '<p class="pesan pesan--info">Buku "' . e($pj['judul']) . '" belum terbit (' . e(STATUS[$pj['status']][0]) . '). Sebaiknya HKI didaftarkan setelah ISBN keluar.</p>' : '')
			. '<p>Periksa data dan berkas, lalu daftarkan di <a href="https://e-hakcipta.dgip.go.id" target="_blank" rel="noopener">e-hakcipta.dgip.go.id</a>. Setelah terdaftar, isi nomor permohonannya di sini.</p>'
			. $form('diajukan', '<div class="kolom-2"><div><label for="nomor_permohonan">Nomor permohonan DJKI</label><input id="nomor_permohonan" name="nomor_permohonan" required placeholder="mis. EC00202612345"></div><div><label for="tanggal_permohonan">Tanggal permohonan</label><input type="date" id="tanggal_permohonan" name="tanggal_permohonan" value="' . date('Y-m-d') . '"></div></div>', 'Tandai Sudah Diajukan ke DJKI')
			. '<details><summary>Minta perbaikan ke pemohon</summary>' . $form('perbaikan', $catatan_input('Catatan perbaikan', 'c-perbaikan'), 'Kirim Permintaan Perbaikan', 'tombol tombol--kedua') . '</details>',
		'PERLU_PERBAIKAN' => '<p>Menunggu pemohon mengirim perbaikan.</p>',
		'DIAJUKAN' => '<p>Terdaftar di DJKI dengan nomor <strong>' . e($h['nomor_permohonan']) . '</strong>. Setelah sertifikat terbit, unggah di sini.</p>' . $form_sertifikat('Simpan Sertifikat &amp; Selesaikan')
			. '<details><summary>DJKI meminta perbaikan</summary>' . $form('perbaikan', $catatan_input('Catatan perbaikan untuk pemohon', 'c-perbaikan'), 'Kirim Permintaan Perbaikan', 'tombol tombol--kedua') . '</details>',
		'SELESAI' => '<p>Sertifikat terbit. Nomor pencatatan: <strong>' . e($h['nomor_pencatatan']) . '</strong>.</p><details><summary>Ganti file atau nomor sertifikat</summary>' . $form_sertifikat('Perbarui Sertifikat') . '</details>',
		'DITOLAK' => '<p>Permohonan ditolak.</p>',
		default => '',
	};
	if (!in_array($h['status'], ['SELESAI', 'DITOLAK'], true)) {
		$aksi .= '<details class="bahaya"><summary>Tolak permohonan</summary>' . $form('tolak', $catatan_input('Alasan penolakan (dikirim ke pemohon)', 'c-tolak'), 'Tolak Permohonan', 'tombol tombol--bahaya') . '</details>';
	}

	// Daftar periksa berkas
	$cek = [['KTP seluruh pencipta', 'ktp'], ['Surat pernyataan hak cipta', 'pernyataan'], ['Contoh ciptaan', 'contoh']];
	if (pemegang_hki($h)) {
		$cek[] = ['Surat pengalihan hak cipta', 'pengalihan'];
	}
	$li = '';
	foreach ($cek as [$label, $j]) {
		$b = berkas_hki($hid, $j);
		$tautan = $j === 'contoh' && !$b && $h['tautan_contoh'] ? '<a href="' . e($h['tautan_contoh']) . '" target="_blank" rel="noopener">tautan</a>' : '';
		$ok = $b || $tautan;
		$li .= '<li class="' . ($ok ? 'ada' : 'belum') . '"><span class="periksa__tanda" aria-hidden="true">' . ($ok ? '✓' : '') . '</span><span>' . e($label)
			. ($b ? ' · <a href="' . e(url('/hki/berkas/' . $b['id'])) . '">' . e($b['nama_asli']) . '</a>' : ($tautan ? ' · ' . $tautan : ' · <em>belum ada</em>')) . '</span></li>';
	}

	$isi = '<section class="panel">
	<p><a href="' . e(url('/admin/hki')) . '">← Semua permohonan HKI</a></p>
	<div class="judul-baris"><h1>' . e($h['judul']) . '</h1>' . label_hki($h['status']) . '</div>
	' . ($h['status'] !== 'DITOLAK' ? stepper_hki($h) : '') . '
	<div class="kotak-langkah kotak-langkah--admin"><h2>Tindakan</h2>' . $aksi . '</div>
	<div class="kotak-langkah"><h2>Berkas HKI</h2><ul class="periksa">' . $li . '</ul>
		<details><summary>Salin data untuk formulir DJKI</summary><textarea class="salin-djki" rows="14" readonly onclick="this.select()">' . e(teks_djki($h)) . '</textarea></details></div>
	<div class="dua-kolom">
		<section><h2>Pemohon</h2><dl class="data-ringkas">
			<div><dt>Nama</dt><dd>' . e($pemohon['nama']) . '</dd></div>
			<div><dt>Email</dt><dd>' . e($pemohon['email']) . '</dd></div>
			<div><dt>WhatsApp</dt><dd><a href="https://wa.me/' . e(preg_replace('/^0/', '62', (string) preg_replace('/\D/', '', $pemohon['whatsapp']))) . '" target="_blank">' . e($pemohon['whatsapp']) . '</a></dd></div>
			' . ($pj ? '<div><dt>Buku</dt><dd><a href="' . e(url('/admin/pengajuan/' . $pj['id'])) . '">' . e($pj['judul']) . '</a> · ' . e(STATUS[$pj['status']][0] ?? $pj['status']) . ($buku && $buku['isbn'] ? ' · ISBN ' . e($buku['isbn']) : '') . '</dd></div>' : '<div><dt>Jalur</dt><dd>Layanan HKI saja</dd></div>') . '
		</dl>
		<h2>Data HKI</h2>' . data_hki($h) . '
		<h2>Semua berkas</h2>' . daftar_berkas_hki($hid, true) . '</section>
		<section><h2>Riwayat &amp; pesan</h2>' . form_catatan() . riwayat_hki($hid) . '</section>
	</div>
</section>';
	tampil($h['judul'], $isi, ['panel' => menu_admin('/admin/hki')]);
}
