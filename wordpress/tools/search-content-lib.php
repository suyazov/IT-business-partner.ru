<?php
/** Native blocks and validation for the separately owned search-content batch. */
require_once __DIR__ . '/../content/blocks.php';
const SY3_SEARCH_BATCH = 'sy3-search-20261022';
const SY3_BEGET_PARTNER_URL = 'https://beget.com/p133852';

function sy3_search_inline(string $text): string {
    $parts = preg_split('/(`[^`]+`|\[[^\]]+\]\([^\s)]+\)|\*\*[^*]+\*\*)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
    return implode('', array_map(function ($part) {
        if (preg_match('/^`([^`]+)`$/us', $part, $m)) return '<code>' . esc_html($m[1]) . '</code>';
        if (preg_match('/^\*\*([^*]+)\*\*$/us', $part, $m)) return '<strong>' . esc_html($m[1]) . '</strong>';
        if (preg_match('/^\[([^\]]+)\]\(([^\s)]+)\)$/us', $part, $m)) return '<a href="' . esc_url($m[2], ['https', 'http']) . '"'.($m[2]===SY3_BEGET_PARTNER_URL?' rel="sponsored nofollow"':'').'>' . esc_html($m[1]) . '</a>';
        return esc_html($part);
    }, $parts));
}

function sy3_search_blocks(string $markdown): string {
    $html = ''; $buffer = []; $items = []; $ordered = false; $code = null;
    $flush = function () use (&$html, &$buffer, &$items, &$ordered) {
        if ($buffer) { $html .= sy3_p(sy3_search_inline(implode(' ', $buffer))); $buffer = []; }
        if ($items) { $html .= sy3_list(array_map('sy3_search_inline', $items), $ordered); $items = []; }
    };
    $lines=explode("\n", trim($markdown));
    for ($index=0;$index<count($lines);$index++) {
        $line=$lines[$index];
        if (preg_match('/^```[a-zA-Z0-9_-]*\s*$/', $line)) {
            if ($code === null) { $flush(); $code = []; }
            else { $html .= sy3_b('code', '<pre class="wp-block-code"><code>' . esc_html(implode("\n", $code)) . '</code></pre>'); $code = null; }
            continue;
        }
        if ($code !== null) { $code[] = $line; continue; }
        if (str_starts_with(trim($line),'|') && isset($lines[$index+1]) && preg_match('/^\s*\|(?:\s*:?-+:?\s*\|)+\s*$/',$lines[$index+1])) {
            $flush();$cells=fn($row)=>array_map('trim',explode('|',trim(trim($row),'|')));
            $headers=$cells($line);$index+=2;$rows=[];
            while($index<count($lines)&&str_starts_with(trim($lines[$index]),'|')){
                $row=$cells($lines[$index]);if(count($row)!==count($headers))WP_CLI::error('Table column mismatch.');$rows[]=$row;$index++;
            }
            $index--; $table='<figure class="wp-block-table"><table class="has-fixed-layout"><thead><tr>';
            foreach($headers as $cell)$table.='<th>'.sy3_search_inline($cell).'</th>';
            $table.='</tr></thead><tbody>';
            foreach($rows as $row){$table.='<tr>';foreach($row as $cell)$table.='<td>'.sy3_search_inline($cell).'</td>';$table.='</tr>';}
            $html.=sy3_b('table',$table.'</tbody></table></figure>');continue;
        }
        if (trim($line) === '') { $flush(); continue; }
        if (preg_match('/^(#{2,3}) (.+)$/u', $line, $m)) { $flush(); $html .= sy3_h(sy3_search_inline($m[2]), strlen($m[1])); continue; }
        if (preg_match('/^(?:- |\d+\. )(.+)$/u', $line, $m)) {
            $is_ordered = !str_starts_with($line, '- ');
            if ($buffer || ($items && $ordered !== $is_ordered)) $flush();
            $ordered = $is_ordered; $items[] = $m[1]; continue;
        }
        if ($items) $flush();
        $buffer[] = trim($line);
    }
    if ($code !== null) WP_CLI::error('Unclosed code fence.');
    $flush(); return $html;
}

function sy3_visible_chars(string $markdown): int {
    $text=preg_replace('/\[([^\]]+)\]\([^\s)]+\)/u','$1',$markdown);
    $text=preg_replace('/^```[^\n]*$|^\s*\|(?:\s*:?-+:?\s*\|)+\s*$/mu','',$text);
    $text=preg_replace('/^#{1,6}\s+/mu','',$text);
    $text=str_replace(['**','`','|'],'',$text);
    return mb_strlen(trim(preg_replace('/\s+/u',' ',$text)));
}

function sy3_process_artifact(array $diagram,string $key): string {
    if(!preg_match('/\A[a-z0-9-]+\z/',$key))WP_CLI::error('Unsafe artifact key.');
    $nodes='';
    foreach($diagram['steps'] as $n=>$step)$nodes.=sy3_g(sy3_p(sprintf('%02d',$n+1),'process-map-number').sy3_h(esc_html($step['label']),3).sy3_p(esc_html($step['detail'])),'process-map-node');
    $decision=$diagram['decision'];$control=$diagram['control'];
    $body=sy3_p('Рабочий сценарий','eyebrow').sy3_h(esc_html($diagram['title']),2).sy3_p(esc_html($diagram['subtitle']),'process-map-intro').sy3_g($nodes,'process-map-steps');
    $body.=sy3_g(sy3_h(esc_html($decision['question']),3).sy3_p('<strong>Условие выполнено:</strong> '.esc_html($decision['yes'])).sy3_p('<strong>Условие не выполнено:</strong> '.esc_html($decision['no'])),'process-map-decision');
    $body.=sy3_g(sy3_p('<strong>Ответственный</strong><br>'.esc_html($control['owner'])).sy3_p('<strong>Подтверждение</strong><br>'.esc_html($control['evidence'])).sy3_p('<strong>Что измеряем</strong><br>'.esc_html($control['metric'])),'process-map-control');
    $body.=sy3_p('<a href="'.esc_url(get_theme_file_uri('assets/infographics/'.$key.'.svg')).'" download>Скачать схему процесса в SVG</a> · <a href="'.esc_url(get_theme_file_uri('assets/infographics/'.$key.'.csv')).'" download>Скачать рабочую таблицу CSV</a>','process-map-download');
    return sy3_g($body,'process-map');
}

function sy3_search_load(string $collection='search'): array {
    if(!in_array($collection,['search','beget'],true))WP_CLI::error('Unknown article collection.');
    $beget=$collection==='beget';$expected=$beget?range(202,301):range(101,201);
    $base = __DIR__ . ($beget?'/../beget-content/':'/../search-content/');
    $service_base=__DIR__.'/../search-content/';
    $records = json_decode(file_get_contents($base . 'manifest.json'), true, 512, JSON_THROW_ON_ERROR);
    $services = array_merge(json_decode(file_get_contents($service_base . 'services.json'), true, 512, JSON_THROW_ON_ERROR), json_decode(file_get_contents($service_base . 'partner-services.json'), true, 512, JSON_THROW_ON_ERROR));
    $media = json_decode(file_get_contents(__DIR__ . '/../editorial/media.json'), true, 512, JSON_THROW_ON_ERROR);
    $known_services = array_merge(array_column($services, 'slug'), array_column(json_decode(file_get_contents(__DIR__ . '/../content/services.json'), true), 'slug'));
    $known_cases = array_column(json_decode(file_get_contents(__DIR__ . '/../content/cases.json'), true), 'slug');
    if (count($services) !== 12 || count(array_unique(array_column($services, 'slug'))) !== 12) WP_CLI::error('Twelve distinct landing pages required.');
    foreach ($services as $service) {
        if (!preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', $service['slug']) || count($service['sections']) < 4 || count($service['faqs']) < 2) WP_CLI::error('Incomplete landing page.');
        foreach ($service['case_slugs'] as $slug) if (!in_array($slug, $known_cases, true)) WP_CLI::error('Unknown service case.');
    }
    usort($records, fn($a, $b) => $a['id'] <=> $b['id']);
    $partial=getenv('SY3_SEARCH_PARTIAL_IMPORT')==='1';$ids=array_column($records,'id');
    if (!$ids||count($ids)!==count(array_unique($ids))||array_diff($ids,$expected)||(!$partial&&$ids!==$expected)) WP_CLI::error('Complete numbered collection required, or explicit partial-import mode.');
    $planned = [];
    foreach (['slug', 'title', 'primary_query'] as $field) if (count(array_unique(array_column($records, $field))) !== count($records)) WP_CLI::error('Duplicate search-content ' . $field);
    $bodies = [];
    foreach ($records as &$record) {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $record['planned_at'] ?? '', new DateTimeZone('Europe/Moscow'));
        if (!$date || $date->format('Y-m-d H:i:s') !== ($record['planned_at'] ?? '')) WP_CLI::error('Invalid planned date.');
        if ($record['id']===101 && $record['planned_at']!=='2027-08-21 10:00:00') WP_CLI::error('Expertise continuation date mismatch.');
        $standalone=!empty($record['standalone']);
        if($standalone&&(!$beget||$record['id']!==202))WP_CLI::error('Only approved Beget introduction may be standalone.');
        if ($record['id']!==101&&!$standalone) $planned[]=$date->getTimestamp();
        if (!preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', $record['slug'])) WP_CLI::error('Unsafe slug.');
        $path = $base . 'articles/' . $record['id'] . '-' . $record['slug'] . '.md';
        $raw = file_get_contents($path);
        if (!str_starts_with($raw, '# ' . $record['title'] . "\n")) WP_CLI::error('Title/file mismatch: ' . $record['slug']);
        $body = trim(substr($raw, strpos($raw, "\n") + 1));
        preg_match_all('/[\p{L}\p{N}]+/u', $body, $words);
        if (sy3_visible_chars($body) < 10000 || substr_count($body, '## ') < 3 || !preg_match('/^\s*\|(?:\s*:?-+:?\s*\|)+\s*$/m',$body)) WP_CLI::error('Article requires 10000 visible characters and a practical table: ' . $record['slug']);
        if(empty($record['infographic'])||count($record['infographic']['steps']??[])<5||empty($record['infographic']['decision']['question'])||empty($record['infographic']['control']['evidence']))WP_CLI::error('Missing process artifact: '.$record['slug']);
        if (preg_match('/(?<!\p{L})сказк\p{L}*|skazka|parkops|AlekseiGunko|sy3-wordpress|BEGIN[^\n]*PRIVATE KEY|gh[pousr]_[a-zA-Z0-9]{20}/iu', $raw)) WP_CLI::error('Restricted content: ' . $record['slug']);
        if (!in_array($record['service'], $known_services, true) || (!empty($record['case']) && !in_array($record['case'], $known_cases, true))) WP_CLI::error('Invalid relation: ' . $record['slug']);
        if (count($record['related_queries']) < 3 || !$record['sources'] || mb_strlen($record['excerpt']) < 100 || mb_strlen($record['excerpt']) > 230) WP_CLI::error('Incomplete search brief: ' . $record['slug']);
        foreach ($record['sources'] as $source) if (!filter_var($source['url'], FILTER_VALIDATE_URL) || wp_parse_url($source['url'], PHP_URL_SCHEME) !== 'https') WP_CLI::error('Invalid source URL.');
        if($beget){
            if(!array_filter($record['sources'],fn($s)=>wp_parse_url($s['url'],PHP_URL_HOST)==='beget.com'))WP_CLI::error('Beget primary documentation required.');
            if(substr_count($body,SY3_BEGET_PARTNER_URL)!==1||!str_contains(mb_strtolower($body),'партнёрск'))WP_CLI::error('One disclosed partner link required.');
        }
        if (!isset($media[$record['cover']])) WP_CLI::error('Unknown cover.');
        $cover = $media[$record['cover']]; $cover_path = __DIR__ . '/../content/media/' . basename($cover['file']);
        if (!hash_equals($cover['sha256'], hash_file('sha256', $cover_path))) WP_CLI::error('Cover hash mismatch.');
        foreach(['.svg','.csv'] as $suffix){$artifact=__DIR__.'/../theme/assets/infographics/article-'.$record['id'].$suffix;if(!is_file($artifact)||!hash_equals($record['artifacts'][$suffix]??'',hash_file('sha256',$artifact)))WP_CLI::error('Artifact missing or changed: '.$record['slug']);}
        $record['body'] = $body; $blocks=parse_blocks(sy3_search_blocks($body));
        array_splice($blocks,max(1,(int)floor(count($blocks)/3)),0,parse_blocks(sy3_process_artifact($record['infographic'],'article-'.$record['id'])));
        $record['blocks'] = serialize_blocks($blocks); $bodies[] = hash('sha256', $body);
    }
    unset($record);
    sort($planned);
    $start=(new DateTimeImmutable($beget?'2027-01-31 10:00:00':'2026-10-23 10:00:00',new DateTimeZone('Europe/Moscow')))->getTimestamp();
    $allowed_dates=array_map(fn($n)=>$start+$n*DAY_IN_SECONDS,range(0,$beget?98:99));
    if(count($planned)!==count(array_unique($planned))||array_diff($planned,$allowed_dates)||(!$partial&&$planned!==$allowed_dates)) WP_CLI::error('Expected distinct daily slots within the collection calendar.');
    if (count(array_unique($bodies)) !== count($records)) WP_CLI::error('Duplicate article body.');
    return [$records, $services, $media];
}
