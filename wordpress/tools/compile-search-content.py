"""Collect reviewed author files and generate the dated semantic/content inventory."""
import argparse,collections,csv,datetime,json,pathlib,re,shutil,sys
from content_artifacts import validate_article,repeated_paragraphs,write_artifacts
parser=argparse.ArgumentParser(description=__doc__)
parser.add_argument('directories',nargs='+',type=pathlib.Path)
parser.add_argument('--ready-only',action='store_true',help='Compile only explicitly finished records, retaining the full calendar positions')
args=parser.parse_args()

base=pathlib.Path(__file__).resolve().parents[1]/'search-content'
records=[];origin={}
for directory in args.directories:
    rows=json.loads((directory/'manifest.json').read_text())
    ready_ids=json.loads((directory/'ready.json').read_text()).get('readyIds',[]) if (directory/'ready.json').is_file() else []
    for row in rows:
        number=int(row['id']);row['id']=number
        if not 101 <= number <= 201:continue
        row['_ready']=bool(row.get('longform_ready')) or number in ready_ids
        name=f"{number}-{row['slug']}.md"
        if not re.fullmatch(r'\d+-[a-z0-9-]+\.md',name):raise ValueError('Unsafe file name')
        candidate=directory/row.get('file',name)
        if not candidate.resolve().is_relative_to(directory.resolve()):raise ValueError('Unsafe source path')
        if not candidate.exists() and number==101:candidate=base/'articles'/name
        if not candidate.is_file():raise ValueError(f'Missing {candidate}')
        if number in origin:
            if number!=101:raise ValueError(f'Duplicate ID {number}')
            prior=next(r for r in records if r['id']==101)
            if prior['slug']!=row['slug'] or prior['title']!=row['title']:raise ValueError('Cornerstone identity mismatch')
            prior.update({k:v for k,v in row.items() if k in ('infographic','sources','longform_ready','_ready')})
            origin[number]=candidate;continue
        origin[number]=candidate;records.append(row)
records.sort(key=lambda r:r['id'])
assert [r['id'] for r in records]==list(range(101,202)), 'Expected all 101 texts'
def cluster(n):
    if n==101:return 'Стратегия автоматизации'
    if 102<=n<=120:return 'Техническое SEO'
    if 121<=n<=141 or 186<=n<=187:return 'Поддержка сайта'
    if 142<=n<=160:return 'Битрикс24'
    if 161<=n<=181:return 'Бизнес-процессы'
    if 182<=n<=185:return 'Интеграции'
    if 188<=n<=190:return 'Интернет-магазины'
    if 191<=n<=192:return 'Онлайн-запись'
    if 193<=n<=197:return 'ИИ и база знаний'
    if 198<=n<=199:return 'Веб-сервисы'
    return 'Управление IT'
groups=collections.OrderedDict()
for record in records:
    record['cluster']=cluster(record['id'])
    if record['id']!=101:groups.setdefault(record['cluster'],collections.deque()).append(record['id'])
order=[]
while any(groups.values()):
    for group in groups.values():
        if group:order.append(group.popleft())
assert len(order)==100
first=datetime.datetime(2026,10,23,10,0,tzinfo=datetime.timezone(datetime.timedelta(hours=3)))
dates={n:first+datetime.timedelta(days=i) for i,n in enumerate(order)}
if args.ready_only:records=[r for r in records if r['_ready']]
assert records,'No reviewed manuscripts available'
words=[];hashes=set();slugs=set();titles=set();queries=set();texts={};quality={}
for record in records:
    n=record['id'];record['planned_at']='2027-08-21 10:00:00' if n==101 else dates[n].strftime('%Y-%m-%d %H:%M:%S')
    record['series']='expertise' if n==101 else 'search'
    record['intent']='информационный / выбор решения' if n==101 else 'информационный / практическая задача'
    name=f"{n}-{record['slug']}.md";source=origin[n];raw=source.read_text();body=raw.split('\n',1)[1]
    count=len(re.findall(r'[\w]+',body,re.UNICODE));words.append(count)
    quality[n]=validate_article(record,raw);texts[n]=body
    for field,seen in [('slug',slugs),('title',titles),('primary_query',queries)]:
        assert record[field] not in seen,f'Duplicate {field}: {record[field]}'
        seen.add(record[field])
    assert body not in hashes,f'Duplicate body {n}';hashes.add(body)
    # Author scratch metadata never enters the published manifest.
    record.pop('file',None);record.pop('word_count',None);record.pop('_ready',None)
duplicates=repeated_paragraphs(texts)
if duplicates:raise ValueError('Repeated filler paragraphs: '+json.dumps(duplicates,ensure_ascii=False))
# No canonical output is changed until the entire collection passes preflight.
for record in records:
    n=record['id'];target=base/'articles'/f"{n}-{record['slug']}.md"
    if origin[n].resolve()!=target.resolve():shutil.copyfile(origin[n],target)
    record['artifacts']=write_artifacts(record,target.read_text(),base.parent/'theme/assets/infographics','article-'+str(n))
(base/'quality.json').write_text(json.dumps(quality,ensure_ascii=False,indent=2)+'\n')
(base/'manifest.json').write_text(json.dumps(records,ensure_ascii=False,indent=2)+'\n')
with (base/'semantic-core.csv').open('w',newline='') as f:
    fields=['type','id','cluster','primary_query','related_queries','intent','title','url','service','case','planned_at_msk']
    writer=csv.DictWriter(f,fieldnames=fields,lineterminator='\n');writer.writeheader()
    for r in records:writer.writerow(dict(type='article',id=r['id'],cluster=r['cluster'],primary_query=r['primary_query'],related_queries='; '.join(r['related_queries']),intent=r['intent'],title=r['title'],url='https://sy3.ru/journal/'+r['slug']+'/',service=r['service'],case=r.get('case') or '',planned_at_msk=r['planned_at']))
    services=json.loads((base/'services.json').read_text())+json.loads((base/'partner-services.json').read_text())
    for n,s in enumerate(services,1):writer.writerow(dict(type='service',id=n,cluster='Коммерческая услуга',primary_query=s['primary_query'],related_queries='',intent='заказ услуги',title=s['title'],url='https://sy3.ru/services/'+s['slug']+'/',service=s['slug'],case=','.join(s['case_slugs']),planned_at_msk='после review'))
calendar=sorted([{'id':r['id'],'title':r['title'],'slug':r['slug'],'series':r['series'],'publish_msk':r['planned_at'],'cluster':r['cluster']} for r in records],key=lambda r:r['publish_msk'])
(base/'calendar.json').write_text(json.dumps(calendar,ensure_ascii=False,indent=2)+'\n')
with (base/'calendar.csv').open('w',newline='') as f:
    writer=csv.DictWriter(f,fieldnames=calendar[0].keys(),lineterminator='\n');writer.writeheader();writer.writerows(calendar)
print(json.dumps({'articles':len(records),'ready_search_articles':sum(r['id']!=101 for r in records),'planned_search_articles':100,'new_services':len(services),'words':sum(words),'shortest':min(words),'longest':max(words),'search_first':dates[order[0]].isoformat(),'search_last':dates[order[-1]].isoformat(),'query_forms':len({v.casefold() for r in records for v in [r['primary_query'],*r['related_queries']]})},ensure_ascii=False))
