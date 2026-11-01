<?php
// Pemasangan pertama: buat database, akun admin, dan config.php.
// Halaman ini hanya bisa dibuka selama config.php belum ada.

declare(strict_types=1);

function halaman_pasang(bool $post): void
{
	$sqlite = getenv('LITERAINDO_SQLITE') ?: ''; // hanya untuk uji lokal
	$v = ['host' => 'localhost', 'name' => '', 'user' => '', 'email_pengirim' => '', 'admin_nama' => '', 'admin_email' => ''];
	$galat = [];

	if ($post) {
		cek_csrf();
		foreach ($v as $k => $_) {
			$v[$k] = input($k);
		}
		$sandi = (string) ($_POST['admin_sandi'] ?? '');
		$db = $sqlite
			? ['driver' => 'sqlite', 'path' => $sqlite]
			: ['driver' => 'mysql', 'host' => $v['host'], 'name' => $v['name'], 'user' => $v['user'], 'pass' => (string) ($_POST['pass'] ?? '')];

		if (!$sqlite && ($v['name'] === '' || $v['user'] === '')) {
			$galat[] = 'Isi nama database dan nama pengguna database.';
		}
		if (mb_strlen($v['admin_nama']) < 3 || !filter_var($v['admin_email'], FILTER_VALIDATE_EMAIL)) {
			$galat[] = 'Isi nama dan email admin dengan benar.';
		}
		if (strlen($sandi) < 8) {
			$galat[] = 'Kata sandi admin minimal 8 karakter.';
		}
		if ($v['email_pengirim'] !== '' && !filter_var($v['email_pengirim'], FILTER_VALIDATE_EMAIL)) {
			$galat[] = 'Email pengirim tidak valid (boleh dikosongkan).';
		}
		if (!is_dir(DATA_DIR) && !@mkdir(DATA_DIR, 0750, true)) {
			$galat[] = 'Folder data tidak bisa dibuat: ' . DATA_DIR;
		}

		$pdo = null;
		if (!$galat) {
			try {
				$pdo = buat_pdo($db);
			} catch (Throwable $t) {
				$galat[] = 'Tidak bisa terhubung ke database. Periksa nama database, pengguna, dan kata sandinya. (' . $t->getMessage() . ')';
			}
		}

		if (!$galat && $pdo) {
			try {
				$pasang_db = function (string $sql, array $p = []) use ($pdo): void {
					$pdo->prepare($sql)->execute($p);
				};
				$sudah = false;
				try {
					$pdo->query('SELECT 1 FROM pengguna LIMIT 1');
					$sudah = true; // tabel sudah ada (pasang ulang): jangan buat ulang
				} catch (Throwable) {
					$sudah = false;
				}
				if (!$sudah) {
					foreach (skema($db['driver']) as $sql) {
						$pdo->exec($sql);
					}
					$pasang_db('INSERT INTO pengaturan (k, v) VALUES (?, ?)', ['biaya_awal', '250000']);
					$awal = json_decode((string) file_get_contents(APP_DIR . '/data/buku-awal.json'), true) ?: [];
					foreach ($awal as $b) {
						$pasang_db(
							'INSERT INTO buku (slug, judul, subjudul, penulis, editor, kategori, tahun, status, isbn, eisbn, edisi, halaman, ukuran, bahasa, harga_cetak, harga_ebook, sampul, deskripsi, tampil, dibuat) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)',
							[$b['slug'], $b['judul'], $b['subjudul'] ?? '', implode("\n", (array) $b['penulis']), $b['editor'] ?? '', $b['kategori'] ?? '', $b['tahun'] ?? '', $b['status'] ?? 'terbit', $b['isbn'] ?? '', $b['eisbn'] ?? '', $b['edisi'] ?? '', $b['halaman'] ?? '', $b['ukuran'] ?? '', $b['bahasa'] ?? 'Indonesia', (int) ($b['harga_cetak'] ?? 0), (int) ($b['harga_ebook'] ?? 0), $b['sampul'] ?? '', $b['deskripsi'] ?? '', ($b['tanggal_tambah'] ?? date('Y-m-d')) . ' 00:00:00']
						);
					}
				}
				$email = mb_strtolower($v['admin_email']);
				$ada = $pdo->prepare('SELECT id FROM pengguna WHERE email = ?');
				$ada->execute([$email]);
				if ($ada->fetchColumn()) {
					$pasang_db('UPDATE pengguna SET peran = ?, sandi = ? WHERE email = ?', ['admin', password_hash($sandi, PASSWORD_DEFAULT), $email]);
				} else {
					$pasang_db('INSERT INTO pengguna (nama, email, whatsapp, sandi, peran, dibuat) VALUES (?, ?, ?, ?, ?, ?)', [$v['admin_nama'], $email, '', password_hash($sandi, PASSWORD_DEFAULT), 'admin', sekarang()]);
				}

				@mkdir(DATA_DIR . '/berkas', 0750, true);
				@file_put_contents(DATA_DIR . '/.htaccess', "Require all denied\n");
				$isi = "<?php\n// Dibuat otomatis oleh halaman /pasang. Jangan dibagikan.\nreturn " . var_export(['db' => $db, 'email_pengirim' => $v['email_pengirim']], true) . ";\n";
				if (file_put_contents(DATA_DIR . '/config.php', $isi) === false) {
					throw new RuntimeException('config.php tidak bisa ditulis di ' . DATA_DIR);
				}
				@chmod(DATA_DIR . '/config.php', 0640);

				pesan('Pemasangan selesai. Silakan masuk dengan akun admin.');
				arahkan('/masuk');
			} catch (Throwable $t) {
				$galat[] = 'Gagal memasang: ' . $t->getMessage();
			}
		}
	}

	$daftar_galat = $galat ? '<ul class="pesan pesan--galat" role="alert">' . implode('', array_map(fn ($g) => '<li>' . e($g) . '</li>', $galat)) . '</ul>' : '';
	$bagian_db = $sqlite
		? '<p class="pesan pesan--info">Mode uji lokal: memakai SQLite di ' . e($sqlite) . '.</p>'
		: '<fieldset><legend>1. Database MySQL</legend>
			<p class="catatan">Buat dulu di hPanel: <strong>Database → Manajemen</strong>. Salin nama database, nama pengguna, dan kata sandinya ke sini.</p>
			<label for="host">Host</label><input id="host" name="host" value="' . e($v['host']) . '">
			<label for="name">Nama database</label><input id="name" name="name" required placeholder="u123456789_literaindo" value="' . e($v['name']) . '">
			<label for="user">Nama pengguna database</label><input id="user" name="user" required placeholder="u123456789_admin" value="' . e($v['user']) . '">
			<label for="pass">Kata sandi database</label><input type="password" id="pass" name="pass">
		</fieldset>';

	$isi = '<section class="panel panel--sempit">
	<h1>Pasang Panel Literaindo Press</h1>
	<p class="catatan">Langkah ini hanya dilakukan sekali. Setelah selesai, halaman ini otomatis tertutup.</p>
	' . $daftar_galat . '
	<form method="post" class="formulir" autocomplete="off">' . csrf_field() . $bagian_db . '
		<fieldset><legend>2. Akun admin pertama</legend>
			<label for="admin_nama">Nama</label><input id="admin_nama" name="admin_nama" required value="' . e($v['admin_nama']) . '">
			<label for="admin_email">Email</label><input type="email" id="admin_email" name="admin_email" required value="' . e($v['admin_email']) . '">
			<label for="admin_sandi">Kata sandi (minimal 8 karakter)</label><input type="password" id="admin_sandi" name="admin_sandi" required minlength="8" autocomplete="new-password">
		</fieldset>
		<fieldset><legend>3. Email notifikasi (opsional)</legend>
			<p class="catatan">Alamat pengirim email otomatis, misalnya <strong>admin@literaindo.com</strong> (buat dulu di hPanel: Email). Kosongkan jika belum ada; notifikasi email dimatikan.</p>
			<label for="email_pengirim">Email pengirim</label><input type="email" id="email_pengirim" name="email_pengirim" value="' . e($v['email_pengirim']) . '">
		</fieldset>
		<p class="catatan">Data disimpan di folder aman di luar website: ' . e(DATA_DIR) . '</p>
		<button type="submit" class="tombol">Pasang Sekarang</button>
	</form>
</section>';
	tampil('Pemasangan', $isi);
}
