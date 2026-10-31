<?php
// Notifikasi (ikon lonceng) untuk penulis dan admin.

declare(strict_types=1);

function halaman_notifikasi(bool $post): void
{
	$u = wajib_masuk();
	$uid = (int) $u['id'];
	if ($post && ($_POST['aksi'] ?? '') === 'baca_semua') {
		q('UPDATE notifikasi SET dibaca = 1 WHERE pengguna_id = ? AND dibaca = 0', [$uid]);
		pesan('Semua notifikasi ditandai sudah dibaca.');
		arahkan('/notifikasi');
	}
	// Bersihkan notifikasi lama yang sudah dibaca (lebih dari 90 hari).
	q('DELETE FROM notifikasi WHERE pengguna_id = ? AND dibaca = 1 AND dibuat < ?', [$uid, date('Y-m-d H:i:s', strtotime('-90 days'))]);

	$daftar = semua('SELECT * FROM notifikasi WHERE pengguna_id = ? ORDER BY id DESC LIMIT 100', [$uid]);
	$belum = jumlah_notif($uid);
	$li = '';
	foreach ($daftar as $n) {
		$li .= '<li class="notif' . ($n['dibaca'] ? '' : ' notif--baru') . '"><a href="' . e(url('/notifikasi/' . $n['id'])) . '">'
			. '<span class="notif__pesan">' . e($n['pesan']) . '</span>'
			. '<span class="notif__waktu">' . e(tanggal($n['dibuat'])) . ($n['dibaca'] ? '' : ' · <strong>Baru</strong>') . '</span></a></li>';
	}
	$isi = '<section class="panel">
	<div class="judul-baris"><h1>Notifikasi</h1>'
		. ($belum ? '<form method="post">' . csrf_field() . '<input type="hidden" name="aksi" value="baca_semua"><button type="submit" class="tombol tombol--kedua tombol--kecil">Tandai semua dibaca</button></form>' : '') . '</div>
	' . ($li ? '<ul class="daftar-notif">' . $li . '</ul>' : '<p class="kosong">Belum ada notifikasi.</p>') . '
</section>';
	$menu = $u['peran'] === 'admin' ? menu_admin('') : menu_pengaju('');
	tampil('Notifikasi', $isi, ['panel' => $menu]);
}

function notifikasi_buka(bool $post, string $id): void
{
	$u = wajib_masuk();
	$n = satu('SELECT * FROM notifikasi WHERE id = ? AND pengguna_id = ?', [(int) $id, (int) $u['id']]);
	if (!$n) {
		arahkan('/notifikasi');
	}
	q('UPDATE notifikasi SET dibaca = 1 WHERE id = ?', [$n['id']]);
	$tujuan = (string) $n['tautan'];
	arahkan(str_starts_with($tujuan, '/') && !str_starts_with($tujuan, '//') ? $tujuan : '/notifikasi');
}

function notifikasi_jumlah(bool $post): void
{
	header('Content-Type: application/json');
	header('Cache-Control: no-store');
	$u = pengguna();
	echo json_encode(['jumlah' => $u ? jumlah_notif((int) $u['id']) : 0]);
}
