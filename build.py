#!/usr/bin/env python3
"""Build pkg_chaosfilet: check versions, minify, zip, and publish to the CFD update server.

    python3 build.py            # build dist/pkg_chaosfilet-<version>.zip
    python3 build.py --no-min   # skip minifying (uses the .min files already there)

Needs Python 3.9+ and Node (npx fetches terser and csso the first time).
The update-server step runs only when the cfd-updates folder is found:
$CFD_UPDATES, else ../cfd-updates next to this folder.
"""

import os
import re
import subprocess
import sys
import zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parent
DIST = ROOT / 'dist'
CON = ROOT / 'plg_content_chaosfilet'
BTN = ROOT / 'plg_editors-xtd_chaosfilet'
JS = BTN / 'media/js/insert-tag.js'
CSS = BTN / 'media/css/insert-tag.css'
PHP_MIN = '8.2'


def fail(msg):
    print(f'\n✗ {msg}')
    sys.exit(1)


def xml_version(path):
    m = re.search(r'<version>([^<]+)</version>', path.read_text(encoding='utf-8'))
    return m.group(1).strip() if m else None


def header_version(path, name):
    m = re.search(rf'/\*! {re.escape(name)} ([0-9][^ |]*)', path.read_text(encoding='utf-8'))
    return m.group(1) if m else None


def check_versions():
    want = xml_version(ROOT / 'pkg_chaosfilet.xml')
    found = {
        'pkg_chaosfilet.xml': want,
        'content chaosfilet.xml': xml_version(CON / 'chaosfilet.xml'),
        'button chaosfilet.xml': xml_version(BTN / 'chaosfilet.xml'),
        'insert-tag.js header': header_version(JS, 'Chaos Filet editor button'),
        'insert-tag.css header': header_version(CSS, 'Chaos Filet editor button'),
    }
    php_files = [ROOT / 'script.php', *sorted(CON.rglob('*.php')), *sorted(BTN.rglob('*.php'))]
    for p in php_files:
        m = re.search(r'@version\s+(\S+)', p.read_text(encoding='utf-8'))
        found[f'{p.relative_to(ROOT)} @version'] = m.group(1) if m else None
    assets = re.findall(r"'version' => '([^']+)'", (BTN / 'src/Extension/ChaosFilet.php').read_text(encoding='utf-8'))
    if not assets:
        fail('ChaosFilet.php (button) registers no asset with a version.')
    for i, v in enumerate(assets, 1):
        found[f'button asset version {i}'] = v
    bad = {k: v for k, v in found.items() if v != want}
    if bad:
        fail('Versions disagree (want ' + str(want) + '):\n  ' + '\n  '.join(f'{k}: {v}' for k, v in bad.items()))
    readme = (ROOT / 'README.md').read_text(encoding='utf-8')
    if not re.search(rf'^### {re.escape(want)}\b', readme, re.M):
        fail(f'README.md has no "### {want}" changelog entry.')
    print(f'✓ Version {want} everywhere ({len(found)} places) and in the README changelog')
    return want


def check_css_order(css, where):
    """Minified rules keep their properties in alphabetical order (house style)."""
    flat = re.sub(r'/\*.*?\*/', '', css, flags=re.S)
    flat = re.sub(r'@(media|supports)[^{]*\{', '', flat)
    flat = re.sub(r'@keyframes[^{]*\{(?:[^{}]*\{[^}]*\})*\s*\}', '', flat)
    problems = []
    for sel, body in re.findall(r'([^{}]+)\{([^{}]*)\}', flat):
        if ':root' in sel or '\n' in body.strip():
            continue  # :root and the expanded @media blocks are written long-hand
        props = [p.split(':', 1)[0].strip() for p in body.split(';') if ':' in p]
        props = [p for p in props if not p.startswith('--')]
        if props != sorted(props):
            problems.append(sel.strip())
    if problems:
        fail(f'{where}: properties out of order in\n  ' + '\n  '.join(problems))


def run(cmd):
    r = subprocess.run(cmd, cwd=ROOT, capture_output=True, text=True)
    if r.returncode:
        fail(' '.join(cmd) + '\n' + r.stderr)


