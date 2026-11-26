<?php
// Masuk, daftar, keluar.

declare(strict_types=1);

function halaman_masuk(bool $post): void
{
	if (pengguna()) {
		arahkan(beranda_peran(pengguna()['peran']));
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
			arahkan(beranda_peran($u['peran']));
		}
		usleep(700000);
		$galat = 'Email atau kata sandi salah.';
	}
	$isi = '<section class="panel panel--sempit">
	<h1>Masuk</h1>
	<p class="catatan">Masuk ke akun Anda di ' . e($GLOBALS['SITUS']['nama']) . '.</p>
	' . ($galat ? '<p class="pesan pesan--galat" role="alert">' . e($galat) . '</p>' : '') . '
	<form method="post" class="formulir">' . csrf_field() . '
		<label for="email">Email</label>
		<input type="email" id="email" name="email" required autocomplete="email" value="' . e($email) . '">
		<label for="sandi">Kata sandi</label>
		<input type="password" id="sandi" name="sandi" required autocomplete="current-password">
		<button type="submit" class="tombol">Masuk</button>
	</form>
	' . (pendaftaran_dibuka() ? '<p>Belum punya akun? <a href="' . e(url('/daftar')) . '">Daftar</a></p>' : '') . '
	<p class="catatan"><a href="' . e(url('/lupa-sandi')) . '">Lupa kata sandi?</a></p>
</section>';
	tampil('Masuk', $isi);
}

/** Pendaftaran akun mandiri hanya dibuka bila halaman layanan ditampilkan; admin tetap bisa membuat akun di menu Pengguna. */
function pendaftaran_dibuka(): bool
{
	return layanan_tampil('terbit') || layanan_tampil('hki');
}

function halaman_daftar(bool $post): void
{
	if (pengguna()) {
		arahkan('/akun');
	}
	if (!pendaftaran_dibuka()) {
		tidak_ditemukan();
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
			kirim_email($v['email'], 'Selamat datang di ' . $GLOBALS['SITUS']['nama'], "Halo {$v['nama']},\n\nAkun Anda sudah aktif. Ajukan naskah buku Anda di: " . url_absolut('/akun/pengajuan/baru') . "\n\nSalam,\n" . $GLOBALS['SITUS']['nama']);
			notif_admin('Penulis baru mendaftar: ' . $v['nama'] . ' (' . $v['email'] . ')', '/admin/pengguna');
			$kembali = (string) ($_SESSION['kembali'] ?? '');
			unset($_SESSION['kembali']);
			if (str_starts_with($kembali, base() . '/akun/hki')) {
				pesan('Akun berhasil dibuat. Silakan isi formulir HKI.');
				header('Location: ' . $kembali, true, 303);
				exit;
			}
			pesan('Akun berhasil dibuat. Silakan ajukan buku Anda.');
			arahkan('/akun/pengajuan/baru');
		}
	}
	$daftar_galat = $galat ? '<ul class="pesan pesan--galat" role="alert">' . implode('', array_map(fn ($g) => '<li>' . e($g) . '</li>', $galat)) . '</ul>' : '';
	$isi = '<section class="panel panel--sempit">
	<h1>Daftar Akun</h1>
	<p class="catatan">Buat akun untuk mengajukan naskah atau layanan, membayar, serta memantau statusnya.</p>
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

const RESET_MENIT = 60;      // masa berlaku tautan atur ulang
const RESET_PER_JAM = 3;     // batas permintaan per akun per jam

function halaman_lupa_sandi(bool $post): void
{
	if (pengguna()) {
		arahkan(beranda_peran(pengguna()['peran']));
	}
	$terkirim = false;
	$email = '';
	if ($post) {
		$mulai = microtime(true);
		$email = mb_strtolower(input('email'));
		$u = filter_var($email, FILTER_VALIDATE_EMAIL) ? satu('SELECT * FROM pengguna WHERE email = ?', [$email]) : null;
		if ($u) {
			$batas = date('Y-m-d H:i:s', time() - 3600);
			$jumlah = (int) (satu('SELECT COUNT(*) AS n FROM reset_sandi WHERE pengguna_id = ? AND dibuat > ?', [$u['id'], $batas])['n'] ?? 0);
			if ($jumlah < RESET_PER_JAM) {
				$token = bin2hex(random_bytes(32));
				q('INSERT INTO reset_sandi (pengguna_id, token_hash, kedaluwarsa, dipakai, dibuat) VALUES (?, ?, ?, 0, ?)', [
					$u['id'], hash('sha256', $token), date('Y-m-d H:i:s', time() + RESET_MENIT * 60), sekarang(),
				]);
				$nama = $GLOBALS['SITUS']['nama'];
				kirim_email($u['email'], 'Atur ulang kata sandi akun ' . $nama,
					"Halo {$u['nama']},\n\n"
					. "Kami menerima permintaan untuk mengatur ulang kata sandi akun Anda di $nama.\n"
					. "Buka tautan berikut untuk membuat kata sandi baru:\n\n"
					. url_absolut('/atur-sandi/' . $token) . "\n\n"
					. 'Tautan ini hanya bisa dipakai sekali dan berlaku ' . RESET_MENIT . " menit.\n"
					. "Jika Anda tidak meminta ini, abaikan email ini; kata sandi Anda tidak berubah.\n\n"
					. "Salam,\n$nama");
			}
		}
		// Waktu jawaban dibuat sama supaya tidak bisa ditebak apakah email terdaftar.
		$sisa = 1.5 - (microtime(true) - $mulai);
		if ($sisa > 0) {
			usleep((int) ($sisa * 1000000));
		}
		$terkirim = true;
	}
	$wa = '<a href="' . e(wa_link('Halo ' . $GLOBALS['SITUS']['nama'] . ', saya lupa kata sandi akun saya.')) . '">WhatsApp</a>';
	if ($terkirim) {
		$isi = '<section class="panel panel--sempit">
	<h1>Cek email Anda</h1>
	<p>Jika <strong>' . e($email) . '</strong> terdaftar, kami sudah mengirim tautan untuk membuat kata sandi baru. Tautan berlaku ' . RESET_MENIT . ' menit dan hanya bisa dipakai sekali.</p>
	<p class="catatan">Belum masuk dalam beberapa menit? Periksa folder <em>Spam</em> atau <em>Promosi</em>, pastikan email yang diketik sama dengan email akun, lalu <a href="' . e(url('/lupa-sandi')) . '">coba lagi</a>. Jika tetap tidak ada, hubungi admin melalui ' . $wa . '.</p>
	<p><a href="' . e(url('/masuk')) . '">Kembali ke halaman masuk</a></p>
</section>';
	} else {
		$isi = '<section class="panel panel--sempit">
	<h1>Lupa Kata Sandi</h1>
	<p class="catatan">Masukkan email akun Anda. Kami akan mengirim tautan untuk membuat kata sandi baru.</p>
	<form method="post" class="formulir">' . csrf_field() . '
		<label for="email">Email</label>
		<input type="email" id="email" name="email" required autocomplete="email" autofocus>
		<button type="submit" class="tombol">Kirim Tautan</button>
	</form>
	<p><a href="' . e(url('/masuk')) . '">Kembali ke halaman masuk</a></p>
</section>';
	}
	tampil('Lupa Kata Sandi', $isi);
}

