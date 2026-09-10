#!/usr/bin/env bash
# Rebuild the stylesheet after changing any Blade template or resources/css/app.css.
#
# Tailwind runs through its standalone binary rather than Vite: Node here is 18
# and Vite 7 needs 20+. Download the binary once with:
#
#   curl -L -o tools/tailwindcss.exe \
#     https://github.com/tailwindlabs/tailwindcss/releases/latest/download/tailwindcss-windows-x64.exe
#
# Pass --watch to rebuild automatically while working.
set -e
cd "$(dirname "$0")"
exec ./tools/tailwindcss.exe -i resources/css/app.css -o public/build/app.css --minify "$@"
