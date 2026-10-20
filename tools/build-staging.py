#!/usr/bin/env python3
"""Build a byte-reproducible staging ZIP from an explicit runtime allowlist."""
import argparse
import hashlib
from pathlib import Path
import re
import zipfile

ROOT = Path(__file__).resolve().parents[1]
RUNTIME_FILES = (
    'LICENSE', 'readme.txt', 'editorial-scout.php', 'uninstall.php',
    'includes/class-editorial-scout-admin.php',
    'includes/class-editorial-scout-dashboard.php',
    'includes/class-editorial-scout-feed.php',
    'includes/class-editorial-scout-runner.php',
    'includes/class-editorial-scout-scheduling.php', 'assets/admin.js',
    'includes/class-editorial-scout-store.php', 'assets/admin.css',
)


def build(output_dir):
    header = (ROOT / 'editorial-scout.php').read_text()
    match = re.search(r'^ \* Version: ([0-9A-Za-z.-]+)$', header, re.M)
    if not match:
        raise ValueError('Missing safe plugin version')
    output_dir.mkdir(parents=True, exist_ok=True)
    target = output_dir / f'editorial-scout-{match[1]}.zip'
    expected = {}
    for relative in sorted(RUNTIME_FILES):
        source = ROOT / relative
        if source.is_symlink() or not source.resolve(strict=True).is_relative_to(ROOT):
            raise ValueError(f'Runtime file is not a regular checkout file: {relative}')
        expected['editorial-scout/' + relative] = source.read_bytes()
    # Stored entries avoid dependence on a particular zlib release. No file timestamps,
    # owner IDs, secrets, fixture files, or dependency trees enter the archive.
    with zipfile.ZipFile(target, 'w', compression=zipfile.ZIP_STORED) as archive:
        for name, data in expected.items():
            info = zipfile.ZipInfo(name, date_time=(1980, 1, 1, 0, 0, 0))
            info.create_system = 3
            info.external_attr = 0o100644 << 16
            archive.writestr(info, data)
    with zipfile.ZipFile(target) as archive:
        assert archive.testzip() is None
        assert archive.namelist() == list(expected)
        assert all(archive.read(name) == data for name, data in expected.items())
    digest = hashlib.sha256(target.read_bytes()).hexdigest()
    target.with_suffix('.zip.sha256').write_text(f'{digest}  {target.name}\n')
    print(f'{target}\nSHA256 {digest}\n{len(expected)} runtime files; {target.stat().st_size} bytes')
    return target


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--output-dir', type=Path, default=ROOT / 'artifacts')
    build(parser.parse_args().output_dir)
