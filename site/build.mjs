// Pembuat website Literaindo Press.
//
// Membaca data/situs.json, data/buku.json, dan konten/*.html, lalu menulis
// website jadi ke folder dist/ di akar repo. Tanpa dependensi: cukup Node 18+.
//
//   node site/build.mjs

import { readFileSync, writeFileSync, mkdirSync, rmSync, cpSync, readdirSync, existsSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = dirname(fileURLToPath(import.meta.url));
const OUT = join(ROOT, '..', 'dist');

const situs = JSON.parse(readFileSync(join(ROOT, 'data/situs.json'), 'utf8'));
const buku = JSON.parse(readFileSync(process.env.DATA_BUKU || join(ROOT, 'data/buku.json'), 'utf8'));
const tahunIni = new Date().getFullYear();

// ---------- Utilitas ----------

const esc = (s) =>
	String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);

const rupiah = (n) => 'Rp' + Number(n).toLocaleString('id-ID');

const waLink = (pesan) =>
	`https://wa.me/${situs.whatsapp}` + (pesan ? `?text=${encodeURIComponent(pesan)}` : '');

const tulis = (path, isi) => {
	const file = join(OUT, path);
	mkdirSync(dirname(file), { recursive: true });
	writeFileSync(file, isi);
};

const segeraTerbit = (b) => b.status === 'segera_terbit';
const judulLengkap = (b) => (b.subjudul ? `${b.judul}: ${b.subjudul}` : b.judul);
const penulis = (b) => (b.penulis || []).join(', ');
const urlBuku = (b) => `/buku/${b.slug}/`;

function periksaData() {
	const slugs = new Set();
	for (const b of buku) {
		for (const kolom of ['slug', 'judul', 'kategori']) {
			if (!b[kolom]) throw new Error(`Buku "${b.judul || b.slug}" belum punya kolom "${kolom}".`);
		}
		if (!/^[a-z0-9]+(-[a-z0-9]+)*$/.test(b.slug)) {
			throw new Error(`Slug "${b.slug}" hanya boleh huruf kecil, angka, dan tanda minus.`);
		}
		if (slugs.has(b.slug)) throw new Error(`Slug "${b.slug}" dipakai dua kali.`);
		slugs.add(b.slug);
	}
}

// Ikon garis sederhana (24×24) untuk daftar informasi buku.
const IKON = {
	penulis: '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/>',
	editor: '<path d="M4 20h4L19 9l-4-4L4 16z"/>',
	penerbit: '<path d="M4 21V5l8-3 8 3v16"/><path d="M9 21v-5h6v5M8 9h2M14 9h2M8 13h2M14 13h2"/>',
	isbn: '<path d="M4 9h16M4 15h16M10 3 8 21M16 3l-2 18"/>',
	tahun: '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
	halaman: '<path d="M6 2h9l5 5v15H6z"/><path d="M14 2v6h6"/>',
	ukuran: '<path d="M3 21 21 3M14 3h7v7M10 21H3v-7"/>',
	bahasa: '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c3 3 3 15 0 18M12 3c-3 3-3 15 0 18"/>',
	kategori: '<path d="M3 12V3h9l9 9-9 9z"/><circle cx="8" cy="8" r="1.5"/>',
	edisi: '<path d="M4 4h12l4 4v12H4z"/><path d="M8 12h8M8 16h5"/>',
	status: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/>',
	deskripsi: '<path d="M4 20h4L19 9l-4-4L4 16z"/><path d="M13 7l4 4"/>',
	cari: '<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>',
	ulang: '<path d="M20 11a8 8 0 1 0-2.3 5.7"/><path d="M20 4v7h-7"/>',
	kembali: '<path d="m11 6-6 6 6 6M5 12h14"/>',
	wa: '<path d="M4 20l1.3-4A8 8 0 1 1 8 18.7z"/><path d="M9 9c0 3 3 6 6 6l1-1.5-2-1-1 1c-1-.5-2-1.5-2.5-2.5l1-1-1-2z"/>',
};
const ikon = (nama) =>
	`<svg class="ikon" viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">${IKON[nama]}</svg>`;

// ---------- Kerangka halaman ----------

