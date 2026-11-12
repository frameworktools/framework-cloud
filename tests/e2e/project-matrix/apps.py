from project_matrix import *
import socket, urllib.request
results=[]
for name in ['r-shiny-scripts','r-plumber-scripts','python-shiny_python-scripts','python-fastapi-scripts']:
    folder=ROOT/'projects'/name
    port=38000+len(results)
    if name.startswith('r-shiny'):cmd=['Rscript','-e',f'shiny::runApp(".",host="127.0.0.1",port={port},launch.browser=FALSE)']; endpoint='/'
    elif name.startswith('r-plumber'):cmd=['Rscript','-e',f'plumber::plumb("plumber.R")$run(host="127.0.0.1",port={port},docs=FALSE)'];endpoint='/health'
    elif 'fastapi' in name:cmd=[PY,'-m','uvicorn','app.main:app','--host','127.0.0.1','--port',str(port)];endpoint='/health'
    else:cmd=[PY,'-m','shiny','run','--host','127.0.0.1','--port',str(port),'app.py'];endpoint='/'
    result={'id':name,'errors':[]}
    with (ROOT/'logs'/f'{name}-app.log').open('w') as log:
        proc=subprocess.Popen(cmd,cwd=folder,env=BASE|{'FW_CONFIG_HOME':str(ROOT/'config'/name)},stdout=log,stderr=subprocess.STDOUT)
        try:
            for _ in range(90):
                try:
                    with urllib.request.urlopen(f'http://127.0.0.1:{port}{endpoint}',timeout=1) as response:
                        body=response.read().decode();assert response.status==200
                    break
                except Exception:
                    if proc.poll() is not None:raise RuntimeError('App exited; see log')
                    time.sleep(.5)
            else:raise RuntimeError('No response before timeout')
            if 'plumber' in name:
                body=urllib.request.urlopen(f'http://127.0.0.1:{port}/echo?msg=smoke').read().decode();assert 'smoke' in body
            if 'fastapi' in name:
                req=urllib.request.Request(f'http://127.0.0.1:{port}/echo',data=b'{"message":"smoke"}',headers={'Content-Type':'application/json'})
                assert json.load(urllib.request.urlopen(req))=={'message':'smoke'}
        except Exception as e:result['errors'].append(str(e))
        finally:
            proc.terminate()
            try:proc.wait(timeout=10)
            except subprocess.TimeoutExpired:proc.kill();proc.wait()
    results.append(result);print(result,flush=True)
(ROOT/'app-results.json').write_text(json.dumps(results,indent=2))

raise SystemExit(1 if any(r["errors"] for r in results) else 0)
