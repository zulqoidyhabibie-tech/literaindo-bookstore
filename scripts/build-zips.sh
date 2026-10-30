#!/usr/bin/env bash
# Membuat file zip tema dan plugin yang siap diunggah lewat admin WordPress.
# Pemakaian: scripts/build-zips.sh [folder-keluaran]   (bawaan: dist/)
set -euo pipefail

root="$(cd "$(dirname "$0")/.." && pwd)"
out="${1:-$root/dist}"
mkdir -p "$out"
out="$(cd "$out" && pwd)"

build() {
	local dir="$1" name="$2"
	if [ ! -d "$root/$dir/$name" ]; then
		echo "Lewati $name: folder $dir/$name tidak ada di branch ini" >&2
		return
	fi
	rm -f "$out/$name.zip"
	(cd "$root/$dir" && zip -qr "$out/$name.zip" "$name" -x "$name/tests/*" "*.DS_Store")
	echo "Dibuat: $out/$name.zip"
}

build wordpress/themes literaindo-child
build wordpress/plugins literaindo-bookstore
