// Perbarui angka di ikon lonceng setiap 60 detik tanpa memuat ulang halaman.
(function () {
	var lonceng = document.querySelector('.navigasi__lonceng');
	if (!lonceng || !window.fetch) return;
	var angka = lonceng.querySelector('.lonceng__angka');
	var judulAsli = document.title.replace(/^\(\d+\+?\) /, '');
	function tampilkan(n) {
		angka.hidden = n < 1;
		angka.textContent = n > 99 ? '99+' : String(n);
		lonceng.setAttribute('aria-label', n ? 'Notifikasi, ' + n + ' belum dibaca' : 'Notifikasi');
		document.title = (n ? '(' + (n > 99 ? '99+' : n) + ') ' : '') + judulAsli;
	}
	function cek() {
		if (document.hidden) return;
		fetch(lonceng.getAttribute('data-jumlah-url'), { credentials: 'same-origin' })
			.then(function (r) { return r.json(); })
			.then(function (d) { tampilkan(d.jumlah || 0); })
			.catch(function () {});
	}
	tampilkan(parseInt(angka.textContent, 10) || 0);
	setInterval(cek, 60000);
	document.addEventListener('visibilitychange', cek);
})();
