// Isian uang: tampil "Rp" di depan dan titik pemisah ribuan (350.000). Server tetap membaca angkanya saja.
(function () {
	function fmt(v) {
		var d = String(v == null ? '' : v).replace(/\D/g, '').replace(/^0+(?=\d)/, '');
		return d ? Number(d).toLocaleString('id-ID') : '';
	}
	function pasang(el) {
		if (el.dataset.rpSiap) return;
		el.dataset.rpSiap = '1';
		var w = document.createElement('span');
		w.className = 'isian-rp' + (el.classList.contains('isian-harga') ? ' isian-rp--kecil' : '');
		el.parentNode.insertBefore(w, el);
		w.appendChild(el);
		el.value = fmt(el.value);
		el.addEventListener('input', function () {
			var dariKanan = el.value.length - (el.selectionEnd || 0);
			el.value = fmt(el.value);
			var p = Math.max(0, el.value.length - dariKanan);
			try { el.setSelectionRange(p, p); } catch (e) {}
		});
	}
	window.rupiahFmt = fmt;
	document.querySelectorAll('input[data-rupiah]').forEach(pasang);
})();
