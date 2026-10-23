#!/usr/bin/env python3
"""Display one complete baseline body for an individual editorial pass."""
import json,re,html,sys
rows=json.load(open('/root/preview/sy3-wordpress-20261022/private/editorial-refactor-20261023/runtime-baseline.json'))['rows']
r=next(r for r in rows if r['ID']==sys.argv[1])
print(r['ID'],r['post_name'],r['post_title'])
print(html.unescape(re.sub('<[^>]+>','',r['post_content'])))
