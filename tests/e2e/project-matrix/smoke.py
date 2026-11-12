import json
from pathlib import Path
import sys
import numpy as np
import pandas as pd
import matplotlib
matplotlib.use('Agg')
import matplotlib.pyplot as plt
import framework as fw

root = Path(sys.argv[1])
import os
os.chdir(root)
fw.scaffold()
if Path('.e2e-functions').exists():
    import smoke_helper
    assert smoke_helper.smoke_double(3) == 6
spec = json.loads(Path('.framework/spec.json').read_text())
dirs = spec.get('directories', {})
data_dir = next((dirs[k] for k in ['inputs_private_raw', 'inputs_raw', 'data'] if dirs.get(k)), 'smoke-data')
Path(data_dir).mkdir(parents=True, exist_ok=True)
frame = pd.DataFrame({'x': list(range(1, 6)), 'y': [1 + 2*x for x in range(1, 6)]})
source = f'{data_dir}/smoke.csv'
fw.data_save(frame, source, force=True)
loaded = fw.data_read(source)
assert len(loaded) == 5 and list(loaded.y) == list(frame.y)
slope, intercept = np.polyfit(loaded.x, loaded.y, 1)
assert abs(slope - 2) < 1e-8
model = {'slope': float(slope), 'intercept': float(intercept)}
table = fw.save_table(pd.DataFrame({'rows': [5], 'slope': [slope]}), 'smoke-summary')
model_path = fw.save_model(model, 'smoke-model')
assert abs(fw.load_model('smoke-model')['slope'] - 2) < 1e-8
fig, ax = plt.subplots()
ax.scatter(loaded.x, loaded.y)
figure = fw.save_figure(fig, 'smoke-figure')
plt.close(fig)
fw.cache('smoke-result', {'slope': 2})
assert fw.cache_get('smoke-result')['slope'] == 2
assert Path(table).is_file() and Path(model_path).is_file() and Path(figure).is_file()
assert Path('framework.db').is_file()
Path('smoke-result.json').write_text(json.dumps({'input': source, 'table': str(table), 'model': str(model_path), 'figure': str(figure), 'rows': 5, 'slope': float(slope), 'cache': True}))
