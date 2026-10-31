<?php
// Masuk, daftar, keluar.

declare(strict_types=1);

function halaman_masuk(bool $post): void
{
	if (pengguna()) {
		arahkan(pengguna()['peran'] === 'admin' ? '/admin' : '/akun');
	}
	$email = '';
	$galat = '';
	if ($post) {
		$email = mb_strtolower(input('email'));
		$u = satu('SELECT * FROM pengguna WHERE email = ?', [$email]);
		if ($u && password_verify((string) ($_POST['sandi'] ?? ''), $u['sandi'])) {
			session_regenerate_id(true);
			$_SESSION['uid'] = (int) $u['id'];
			$kembali = $_SESSION['kembali'] ?? '';
			unset($_SESSION['kembali']);
			if ($kembali && str_starts_with($kembali, base() . '/') && !str_starts_with($kembali, '//')) {
				header('Location: ' . $kembali, true, 303);
				exit;
			}
			arahkan($u['peran'] === 'admin' ? '/admin' : '/akun');
		}
		usleep(700000);
		$galat = 'Email atau kata sandi salah.';
	}
	$isi = '<section class="panel panel--sempit">
	<h1>Masuk</h1>
	<p class="catatan">Untuk penulis yang mengajukan penerbitan buku dan admin Literaindo Press.</p>
	' . ($galat ? '<p class="pesan pesan--galat" role="alert">' . e($galat) . '</p>' : '') . '
	<form method="post" class="formulir">' . csrf_field() . '
		<label for="email">Email</label>
		<input type="email" id="email" name="email" required autocomplete="email" value="' . e($email) . '">
		<label for="sandi">Kata sandi</label>
		<input type="password" id="sandi" name="sandi" required autocomplete="current-password">
		<button type="submit" class="tombol">Masuk</button>
	</form>
	<p>Belum punya akun? <a href="' . e(url('/daftar')) . '">Daftar sebagai penulis</a></p>
	<p class="catatan">Lupa kata sandi? Hubungi admin melalui <a href="' . e(wa_link('Halo Literaindo Press, saya lupa kata sandi akun saya.')) . '">WhatsApp</a>.</p>
</section>';
	tampil('Masuk', $isi);
}

function halaman_daftar(bool $post): void
{
	if (pengguna()) {
		arahkan('/akun');
	}
	$v = ['nama' => '', 'email' => '', 'whatsapp' => ''];
	$galat = [];
	if ($post) {
		$v = ['nama' => input('nama'), 'email' => mb_strtolower(input('email')), 'whatsapp' => preg_replace('/[^0-9+]/', '', input('whatsapp'))];
		$sandi = (string) ($_POST['sandi'] ?? '');
		if (mb_strlen($v['nama']) < 3) {
			$galat[] = 'Isi nama lengkap.';
		}
		if (!filter_var($v['email'], FILTER_VALIDATE_EMAIL)) {
			$galat[] = 'Alamat email tidak valid.';
		} elseif (satu('SELECT id FROM pengguna WHERE email = ?', [$v['email']])) {
			$galat[] = 'Email ini sudah terdaftar. Silakan masuk.';
		}
		if (strlen((string) $v['whatsapp']) < 9) {
			$galat[] = 'Isi nomor WhatsApp yang aktif.';
		}
		if (strlen($sandi) < 8) {
			$galat[] = 'Kata sandi minimal 8 karakter.';
		}
		if (($sandi !== ($_POST['sandi2'] ?? ''))) {
			$galat[] = 'Konfirmasi kata sandi tidak sama.';
		}
		if (empty($_POST['setuju'])) {
			$galat[] = 'Centang persetujuan Syarat & Ketentuan.';
		}
		if (!$galat) {
			q('INSERT INTO pengguna (nama, email, whatsapp, sandi, peran, dibuat) VALUES (?, ?, ?, ?, ?, ?)', [$v['nama'], $v['email'], $v['whatsapp'], password_hash($sandi, PASSWORD_DEFAULT), 'pengaju', sekarang()]);
			session_regenerate_id(true);
			$_SESSION['uid'] = (int) db()->lastInsertId();
			kirim_email($v['email'], 'Selamat datang di Literaindo Press', "Halo {$v['nama']},\n\nAkun Anda sudah aktif. Ajukan naskah buku Anda di: " . url_absolut('/akun/pengajuan/baru') . "\n\nSalam,\nLiteraindo Press");
			pesan('Akun berhasil dibuat. Silakan ajukan buku Anda.');
			arahkan('/akun/pengajuan/baru');
		}
	}
	$daftar_galat = $galat ? '<ul class="pesan pesan--galat" role="alert">' . implode('', array_map(fn ($g) => '<li>' . e($g) . '</li>', $galat)) . '</ul>' : '';
	$isi = '<section class="panel panel--sempit">
	<h1>Daftar sebagai Penulis</h1>
	<p class="catatan">Buat akun untuk mengajukan penerbitan buku dan ISBN, membayar, serta memantau status naskah Anda.</p>
	' . $daftar_galat . '
	<form method="post" class="formulir">' . csrf_field() . '
		<label for="nama">Nama lengkap (beserta gelar)</label>
		<input id="nama" name="nama" required autocomplete="name" value="' . e($v['nama']) . '">
		<label for="email">Email</label>
		<input type="email" id="email" name="email" required autocomplete="email" value="' . e($v['email']) . '">
		<label for="whatsapp">Nomor WhatsApp</label>
		<input id="whatsapp" name="whatsapp" required inputmode="tel" autocomplete="tel" placeholder="08xxxxxxxxxx" value="' . e($v['whatsapp']) . '">
		<label for="sandi">Kata sandi (minimal 8 karakter)</label>
		<input type="password" id="sandi" name="sandi" required minlength="8" autocomplete="new-password">
		<label for="sandi2">Ulangi kata sandi</label>
		<input type="password" id="sandi2" name="sandi2" required minlength="8" autocomplete="new-password">
		<label class="centang"><input type="checkbox" name="setuju" id="setuju" value="1"> Saya menyetujui <a href="' . e(url('/syarat-ketentuan/')) . '" target="_blank">Syarat &amp; Ketentuan</a>, termasuk biaya awal yang tidak dapat dikembalikan.</label>
		<button type="submit" class="tombol">Buat Akun</button>
	</form>
	<p>Sudah punya akun? <a href="' . e(url('/masuk')) . '">Masuk</a></p>
</section>';
	tampil('Daftar', $isi);
}

function halaman_keluar(bool $post): void
{
	if ($post) {
		$_SESSION = [];
		session_destroy();
	}
	arahkan('/');
}
