from project_matrix import *
results=[]
for lang in ['r','python','stata']:
    ident=lang+'-project-scripts'; folder=ROOT/'projects'/('headless-'+lang); log=ROOT/'logs'/('headless-'+lang+'.log');log.write_text('')
    if folder.exists(): folder.rename(folder.with_name(folder.name + '-previous-' + str(time.time_ns())))
    result={'language':lang,'errors':[]}
    try:
        command([str(ROOT/'fw'),'new','--from',str(ROOT/'cases'/f'{ident}.json'),'--dir',str(folder),'--headless'],ROOT,BASE|{'FW_CONFIG_HOME':str(ROOT/'config'/('headless-'+lang)),'FW_PYTHON':PY},log,120)
        assert (folder/'settings.yml').is_file();assert (folder/'.framework/spec.json').is_file();assert not (folder/'.git').exists()
    except Exception as e:result['errors'].append(str(e))
    print(result,flush=True);results.append(result)
(ROOT/'headless-results.json').write_text(json.dumps(results,indent=2))

raise SystemExit(1 if any(r["errors"] for r in results) else 0)
