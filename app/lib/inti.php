<?php
// Inti aplikasi: konfigurasi, database, sesi, dan fungsi bantu.

declare(strict_types=1);

const APP_DIR = __DIR__ . '/..';

// Folder data (config.php, berkas unggahan) berada DI LUAR folder website,
// supaya tidak terhapus saat deploy dan tidak bisa diakses dari internet.
// Di Hostinger: /home/uXXX/domains/literaindo.com/literaindo-data
define('DATA_DIR', rtrim(getenv('LITERAINDO_DATA') ?: dirname(__DIR__, 3) . '/literaindo-data', '/'));

$SITUS = json_decode((string) file_get_contents(APP_DIR . '/data/situs.json'), true);

const STATUS = [
	'DRAF'               => ['Draf', 'Formulir belum dikirim', 'pengaju'],
	'MENUNGGU_BAYAR_1'   => ['Menunggu biaya awal', 'Silakan bayar biaya awal', 'pengaju'],
	'VERIFIKASI_BAYAR_1' => ['Verifikasi biaya awal', 'Bukti bayar sedang dicek bagian keuangan', 'admin'],
	'DITINJAU'           => ['Ditinjau redaksi', 'Naskah sedang diproses redaksi', 'admin'],
	'PERLU_REVISI'       => ['Perlu revisi', 'Ada catatan dari redaksi; unggah naskah perbaikan', 'pengaju'],
	'PROSES_ISBN'        => ['Proses ISBN', 'Sedang diajukan ke Perpusnas', 'admin'],
	'MENUNGGU_BAYAR_2'   => ['Menunggu pelunasan', 'ISBN sudah terbit; silakan bayar pelunasan', 'pengaju'],
	'VERIFIKASI_BAYAR_2' => ['Verifikasi pelunasan', 'Bukti pelunasan sedang dicek bagian keuangan', 'admin'],
	'LUNAS'              => ['Lunas, antre cetak', 'Pelunasan diterima, buku masuk antrean cetak', 'admin'],
	'SELESAI'            => ['Selesai', 'Buku sudah dicetak dan dikirim', ''],
	'DITOLAK'            => ['Ditolak', 'Pengajuan tidak dapat diproses', ''],
];

function config(): ?array
{
	static $cfg = false;
	if ($cfg === false) {
		$file = DATA_DIR . '/config.php';
		$cfg = is_file($file) ? require $file : null;
	}
	return $cfg;
}

function db(): PDO
{
	static $pdo = null;
	if ($pdo) {
		return $pdo;
	}
	$c = config();
	if (!$c) {
		throw new RuntimeException('Aplikasi belum dipasang.');
	}
	$pdo = buat_pdo($c['db']);
	return $pdo;
}

function buat_pdo(array $db): PDO
{
	if (($db['driver'] ?? 'mysql') === 'sqlite') {
		$pdo = new PDO('sqlite:' . $db['path']);
		$pdo->exec('PRAGMA foreign_keys = ON');
	} else {
		$dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $db['host'], $db['name']);
		$pdo = new PDO($dsn, $db['user'], $db['pass']);
	}
	$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
	return $pdo;
}

function q(string $sql, array $p = []): PDOStatement
{
	$st = db()->prepare($sql);
	$st->execute($p);
	return $st;
}

function satu(string $sql, array $p = []): ?array
{
	$r = q($sql, $p)->fetch();
	return $r ?: null;
}

function semua(string $sql, array $p = []): array
{
	return q($sql, $p)->fetchAll();
}

function sekarang(): string
{
	return date('Y-m-d H:i:s');
}

function pengaturan(string $k, string $bawaan = ''): string
{
	$r = satu('SELECT v FROM pengaturan WHERE k = ?', [$k]);
	return $r ? (string) $r['v'] : $bawaan;
}

function simpan_pengaturan(string $k, string $v): void
{
	if (satu('SELECT k FROM pengaturan WHERE k = ?', [$k])) {
		q('UPDATE pengaturan SET v = ? WHERE k = ?', [$v, $k]);
	} else {
		q('INSERT INTO pengaturan (k, v) VALUES (?, ?)', [$k, $v]);
	}
}

// ---------- Teks & URL ----------

