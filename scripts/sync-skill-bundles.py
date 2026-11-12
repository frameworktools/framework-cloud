#!/usr/bin/env python3
"""Update canonical skill bundle snapshots from the sibling package sources."""
import argparse
import base64
import hashlib
import json
from pathlib import Path

parser = argparse.ArgumentParser()
parser.add_argument('--r', type=Path, default=Path('../framework/inst/templates/skills'))
parser.add_argument('--python', type=Path, default=Path('../framework-py/src/framework/templates/skills'))
parser.add_argument('--stata', type=Path, default=Path('../framework-cli/internal/stata/skills'))
args = parser.parse_args()
registry_path = Path('database/seeders/masters/skill-bundles.json')
previous = json.loads(registry_path.read_text()) if registry_path.exists() else {}
registry = {}
for variant, root in [('r', args.r), ('python', args.python), ('stata', args.stata)]:
    for folder in sorted(root.iterdir()):
        if not (folder / 'SKILL.md').is_file(): continue
        files = []
        for path in sorted(folder.rglob('*')):
            if not path.is_file() or path.name == '.framework-skill.json': continue
            if path.is_symlink(): raise ValueError(f'Symlink: {path}')
            raw = path.read_bytes()
            files.append({'path': path.relative_to(folder).as_posix(), 'content': base64.b64encode(raw).decode('ascii'), 'executable': bool(path.stat().st_mode & 0o111)})
        manifest = 'framework-skill-bundle-v1\n'
        for f in files:
            raw = base64.b64decode(f['content'])
            # Text files hash with LF line endings; a file with a NUL byte hashes raw
            raw = raw if b'\0' in raw else raw.replace(b'\r\n', b'\n')
            manifest += f"{f['path']}\t{hashlib.sha256(raw).hexdigest()}\t{755 if f['executable'] else 644}\t{len(raw)}\n"
        checksum = hashlib.sha256(manifest.encode('ascii')).hexdigest()
        old = previous.get(folder.name, {}).get(variant, {})
        version = old.get('release', 0) + (old.get('hash') != checksum)
        record = {'skill_id': 'framework:' + folder.name, 'release': version, 'hash': checksum, 'bundle': files}
        registry.setdefault(folder.name, {})[variant] = record
        (folder / '.framework-skill.json').write_text(json.dumps({k:v for k,v in record.items() if k != 'bundle'}, indent=2) + '\n')
registry_path.write_text(json.dumps(registry, indent=2) + '\n')
