// Filter katalog di beranda. Tanpa JavaScript, semua buku tetap tampil.
(function () {
	var form = document.querySelector('.filter');
	var dataEl = document.getElementById('data-katalog');
	if (!form || !dataEl) return;

	var data = JSON.parse(dataEl.textContent);
	var kartu = {};
	document.querySelectorAll('.kartu').forEach(function (el) {
		kartu[el.getAttribute('data-buku')] = el;
	});
	var jumlah = document.querySelector('.katalog__jumlah');
	var kosong = document.querySelector('.katalog__kosong');

	function nilai(nama) {
		return (form.elements[nama].value || '').trim();
	}

	function terapkan() {
		var judul = nilai('judul').toLowerCase();
		var isbn = nilai('isbn').replace(/[^0-9xX]/g, '');
		var kategori = nilai('kategori');
		var tahun = nilai('tahun');
		var bahasa = nilai('bahasa');
		var penulis = nilai('penulis');
		var tampil = 0;

		data.forEach(function (b) {
			var cocok =
				(!judul || b.judul.indexOf(judul) !== -1 || b.penulis.join(' ').toLowerCase().indexOf(judul) !== -1) &&
				(!isbn || b.isbn.replace(/ /g, '').indexOf(isbn) !== -1 || b.isbn.indexOf(isbn) !== -1) &&
				(!kategori || b.kategori === kategori) &&
				(!tahun || b.tahun === tahun) &&
				(!bahasa || b.bahasa === bahasa) &&
				(!penulis || b.penulis.indexOf(penulis) !== -1);
			if (kartu[b.slug]) kartu[b.slug].hidden = !cocok;
			if (cocok) tampil++;
		});

		var aktif = judul || isbn || kategori || tahun || bahasa || penulis;
		jumlah.textContent = aktif
			? 'Menampilkan ' + tampil + ' dari ' + data.length + ' buku'
			: 'Menampilkan ' + data.length + ' buku';
		kosong.hidden = tampil > 0;
	}

	// Isi form dari alamat (?judul=...), supaya hasil pencarian bisa dibagikan.
	var params = new URLSearchParams(location.search);
	['judul', 'isbn', 'kategori', 'tahun', 'bahasa', 'penulis'].forEach(function (n) {
		if (params.has(n)) form.elements[n].value = params.get(n);
	});

	form.addEventListener('submit', function (e) {
		e.preventDefault();
		var q = new URLSearchParams();
		['judul', 'isbn', 'kategori', 'tahun', 'bahasa', 'penulis'].forEach(function (n) {
			if (nilai(n)) q.set(n, nilai(n));
		});
		history.replaceState(null, '', (q.toString() ? '?' + q : location.pathname) + '#katalog');
		terapkan();
	});
	form.addEventListener('reset', function () {
		history.replaceState(null, '', location.pathname + '#katalog');
		setTimeout(terapkan, 0);
	});
	form.querySelectorAll('select').forEach(function (s) {
		s.addEventListener('change', terapkan);
	});

	terapkan();
})();