function layout({ judul, deskripsi, path, isi, kelas = '', kepala = '' }) {
	const judulTab = judul ? `${judul} | ${situs.nama}` : `${situs.nama} | ${situs.tagline}`;
	const menu = situs.menu
		.map((m) => {
			const aktif = m.url === path || (m.url === '/' && path.startsWith('/buku/'));
			return `<li><a href="${esc(m.url)}"${aktif ? ' aria-current="page"' : ''}>${esc(m.label)}</a></li>`;
		})
		.join('');
	const tautan = situs.tautan_terkait.map((t) => `<li><a href="${esc(t.url)}">${esc(t.label)}</a></li>`).join('');

	return `<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>${esc(judulTab)}</title>
<meta name="description" content="${esc(deskripsi || situs.deskripsi)}">
<link rel="canonical" href="${esc(situs.url + path)}">
<meta property="og:site_name" content="${esc(situs.nama)}">
<meta property="og:title" content="${esc(judul || situs.nama)}">
<meta property="og:description" content="${esc(deskripsi || situs.deskripsi)}">
<meta property="og:url" content="${esc(situs.url + path)}">
<link rel="icon" href="/assets/img/logo.svg" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="/assets/css/gaya.css">
<style>:root{--warna-utama:${esc(situs.warna_utama)}}</style>
${kepala}</head>
<body class="${esc(kelas)}">
<a class="lewati" href="#isi">Langsung ke isi</a>
<header class="kepala">
	<div class="wadah kepala__merek">
		<a class="merek" href="/">
			<img class="merek__logo" src="/assets/img/logo.svg" alt="" width="56" height="56">
			<span><span class="merek__nama">${esc(situs.nama)}</span><span class="merek__tagline">${esc(situs.tagline)}</span></span>
		</a>
	</div>
	<nav class="navigasi" aria-label="Menu utama">
		<div class="wadah navigasi__isi">
			<details class="navigasi__lipat">
				<summary>Menu</summary>
				<ul>${menu}</ul>
			</details>
			<ul class="navigasi__menu">${menu}</ul>
			<a class="navigasi__cari" href="/#katalog" aria-label="Cari buku">${ikon('cari')}</a>
		</div>
	</nav>
</header>
<main id="isi" class="wadah">
${isi}
</main>
<footer class="kaki">
	<div class="wadah kaki__kolom">
		<section>
			<h2>${esc(situs.nama)}</h2>
			<p>${esc(situs.deskripsi)}</p>
		</section>
		<section>
			<h2>Tautan</h2>
			<ul>${tautan}</ul>
		</section>
		<section>
			<h2>Kontak Kami</h2>
			<p>${esc(situs.alamat)}<br>
			WhatsApp: <a href="${esc(waLink())}">${esc(situs.whatsapp_tampil)}</a><br>
			Email: <a href="mailto:${esc(situs.email)}">${esc(situs.email)}</a></p>
		</section>
	</div>
	<p class="kaki__hak">© ${tahunIni} ${esc(situs.nama)}. Seluruh hak cipta dilindungi.</p>
</footer>
</body>
</html>
`;
}

// ---------- Katalog (beranda) ----------

function kartuBuku(b) {
	return `<li class="kartu" data-buku="${esc(b.slug)}">
	<a class="kartu__tautan" href="${urlBuku(b)}">
		<span class="kartu__sampul">${
			b.sampul ? `<img src="${esc(b.sampul)}" alt="Sampul ${esc(b.judul)}" loading="lazy" width="200" height="300">` : `<span class="kartu__tanpa-sampul">${esc(b.judul)}</span>`
		}</span>
		${segeraTerbit(b) ? '<span class="label label--segera">Segera Terbit</span>' : ''}
		<span class="kartu__judul">${esc(b.judul)}</span>
	</a>
	<span class="kartu__kategori">${esc(b.kategori)}</span>
	<span class="kartu__penulis">${esc(penulis(b))}</span>
</li>`;
}

function pilihan(nama, label, nilai) {
	const opsi = [...new Set(nilai.filter(Boolean))].sort((a, b) => String(a).localeCompare(String(b), 'id'));
	return `<label class="filter__isian"><span class="tersembunyi">${esc(label)}</span>
		<select name="${nama}"><option value="">${esc(label)}</option>${opsi
			.map((o) => `<option value="${esc(o)}">${esc(o)}</option>`)
			.join('')}</select></label>`;
}

