<?php
// Struktur database. Ditulis agar jalan di MySQL (Hostinger) dan SQLite (uji lokal).

declare(strict_types=1);

function skema_notifikasi(string $driver): string
{
	$id = $driver === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';
	$fk = $driver === 'sqlite' ? 'INTEGER' : 'INT UNSIGNED';
	$akhir = $driver === 'sqlite' ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
	return "CREATE TABLE IF NOT EXISTS notifikasi (
			id $id,
			pengguna_id $fk NOT NULL,
			pesan VARCHAR(500) NOT NULL,
			tautan VARCHAR(255) NOT NULL DEFAULT '',
			dibaca TINYINT NOT NULL DEFAULT 0,
			dibuat DATETIME NOT NULL
		)$akhir";
}

function skema_pesanan(string $driver): string
{
	$id = $driver === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';
	$fk = $driver === 'sqlite' ? 'INTEGER' : 'INT UNSIGNED';
	$teks = $driver === 'sqlite' ? 'TEXT' : 'TEXT';
	$akhir = $driver === 'sqlite' ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
	return "CREATE TABLE IF NOT EXISTS pesanan (
			id $id,
			kode VARCHAR(20) NOT NULL UNIQUE,
			token VARCHAR(64) NOT NULL,
			pengguna_id $fk NULL,
			buku_id $fk NOT NULL,
			format VARCHAR(10) NOT NULL,
			jumlah INT NOT NULL DEFAULT 1,
			harga_satuan INT NOT NULL DEFAULT 0,
			subtotal INT NOT NULL DEFAULT 0,
			ongkir INT NOT NULL DEFAULT 0,
			total INT NOT NULL DEFAULT 0,
			nama VARCHAR(150) NOT NULL,
			email VARCHAR(190) NOT NULL,
			whatsapp VARCHAR(30) NOT NULL DEFAULT '',
			alamat $teks,
			kode_pos VARCHAR(10) NOT NULL DEFAULT '',
			catatan $teks,
			status VARCHAR(30) NOT NULL,
			kurir VARCHAR(60) NOT NULL DEFAULT '',
			resi VARCHAR(120) NOT NULL DEFAULT '',
			bayar_status VARCHAR(10) NOT NULL DEFAULT '',
			bukti_path VARCHAR(255) NOT NULL DEFAULT '',
			bukti_dibuat DATETIME NULL,
			diverifikasi DATETIME NULL,
			pemeriksa_id $fk NULL,
			catatan_bayar VARCHAR(500) NOT NULL DEFAULT '',
			ebook_sampai DATETIME NULL,
			ebook_unduh INT NOT NULL DEFAULT 0,
			dibuat DATETIME NOT NULL,
			diubah DATETIME NOT NULL
		)$akhir";
}