function halaman_atur_sandi(bool $post, string $token): void
{
	header('Cache-Control: no-store');
	header('X-Robots-Tag: noindex');
	$r = satu('SELECT r.*, u.nama, u.email FROM reset_sandi r JOIN pengguna u ON u.id = r.pengguna_id WHERE r.token_hash = ?', [hash('sha256', $token)]);
	if (!$r || (int) $r['dipakai'] === 1 || $r['kedaluwarsa'] < sekarang()) {
		http_response_code(410);
		tampil('Tautan tidak berlaku', '<section class="panel panel--sempit">
	<h1>Tautan tidak berlaku</h1>
	<p>Tautan atur ulang kata sandi ini sudah dipakai atau sudah lewat ' . RESET_MENIT . ' menit.</p>
	<p><a class="tombol" href="' . e(url('/lupa-sandi')) . '">Minta tautan baru</a></p>
</section>');
		return;
	}
	$galat = '';
	if ($post) {
		$sandi = (string) ($_POST['sandi'] ?? '');
		if (strlen($sandi) < 8) {
			$galat = 'Kata sandi minimal 8 karakter.';
		} elseif ($sandi !== ($_POST['sandi2'] ?? '')) {
			$galat = 'Konfirmasi kata sandi tidak sama.';
		} else {
			q('UPDATE pengguna SET sandi = ? WHERE id = ?', [password_hash($sandi, PASSWORD_DEFAULT), $r['pengguna_id']]);
			// Semua tautan lain milik akun ini ikut tidak berlaku.
			q('UPDATE reset_sandi SET dipakai = 1 WHERE pengguna_id = ?', [$r['pengguna_id']]);
			$nama = $GLOBALS['SITUS']['nama'];
			kirim_email($r['email'], 'Kata sandi akun ' . $nama . ' sudah diganti',
				"Halo {$r['nama']},\n\nKata sandi akun Anda di $nama baru saja diganti melalui tautan atur ulang kata sandi.\n"
				. "Jika bukan Anda yang melakukannya, segera hubungi admin.\n\nSalam,\n$nama");
			$_SESSION = [];
			session_regenerate_id(true);
			pesan('Kata sandi baru tersimpan. Silakan masuk.');
			arahkan('/masuk');
		}
	}
	$isi = '<section class="panel panel--sempit">
	<h1>Buat Kata Sandi Baru</h1>
	<p class="catatan">Untuk akun <strong>' . e($r['email']) . '</strong>.</p>
	' . ($galat ? '<p class="pesan pesan--galat" role="alert">' . e($galat) . '</p>' : '') . '
	<form method="post" class="formulir">' . csrf_field() . '
		<input type="email" name="email" value="' . e($r['email']) . '" autocomplete="username" hidden>
		<label for="sandi">Kata sandi baru (minimal 8 karakter)</label>
		<input type="password" id="sandi" name="sandi" required minlength="8" autocomplete="new-password" autofocus>
		<label for="sandi2">Ulangi kata sandi baru</label>
		<input type="password" id="sandi2" name="sandi2" required minlength="8" autocomplete="new-password">
		<button type="submit" class="tombol">Simpan Kata Sandi</button>
	</form>
</section>';
	tampil('Kata Sandi Baru', $isi);
}

function halaman_keluar(bool $post): void
{
	if ($post && !empty($_SESSION['admin_asal'])) {
		kembali_ke_admin(true);
	}
	if ($post) {
		$_SESSION = [];
		session_destroy();
	}
	arahkan('/');
}