function halamanKatalog(daftar) {
	const dataFilter = daftar.map((b) => ({
		slug: b.slug,
		judul: judulLengkap(b).toLowerCase(),
		isbn: [b.isbn, b.eisbn].join(' ').replace(/[^0-9xX ]/g, ''),
		kategori: b.kategori,
		tahun: b.tahun,
		bahasa: b.bahasa,
		penulis: b.penulis || [],
	}));

	const isi = `<section class="panel" id="katalog" aria-labelledby="judul-katalog">
	<h1 id="judul-katalog">Katalog</h1>
	<form class="filter" role="search" action="/" method="get">
		<label class="filter__isian"><span class="tersembunyi">Judul buku</span><input type="search" name="judul" placeholder="Judul buku"></label>
		<label class="filter__isian"><span class="tersembunyi">ISBN</span><input type="search" name="isbn" placeholder="ISBN" inputmode="numeric"></label>
		${pilihan('kategori', 'Semua Kategori', daftar.map((b) => b.kategori))}
		${pilihan('tahun', 'Semua Tahun', daftar.map((b) => b.tahun))}
		${pilihan('bahasa', 'Semua Bahasa', daftar.map((b) => b.bahasa))}
		${pilihan('penulis', 'Semua Penulis', daftar.flatMap((b) => b.penulis || []))}
		<div class="filter__tombol">
			<button type="submit" class="tombol">Cari Buku</button>
			<button type="reset" class="tombol tombol--ikon" aria-label="Atur ulang pencarian" title="Atur ulang">${ikon('ulang')}</button>
		</div>
	</form>
	<p class="katalog__jumlah" aria-live="polite">Menampilkan ${daftar.length} buku</p>
	<ul class="katalog">${daftar.map(kartuBuku).join('\n')}</ul>
	<p class="katalog__kosong" hidden>Buku tidak ditemukan. Coba kata kunci lain atau atur ulang pencarian.</p>
</section>
<script type="application/json" id="data-katalog">${JSON.stringify(dataFilter).replace(/</g, '\\u003c')}</script>
<script src="/assets/js/katalog.js" defer></script>`;

	return layout({ path: '/', isi, kelas: 'beranda' });
}

// ---------- Detail buku ----------

function halamanBuku(b) {
	const isbnTampil = b.isbn || (segeraTerbit(b) ? 'Dalam proses pengajuan' : '');
	const baris = [
		['penulis', 'Penulis', penulis(b)],
		['editor', 'Editor', b.editor],
		['penerbit', 'Penerbit', situs.nama],
		['isbn', 'ISBN', isbnTampil],
		['isbn', 'e-ISBN', b.eisbn],
		['tahun', 'Tahun terbit', b.tahun],
		['edisi', 'Edisi', b.edisi],
		['halaman', 'Jumlah halaman', b.halaman],
		['ukuran', 'Ukuran', b.ukuran],
		['bahasa', 'Bahasa', b.bahasa],
		['kategori', 'Kategori', b.kategori],
		['status', 'Status', segeraTerbit(b) ? 'Segera Terbit' : 'Sudah Terbit'],
	].filter(([, , v]) => v);

	const harga = [];
	if (b.harga_cetak > 0) harga.push(`<li><span>Cetak</span><strong>${rupiah(b.harga_cetak)}</strong></li>`);
	if (b.harga_ebook > 0) harga.push(`<li><span>E-book (PDF)</span><strong>${rupiah(b.harga_ebook)}</strong></li>`);

	const pesan = segeraTerbit(b)
		? `<p class="catatan">Buku ini segera terbit dan belum dapat dipesan.</p>
		<a class="tombol tombol--wa" href="${esc(waLink(`Halo ${situs.nama}, saya ingin menanyakan jadwal terbit buku "${b.judul}".`))}">${ikon('wa')} Tanya jadwal terbit</a>`
		: `<a class="tombol tombol--wa" href="${esc(waLink(`Halo ${situs.nama}, saya ingin memesan buku "${b.judul}". Format (cetak/e-book): `))}">${ikon('wa')} Pesan via WhatsApp</a>
		<p class="catatan">Pembayaran melalui QRIS. Lihat <a href="/cara-pemesanan/">cara pemesanan</a>.</p>`;

	const deskripsi = (b.deskripsi || '')
		.split(/\n\s*\n/)
		.filter((p) => p.trim())
		.map((p) => `<p>${esc(p.trim())}</p>`)
		.join('\n');

	const ringkas = (b.deskripsi || '').replace(/\s+/g, ' ').trim().slice(0, 155);

	const schema = {
		'@context': 'https://schema.org',
		'@type': 'Book',
		name: judulLengkap(b),
		url: situs.url + urlBuku(b),
		author: (b.penulis || []).map((n) => ({ '@type': 'Person', name: n })),
		publisher: { '@type': 'Organization', name: situs.nama },
		inLanguage: b.bahasa === 'Indonesia' ? 'id' : b.bahasa,
		...(b.isbn && { isbn: b.isbn }),
		...(b.tahun && { datePublished: b.tahun }),
		...(b.halaman && { numberOfPages: Number(b.halaman) || undefined }),
		...(b.sampul && { image: situs.url + b.sampul }),
		...(ringkas && { description: ringkas }),
	};

	const isi = `<article class="panel buku">
	<div class="buku__atas">
		<figure class="buku__sampul">${
			b.sampul ? `<img src="${esc(b.sampul)}" alt="Sampul ${esc(b.judul)}" width="400" height="600">` : `<span class="kartu__tanpa-sampul">${esc(b.judul)}</span>`
		}</figure>
		<div class="buku__info">
			${segeraTerbit(b) ? '<span class="label label--segera">Segera Terbit</span>' : ''}
			<h1>${esc(b.judul)}${b.subjudul ? `<span class="buku__subjudul">${esc(b.subjudul)}</span>` : ''}</h1>
			<dl class="buku__data">
				${baris.map(([ik, label, v]) => `<div><dt>${ikon(ik)}${esc(label)}</dt><dd>${esc(v)}</dd></div>`).join('\n\t\t\t\t')}
			</dl>
			${harga.length ? `<ul class="buku__harga">${harga.join('')}</ul>` : ''}
			<div class="buku__pesan">${pesan}</div>
		</div>
	</div>
	${deskripsi ? `<section class="buku__deskripsi" aria-labelledby="judul-deskripsi"><h2 id="judul-deskripsi">${ikon('deskripsi')}Deskripsi</h2>${deskripsi}</section>` : ''}
	<a class="tombol tombol--kembali" href="/#katalog">${ikon('kembali')} Kembali ke Katalog</a>
</article>`;

	return layout({
		judul: judulLengkap(b),
		deskripsi: ringkas || `${judulLengkap(b)} oleh ${penulis(b)}, diterbitkan ${situs.nama}.`,
		path: urlBuku(b),
		isi,
		kelas: 'halaman-buku',
		kepala: `<meta property="og:type" content="book">${b.sampul ? `\n<meta property="og:image" content="${esc(situs.url + b.sampul)}">` : ''}
<script type="application/ld+json">${JSON.stringify(schema).replace(/</g, '\\u003c')}</script>
`,
	});
}