function e($s): string
{
	return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function rupiah($n): string
{
	return 'Rp' . number_format((int) $n, 0, ',', '.');
}

function tanggal(?string $t): string
{
	if (!$t) {
		return '';
	}
	$bulan = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
	$ts = strtotime($t);
	return date('j', $ts) . ' ' . $bulan[(int) date('n', $ts)] . ' ' . date('Y, H.i', $ts);
}

function base(): string
{
	static $b = null;
	if ($b === null) {
		$b = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
	}
	return $b;
}

function url(string $path = '/'): string
{
	return base() . $path;
}

function slugify(string $s): string
{
	$s = strtolower(trim($s));
	$s = preg_replace('/[^a-z0-9]+/', '-', $s);
	return trim((string) $s, '-') ?: 'buku';
}

function wa_link(string $pesan = ''): string
{
	global $SITUS;
	return 'https://wa.me/' . $SITUS['whatsapp'] . ($pesan !== '' ? '?text=' . rawurlencode($pesan) : '');
}

function arahkan(string $path): never
{
	header('Location: ' . url($path), true, 303);
	exit;
}

function tidak_ditemukan(): never
{
	http_response_code(404);
	tampil('Halaman tidak ditemukan', '<section class="panel konten"><h1>Halaman tidak ditemukan</h1><p>Maaf, halaman yang Anda cari tidak ada. Silakan kembali ke <a href="' . e(url('/')) . '">katalog buku</a>.</p></section>');
	exit;
}

// ---------- Sesi, CSRF, pesan ----------

function mulai_sesi(): void
{
	if (session_status() === PHP_SESSION_ACTIVE) {
		return;
	}
	$https = ($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
	session_name('literaindo');
	session_set_cookie_params(['lifetime' => 0, 'path' => base() ?: '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax']);
	session_start();
}

function csrf(): string
{
	if (empty($_SESSION['csrf'])) {
		$_SESSION['csrf'] = bin2hex(random_bytes(16));
	}
	return $_SESSION['csrf'];
}

function csrf_field(): string
{
	return '<input type="hidden" name="_csrf" value="' . e(csrf()) . '">';
}

function cek_csrf(): void
{
	if (!hash_equals($_SESSION['csrf'] ?? '', (string) ($_POST['_csrf'] ?? ''))) {
		http_response_code(400);
		exit('Sesi formulir kedaluwarsa. Muat ulang halaman lalu coba lagi.');
	}
}

function pesan(string $teks, string $jenis = 'ok'): void
{
	$_SESSION['pesan'][] = [$jenis, $teks];
}

function ambil_pesan(): string
{
	$out = '';
	foreach ($_SESSION['pesan'] ?? [] as [$jenis, $teks]) {
		$out .= '<p class="pesan pesan--' . e($jenis) . '" role="status">' . e($teks) . '</p>';
	}
	unset($_SESSION['pesan']);
	return $out;
}

function pengguna(): ?array
{
	static $u = false;
	if ($u === false) {
		$u = !empty($_SESSION['uid']) ? satu('SELECT * FROM pengguna WHERE id = ?', [$_SESSION['uid']]) : null;
	}
	return $u;
}

const PERAN = ['pengaju' => 'Penulis (pengaju)', 'keuangan' => 'Keuangan', 'admin' => 'Admin'];

/** Halaman awal tiap peran setelah masuk. */
function beranda_peran(string $peran): string
{
	return match ($peran) {
		'admin' => '/admin',
		'keuangan' => '/keuangan',
		default => '/akun',
	};
}

/** Menu panel sesuai peran. */
function menu_peran(array $u, string $aktif = ''): string
{
	return match ($u['peran']) {
		'admin' => menu_admin($aktif),
		'keuangan' => menu_keuangan($aktif),
		default => menu_pengaju($aktif),
	};
}

function wajib_masuk(string|array|null $peran = null): array
{
	$u = pengguna();
	if (!$u) {
		$_SESSION['kembali'] = $_SERVER['REQUEST_URI'] ?? '';
		pesan('Silakan masuk terlebih dahulu.', 'info');
		arahkan('/masuk');
	}
	if ($peran && !in_array($u['peran'], (array) $peran, true)) {
		http_response_code(403);
		tampil('Akses ditolak', '<section class="panel konten"><h1>Akses ditolak</h1><p>Akun Anda tidak memiliki akses ke halaman ini. <a href="' . e(url(beranda_peran($u['peran']))) . '">Kembali ke panel Anda</a>.</p></section>');
		exit;
	}
	return $u;
}

function input(string $k, string $bawaan = ''): string
{
	return trim((string) ($_POST[$k] ?? $bawaan));
}

// ---------- Riwayat & notifikasi ----------

function catat(int $pengajuan_id, ?int $user_id, string $isi, bool $sistem = false): void
{
	q('INSERT INTO catatan (pengajuan_id, pengguna_id, isi, sistem, dibuat) VALUES (?, ?, ?, ?, ?)', [$pengajuan_id, $user_id, $isi, $sistem ? 1 : 0, sekarang()]);
}

function ubah_status(array $p, string $baru, ?string $keterangan = null): void
{
	$u = pengguna();
	q('UPDATE pengajuan SET status = ?, diubah = ? WHERE id = ?', [$baru, sekarang(), $p['id']]);
	catat((int) $p['id'], $u ? (int) $u['id'] : null, 'Status: ' . STATUS[$baru][0] . ($keterangan ? '. ' . $keterangan : ''), true);
	notif_pengajuan($p, STATUS[$baru][0] . ($keterangan ? '. ' . $keterangan : ''));

	$pemilik = satu('SELECT * FROM pengguna WHERE id = ?', [$p['pengguna_id']]);
	if ($pemilik) {
		kirim_email(
			$pemilik['email'],
			'Status pengajuan "' . $p['judul'] . '": ' . STATUS[$baru][0],
			"Halo {$pemilik['nama']},\n\nStatus pengajuan buku \"{$p['judul']}\" sekarang: " . STATUS[$baru][0] . ".\n" . STATUS[$baru][1] . ".\n"
			. ($keterangan ? "\nCatatan: $keterangan\n" : '')
			. "\nLihat detailnya di: " . url_absolut('/akun/pengajuan/' . $p['id']) . "\n\nSalam,\nLiteraindo Press"
		);
	}
}

// ---------- Notifikasi (ikon lonceng) ----------

function notif(int $uid, string $pesan, string $tautan = ''): void
{
	q('INSERT INTO notifikasi (pengguna_id, pesan, tautan, dibaca, dibuat) VALUES (?, ?, ?, 0, ?)', [$uid, mb_substr($pesan, 0, 500), $tautan, sekarang()]);
}

function notif_admin(string $pesan, string $tautan = ''): void
{
	foreach (semua("SELECT id FROM pengguna WHERE peran = 'admin'") as $a) {
		notif((int) $a['id'], $pesan, $tautan);
	}
}

function notif_keuangan(string $pesan, string $tautan = '/keuangan'): void
{
	foreach (semua("SELECT id FROM pengguna WHERE peran = 'keuangan'") as $a) {
		notif((int) $a['id'], $pesan, $tautan);
	}
}

/** Beri tahu pihak lawan: penulis bila admin yang bertindak, admin bila penulis yang bertindak. */
function notif_pengajuan(array $p, string $pesan): void
{
	$u = pengguna();
	if ($u && $u['peran'] === 'pengaju') {
		notif_admin('"' . $p['judul'] . '" (' . $u['nama'] . '): ' . $pesan, '/admin/pengajuan/' . $p['id']);
	} else {
		notif((int) $p['pengguna_id'], '"' . $p['judul'] . '": ' . $pesan, '/akun/pengajuan/' . $p['id']);
	}
}

function jumlah_notif(int $uid): int
{
	return (int) (satu('SELECT COUNT(*) AS n FROM notifikasi WHERE pengguna_id = ? AND dibaca = 0', [$uid])['n'] ?? 0);
}

function url_absolut(string $path): string
{
	global $SITUS;
	return rtrim($SITUS['url'], '/') . $path;
}

function kirim_email(string $ke, string $judul, string $isi): void
{
	$dari = config()['email_pengirim'] ?? '';
	if ($dari === '' || !function_exists('mail')) {
		return;
	}
	$header = "From: Literaindo Press <$dari>\r\nContent-Type: text/plain; charset=UTF-8";
	@mail($ke, '=?UTF-8?B?' . base64_encode($judul) . '?=', $isi, $header);
}
