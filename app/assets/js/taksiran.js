// Taksiran harga cetak di form tagihan admin. Data harga dari <script id="data-harga">.
(function () {
	var akar = document.querySelector('[data-taksiran]');
	var sumber = document.getElementById('data-harga');
	if (!akar || !sumber) return;
	var D = JSON.parse(sumber.textContent);
	var $ = function (s) { return akar.querySelector(s); };
	var spek = function (k) { var el = $('[data-spek="' + k + '"]'); return el ? el.value : ''; };
	var label = function (k) { var el = $('[data-spek="' + k + '"]'); return el ? el.options[el.selectedIndex].text : ''; };
	var rp = function (n) { return 'Rp' + Math.round(n).toLocaleString('id-ID'); };
	var angka = function (el) { return parseInt(String(el.value).replace(/\D/g, ''), 10) || 0; };
	var gram = $('[data-gramasi]'), hal = $('[data-halaman]'), halFinal = $('[data-halaman-final]');
	var perBuku = $('[data-per-buku]'), jumlah = $('[data-jumlah]'), tagihan = $('[data-tagihan]'), rincian = $('[data-rincian]'), potong = $('[data-potong]');
	var paketSel = $('[data-paket]');
	var taksir = 0, jual = 0;
	var ratus = function (n) { return Math.round(n / 100) * 100; };

	function hitungHalaman(teks) {
		var nilai = { i: 1, v: 5, x: 10, l: 50, c: 100, d: 500, m: 1000 }, total = 0;
		(teks.toLowerCase().match(/[0-9]+|[ivxlcdm]+/g) || []).forEach(function (t) {
			if (/^\d+$/.test(t)) { total += parseInt(t, 10); return; }
			for (var i = 0; i < t.length; i++) {
				var v = nilai[t[i]], b = nilai[t[i + 1]] || 0;
				total += b > v ? -v : v;
			}
		});
		return total;
	}

	function isiGramasi() {
		var uk = spek('ukuran').split(' ')[0], lama = gram.value;
		var daftar = D.kertas.filter(function (r) { return r.ukuran === uk && r.kertas === spek('jenis_kertas') && r.warna === spek('warna_isi') && String(r.sisi) === spek('sisi_cetak'); });
		gram.innerHTML = '';
		daftar.forEach(function (r) { var o = document.createElement('option'); o.value = r.gramasi; o.textContent = r.gramasi + ' gr (' + rp(r.harga) + ')'; gram.appendChild(o); });
		if (!daftar.length) { var o = document.createElement('option'); o.value = 0; o.textContent = 'belum ada di daftar harga'; gram.appendChild(o); }
		if (daftar.some(function (r) { return String(r.gramasi) === lama; })) gram.value = lama;
		return daftar;
	}

	function otomatis(el, nilai) { if (el && !el.dataset.manual) el.value = nilai; }

	function hitung() {
		var uk = spek('ukuran').split(' ')[0], daftar = isiGramasi(), catatan = [];
		var k = daftar.filter(function (r) { return String(r.gramasi) === gram.value; })[0];
		var c = D.cover.filter(function (r) { return r.ukuran === uk && r.sampul === spek('jenis_sampul') && r.jilid === spek('jilid') && r.laminasi === spek('laminasi'); })[0];
		if (!k) catatan.push('Harga kertas untuk spesifikasi ini belum ada di daftar harga.');
		if (!c) catatan.push('Harga cover untuk spesifikasi ini belum ada di daftar harga.');
		var n = angka(hal), perLembar = spek('sisi_cetak') === '2' && D.per_lembar;
		var lembar = perLembar ? Math.ceil(n / 2) : n;
		var isi = k ? lembar * k.harga : 0, cover = c ? c.harga : 0;
		taksir = isi + cover;
		akar.querySelector('[data-t="rumus"]').textContent = lembar + (perLembar ? ' lembar' : ' halaman') + ' × ' + rp(k ? k.harga : 0) + (k ? ' (' + k.gramasi + ' gr)' : '');
		akar.querySelector('[data-t="isi"]').textContent = rp(isi);
		akar.querySelector('[data-t="rumus-cover"]').textContent = label('jenis_sampul') + ', ' + label('jilid') + ', ' + label('laminasi');
		akar.querySelector('[data-t="cover"]').textContent = rp(cover);
		akar.querySelector('[data-t="per_buku"]').textContent = rp(taksir);
		var cat = akar.querySelector('[data-t="catatan"]');
		cat.hidden = !catatan.length;
		if (catatan.length) cat.firstChild.textContent = catatan.join(' ') + ' Isi harga per buku secara manual atau lengkapi ';
		jual = ratus(taksir * (100 + (D.margin || 0)) / 100);
		otomatis(perBuku, jual || '');
		hitungTagihan();
		hitungBerat(uk, n, k ? k.gramasi : (parseInt(gram.value, 10) || 0));
	}

	// Taksiran berat: luas kertas (m²) × gramasi. Cover soft ±260 gsm, hard cover memakai karton ±1.300 gsm.
	var LUAS = { A5: 0.148 * 0.21, B5: 0.176 * 0.25, A4: 0.21 * 0.297 }, KEMASAN = 100, DEPOSIT = 2, TIDAK_DIKIRIM = 4;
	var bulat = function (g) { return g >= 1000 ? '±' + (g / 1000).toLocaleString('id-ID', { maximumFractionDigits: 2 }) + ' kg' : '±' + Math.round(g) + ' g'; };
	var tulis = function (k, v) { var el = akar.querySelector('[data-b="' + k + '"]'); if (el) el.textContent = v; };
	function hitungBerat(uk, halaman, gramasi) {
		var luas = LUAS[uk] || LUAS.A5;
		var lembarFisik = spek('sisi_cetak') === '2' ? Math.ceil(halaman / 2) : halaman;
		var isi = lembarFisik * luas * (gramasi || 75);
		var cover = 2 * luas * (spek('jenis_sampul') === 'hard' ? 1300 : 260) + (spek('jilid') === 'spiral' ? 10 : 5);
		var buku = isi + cover, eks = angka(jumlah);
		var penulis = Math.max(0, eks - TIDAK_DIKIRIM);
		tulis('buku', halaman ? bulat(buku) : '-');
		tulis('n-total', eks + ' eks');
		tulis('total', halaman ? bulat(buku * eks) : '-');
		tulis('n-penulis', penulis + ' eks + kemasan');
		tulis('penulis', halaman && penulis ? bulat(buku * penulis + KEMASAN) : '-');
		tulis('perpusnas', halaman ? bulat(buku * DEPOSIT + KEMASAN) : '-');
		tulis('perpusda', halaman ? bulat(buku + KEMASAN) : '-');
	}

	function tulisP(k, v) { var el = akar.querySelector('[data-p="' + k + '"]'); if (el) el.textContent = v; }

	function hitungTagihan() {
		var pb = angka(perBuku), eks = angka(jumlah);
		var pk = paketSel && D.paket ? D.paket[paketSel.value] : null;
		var tabel = akar.querySelector('[data-rincian-paket]'), cek = akar.querySelector('[data-hanya-tanpa-paket]');
		if (tabel) tabel.hidden = !pk;
		if (cek) cek.hidden = !!pk;
		if (pk) {
			jumlah.min = pk.eks;
			akar.querySelectorAll('[data-min-eks]').forEach(function (el) { el.textContent = pk.eks; });
			var selisih = Math.max(0, pb - D.per_buku_std) * pk.eks;
			var tambahEks = Math.max(0, eks - pk.eks), tambah = tambahEks * pb;
			var total = pk.harga + selisih + tambah, sisa = Math.max(0, total - D.biaya_awal);
			tulisP('nama', pk.nama); tulisP('harga', rp(pk.harga));
			tulisP('ket-selisih', selisih ? pk.eks + ' eks × ' + rp(pb - D.per_buku_std) : 'spesifikasi standar'); tulisP('selisih', rp(selisih));
			tulisP('ket-tambah', tambahEks ? tambahEks + ' eks × ' + rp(pb) : 'tidak ada'); tulisP('tambah', rp(tambah));
			tulisP('total', rp(total)); tulisP('dp', '− ' + rp(D.biaya_awal)); tulisP('pelunasan', rp(sisa));
			otomatis(tagihan, sisa);
			otomatis(rincian, pk.nama + ' ' + rp(pk.harga) + (selisih ? ' + selisih spesifikasi ' + rp(selisih) : '') + (tambah ? ' + ' + tambahEks + ' eks tambahan × ' + rp(pb) : '') + ' − uang muka ' + rp(D.biaya_awal) + ' = ' + rp(sisa));
			return;
		}
		akar.querySelectorAll('[data-min-eks]').forEach(function (el) { el.textContent = 5; });
		jumlah.min = 5;
		if (!pb || !eks) return;
		var total2 = pb * eks, kurang = potong && potong.checked ? D.biaya_awal : 0;
		otomatis(tagihan, Math.max(0, total2 - kurang));
		otomatis(rincian, eks + ' eks × ' + rp(pb) + ' = ' + rp(total2) + (kurang ? ' − biaya awal ' + rp(kurang) : ''));
	}

	// Kolom yang diketik sendiri tidak ditimpa lagi oleh hitungan otomatis.
	[perBuku, tagihan, rincian].forEach(function (el) { if (el) el.addEventListener('input', function () { el.dataset.manual = '1'; }); });
	hal.addEventListener('input', function () { hal.dataset.manual = '1'; hitung(); });
	halFinal.addEventListener('input', function () { if (!hal.dataset.manual) { var n = hitungHalaman(halFinal.value); if (n) hal.value = n; } hitung(); });
	akar.querySelectorAll('[data-spek]').forEach(function (el) { el.addEventListener('change', hitung); });
	gram.addEventListener('change', hitung);
	perBuku.addEventListener('input', hitungTagihan);
	jumlah.addEventListener('input', hitung);
	if (paketSel) paketSel.addEventListener('change', function () { delete tagihan.dataset.manual; delete rincian.dataset.manual; hitung(); });
	if (potong) potong.addEventListener('change', function () { delete tagihan.dataset.manual; delete rincian.dataset.manual; hitungTagihan(); });
	akar.querySelector('[data-pakai-taksiran]').addEventListener('click', function () {
		delete perBuku.dataset.manual; delete tagihan.dataset.manual; delete rincian.dataset.manual;
		perBuku.value = jual || ''; hitungTagihan();
	});
	hitung();
})();
