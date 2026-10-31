<?php
// Panel keuangan: rekap dan unduhan data transaksi (hanya baca).
// Bisa dibuka oleh akun berperan "keuangan" dan "admin".

declare(strict_types=1);

const STATUS_BAYAR = ['menunggu' => 'Menunggu verifikasi', 'diterima' => 'Diterima', 'ditolak' => 'Ditolak'];
const TAHAP_BAYAR = [1 => 'Biaya awal', 2 => 'Pelunasan'];

/** Baca filter dari URL dan susun klausa WHERE. */
function filter_transaksi(): array
{
	$f = [
		'dari' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['dari'] ?? '') ? $_GET['dari'] : date('Y-m-01'),
		'sampai' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['sampai'] ?? '') ? $_GET['sampai'] : date('Y-m-d'),
		'tahap' => in_array($_GET['tahap'] ?? '', ['1', '2'], true) ? $_GET['tahap'] : '',
		'status' => isset(STATUS_BAYAR[$_GET['status'] ?? '']) ? $_GET['status'] : '',
		'cari' => trim((string) ($_GET['cari'] ?? '')),
	];
	$w = ['py.dibuat >= ?', 'py.dibuat <= ?'];
	$p = [$f['dari'] . ' 00:00:00', $f['sampai'] . ' 23:59:59'];
	if ($f['tahap'] !== '') {
		$w[] = 'py.tahap = ?';
		$p[] = (int) $f['tahap'];
	}
	if ($f['status'] !== '') {
		$w[] = 'py.status = ?';
		$p[] = $f['status'];
	}
	if ($f['cari'] !== '') {
		$w[] = '(pj.judul LIKE ? OR u.nama LIKE ? OR u.email LIKE ?)';
		array_push($p, "%{$f['cari']}%", "%{$f['cari']}%", "%{$f['cari']}%");
	}
	return [$f, implode(' AND ', $w), $p];
}

function data_transaksi(string $where, array $param): array
{
	return semua(
		"SELECT py.*, pj.judul, pj.status AS status_pengajuan, u.nama, u.email, u.whatsapp
		FROM pembayaran py
		JOIN pengajuan pj ON pj.id = py.pengajuan_id
		JOIN pengguna u ON u.id = pj.pengguna_id
		WHERE $where
		ORDER BY py.dibuat DESC, py.id DESC",
		$param
	);
}

function no_transaksi(array $t): string
{
	return 'TRX-' . date('Ymd', strtotime($t['dibuat'])) . '-' . str_pad((string) $t['id'], 4, '0', STR_PAD_LEFT);
}