/** Tambahkan tabel baru pada database yang dipasang dengan versi lama. */
function pastikan_skema(): void
{
	$versi = (int) pengaturan('versi_skema', '1');
	if ($versi >= 8) {
		return;
	}
	if ($versi < 2) {
		db()->exec(skema_notifikasi(config()['db']['driver'] ?? 'mysql'));
	}
	// v3: kolom jenis karya (asli/terjemahan/dialihkan/milik umum)
	try {
		db()->query('SELECT jenis_karya FROM pengajuan LIMIT 1');
	} catch (PDOException) {
		db()->exec("ALTER TABLE pengajuan ADD COLUMN jenis_karya VARCHAR(20) NOT NULL DEFAULT 'asli'");
	}
	// v4: siapa yang memverifikasi pembayaran
	try {
		db()->query('SELECT pemeriksa_id FROM pembayaran LIMIT 1');
	} catch (PDOException) {
		db()->exec('ALTER TABLE pembayaran ADD COLUMN pemeriksa_id ' . ((config()['db']['driver'] ?? 'mysql') === 'sqlite' ? 'INTEGER' : 'INT UNSIGNED') . ' NULL');
	}
	// v5/v6: spesifikasi cetak, taksiran harga, dan daftar harga
	$kolom_baru = [
		'sisi_cetak' => "VARCHAR(20) NOT NULL DEFAULT ''", 'warna_isi' => "VARCHAR(20) NOT NULL DEFAULT ''",
		'jenis_kertas' => "VARCHAR(20) NOT NULL DEFAULT ''", 'jenis_sampul' => "VARCHAR(20) NOT NULL DEFAULT ''",
		'jilid' => "VARCHAR(20) NOT NULL DEFAULT ''", 'laminasi' => "VARCHAR(20) NOT NULL DEFAULT ''",
		'paket' => "VARCHAR(20) NOT NULL DEFAULT ''", 'gramasi' => 'INT NOT NULL DEFAULT 0', 'halaman_cetak' => 'INT NOT NULL DEFAULT 0', 'harga_per_buku' => 'INT NOT NULL DEFAULT 0',
	];
	foreach ($kolom_baru as $kolom => $tipe) {
		try {
			db()->query("SELECT $kolom FROM pengajuan LIMIT 1");
		} catch (PDOException) {
			db()->exec("ALTER TABLE pengajuan ADD COLUMN $kolom $tipe");
		}
	}
	foreach (skema_harga(config()['db']['driver'] ?? 'mysql') as $sql) {
		db()->exec($sql);
	}
	isi_harga_awal();
	// v8: pemesanan buku lewat website
	db()->exec(skema_pesanan(config()['db']['driver'] ?? 'mysql'));
	try {
		db()->query('SELECT ebook_path FROM buku LIMIT 1');
	} catch (PDOException) {
		db()->exec("ALTER TABLE buku ADD COLUMN ebook_path VARCHAR(255) NOT NULL DEFAULT ''");
	}
	simpan_pengaturan('versi_skema', '8');
}

