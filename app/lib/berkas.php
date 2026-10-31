<?php
// Unggah dan unduh berkas. Semua berkas disimpan di DATA_DIR/berkas,
// di luar folder website; unduhan selalu lewat pemeriksaan hak akses.

declare(strict_types=1);

const JENIS_BERKAS = [
	'naskah'     => ['Naskah', ['pdf', 'doc', 'docx'], 30],
	'pernyataan' => ['Surat pernyataan keaslian', ['pdf', 'jpg', 'jpeg', 'png'], 5],
	'sampul'     => ['Sampul', ['jpg', 'jpeg', 'png', 'webp'], 10],
	'bukti_1'    => ['Bukti bayar biaya awal', ['jpg', 'jpeg', 'png', 'pdf'], 5],
	'bukti_2'    => ['Bukti bayar pelunasan', ['jpg', 'jpeg', 'png', 'pdf'], 5],
];

const MIME_DIIZINKAN = [
	'pdf'  => ['application/pdf'],
	'doc'  => ['application/msword', 'application/octet-stream', 'application/x-ole-storage', 'application/CDFV2'],
	'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
	'jpg'  => ['image/jpeg'],
	'jpeg' => ['image/jpeg'],
	'png'  => ['image/png'],
	'webp' => ['image/webp'],
];

/**
 * Simpan satu berkas dari $_FILES[$field]. Mengembalikan id berkas, null bila
 * tidak ada berkas, atau melempar RuntimeException berisi pesan untuk pengguna.
 */
function simpan_unggahan(string $field, string $jenis, int $pengajuan_id): ?int
{
	$f = $_FILES[$field] ?? null;
	if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
		return null;
	}
	[$label, $ext_ok, $maks_mb] = JENIS_BERKAS[$jenis];
	if ($f['error'] !== UPLOAD_ERR_OK) {
		throw new RuntimeException("$label gagal diunggah. Pastikan ukurannya di bawah {$maks_mb} MB.");
	}
	if ($f['size'] > $maks_mb * 1024 * 1024) {
		throw new RuntimeException("$label terlalu besar. Maksimal {$maks_mb} MB.");
	}
	$ext = strtolower(pathinfo((string) $f['name'], PATHINFO_EXTENSION));
	if (!in_array($ext, $ext_ok, true)) {
		throw new RuntimeException("$label harus berformat " . strtoupper(implode(', ', $ext_ok)) . '.');
	}
	$mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']) ?: '';
	if (!in_array($mime, MIME_DIIZINKAN[$ext], true)) {
		throw new RuntimeException("Isi berkas $label tidak sesuai dengan formatnya.");
	}

	$folder = DATA_DIR . '/berkas/' . $pengajuan_id;
	if (!is_dir($folder) && !mkdir($folder, 0750, true)) {
		throw new RuntimeException('Folder penyimpanan tidak dapat dibuat. Hubungi admin.');
	}
	$nama = $jenis . '-' . bin2hex(random_bytes(8)) . '.' . $ext;
	if (!move_uploaded_file($f['tmp_name'], "$folder/$nama")) {
		throw new RuntimeException("$label tidak dapat disimpan. Coba lagi.");
	}

	$u = pengguna();
	q(
		'INSERT INTO berkas (pengajuan_id, jenis, path, nama_asli, ukuran, pengunggah_id, dibuat) VALUES (?, ?, ?, ?, ?, ?, ?)',
		[$pengajuan_id, $jenis, "$pengajuan_id/$nama", mb_substr(basename((string) $f['name']), 0, 200), (int) $f['size'], $u ? $u['id'] : null, sekarang()]
	);
	return (int) db()->lastInsertId();
}

function berkas_terakhir(int $pengajuan_id, string $jenis): ?array
{
	return satu('SELECT * FROM berkas WHERE pengajuan_id = ? AND jenis = ? ORDER BY id DESC LIMIT 1', [$pengajuan_id, $jenis]);
}

function ukuran_berkas(int $b): string
{
	return $b >= 1048576 ? number_format($b / 1048576, 1, ',', '.') . ' MB' : max(1, (int) round($b / 1024)) . ' KB';
}

function kirim_berkas(string $path, string $nama_unduh, bool $inline = false, int $cache = 0): never
{
	$file = DATA_DIR . '/berkas/' . $path;
	if (!is_file($file)) {
		tidak_ditemukan();
	}
	$mime = (new finfo(FILEINFO_MIME_TYPE))->file($file) ?: 'application/octet-stream';
	header('Content-Type: ' . $mime);
	header('Content-Length: ' . filesize($file));
	header('X-Content-Type-Options: nosniff');
	header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . str_replace('"', '', $nama_unduh) . '"');
	header($cache ? "Cache-Control: public, max-age=$cache" : 'Cache-Control: private, no-store');
	readfile($file);
	exit;
}

/**
 * Salin sampul pengajuan menjadi sampul publik katalog. Mengembalikan URL
 * sampul (/media/sampul/...) atau string kosong.
 */
function terbitkan_sampul(int $pengajuan_id): string
{
	$b = berkas_terakhir($pengajuan_id, 'sampul');
	if (!$b) {
		return '';
	}
	$folder = DATA_DIR . '/berkas/sampul';
	if (!is_dir($folder)) {
		mkdir($folder, 0750, true);
	}
	$nama = 'p' . $pengajuan_id . '-' . bin2hex(random_bytes(4)) . '.' . pathinfo($b['path'], PATHINFO_EXTENSION);
	copy(DATA_DIR . '/berkas/' . $b['path'], "$folder/$nama");
	return '/media/sampul/' . $nama;
}

/** Unggah sampul langsung dari formulir katalog admin. */
function unggah_sampul_katalog(string $field): string
{
	$f = $_FILES[$field] ?? null;
	if (!$f || $f['error'] === UPLOAD_ERR_NO_FILE) {
		return '';
	}
	if ($f['error'] !== UPLOAD_ERR_OK || $f['size'] > 10 * 1024 * 1024) {
		throw new RuntimeException('Sampul gagal diunggah. Maksimal 10 MB.');
	}
	$ext = strtolower(pathinfo((string) $f['name'], PATHINFO_EXTENSION));
	$mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']) ?: '';
	if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true) || !in_array($mime, MIME_DIIZINKAN[$ext], true)) {
		throw new RuntimeException('Sampul harus berupa gambar JPG, PNG, atau WebP.');
	}
	$folder = DATA_DIR . '/berkas/sampul';
	if (!is_dir($folder)) {
		mkdir($folder, 0750, true);
	}
	$nama = 'k-' . bin2hex(random_bytes(6)) . '.' . $ext;
	if (!move_uploaded_file($f['tmp_name'], "$folder/$nama")) {
		throw new RuntimeException('Sampul tidak dapat disimpan.');
	}
	return '/media/sampul/' . $nama;
}
