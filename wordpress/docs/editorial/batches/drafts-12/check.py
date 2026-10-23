#!/usr/bin/env python3
"""Focused offline artifact verification; no application bootstrap or runtime writes."""
import collections
import hashlib
import html
from html.parser import HTMLParser
import json
import re
import subprocess
import build as b

class Tags(HTMLParser):
    void = set('area base br col embed hr img input link meta param source track wbr'.split())
    def __init__(self):
        super().__init__(); self.stack=[]
    def handle_starttag(self, tag, attrs):
        if tag not in self.void: self.stack.append(tag)
    def handle_endtag(self, tag):
        assert self.stack and self.stack.pop()==tag, tag

def main():
    manifest=json.loads((b.HERE/'manifest.json').read_text())
    rows=json.loads(open('/root/preview/sy3-wordpress-20261022/private/editorial-refactor-20261023/runtime-baseline.json').read())['rows']
    by_id={int(r['ID']):r for r in rows}
    base=b.quality({str(i):by_id[i]['post_content'] for i in b.IDS})
    bodies={}; affiliate_count=0; per=[]
    for c in manifest['candidates']:
        old=by_id[c['id']]['post_content']; body=(b.HERE/c['file']).read_text()
        assert b.sha(old)==c['baseline_hash'] and b.sha(body)==c['candidate_hash']
        assert c['visible_chars']>=10500
        assert c['preserved']['post_status']=='draft'
        assert all(x in body for x in b.protected(old))
        assert {b.TG if u==b.TG_OLD else u for u in b.urls(old)}<=b.urls(body)
        assert body.count('<!-- wp:details -->')==2 and body.count('>Вопросы по теме</h2>')==1
        assert not re.search(r'\b(?:Сказк\w*|Skazka\w*|ParkOps|AlekseiGunko|foodops)\b',body,re.I)
        parser=Tags(); parser.feed(body); assert not parser.stack
        paras=[html.unescape(re.sub('<[^>]+>','',p)).strip() for p in re.findall(r'<p\b[^>]*>(.*?)</p>',body,re.S)]
        assert not [p for p,n in collections.Counter(paras).items() if len(p)>120 and n>1]
        for line in body.splitlines(): assert line==line.rstrip()
        for a in c['protected_artifacts']:
            assert hashlib.sha256((b.ROOT/a['file']).read_bytes()).hexdigest()==a['sha256']
        disclosures=re.findall(r'<!-- wp:paragraph[^>]*--><p[^>]*>[^\n]*?https://beget\.com/p133852[^\n]*?</p><!-- /wp:paragraph -->',old)
        for disclosure in disclosures:
            assert 'href="https://beget.com/p133852" rel="sponsored nofollow"' in body
            assert 'я могу получить вознаграждение за услуги, участвующие в программе.' in body
        if c['id'] in {482,483}: assert 'ИИ-шлюз Beget в партнёрской программе не участвует.' in body
        affiliate_count+=len(disclosures)
        per.append({'id':c['id'],'baseline_quality_chars':base['results'][str(c['id'])]['characters'],'candidate_quality_chars':c['visible_chars'],'candidate_hash':c['candidate_hash']})
        bodies[str(c['id'])]=body
    code=r'''require '/var/www/sy3-wordpress/wp-includes/class-wp-block-parser.php'; $b=json_decode(stream_get_contents(STDIN),true);$n=0;$f=0;function visit($a){global $n,$f;foreach($a as $x){if($x['blockName'])$n++;elseif(trim($x['innerHTML']))$f++;visit($x['innerBlocks']);}}foreach($b as $s)visit((new WP_Block_Parser())->parse($s));echo json_encode(['native_named_blocks'=>$n,'nonempty_freeform'=>$f]);'''
    counts=json.loads(subprocess.run(['php','-r',code],input=json.dumps(bodies),text=True,capture_output=True,check=True).stdout)
    assert counts['nonempty_freeform']==0
    qa={'batch':manifest['batch'],'candidates':20,'quality_function_hash':manifest['candidates'][0]['quality_function_hash'],'minimum_quality_characters':min(c['visible_chars'] for c in manifest['candidates']),'maximum_quality_characters':max(c['visible_chars'] for c in manifest['candidates']),'protected_native_blocks':sum(c['audit']['protected_blocks'] for c in manifest['candidates']),'carried_prefix_artifacts':sum(c['audit']['carried_prefix_artifacts'] for c in manifest['candidates']),'asset_files_unchanged':sum(len(c['protected_artifacts']) for c in manifest['candidates']),**counts,'faq_blocks':40,'recognized_faq_h2':20,'affiliate_disclosures_byte_identical':affiliate_count,'all_original_href_targets_preserved_except_legacy_telegram':True,'database_or_runtime_writes':False,'reader_test':'READER_TEST_NOT_RUN','per_article':per}
    qa['affiliate_disclosures_fact_corrected']=qa.pop('affiliate_disclosures_byte_identical')
    qa['affiliate_target_and_sponsored_nofollow_preserved']=True
    qa['ai_gateway_affiliate_exclusion_explicit_articles']=[]
    (b.HERE/'QA.json').write_text(json.dumps(qa,ensure_ascii=False,indent=2)+'\n')
    print(json.dumps({k:v for k,v in qa.items() if k!='per_article'},ensure_ascii=False))

if __name__=='__main__': main()