function skema(string $driver): array
{
	$id = $driver === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';
	$fk = $driver === 'sqlite' ? 'INTEGER' : 'INT UNSIGNED';
	$teks = $driver === 'sqlite' ? 'TEXT' : 'MEDIUMTEXT';
	$akhir = $driver === 'sqlite' ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

	return [
		"CREATE TABLE pengguna (
			id $id,
			nama VARCHAR(150) NOT NULL,
			email VARCHAR(190) NOT NULL UNIQUE,
			whatsapp VARCHAR(30) NOT NULL DEFAULT '',
			sandi VARCHAR(255) NOT NULL,
			peran VARCHAR(10) NOT NULL DEFAULT 'pengaju',
			dibuat DATETIME NOT NULL
		)$akhir",
		"CREATE TABLE buku (
			id $id,
			slug VARCHAR(190) NOT NULL UNIQUE,
			judul VARCHAR(255) NOT NULL,
			subjudul VARCHAR(255) NOT NULL DEFAULT '',
			penulis VARCHAR(500) NOT NULL DEFAULT '',
			editor VARCHAR(255) NOT NULL DEFAULT '',
			kategori VARCHAR(120) NOT NULL DEFAULT '',
			tahun VARCHAR(10) NOT NULL DEFAULT '',
			status VARCHAR(20) NOT NULL DEFAULT 'segera_terbit',
			isbn VARCHAR(30) NOT NULL DEFAULT '',
			eisbn VARCHAR(30) NOT NULL DEFAULT '',
			edisi VARCHAR(120) NOT NULL DEFAULT '',
			halaman VARCHAR(40) NOT NULL DEFAULT '',
			ukuran VARCHAR(60) NOT NULL DEFAULT '',
			bahasa VARCHAR(60) NOT NULL DEFAULT 'Indonesia',
			harga_cetak INT NOT NULL DEFAULT 0,
			harga_ebook INT NOT NULL DEFAULT 0,
			sampul VARCHAR(255) NOT NULL DEFAULT '',
			ebook_path VARCHAR(255) NOT NULL DEFAULT '',
			deskripsi $teks,
			tampil TINYINT NOT NULL DEFAULT 1,
			pengajuan_id $fk NULL,
			dibuat DATETIME NOT NULL
		)$akhir",
		"CREATE TABLE pengajuan (
			id $id,
			pengguna_id $fk NOT NULL,
			judul VARCHAR(255) NOT NULL,
			subjudul VARCHAR(255) NOT NULL DEFAULT '',
			penulis VARCHAR(500) NOT NULL DEFAULT '',
			kategori VARCHAR(120) NOT NULL DEFAULT '',
			sinopsis $teks,
			perkiraan_halaman INT NOT NULL DEFAULT 0,
			ukuran VARCHAR(60) NOT NULL DEFAULT '',
			jumlah_cetak_diminta INT NOT NULL DEFAULT 0,
			jenis_karya VARCHAR(20) NOT NULL DEFAULT 'asli',
			sisi_cetak VARCHAR(20) NOT NULL DEFAULT '',
			warna_isi VARCHAR(20) NOT NULL DEFAULT '',
			jenis_kertas VARCHAR(20) NOT NULL DEFAULT '',
			jenis_sampul VARCHAR(20) NOT NULL DEFAULT '',
			jilid VARCHAR(20) NOT NULL DEFAULT '',
			paket VARCHAR(20) NOT NULL DEFAULT '',
			gramasi INT NOT NULL DEFAULT 0,
			halaman_cetak INT NOT NULL DEFAULT 0,
			harga_per_buku INT NOT NULL DEFAULT 0,
			laminasi VARCHAR(20) NOT NULL DEFAULT '',
			jumlah_cetak INT NOT NULL DEFAULT 0,
			halaman_final VARCHAR(40) NOT NULL DEFAULT '',
			tagihan_2 INT NOT NULL DEFAULT 0,
			rincian_tagihan_2 VARCHAR(500) NOT NULL DEFAULT '',
			resi VARCHAR(120) NOT NULL DEFAULT '',
			status VARCHAR(30) NOT NULL DEFAULT 'DRAF',
			buku_id $fk NULL,
			dibuat DATETIME NOT NULL,
			diubah DATETIME NOT NULL,
			FOREIGN KEY (pengguna_id) REFERENCES pengguna(id)
		)$akhir",
		"CREATE TABLE berkas (
			id $id,
			pengajuan_id $fk NOT NULL,
			jenis VARCHAR(20) NOT NULL,
			path VARCHAR(255) NOT NULL,
			nama_asli VARCHAR(255) NOT NULL,
			ukuran INT NOT NULL DEFAULT 0,
			pengunggah_id $fk NULL,
			dibuat DATETIME NOT NULL,
			FOREIGN KEY (pengajuan_id) REFERENCES pengajuan(id)
		)$akhir",
		"CREATE TABLE pembayaran (
			id $id,
			pengajuan_id $fk NOT NULL,
			tahap TINYINT NOT NULL,
			nominal INT NOT NULL,
			berkas_id $fk NULL,
			status VARCHAR(12) NOT NULL DEFAULT 'menunggu',
			catatan VARCHAR(500) NOT NULL DEFAULT '',
			dibuat DATETIME NOT NULL,
			diverifikasi DATETIME NULL,
			pemeriksa_id $fk NULL,
			FOREIGN KEY (pengajuan_id) REFERENCES pengajuan(id)
		)$akhir",
		"CREATE TABLE catatan (
			id $id,
			pengajuan_id $fk NOT NULL,
			pengguna_id $fk NULL,
			isi $teks NOT NULL,
			sistem TINYINT NOT NULL DEFAULT 0,
			dibuat DATETIME NOT NULL,
			FOREIGN KEY (pengajuan_id) REFERENCES pengajuan(id)
		)$akhir",
		skema_notifikasi($driver),
		"CREATE TABLE pengaturan (
			k VARCHAR(60) NOT NULL PRIMARY KEY,
			v $teks NOT NULL
		)$akhir",
	];
}