// ---------- Halaman konten ----------

function blokQris() {
	const gambar = existsSync(join(ROOT, situs.qris_gambar.replace(/^\//, ''))) ? situs.qris_gambar : '/assets/img/qris-contoh.svg';
	return `<figure class="qris"><img src="${esc(gambar)}" alt="Kode QRIS ${esc(situs.nama)}" width="300"><figcaption>QRIS a.n. ${esc(situs.qris_nama_merchant)}</figcaption></figure>`;
}

function halamanKonten(file) {
	const mentah = readFileSync(join(ROOT, 'konten', file), 'utf8');
	const judul = (mentah.match(/<!--\s*judul:\s*(.+?)\s*-->/) || [])[1];
	if (!judul) throw new Error(`konten/${file} belum punya baris <!-- judul: ... -->`);
	const ganti = {
		badan_usaha: esc(situs.badan_usaha),
		alamat: esc(situs.alamat),
		email: esc(situs.email),
		whatsapp_tampil: esc(situs.whatsapp_tampil),
		jam_layanan: esc(situs.jam_layanan),
		qris_nama_merchant: esc(situs.qris_nama_merchant),
		wa_link: esc(waLink()),
		qris: blokQris(),
	};
	const isiHtml = mentah
		.replace(/<!--\s*judul:.*?-->\s*/, '')
		.replace(/\{\{(\w+)\}\}/g, (m, k) => (k in ganti ? ganti[k] : m));
	const slug = file.replace(/\.html$/, '');
	return {
		path: `/${slug}/`,
		html: layout({
			judul,
			path: `/${slug}/`,
			isi: `<article class="panel konten"><h1>${esc(judul)}</h1>\n${isiHtml}</article>`,
			kelas: 'halaman-konten',
		}),
	};
}

// ---------- Jalankan ----------

periksaData();
rmSync(OUT, { recursive: true, force: true });
mkdirSync(OUT, { recursive: true });
cpSync(join(ROOT, 'assets'), join(OUT, 'assets'), { recursive: true });
cpSync(join(ROOT, 'statis'), OUT, { recursive: true });

const urut = [...buku].sort((a, b) => String(b.tanggal_tambah || '').localeCompare(String(a.tanggal_tambah || '')));
const paths = ['/'];

tulis('index.html', halamanKatalog(urut));
for (const b of urut) {
	tulis(`buku/${b.slug}/index.html`, halamanBuku(b));
	paths.push(urlBuku(b));
}
for (const file of readdirSync(join(ROOT, 'konten')).filter((f) => f.endsWith('.html'))) {
	const { path, html } = halamanKonten(file);
	tulis(`${path.slice(1)}index.html`, html);
	paths.push(path);
}
tulis(
	'404.html',
	layout({
		judul: 'Halaman tidak ditemukan',
		path: '/404.html',
		isi: '<section class="panel konten"><h1>Halaman tidak ditemukan</h1><p>Maaf, halaman yang Anda cari tidak ada. Silakan kembali ke <a href="/">katalog buku</a>.</p></section>',
	})
);
tulis(
	'sitemap.xml',
	`<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n${paths
		.map((p) => `  <url><loc>${esc(situs.url + p)}</loc></url>`)
		.join('\n')}\n</urlset>\n`
);
tulis('robots.txt', `User-agent: *\nAllow: /\nSitemap: ${situs.url}/sitemap.xml\n`);

console.log(`Selesai: ${urut.length} buku, ${paths.length} halaman → ${OUT}`);
