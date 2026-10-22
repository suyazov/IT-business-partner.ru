"""Compile reviewed, original Beget articles into the existing daily SEO queue."""
import argparse,csv,datetime,hashlib,json,pathlib,re
from content_artifacts import validate_article,repeated_paragraphs,write_artifacts

parser=argparse.ArgumentParser(description=__doc__)
parser.add_argument('directories',nargs='+',type=pathlib.Path,help='Full brief inventory followed by separate author packages')
parser.add_argument('--ready-only',action='store_true')
parser.add_argument('--audit-only',action='store_true',help='Validate without changing canonical content')
args=parser.parse_args()
root=pathlib.Path(__file__).resolve().parents[1];base=root/'beget-content'
directory=args.directories[0]
rows=json.loads((directory/'manifest.json').read_text())
assert sorted(r['id'] for r in rows)==list(range(202,302)),'Expected all 100 Beget briefs'
indexed={r['id']:r for r in rows};origins={r['id']:directory for r in rows}
identity=('id','slug','title','primary_query','related_queries','cluster','service','case','cover','angle')
for directory in args.directories[1:]:
    incoming=json.loads((directory/'manifest.json').read_text())
    if len({r['id'] for r in incoming})!=len(incoming):raise ValueError('Duplicate author ID')
    for row in incoming:
        number=row['id']
        if number not in indexed:raise ValueError('Unknown Beget ID')
        prior=indexed[number]
        if any(row.get(k,prior.get(k))!=prior.get(k) for k in identity):raise ValueError('Beget brief identity changed: '+str(number))
        if not row.get('longform_ready'):continue
        if prior.get('longform_ready'):raise ValueError('Two finished manuscripts for ID '+str(number))
        indexed[number]={**prior,**row};origins[number]=directory
rows=list(indexed.values())
assert len({r['slug'] for r in rows})==100 and len({r['primary_query'] for r in rows})==100
previous=json.loads((root/'search-content/manifest.json').read_text())
assert not {r['slug'] for r in rows}&{r['slug'] for r in previous},'Search article collision'
plan=[];ready=[];texts={};quality={}
first=datetime.datetime(2027,1,31,10,0,tzinfo=datetime.timezone(datetime.timedelta(hours=3)))
for r in sorted(rows,key=lambda r:r['id']):
    if not re.fullmatch('[a-z0-9-]+',r['slug']):raise ValueError('Unsafe slug')
    standalone=r['id']==202
    date=datetime.datetime(2026,10,23,0,0,tzinfo=first.tzinfo) if standalone else first+datetime.timedelta(days=r['id']-203)
    item={k:r[k] for k in ['id','slug','title','primary_query','related_queries','cluster','service','case','cover','sources','angle']}
    item.update(planned_at=date.strftime('%Y-%m-%d %H:%M:%S'),series='standalone' if standalone else 'search',standalone=standalone,collection='beget',manuscript_ready=bool(r.get('longform_ready')))
    plan.append(item)
    if not r.get('longform_ready'):
        if not args.ready_only:raise ValueError('Unfinished manuscript '+r['slug'])
        continue
    origin=origins[r['id']]
    file=origin/r['file']
    if not file.resolve().is_relative_to(origin.resolve()):raise ValueError('Unsafe input file')
    raw=file.read_text();quality[r['id']]=validate_article(r,raw);texts[r['id']]=raw
    if len(r.get('excerpt','')) not in range(100,231) or not r['excerpt'].endswith(('.', '!', '?')):raise ValueError('Incomplete excerpt')
    if raw.count('https://beget.com/p133852')!=1 or 'партнёрск' not in raw.casefold():raise ValueError('One disclosed affiliate link required')
    item={**item,'excerpt':r['excerpt'],'infographic':r['infographic'],'intent':'информационный / практическая задача бизнеса','longform_ready':True}
    ready.append(item)
if not ready:raise ValueError('No finished manuscripts')
if repeated_paragraphs(texts):raise ValueError('Repeated filler paragraphs')
if args.audit_only:
    print(json.dumps({'planned':100,'ready':len(ready),'canonical_written':False,'ready_minimum_characters':min(q['visible_characters'] for q in quality.values())},ensure_ascii=False))
    raise SystemExit(0)
directory=base/'articles';directory.mkdir(parents=True,exist_ok=True)
for r in ready:
    file=directory/f"{r['id']}-{r['slug']}.md";file.write_text(texts[r['id']])
    r['artifacts']=write_artifacts(r,texts[r['id']],root/'theme/assets/infographics','article-'+str(r['id']))
(base/'manifest.json').write_text(json.dumps(ready,ensure_ascii=False,indent=2)+'\n')
(base/'plan.json').write_text(json.dumps(plan,ensure_ascii=False,indent=2)+'\n')
(base/'quality.json').write_text(json.dumps(quality,ensure_ascii=False,indent=2)+'\n')
with (base/'topics.csv').open('w',newline='') as f:
    fields=['id','title','primary_query','cluster','service','planned_at','manuscript_ready']
    writer=csv.DictWriter(f,fieldnames=fields,lineterminator='\n');writer.writeheader();writer.writerows({k:r[k] for k in fields} for r in plan)
print(json.dumps({'planned':100,'ready':len(ready),'standalone_article':202,'daily_articles':99,'ready_minimum_characters':min(q['visible_characters'] for q in quality.values()),'daily_first':first.isoformat(),'daily_last':(first+datetime.timedelta(days=98)).isoformat()},ensure_ascii=False))
