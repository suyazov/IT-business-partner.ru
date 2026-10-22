#!/usr/bin/env python3
"""Check the two isolated WP installs. No writes, external requests or credentials."""
import json
from pathlib import Path
import sys
import urllib.error
import urllib.parse
import urllib.request
import xml.etree.ElementTree as ET
from html.parser import HTMLParser


class Page(HTMLParser):
    def __init__(self):
        super().__init__()
        self.h1 = 0
        self.links = []
        self.assets = []
        self.canonicals = []
        self.descriptions = []

    def handle_starttag(self, tag, attrs):
        a = dict(attrs)
        if tag == 'h1': self.h1 += 1
        if tag == 'a' and a.get('href'): self.links.append(a['href'])
        if tag in ('img', 'script') and a.get('src'): self.assets.append(a['src'])
        if tag == 'link' and a.get('rel') == 'stylesheet': self.assets.append(a['href'])
        if tag == 'link' and a.get('rel') == 'canonical': self.canonicals.append(a['href'])
        if tag == 'meta' and a.get('name') == 'description': self.descriptions.append(a.get('content'))


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl): return None


def main():
    if len(sys.argv) != 3: raise SystemExit('Usage: smoke.py MAIN_URL PERSONAL_URL')
    primary, personal = (x.rstrip('/') for x in sys.argv[1:])
    allowed = {urllib.parse.urlsplit(primary).netloc, urllib.parse.urlsplit(personal).netloc}
    if any(urllib.parse.urlsplit(x).hostname not in ('127.0.0.1', 'localhost') for x in (primary, personal)):
        raise SystemExit('This smoke is limited to explicitly local previews.')
    seen = set()
    assets = set()
    expected_cases = {item['slug'] for item in json.loads((Path(__file__).resolve().parents[1] / 'content/cases.json').read_text())}

    def request(url, expected=200, redirect=False):
        opener = urllib.request.build_opener() if redirect else urllib.request.build_opener(NoRedirect())
        try:
            with opener.open(url, timeout=15) as r: code, headers, body = r.status, r.headers, r.read()
        except urllib.error.HTTPError as e: code, headers, body = e.code, e.headers, e.read()
        assert code == expected, (url, code, expected)
        return headers, body

    queue = [(primary + '/', False), (personal + '/', False)]
    extra = ['/services/', '/cases/', '/journal/', '/?s=CRM', '/journal/category/crm/']
    queue += [(primary + p, False) for p in extra]
    queue += [(personal + '/portfolio/', False), (personal + '/blog/', False)]
    while queue:
        url, _ = queue.pop(0)
        if url in seen: continue
        assert len(seen) < 120, 'Unexpected crawl expansion'
        headers, body = request(url, redirect=True)
        if 'text/html' not in headers.get('Content-Type', ''): continue
        page = Page(); page.feed(body.decode())
        assert page.h1 == 1, ('Expected one H1', url, page.h1)
        assert len(page.descriptions) == 1, ('Expected one description', url)
        assert 'noindex' in headers.get('X-Robots-Tag', ''), ('Preview indexing', url)
        if '?s=' not in url: assert len(page.canonicals) == 1, ('Canonical missing or duplicated', url, page.canonicals)
        assert b'Fatal error' not in body and b'Warning:' not in body, ('PHP error', url)
        seen.add(url)
        assets.update(page.assets)
        for href in page.links:
            target = urllib.parse.urljoin(url, href).split('#')[0]
            parsed = urllib.parse.urlsplit(target)
            if parsed.netloc in allowed and parsed.scheme in ('http', 'https') and target not in seen:
                if not parsed.query and '/wp-' not in parsed.path: queue.append((target, False))

    for asset in assets:
        if urllib.parse.urlsplit(asset).netloc in allowed: request(asset)
    redirects = {
        primary + '/index.html': primary + '/',
        primary + '/services.html': primary + '/services/',
        primary + '/bitrix24.html': primary + '/services/bitrix24/',
        primary + '/portfolio.html': primary + '/cases/',
        primary + '/about.html': primary + '/about/',
        primary + '/contact.html': primary + '/contacts/',
        personal + '/portfolio/matklass': primary + '/cases/matklass/',
        personal + '/portfolio/antispam-24-sites': primary + '/cases/antispam-24-sites/',
    }
    for slug in ('postavki26', 'glosscheck', 'petlifeexpo', 'ocifrovkavideokasset', 'avg-realestate'):
        redirects[personal + '/portfolio/' + slug] = primary + '/cases/' + slug + '/'
    for old, new in {'ii-dlya-biznesa-gde-nachat': 'pervyy-ai-pilot', 'ai-kontent-marketing-kak-avtomatizirovat': 'ai-kontent-redaktor', 'vnedrenie-crm-bitrix24-lms-platforma': 'crm-lms-integratsiya'}.items():
        for suffix in ('', '/'):
            redirects[personal + '/blog/' + old + suffix] = primary + '/journal/' + new + '/'
    for suffix in ('', '/'):
        redirects[personal + '/blog' + suffix] = primary + '/journal/'
    for source, target in redirects.items():
        headers, _ = request(source, 301)
        assert headers['Location'] == target, (source, headers['Location'], target)
    for base in (primary, personal):
        request(base + '/does-not-exist-sy3-check/', 404)
        request(base + '/wp-config.php', 404)
        _, xml = request(base + '/wp-sitemap.xml')
        root = ET.fromstring(xml)
        assert 'sitemapindex' in root.tag
        for loc in root.iter('{http://www.sitemaps.org/schemas/sitemap/0.9}loc'):
            _, child = request(loc.text)
            assert ET.fromstring(child).tag.endswith('urlset')
        _, feed = request(base + '/sales/portfolio-feed.json')
        items = json.loads(feed)['cases']
        assert {item['slug'] for item in items} == expected_cases
        assert len(items) == len(expected_cases)
        for item in items:
            assert item['url'] == primary + '/cases/' + item['slug'] + '/'
        _, llms = request(base + '/llms.txt')
        assert '/cases/' in llms.decode()
    request(personal + '/portfolio/vip26/', 404)
    print(json.dumps({'status': 'passed', 'html_pages': len(seen), 'local_assets': len(assets), 'exact_redirects': len(redirects), 'primary_cases': len(expected_cases), 'personal_case_links': len(expected_cases)}, ensure_ascii=False))


if __name__ == '__main__': main()
