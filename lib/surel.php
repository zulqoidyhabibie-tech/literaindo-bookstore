<?php
// Pengiriman email. Memakai SMTP kotak surat Hostinger bila diatur
// (Admin → Pengaturan → Pengiriman email), atau fungsi mail() PHP sebagai cadangan.

declare(strict_types=1);

/**
 * Pengaturan SMTP. Urutan: config.php ('smtp' => [...]) di folder literaindo-data,
 * lalu isian admin di menu Pengaturan. Kosong berarti SMTP belum diatur.
 */
function smtp_pengaturan(): array
{
	$c = config()['smtp'] ?? null;
	if (is_array($c) && !empty($c['host'])) {
		$s = $c + ['port' => 465, 'user' => '', 'pass' => '', 'aman' => 'ssl'];
		$s['dari_config'] = true;
		return $s;
	}
	return [
		'host' => pengaturan('smtp_host'),
		'port' => (int) pengaturan('smtp_port', '465'),
		'user' => pengaturan('smtp_user'),
		'pass' => pengaturan('smtp_pass'),
		'aman' => pengaturan('smtp_aman', 'ssl'),
		'dari_config' => false,
	];
}

function smtp_siap(): bool
{
	$s = smtp_pengaturan();
	return $s['host'] !== '' && $s['user'] !== '' && $s['pass'] !== '';
}

/** Bisakah website mengirim email (SMTP atau mail())? */
function email_siap(): bool
{
	return smtp_siap() || (((config()['email_pengirim'] ?? '') !== '') && function_exists('mail'));
}

/**
 * Kirim email teks biasa. Mengembalikan true bila diterima server surat.
 * $galat berisi alasan bila gagal (untuk tombol "Kirim email uji").
 */
function kirim_email(string $ke, string $judul, string $isi, ?string &$galat = null): bool
{
	$galat = '';
	$nama = (string) ($GLOBALS['SITUS']['nama'] ?? '');
	try {
		if (smtp_siap()) {
			smtp_kirim(smtp_pengaturan(), $nama, $ke, $judul, $isi);
			return true;
		}
		$dari = config()['email_pengirim'] ?? '';
		if ($dari === '' || !function_exists('mail')) {
			throw new RuntimeException('Pengiriman email belum diatur.');
		}
		$header = 'From: ' . header_pengirim($nama) . " <$dari>\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64";
		if (!@mail($ke, header_nama($judul), chunk_split(base64_encode($isi)), $header)) {
			throw new RuntimeException('Fungsi mail() server menolak email.');
		}
		return true;
	} catch (Throwable $ex) {
		$galat = $ex->getMessage();
		error_log('[email] gagal ke ' . $ke . ': ' . $galat);
		return false;
	}
}

/** Nama pengirim untuk header From (diberi tanda kutip bila perlu). */
function header_pengirim(string $s): string
{
	return preg_match('/[^\x20-\x7e]/', $s) ? header_nama($s) : '"' . addcslashes($s, '"\\') . '"';
}

function header_nama(string $s): string
{
	return preg_match('/[^\x20-\x7e]/', $s) ? '=?UTF-8?B?' . base64_encode($s) . '?=' : $s;
}

/** Klien SMTP kecil (SSL port 465 atau STARTTLS port 587) dengan AUTH LOGIN. */
function smtp_kirim(array $s, string $nama, string $ke, string $judul, string $isi): void
{
	if (!filter_var($ke, FILTER_VALIDATE_EMAIL)) {
		throw new RuntimeException('Alamat tujuan tidak valid.');
	}
	$aman = $s['aman'] === 'tls' ? 'tls' : 'ssl';
	$alamat = ($aman === 'ssl' ? 'ssl://' : 'tcp://') . $s['host'] . ':' . (int) $s['port'];
	$ctx = stream_context_create(['ssl' => ['peer_name' => $s['host'], 'verify_peer' => true, 'verify_peer_name' => true]]);
	$sock = @stream_socket_client($alamat, $no, $pesan, 15, STREAM_CLIENT_CONNECT, $ctx);
	if (!$sock) {
		throw new RuntimeException("Tidak bisa terhubung ke {$s['host']}:{$s['port']} ($pesan).");
	}
	stream_set_timeout($sock, 15);
	$baca = function (array $kode) use ($sock): string {
		$semua = '';
		while (($baris = fgets($sock, 1024)) !== false) {
			$semua .= $baris;
			if (strlen($baris) < 4 || $baris[3] === ' ') {
				break;
			}
		}
		if (!in_array((int) substr($semua, 0, 3), $kode, true)) {
			throw new RuntimeException('Server surat menjawab: ' . trim($semua ?: '(tidak ada jawaban)'));
		}
		return $semua;
	};
	$kirim = function (string $perintah, array $kode) use ($sock, $baca): string {
		fwrite($sock, $perintah . "\r\n");
		return $baca($kode);
	};
	try {
		$baca([220]);
		$helo = preg_replace('/[^a-z0-9.-]/i', '', (string) parse_url(url_absolut('/'), PHP_URL_HOST)) ?: 'localhost';
		$kirim("EHLO $helo", [250]);
		if ($aman === 'tls') {
			$kirim('STARTTLS', [220]);
			if (!stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
				throw new RuntimeException('STARTTLS gagal.');
			}
			$kirim("EHLO $helo", [250]);
		}
		$kirim('AUTH LOGIN', [334]);
		$kirim(base64_encode($s['user']), [334]);
		try {
			$kirim(base64_encode($s['pass']), [235]);
		} catch (RuntimeException $ex) {
			throw new RuntimeException('Email atau kata sandi kotak surat salah. ' . $ex->getMessage());
		}
		$kirim('MAIL FROM:<' . $s['user'] . '>', [250]);
		$kirim('RCPT TO:<' . $ke . '>', [250, 251]);
		$kirim('DATA', [354]);
		$domain = substr(strrchr($s['user'], '@') ?: '@localhost', 1);
		$header = [
			'Date: ' . date('r'),
			'From: ' . header_pengirim($nama) . ' <' . $s['user'] . '>',
			'To: <' . $ke . '>',
			'Subject: ' . header_nama($judul),
			'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $domain . '>',
			'MIME-Version: 1.0',
			'Content-Type: text/plain; charset=UTF-8',
			'Content-Transfer-Encoding: base64',
		];
		fwrite($sock, implode("\r\n", $header) . "\r\n\r\n" . chunk_split(base64_encode($isi)) . ".\r\n");
		$baca([250]);
		fwrite($sock, "QUIT\r\n");
	} finally {
		fclose($sock);
	}
}