function keuangan_transaksi(bool $post): void
{
	$u = wajib_masuk(['keuangan', 'admin']);
	[$f, $where, $param] = filter_transaksi();
	$data = data_transaksi($where, $param);

	$jumlah = ['diterima' => 0, 'menunggu' => 0, 'ditolak' => 0];
	$per_tahap = [1 => 0, 2 => 0];
	foreach ($data as $t) {
		$jumlah[$t['status']] += (int) $t['nominal'];
		if ($t['status'] === 'diterima') {
			$per_tahap[(int) $t['tahap']] += (int) $t['nominal'];
		}
	}
	// Piutang: tagihan pelunasan yang sudah dibuka tapi belum dibayar/diverifikasi.
	$piutang = satu("SELECT COUNT(*) AS n, COALESCE(SUM(tagihan_2), 0) AS total FROM pengajuan WHERE status IN ('MENUNGGU_BAYAR_2', 'VERIFIKASI_BAYAR_2')");

	$baris = '';
	foreach ($data as $t) {
		$kelas = ['diterima' => 'selesai', 'menunggu' => 'giliran', 'ditolak' => 'tolak'][$t['status']];
		$baris .= '<tr>
			<td>' . e(no_transaksi($t)) . '<br><span class="catatan">' . e(tanggal($t['dibuat'])) . '</span></td>
			<td><strong>' . e($t['nama']) . '</strong><br><span class="catatan">' . e($t['judul']) . '</span></td>
			<td>' . e(TAHAP_BAYAR[(int) $t['tahap']] ?? '') . '</td>
			<td class="angka">' . rupiah($t['nominal']) . '</td>
			<td><span class="lencana lencana--' . $kelas . '">' . e(STATUS_BAYAR[$t['status']]) . '</span>' . ($t['diverifikasi'] ? '<br><span class="catatan">' . e(tanggal($t['diverifikasi'])) . '</span>' : '') . ($t['status'] === 'ditolak' && $t['catatan'] ? '<br><span class="catatan">' . e($t['catatan']) . '</span>' : '') . '</td>
			<td>' . ($t['berkas_id'] ? '<a href="' . e(url('/berkas/' . $t['berkas_id'])) . '" target="_blank">Lihat</a>' : '') . '</td>
		</tr>';
	}

	$opsi = fn (array $daftar, string $pilih, string $semua) => '<option value="">' . $semua . '</option>' . implode('', array_map(fn ($k, $v) => '<option value="' . e((string) $k) . '"' . ((string) $k === $pilih ? ' selected' : '') . '>' . e($v) . '</option>', array_keys($daftar), $daftar));
	$qs = http_build_query(array_filter($f, fn ($v) => $v !== ''));

	$isi = '<section class="panel">
	<div class="judul-baris"><h1>Transaksi</h1><a class="tombol" href="' . e(url('/keuangan/unduh?' . $qs)) . '">' . ikon('unduh') . ' Unduh Excel (CSV)</a></div>
	<form method="get" class="filter filter--admin filter--keuangan">
		<label>Dari tanggal<input type="date" name="dari" value="' . e($f['dari']) . '"></label>
		<label>Sampai tanggal<input type="date" name="sampai" value="' . e($f['sampai']) . '"></label>
		<label>Jenis<select name="tahap">' . $opsi(TAHAP_BAYAR, $f['tahap'], 'Semua jenis') . '</select></label>
		<label>Status<select name="status">' . $opsi(STATUS_BAYAR, $f['status'], 'Semua status') . '</select></label>
		<label>Cari<input type="search" name="cari" placeholder="Nama penulis / judul" value="' . e($f['cari']) . '"></label>
		<button type="submit" class="tombol">Tampilkan</button>
	</form>
	<div class="kotak-status-grid kotak-status-grid--uang">
		<div class="kotak-status"><span class="kotak-status__angka">' . rupiah($jumlah['diterima']) . '</span><span>Pemasukan diterima</span></div>
		<div class="kotak-status"><span class="kotak-status__angka">' . rupiah($per_tahap[1]) . '</span><span>Biaya awal</span></div>
		<div class="kotak-status"><span class="kotak-status__angka">' . rupiah($per_tahap[2]) . '</span><span>Pelunasan</span></div>
		<div class="kotak-status' . ($jumlah['menunggu'] ? ' kotak-status--tindakan' : '') . '"><span class="kotak-status__angka">' . rupiah($jumlah['menunggu']) . '</span><span>Menunggu verifikasi</span></div>
		<div class="kotak-status"><span class="kotak-status__angka">' . rupiah($piutang['total']) . '</span><span>Tagihan pelunasan belum lunas (' . (int) $piutang['n'] . ' buku, semua periode)</span></div>
	</div>
	<p class="catatan">' . count($data) . ' transaksi pada ' . e(tanggal($f['dari'] . ' 00:00')) . ' s.d. ' . e(tanggal($f['sampai'] . ' 23:59')) . '. Pemasukan dihitung dari pembayaran yang sudah diterima admin.</p>
	' . ($baris ? '<div class="tabel-wadah"><table class="tabel"><thead><tr><th>No. transaksi</th><th>Penulis / judul</th><th>Jenis</th><th>Nominal</th><th>Status</th><th>Bukti</th></tr></thead><tbody>' . $baris . '</tbody></table></div>' : '<p class="kosong">Tidak ada transaksi pada periode ini.</p>') . '
</section>';
	tampil('Transaksi', $isi, ['panel' => menu_peran($u, '/keuangan')]);
}

function keuangan_unduh(bool $post): void
{
	wajib_masuk(['keuangan', 'admin']);
	[$f, $where, $param] = filter_transaksi();
	$data = data_transaksi($where, $param);

	header('Content-Type: text/csv; charset=UTF-8');
	header('Content-Disposition: attachment; filename="transaksi-literaindo-' . $f['dari'] . '-sd-' . $f['sampai'] . '.csv"');
	header('Cache-Control: no-store');
	$out = fopen('php://output', 'w');
	fwrite($out, "\xEF\xBB\xBF"); // BOM agar Excel membaca huruf dengan benar
	$tulis = function (array $baris) use ($out): void {
		// Cegah formula Excel dari isian pengguna (=, +, -, @).
		fputcsv($out, array_map(fn ($v) => is_string($v) && preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v, $baris), ';', '"', '');
	};
	$tulis(['No. Transaksi', 'Tanggal Bayar', 'Jenis', 'Nominal (Rp)', 'Status', 'Tanggal Verifikasi', 'Catatan', 'Nama Penulis', 'Email', 'WhatsApp', 'ID Pengajuan', 'Judul Buku', 'Status Pengajuan']);
	foreach ($data as $t) {
		$tulis([
			no_transaksi($t),
			$t['dibuat'],
			TAHAP_BAYAR[(int) $t['tahap']] ?? '',
			(int) $t['nominal'],
			STATUS_BAYAR[$t['status']] ?? $t['status'],
			(string) $t['diverifikasi'],
			$t['catatan'],
			$t['nama'],
			$t['email'],
			$t['whatsapp'],
			(int) $t['pengajuan_id'],
			$t['judul'],
			STATUS[$t['status_pengajuan']][0] ?? $t['status_pengajuan'],
		]);
	}
	fclose($out);
}
