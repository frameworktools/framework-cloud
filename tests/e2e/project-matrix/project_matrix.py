import concurrent.futures
import json
import os
from pathlib import Path
import subprocess
import time

ROOT = Path(os.environ['FW_E2E_ROOT']).resolve()
PY = str(ROOT / 'python/bin/python')
CASES = json.loads((ROOT / 'matrix.json').read_text())
(ROOT / 'projects').mkdir(exist_ok=True)
(ROOT / 'logs').mkdir(exist_ok=True)
BASE = os.environ | {
    'PATH': str(ROOT / 'python/bin') + ':' + os.environ['PATH'],
    'R_LIBS': str(ROOT / 'r-library'),
    'JUPYTER_PATH': str(ROOT / 'jupyter/share/jupyter'),
    'JUPYTER_CONFIG_DIR': str(ROOT / 'jupyter-config'),
    'QUARTO_PYTHON': PY,
    'MPLBACKEND': 'Agg',
    'GIT_AUTHOR_NAME': 'Framework smoke test', 'GIT_AUTHOR_EMAIL': 'smoke@example.invalid',
    'GIT_COMMITTER_NAME': 'Framework smoke test', 'GIT_COMMITTER_EMAIL': 'smoke@example.invalid',
}

def command(args, cwd, env, log, timeout=100):
    result = subprocess.run(args, cwd=cwd, env=env, stdin=subprocess.DEVNULL, stdout=subprocess.PIPE, stderr=subprocess.STDOUT, text=True, timeout=timeout)
    with log.open('a') as f:
        f.write('\n$ ' + ' '.join(args) + '\n' + result.stdout)
    if result.returncode:
        raise RuntimeError(f'exit {result.returncode}: {result.stdout[-1400:]}')
    return result.stdout

def run(case):
    started = time.time()
    ident = case['id']
    folder = ROOT / 'projects' / ident
    log = ROOT / 'logs' / f'{ident}.log'
    log.write_text('')
    env = BASE | {'FW_CONFIG_HOME': str(ROOT / 'config' / ident)}
    result = {'id': ident, 'checks': [], 'errors': []}
    def step(name, function):
        try:
            function()
            result['checks'].append(name)
            return True
        except Exception as error:
            result['errors'].append({'step': name, 'error': str(error)})
            return False
    layers = ROOT / 'cases' / f'{ident}.json'
    def create():
        if case['language'] == 'r':
            command(['Rscript', str(ROOT/'create.R'), str(layers), str(folder)], ROOT, env, log)
        elif case['language'] == 'python':
            command([PY, '-c', 'import framework,sys; framework.project_create_from_layers(sys.argv[1], sys.argv[2], interactive=True)', str(layers), str(folder)], ROOT, env, log)
        else:
            command([str(ROOT/'fw'), 'new', '--from', str(layers), '--dir', str(folder)], ROOT, env, log)
    if not step('create', create):
        result['seconds'] = round(time.time()-started, 2)
        return result
    def inventory():
        missing = [e['path'] for e in case['preview']['tree'] if not e['later'] and not (folder/e['path']).exists()]
        assert not missing, f'Preview promises missing paths: {missing}'
        assert (folder/'.framework/spec.json').is_file()
        assert (folder/'.git').is_dir()
        assert command(['git', 'check-ignore', '.env'], folder, env, log).strip() == '.env'
        if case['type'] != 'bare':
            for path in ['AGENTS.md', 'CLAUDE.md', '.github/copilot-instructions.md', '.agents/skills', '.claude/skills']:
                assert (folder/path).exists(), f'Missing agent entry: {path}'
        if case['type'] == 'project_sensitive':
            assert command(['git', 'check-ignore', 'inputs/private/smoke.csv'], folder, env, log).strip()
        for path, entry in case['spec'].get('files', {}).items():
            if entry is not None:
                assert (folder/path).is_file(), f'Missing starter file: {path}'
                if entry.get('template'):
                    assert '{{project_name}}' not in (folder/path).read_text(), f'Unexpanded template in {path}'
        if case['language'] == 'python' and case['spec'].get('tools', {}).get('uv'):
            assert (folder/'pyproject.toml').is_file()
    step('initial_files', inventory)
    if case['language'] == 'stata':
        def stata_files():
            settings = (folder/'settings.do').read_text()
            assert 'global root' in settings
            if case['type'] != 'bare':
                text = (folder/'master.do').read_text()
                assert 'framework' in text.lower()
        step('stata_content', stata_files)
        result['execution_limit'] = 'Stata runtime not installed'
    else:
        def analysis():
            spec = json.loads((folder/'.framework/spec.json').read_text())
            functions = spec.get('directories', {}).get('functions')
            if isinstance(functions, str):
                target = folder/functions
                target.mkdir(parents=True, exist_ok=True)
                (folder/'.e2e-functions').touch()
                if case['language'] == 'r':
                    (target/'smoke-helper.R').write_text('smoke_double <- function(x) x * 2\n')
                else:
                    (target/'smoke_helper.py').write_text('def smoke_double(x):\n    return x * 2\n')
            program = ['Rscript', str(ROOT/'smoke.R')] if case['language'] == 'r' else [PY, str(ROOT/'smoke.py')]
            command(program + [str(folder)], folder, env, log)
            result['analysis'] = json.loads((folder/'smoke-result.json').read_text())
        step('analysis_data_outputs_cache_functions', analysis)
        def git_hooks():
            assert (folder/'.git/hooks/pre-commit').is_file(), 'Pre-commit hook missing'
            assert command(['git', 'rev-parse', 'HEAD'], folder, env, log).strip()
        step('git_hooks', git_hooks)
    result['seconds'] = round(time.time()-started, 2)
    return result

if __name__ == '__main__':
    selected = [c for c in CASES if not os.environ.get('FW_E2E_LANGUAGES') or c['language'] in os.environ['FW_E2E_LANGUAGES'].split(',')]
    results = [r for r in json.loads((ROOT/'results.json').read_text()) if r['id'] not in {c['id'] for c in selected}] if (ROOT/'results.json').exists() else []
    for case in selected:
        old = ROOT/'projects'/case['id']
        if old.exists():
            old.rename(old.with_name(old.name + '-previous-' + str(time.time_ns())))
        oldlog = ROOT/'logs'/f"{case['id']}.log"
        if oldlog.exists():
            oldlog.rename(oldlog.with_suffix('.previous-' + str(time.time_ns()) + '.log'))
    with concurrent.futures.ThreadPoolExecutor(max_workers=4) as pool:
        for future in concurrent.futures.as_completed([pool.submit(run, case) for case in selected]):
            result = future.result()
            results.append(result)
            (ROOT/'results.json').write_text(json.dumps(sorted(results, key=lambda row: row['id']), indent=2))
            print(('FAIL' if result['errors'] else 'PASS') + ' ' + result['id'] + ' ' + ','.join(e['step'] for e in result['errors']), flush=True)
    print(json.dumps({'cases': len(results), 'passed': sum(not r['errors'] for r in results)}), flush=True)

    raise SystemExit(1 if any(r["errors"] for r in results) else 0)
