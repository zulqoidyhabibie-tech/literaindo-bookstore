// Penyunting sederhana untuk halaman website (contenteditable + tombol format).
(function () {
	var f = document.querySelector('[data-penyunting]');
	if (!f) return;
	var isi = f.querySelector('[data-isi]'), keluar = f.querySelector('[data-keluaran]');
	try { document.execCommand('defaultParagraphSeparator', false, 'p'); } catch (e) {}
	f.querySelectorAll('[data-perintah]').forEach(function (b) {
		b.addEventListener('mousedown', function (e) { e.preventDefault(); });
		b.addEventListener('click', function () {
			isi.focus();
			var p = b.dataset.perintah;
			if (p === 'p' || p === 'h2' || p === 'h3') document.execCommand('formatBlock', false, p.toUpperCase());
			else if (p === 'ul') document.execCommand('insertUnorderedList');
			else if (p === 'ol') document.execCommand('insertOrderedList');
			else if (p === 'hapus') { document.execCommand('removeFormat'); document.execCommand('formatBlock', false, 'P'); }
			else if (p === 'link') {
				var u = window.prompt('Alamat tautan (mis. https://… atau /kontak/):', 'https://');
				if (u && u !== 'https://') document.execCommand('createLink', false, u);
			} else document.execCommand(p);
		});
	});
	f.addEventListener('submit', function () { keluar.value = isi.innerHTML; });
})();
