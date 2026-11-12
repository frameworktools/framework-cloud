from project_matrix import *
import nbformat
from nbclient import NotebookClient

def render(c):
    ident=c['id']; folder=ROOT/'projects'/ident; log=ROOT/'logs'/f'{ident}-render.log'; log.write_text('')
    env=BASE|{'FW_CONFIG_HOME':str(ROOT/'config'/ident)}
    r={'id':ident,'errors':[]}
    try:
        inp=json.loads((folder/'smoke-result.json').read_text())['input']
        if c['language']=='r':
            code=f'library(framework)\nscaffold()\nd <- data_read("{inp}")\nstopifnot(nrow(d) == 5, mean(d$y) == 7)\nprint(summary(d))\nplot(d$x, d$y)'
        else:
            code=f'import framework as fw\nfw.scaffold()\nd = fw.data_read("{inp}")\nassert len(d) == 5 and d.y.mean() == 7\nprint(d.describe())\nimport matplotlib.pyplot as plt\nplt.plot(d.x, d.y)\nplt.show()'
        fmt=c['format']; lang=c['language']
        if fmt=='jupyter':
            nb=nbformat.v4.new_notebook(cells=[nbformat.v4.new_code_cell(code)],metadata={'kernelspec':{'name':'ir' if lang=='r' else 'python3','display_name':'R' if lang=='r' else 'Python','language':lang}})
            path=folder/'smoke.ipynb'; nbformat.write(nb,path)
            # Use subprocess so each kernel receives its isolated project environment.
            command([PY,'-c','import nbformat,sys; from nbclient import NotebookClient; p=sys.argv[1]; n=nbformat.read(p,as_version=4); NotebookClient(n,timeout=90,resources={"metadata":{"path":"."}}).execute(); nbformat.write(n,p)',str(path)],folder,env,log,120)
            command(['/Applications/quarto/bin/quarto','render','smoke.ipynb','--to','html'],folder,env,log,120)
        elif fmt=='rmarkdown':
            (folder/'smoke.Rmd').write_text('---\ntitle: "Framework smoke"\noutput: html_document\n---\n\n```{r}\n'+code+'\n```\n')
            command(['Rscript','-e','rmarkdown::render("smoke.Rmd", quiet=TRUE)'],folder,env,log,120)
        else:
            path=folder/'presentation.qmd' if c['type']=='presentation' else folder/'smoke.qmd'
            text=path.read_text() if path.exists() else '---\ntitle: "Framework smoke"\n'+('jupyter: python3\n' if lang=='python' else '')+'---\n'
            path.write_text(text+'\n## Analysis\n\n```{'+lang+'}\n'+code+'\n```\n')
            command(['/Applications/quarto/bin/quarto','render',path.name],folder,env,log,120)
        assert list(folder.rglob('*.html')), 'No rendered HTML'
    except Exception as e:r['errors'].append(str(e))
    print(('FAIL' if r['errors'] else 'PASS')+' render '+ident,flush=True)
    return r
cases=[c for c in CASES if c['language']!='stata' and c['format']!='scripts']
with concurrent.futures.ThreadPoolExecutor(max_workers=3) as pool:
    results=list(pool.map(render,cases))
(ROOT/'render-results.json').write_text(json.dumps(results,indent=2))
print(json.dumps({'cases':len(results),'passed':sum(not r['errors'] for r in results)}),flush=True)

raise SystemExit(1 if any(r["errors"] for r in results) else 0)
