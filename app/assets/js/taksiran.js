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
	var taksir = 0;

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
		otomatis(perBuku, taksir || '');
		hitungTagihan();
	}

	function hitungTagihan() {
		var pb = angka(perBuku), eks = angka(jumlah);
		if (!pb || !eks) return;
		var total = pb * eks, kurang = potong && potong.checked ? D.biaya_awal : 0;
		otomatis(tagihan, Math.max(0, total - kurang));
		otomatis(rincian, eks + ' eks × ' + rp(pb) + ' = ' + rp(total) + (kurang ? ' − biaya awal ' + rp(kurang) : ''));
	}

	// Kolom yang diketik sendiri tidak ditimpa lagi oleh hitungan otomatis.
	[perBuku, tagihan, rincian].forEach(function (el) { if (el) el.addEventListener('input', function () { el.dataset.manual = '1'; }); });
	hal.addEventListener('input', function () { hal.dataset.manual = '1'; hitung(); });
	halFinal.addEventListener('input', function () { if (!hal.dataset.manual) { var n = hitungHalaman(halFinal.value); if (n) hal.value = n; } hitung(); });
	akar.querySelectorAll('[data-spek]').forEach(function (el) { el.addEventListener('change', hitung); });
	gram.addEventListener('change', hitung);
	perBuku.addEventListener('input', hitungTagihan);
	jumlah.addEventListener('input', hitungTagihan);
	if (potong) potong.addEventListener('change', function () { delete tagihan.dataset.manual; delete rincian.dataset.manual; hitungTagihan(); });
	akar.querySelector('[data-pakai-taksiran]').addEventListener('click', function () {
		delete perBuku.dataset.manual; delete tagihan.dataset.manual; delete rincian.dataset.manual;
		perBuku.value = taksir || ''; hitungTagihan();
	});
	hitung();
})();