def minify():
    run(['npx', '--yes', 'terser@5', str(JS.relative_to(ROOT)), '--module', '--compress', '--mangle',
         '--comments', '/^!/', '-o', str(JS.with_suffix('.min.js').relative_to(ROOT))])
    mini = CSS.with_suffix('.min.css')
    run(['npx', '--yes', '-p', 'csso-cli@4', 'csso', str(CSS.relative_to(ROOT)), '--no-restructure',
         '-o', str(mini.relative_to(ROOT))])
    head = re.match(r'/\*!.*?\*/', CSS.read_text(encoding='utf-8'), re.S).group(0)
    body = mini.read_text(encoding='utf-8')
    if not body.startswith('/*!'):
        mini.write_text(head + '\n' + body, encoding='utf-8')
    print('✓ Minified insert-tag.js and insert-tag.css')


def zip_dir(src, dest):
    with zipfile.ZipFile(dest, 'w', zipfile.ZIP_DEFLATED) as z:
        for p in sorted(src.rglob('*')):
            if p.is_dir() or p.name in ('.DS_Store',):
                continue
            z.write(p, p.relative_to(src).as_posix())


def build_zips(version):
    DIST.mkdir(exist_ok=True)
    stage = DIST / 'packages'
    stage.mkdir(exist_ok=True)
    zip_dir(CON, stage / 'plg_content_chaosfilet.zip')
    zip_dir(BTN, stage / 'plg_editors-xtd_chaosfilet.zip')
    pkg = DIST / f'pkg_chaosfilet-{version}.zip'
    with zipfile.ZipFile(pkg, 'w', zipfile.ZIP_DEFLATED) as z:
        z.write(ROOT / 'pkg_chaosfilet.xml', 'pkg_chaosfilet.xml')
        z.write(ROOT / 'script.php', 'script.php')
        z.write(ROOT / 'language/en-GB/pkg_chaosfilet.sys.ini', 'language/en-GB/pkg_chaosfilet.sys.ini')
        z.write(stage / 'plg_content_chaosfilet.zip', 'packages/plg_content_chaosfilet.zip')
        z.write(stage / 'plg_editors-xtd_chaosfilet.zip', 'packages/plg_editors-xtd_chaosfilet.zip')
    for f in stage.iterdir():
        f.unlink()
    stage.rmdir()

    src = DIST / f'chaosfilet-{version}-source.zip'
    with zipfile.ZipFile(src, 'w', zipfile.ZIP_DEFLATED) as z:
        for p in sorted(ROOT.rglob('*')):
            rel = p.relative_to(ROOT)
            if p.is_dir() or rel.parts[0] in ('dist', 'node_modules', '__pycache__'):
                continue
            z.write(p, f'chaosfilet/{rel.as_posix()}')
    return pkg, src


def publish(pkg):
    here = os.environ.get('CFD_UPDATES') or str(ROOT.parent / 'cfd-updates')
    if not Path(here, 'cfd_update.py').is_file():
        print(f'• Update server skipped: no cfd_update.py in {here} (set CFD_UPDATES to its folder).')
        return
    sys.path.insert(0, here)
    import cfd_update  # noqa: E402  (lives in the cfd-updates folder)
    try:
        cfd_update.release('pkg_chaosfilet', str(pkg))
    except cfd_update.Fail as e:
        # The package is built either way; only the update XML wasn't written.
        print(f'• Update server skipped: {e}')
        return
    print('✓ Update server files written by cfd_update.release()')


def main():
    version = check_versions()
    check_css_order(CSS.read_text(encoding='utf-8'), 'insert-tag.css')
    print('✓ CSS properties in order')
    if '--no-min' not in sys.argv:
        minify()
    pkg, src = build_zips(version)
    size = pkg.stat().st_size
    print(f'✓ {pkg.relative_to(ROOT)}  ({size / 1024:.1f} KB)')
    print(f'✓ {src.relative_to(ROOT)}')
    publish(pkg)
    print('\nTools page:')
    print(f'  Version      {version}')
    print(f'  Download     {size / 1024:.0f} KB (pkg_chaosfilet-{version}.zip)')
    print(f'  Needs        Joomla 5 and 6, PHP {PHP_MIN}+')


if __name__ == '__main__':
    main()
