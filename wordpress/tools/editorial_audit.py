#!/usr/bin/env python3
"""Offline SY3 inventory: report candidates; never edit copy, WordPress or review gates.

Usage: editorial_audit.py /private/runtime-baseline.json /output/directory [--pilot-root wordpress/docs/editorial/pilot]
Only this captured sy3.ru baseline and repository files are read. No network/DB/API.
Examples are short public phrases; matching private markers are always redacted.
"""
from __future__ import annotations

import argparse
import csv
import hashlib
import html
from html.parser import HTMLParser
import itertools
import json
from pathlib import Path
import re
from collections import Counter, defaultdict
from urllib.parse import urlparse

WP = Path(__file__).resolve().parents[1]
CODES = ("AI_CLICHE", "BUREAUCRACY", "JARGON", "FACT_UNVERIFIED", "SEO_FILLER",
         "DUPLICATE_INTENT", "CTA_UNCLEAR", "PRIVACY_CRITICAL")
# Deliberately narrow signals, not a list of forbidden words. CRM/данные/согласовать
# alone are not signals. Each match remains a candidate requiring contextual review.
RULES = {
    "AI_CLICHE": r"не просто\s+[^.!?\n]{1,80},?\s+а\s+[^.!?\n]{1,60}|индивидуальные решения под ваши бизнес-потребности|прозрачност\w*,?\s+управляемост\w*",
    "BUREAUCRACY": r"в рамках согласованн\w*(?:\s+\w+){0,3}|согласованным защищ[её]нным способом|важно зафиксировать|по итогам проверки|формируем контур",
    "JARGON": r"\b(?:идемпотентност\w*|тенант\w*|декомпозици\w*|инвариант\w*|артефакт\w*|observability|retry-after|SLA|SIPOC|RACI|AS-IS|TO-BE)\b",
    "FACT_UNVERIFIED": r"(?:увеличил\w*|повысил\w*)\s+(?:\w+\s+){0,3}продаж\w*|(?:снизил\w*|сократил\w*)\s+(?:\w+\s+){0,3}(?:расход\w*|затрат\w*|врем\w*)|гарантиру(?:ем|ю|ет)\s+[^.!?\n]{1,75}",
    "SEO_FILLER": r"в современном мире|на сегодняшний день|неотъемлемой частью|вс[её] зависит от задачи|следует отметить|важно отметить",
    "DUPLICATE_INTENT": r"(?!)",  # exact query collisions handled across objects below
    "CTA_UNCLEAR": r"^Подробнее$|^Нажмите (?:сюда|здесь)$|^Узнать больше$",
    "PRIVACY_CRITICAL": r"/root/|/var/(?:www|lib)/|BEGIN[^\n]{0,30}PRIVATE KEY|gh[pousr]_[A-Za-z0-9]{20,}|(?<!\w)сказк\w*|skazka|parkops|AlekseiGunko",
}
PATTERNS = {code: re.compile(pattern, re.I) for code, pattern in RULES.items()}
FIELDS = ["object_id", "wp_id", "url", "slug", "content_type", "status", "post_date", "post_date_gmt",
          "post_modified", "post_modified_gmt", "body_hash", "surface", "surface_hash", "source", "source_mapping",
          "evidence_kind", "audience", "purpose", "issues", "severity", "current_phrase", "action", "evidence",
          "new_version", "review_state", "characters", "occurrences"]
# Five remaining fact-pattern candidates were read in context at this exact capture.
# These are narrow editorial exceptions, not approval of the article or its facts.
FACT_CONTEXT = {
    "143": ("4ee2870d34aa6b6d8ddcac32fbba4b0de171db078a107c0b07b262b520b437b3", "KEEP: explicitly says one must not assume a correct CRM number guarantees a correct printed form"),
    "169": ("6e8c3cfcc5a4f48c28ff50a1fbb69941ea1d2ffc1b32b52a1637b03d1b96acf1", "KEEP: explicitly denies guaranteed obedience of a video model"),
    "279": ("0ebf9c4bb61a1cbfe054acc3ece186188160a8c73fc6f3da412a160309f8392a", "KEEP: explicitly says there is no universally guaranteed snippet length"),
    "482": ("4ebd3df5c0df7c4d016ace53b729e1f51fe7c97a534675c74bc2b34078a5f5c0", "KEEP: quoted conditional example of an AI inventing a result; article calls it unsupported"),
    "519": ("0b2cb70ff29aab51642dfff725698d436549d026ffb054d6c4b1ed2a5a13a7c5", "KEEP: says not to promise inbox placement from one SPF record"),
}


def sha(text: str) -> str:
    return hashlib.sha256(text.encode()).hexdigest()


class Visible(HTMLParser):
    def __init__(self, value: str):
        super().__init__(convert_charrefs=True)
        self.text, self.alts, self.anchors, self.faq = [], [], [], []
        self.anchor = self.detail = None
        self.ignore = 0
        self.feed(value)

    def handle_starttag(self, tag, attrs):
        attrs = dict(attrs)
        if tag in ("script", "style"):
            self.ignore += 1
        if tag == "img":
            self.alts.append(attrs.get("alt", ""))
        if tag == "a":
            self.anchor = [attrs.get("href", ""), []]
        if tag == "details":
            self.detail = []
        if tag in ("p", "h1", "h2", "h3", "li", "td", "th", "summary", "br"):
            self.text.append(" ")

    def handle_endtag(self, tag):
        if tag in ("script", "style"):
            self.ignore = max(0, self.ignore - 1)
        if tag == "a" and self.anchor is not None:
            self.anchors.append((self.anchor[0], " ".join(self.anchor[1]).strip()))
            self.anchor = None
        if tag == "details" and self.detail is not None:
            self.faq.append(" ".join(self.detail))
            self.detail = None
        if tag in ("p", "h1", "h2", "h3", "li", "td", "th", "summary"):
            self.text.append(" ")

    def handle_data(self, data):
        if self.ignore:
            return
        self.text.append(data)
        if self.anchor is not None:
            self.anchor[1].append(data)
        if self.detail is not None:
            self.detail.append(data)

    def plain(self):
        return re.sub(r"\s+", " ", " ".join(self.text)).strip()


def load(relative):
    return json.loads((WP / relative).read_text())


def safe_file(relative):
    p = (WP / relative).resolve()
    if not p.is_relative_to(WP) or not p.is_file():
        raise ValueError("Missing/out-of-scope canonical source: " + relative)
    return "wordpress/" + p.relative_to(WP).as_posix()


def sources():
    maps = {}
    for name in ("content/services.json", "search-content/services.json", "search-content/partner-services.json"):
        for r in load(name):
            key = ("sy3_service", r["slug"])
            if key in maps:
                raise ValueError("Duplicate service source")
            maps[key] = ([safe_file(name) + "#slug=" + r["slug"]], "SEED_SLUG", r)
    for kind, name in (("sy3_case", "content/cases.json"), ("post", "content/articles.json")):
        for r in load(name):
            maps[(kind, r["slug"])] = ([safe_file(name) + "#slug=" + r["slug"]], "SEED_SLUG", r)
    for slug in ("home", "about", "contacts", "privacy", "journal"):
        paths = ["content/home.php"] if slug == "home" else ["content/pages.php"]
        if slug == "home":
            paths.append("content/home-faq.php")
        if slug == "about":
            paths.append("content/work-process.php")
        if slug == "privacy":
            paths.append("content/lead-privacy.php")
        if slug == "journal":
            # Journal page body is not the archive heading renderer.
            paths = ["tools/import-content.php", "theme/parts/journal-heading.php", "theme/inc/journal.php"]
        maps[("page", slug)] = ([safe_file(p) for p in paths], "SEED_SLUG", {})
    for r in load("editorial/calendar.json"):
        p = "editorial/articles/" + r["file"]
        raw = (WP / p).read_text()
        if not re.search(r"^@@ " + str(r["id"]) + r"\|" + re.escape(r["slug"]) + r"\|", raw, re.M):
            raise ValueError("Editorial calendar/chunk mismatch")
        maps[("post", r["slug"])] = ([safe_file(p) + "#id=" + str(r["id"]),
                                     safe_file("editorial/calendar.json")], "EDITORIAL_KEY", r)
    for name in ("search-content", "beget-content"):
        for r in load(name + "/manifest.json"):
            path = name + "/articles/" + str(r["id"]) + "-" + r["slug"] + ".md"
            if not (WP / path).read_text().startswith("# " + r["title"] + "\n"):
                raise ValueError("Manifest/manuscript title mismatch")
            maps[("post", r["slug"])] = ([safe_file(path), safe_file(name + "/manifest.json")], "EDITORIAL_KEY", r)
    for r in load("longform/manifest.json"):
        key = ("post", r["slug"])
        if key not in maps:
            raise ValueError("Orphan longform source")
        if r["file"] != r["key"] + ".md":
            raise ValueError("Longform filename identity mismatch")
        maps[key][0].extend([safe_file("longform/articles/" + r["file"]), safe_file("longform/manifest.json")])
    return maps


def pilot_overlay(root: Path | None, captured: list[dict]) -> dict[str, dict]:
    """Bind optional prepared copy to the captured body; never approve or apply it."""
    if root is None:
        return {}
    expected = WP / "docs/editorial/pilot"
    if root.resolve(strict=True) != expected or expected.is_symlink():
        raise ValueError("Pilot root must be the exact repository editorial/pilot directory")

    def exact(relative):
        path = expected / relative
        if path.is_symlink() or not path.is_file() or path.resolve(strict=True) != path:
            raise ValueError("Unsafe/missing exact pilot path")
        return path

    by_id = {str(r["ID"]): r for r in captured}
    prepared = {}

    def identity(candidate, kind):
        key = str(candidate["id"])
        row = by_id.get(key)
        if (not row or key in prepared or row["post_type"] != kind
                or row["post_name"] != candidate["slug"]
                or row["body_hash"] != candidate["baseline_hash"]
                or sha(row["post_content"]) != candidate["baseline_hash"]):
            raise ValueError("Pilot baseline identity/hash mismatch: " + key)
        return key, row

    commercial = json.loads(exact("commercial.json").read_text())
    if commercial.get("production_authority") is not False:
        raise ValueError("Pilot manifest must not declare production authority")
    expected_commercial = {
        "4": "wordpress/content/home.php", "5": "wordpress/content/work-process.php",
        "6": "wordpress/content/contact-copy.php", "9": "wordpress/content/services.json",
        "10": "wordpress/content/services.json", "11": "wordpress/content/services.json",
        "21": "wordpress/content/cases.json",
    }
    if {str(c["id"]) for c in commercial["candidates"]} != set(expected_commercial) or len(commercial["candidates"]) != 7:
        raise ValueError("Expected the seven scoped commercial pilot candidates")
    for index, c in enumerate(commercial["candidates"]):
        key, row = identity(c, c["type"])
        if (c["source"] != expected_commercial[key]
                or safe_file(c["source"].removeprefix("wordpress/")) != c["source"]
                or c["url"] != row["url"] or c["status"] != row["post_status"] or c["date"] != row["post_date"]
                or c.get("review_state") != "AWAITING_OWNER_EDITORIAL_REVIEW"
                or not isinstance(c.get("content"), str) or sha(c["content"]) != c["candidate_hash"]):
            raise ValueError("Commercial candidate metadata/content hash mismatch: " + key)
        prepared[key] = {"path": "wordpress/docs/editorial/pilot/commercial.json#/candidates/" + str(index) + "/content",
                         "state": "AWAITING_OWNER_EDITORIAL_REVIEW", "hash": c["candidate_hash"]}
    articles = json.loads(exact("articles/manifest.json").read_text())
    if {str(c["id"]) for c in articles["candidates"]} != {"60", "262", "281"} or len(articles["candidates"]) != 3:
        raise ValueError("Expected the three scoped article pilot candidates")
    for c in articles["candidates"]:
        key, row = identity(c, "post")
        if not re.fullmatch(r"[a-z0-9]+(?:-[a-z0-9]+)*", c["slug"]) or c["file"] != c["slug"] + ".html":
            raise ValueError("Unsafe article candidate filename")
        path = exact("articles/" + c["file"])
        if (sha(path.read_text()) != c["candidate_hash"] or c.get("policy_state") != "POLICY_CONFLICT"
                or c.get("review_state") != "NEEDS_EDITORIAL_REVIEW"
                or not isinstance(c.get("visible_chars"), int) or not 0 < c["visible_chars"] < 10000):
            raise ValueError("Article candidate hash/policy mismatch: " + key)
        prepared[key] = {"path": "wordpress/" + path.relative_to(WP).as_posix(),
                         "state": "POLICY_CONFLICT_NEEDS_EDITORIAL_DECISION", "hash": c["candidate_hash"]}
    return prepared


def detect(text, *, anchor=False):
    matches = {}
    for code, pattern in PATTERNS.items():
        if code == "CTA_UNCLEAR" and not anchor:
            continue
        found = list(pattern.finditer(text))
        if code == "FACT_UNVERIFIED":
            # A statement explicitly denying a guarantee is not a guarantee claim.
            # This small exclusion is not semantic fact-checking of the remaining text.
            found = [m for m in found if not re.search(
                r"\bне(?:\s+(?:всегда|обязательно|может|можем|должен|должна|должно|будет|будем))*\s*$",
                text[max(0, m.start() - 65):m.start()], re.I)]
        if found:
            matches[code] = found
    return matches


def audience(kind, slug):
    if slug in ("privacy", "privacy-policy"):
        return "Посетитель сайта; юридический/предметный reviewer", "Понять обработку данных и способы обращения"
    if kind == "post":
        return "Владелец/руководитель; предметный специалист (уточнить по статье)", "Решить вопрос из заголовка; проверить шаги и пределы применимости"
    if kind == "sy3_case":
        return "Покупатель соответствующей услуги", "Проверить задачу, личную роль, артефакт и границу результата"
    if kind == "sy3_service":
        return "Покупатель соответствующей услуги", "Узнать свою ситуацию, состав первого этапа и способ обращения"
    return "Потенциальный заказчик", "Понять предложение, исполнителя и следующий шаг"


def priority(r):
    if r["post_type"] == "page":
        return "P2" if r["post_status"] == "draft" else ("P1" if r["post_name"] == "journal" else "P0")
    if (r["post_type"] == "sy3_service" and r["post_name"] in ("integrations", "bitrix24", "ai-automation")) or (r["post_type"] == "sy3_case" and r["post_name"] in ("bitrix24-doors", "matklass", "libertygas")):
        return "P0"
    if r["post_type"] == "post" and r["post_status"] == "publish":
        return "P1"
    return "P2"


def make_row(r, surface, text, source, mapping, evidence, matches=None, anchor=False):
    matches = detect(text, anchor=anchor) if matches is None else matches
    counts = {c: len(v) for c, v in matches.items()}
    examples = []
    for code, values in matches.items():
        for match in values[:2]:
            example = "[REDACTED_PRIVATE_MARKER]" if code == "PRIVACY_CRITICAL" else match.group()[:150]
            if example not in examples:
                examples.append(example)
    who, why = audience(r["post_type"], r["post_name"])
    private = "PRIVACY_CRITICAL" in counts
    # KEEP here is a provisional no-lint-finding, never semantic acceptance.
    action = "NEEDS_FACTS" if (private or "FACT_UNVERIFIED" in counts) else ("REWRITE" if counts else "KEEP")
    if counts and set(counts) == {"JARGON"}:
        action = "MOVE_TO_TECH"
    return dict(zip(FIELDS, [
        "wp:" + str(r["ID"]), r["ID"], r["url"], r["post_name"], r["post_type"], r["post_status"],
        r["post_date"], r["post_date_gmt"], r["post_modified"], r["post_modified_gmt"], r["body_hash"],
        surface, sha(text), " | ".join(source), mapping, "CAPTURED_RUNTIME_NOT_FACTCHECKED", who, why,
        "|".join(counts), "P0" if private else priority(r), " | ".join(examples), action, evidence,
        "", "FLAGGED_CANDIDATE" if counts else "INVENTORIED_NOT_SEMANTICALLY_REVIEWED", len(text),
        json.dumps(counts, ensure_ascii=False, sort_keys=True)
    ]))


# Explicit public-copy surfaces; no scanning personal-site branches as SY3 copy.
# Shared templates are inventoried, never assumed safe to edit without site-mode guard.
UI = [
    ("navigation", "theme/header.php", "Навигация и первое обращение", "Меню · Обсудить задачу · Перейти к содержимому", "P0", "SHARED_TEMPLATE_GUARD_REQUIRED"),
    ("brand_tagline", "theme/header.php", "Кратко объяснить специализацию", "системный подход к технологиям", "P0", "MAIN_BRANCH_IN_SHARED_TEMPLATE"),
    ("footer", "theme/footer.php", "Обращение через работающие каналы", "Начнём с вашей задачи. Расскажите, что хотите запустить или улучшить. Написать в Telegram · Написать в MAX", "P0", "MAIN_BRANCH_ONLY"),
    ("footer_legal", "theme/footer.php", "Реквизиты контрагента", "Реквизиты для договора и оплаты", "P0", "KEEP_LEGAL_MEANING_SUBJECT_REVIEW"),
    ("error_404", "theme/404.php", "Объяснить отсутствие страницы и дать выход", "Здесь пока ничего нет. Возможно, адрес изменился. Нужное направление можно найти на главной.", "P0", "SHARED_TEMPLATE_GUARD_REQUIRED"),
    ("empty_search", "theme/index.php", "Помочь изменить неудачный поиск", "Материалов по этому запросу пока нет. Попробуйте другое слово", "P1", "SHARED_TEMPLATE_GUARD_REQUIRED"),
    ("pagination", "theme/index.php", "Следующая страница подборки", "Назад · Дальше", "P2", "SHARED_TEMPLATE_GUARD_REQUIRED"),
    ("archive_cases", "theme/index.php", "Объяснить состав портфолио", "Работа в деталях · Исходная задача, принятые решения и границы подтверждённого результата.", "P0", "MAIN_BRANCH_IN_SHARED_TEMPLATE"),
    ("archive_services", "theme/archive-sy3_service.php", "Помочь выбрать услугу", "Создать. Настроить. Поддерживать и развивать. · Начните с задачи.", "P0", "SHARED_TEMPLATE_GUARD_REQUIRED"),
    ("journal_search", "theme/parts/journal-heading.php", "Найти статью по вопросу и теме", "Поиск по статьям · Что хотите найти? · Найти · Сбросить поиск", "P1", "JOURNAL_MAIN_CONTEXT"),
    ("journal_intro", "theme/parts/journal-heading.php", "Объяснить пользу журнала", "Автоматизация бизнеса · Разборы проектов, рабочие процессы и практические инструкции.", "P1", "JOURNAL_MAIN_CONTEXT"),
    ("card_cta", "theme/functions.php", "Открыть материал с понятным контекстом", "Подробнее", "P1", "SHARED_TEMPLATE_GUARD_REQUIRED"),
    ("document_title", "theme/functions.php", "Сохранить интент поиска", "Создание сайтов, веб-сервисов и автоматизация бизнеса — Сутри", "P0", "MAIN_TITLE_GUARD_PRESENT"),
    ("journal_title", "theme/functions.php", "Назвать тему поискового результата", "Автоматизация бизнеса, CRM, сайты и ИИ — журнал Сутри", "P1", "MAIN_TITLE_GUARD_PRESENT"),
    ("description_og_schema", "plugin/sy3-content.php", "Передать description в SEO/OpenGraph/Article/Service", "_sy3_description / excerpt / blog description fallback", "P1", "DERIVED_RULE_NOT_RENDERED_CAPTURE"),
    ("article_faq", "theme/inc/article-support.php", "Коротко ответить по статье", "Вопросы по теме · Короткие ответы · С чего начать? · Как проверить результат?", "P1", "MAIN_ONLY_FILTER_PRESENT"),
    ("article_cta_fallback", "theme/inc/article-support.php", "Перейти от чтения к конкретному обращению", "Хотите применить это в своей компании? · Перейдём от разбора к первому шагу · Согласуем границы первого этапа и способ проверить, что задача решена.", "P1", "MAIN_ONLY_FILTER_PRESENT"),
    ("article_cta_privacy", "theme/inc/article-support.php", "Не получить секреты в первом сообщении", "Сообщением, без звонка. Пароли и доступы присылать не нужно.", "P0", "KEEP_PRIVACY_BOUNDARY"),
    ("article_curated_faq_cta", "theme/inc/article-support-copy.json", "Редакционные FAQ и CTA для 11 опубликованных статей", "", "P1", "HASH_BOUND_RENDERING_NOT_STORED_IN_BODY"),
    ("visual_pause", "theme/functions.php", "Остановить декоративную анимацию", "Остановить анимацию · Пауза", "P2", "SHARED_TEMPLATE_GUARD_REQUIRED"),
    ("glossary", "tools/search-content-lib.php", "Объяснить необходимый термин в контексте", "", "P2", "NO_SEPARATE_GLOSSARY_FOUND_CONTEXTUAL_REVIEW_PENDING"),
    ("form_labels", "plugin/sy3-leads.php", "Описать задачу и оставить контакт", "Как к вам обращаться · Телефон или email · Что нужно сделать · Отправить заявку", "P0", "DORMANT_SOURCE_DO_NOT_ACTIVATE"),
    ("form_consent", "plugin/sy3-leads.php", "Сохранить смысл согласия на передачу сообщения", "Согласен на обработку данных заявки и её передачу Артёму через Telegram и MAX для ответа на обращение.", "P0", "DORMANT_SOURCE_LEGAL_MEANING_REVIEW_REQUIRED"),
    ("form_errors", "plugin/sy3-leads.php", "Объяснить ошибку и доступную альтернативу", "Форма временно недоступна. Напишите Артёму в Telegram или позвоните.", "P0", "DORMANT_SOURCE_CONFLICTS_WITH_NO_CALL_PREFERENCE"),
    ("form_loading_success", "plugin/sy3-leads-assets/leads.js", "Различить сохранение и доставку заявки", "Сохраняем заявку… · Заявка сохранена", "P0", "DORMANT_SOURCE_DO_NOT_CLAIM_DELIVERED"),
    ("navigation_menu_items", "tools/import-content.php", "Выбрать раздел сайта", "", "P0", "CAPTURED_RUNTIME_MENUS_SEED_REFERENCE_ONLY"),
]


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("baseline", type=Path)
    parser.add_argument("output", type=Path)
    parser.add_argument("--pilot-root", type=Path, help="Optional exact repository pilot directory; overlay body review pointers only")
    args = parser.parse_args()
    baseline = json.loads(args.baseline.read_text())
    if baseline.get("site") != "https://sy3.ru/":
        raise ValueError("Only captured sy3.ru baseline is in scope; personal site rejected")
    captured = baseline.get("rows")
    if not isinstance(captured, list) or len({str(r["ID"]) for r in captured}) != len(captured):
        raise ValueError("Invalid/duplicate runtime identities")
    source_map = sources()
    prepared = pilot_overlay(args.pilot_root, captured)
    exact_queries = defaultdict(list)
    for r in captured:
        m = source_map.get((r["post_type"], r["post_name"]))
        if m and m[2].get("primary_query"):
            exact_queries[re.sub(r"\s+", " ", m[2]["primary_query"].strip().casefold())].append(str(r["ID"]))
    query_collisions = [ids for ids in exact_queries.values() if len(ids) > 1]
    collided = {i for ids in query_collisions for i in ids}
    inventory, primary = [], []
    summaries = {c: {"objects": 0, "occurrences": 0} for c in CODES}
    mapped = Counter()
    titles = []
    for r in captured:
        if urlparse(r["url"]).hostname != "sy3.ru" or r["post_type"] not in ("page", "post", "sy3_case", "sy3_service"):
            raise ValueError("Out-of-scope object")
        if sha(r["post_content"]) != r["body_hash"]:
            raise ValueError("Captured body hash mismatch: " + str(r["ID"]))
        entry = source_map.get((r["post_type"], r["post_name"]))
        src, mapping, manifest = entry if entry else ([], "RUNTIME_ONLY_DEFAULT_WORDPRESS", {})
        if mapping == "SEED_SLUG" and r["meta"].get("_sy3_seed") != {"page":"page", "post":"post", "sy3_case":"case", "sy3_service":"service"}[r["post_type"]] + ":" + r["post_name"]:
            raise ValueError("Seed mismatch")
        if mapping == "EDITORIAL_KEY" and int(r["meta"].get("_sy3_editorial_key") or -1) != int(manifest["id"]):
            raise ValueError("Editorial key mismatch")
        mapped[mapping] += 1
        visible = Visible(r["post_content"])
        text = visible.plain()
        evidence = "Captured body SHA256 verified; source identity mapped, not reconstructed equality; primary evidence/rights not revalidated"
        row = make_row(r, "body", text, src, mapping, evidence)
        exception = FACT_CONTEXT.get(str(r["ID"]))
        if exception and exception[0] == r["body_hash"] and "FACT_UNVERIFIED" in row["issues"]:
            row["evidence"] += "; FACT_RULE_CONTEXT_CHECKED: " + exception[1]
            row["review_state"] = "FACT_RULE_CONTEXT_CHECKED_OTHER_CONTENT_PENDING"
            remaining = set(row["issues"].split("|")) - {"FACT_UNVERIFIED"}
            row["action"] = "MOVE_TO_TECH" if remaining == {"JARGON"} else ("REWRITE" if remaining else "KEEP")
        if str(r["ID"]) in prepared:
            candidate = prepared[str(r["ID"])]
            row["new_version"] = candidate["path"]
            row["review_state"] = candidate["state"]
            row["action"] = "REWRITE"
            row["evidence"] += "; PREPARED_COPY_NOT_APPROVED candidate_sha256=" + candidate["hash"]
        primary.append(row)
        inventory.append(row)
        for code, count in json.loads(row["occurrences"]).items():
            summaries[code]["objects"] += 1
            summaries[code]["occurrences"] += count
        inventory.append(make_row(r, "title_h1", r["post_title"], src, mapping, "WP title; archive/home overrides inventoried separately"))
        inventory.append(make_row(r, "excerpt", r["post_excerpt"], src, mapping, "Captured explicit excerpt; empty means runtime fallback not captured"))
        inventory.append(make_row(r, "meta_description", r["meta"].get("_sy3_description", ""), src, mapping, "Captured _sy3_description; plugin fallback/source separate"))
        inventory.append(make_row(r, "seo_title", r["post_title"], src + [safe_file("theme/functions.php")], mapping,
                                  "Singular title input only; site suffix/options and rendered title not captured; home/journal override source inventoried"))
        inventory.append(make_row(r, "links", " ".join(label for _, label in visible.anchors), src, mapping,
                                  f"{len(visible.anchors)} stored anchors; URL availability not fetched; CTA routes require functional evidence"))
        if manifest.get("primary_query"):
            query = manifest["primary_query"]
            query_matches = {"DUPLICATE_INTENT": [re.match(r".+", query)]} if str(r["ID"]) in collided else {}
            inventory.append(make_row(r, "primary_query", query, src, mapping,
                                      "Source query, not runtime postmeta capture; identical service/article query is a comparison candidate, not duplicate verdict",
                                      matches=query_matches))
        if visible.faq:
            inventory.append(make_row(r, "stored_faq", " ".join(visible.faq), src, mapping, "Stored details only; article dynamic FAQ source separately inventoried"))
        if visible.alts:
            inventory.append(make_row(r, "body_image_alt", " ".join(visible.alts), src, mapping,
                                      f"{len(visible.alts)} inline img tags, {sum(not a for a in visible.alts)} empty alt; decorative/meaningful distinction requires visual review"))
        if r["meta"].get("_thumbnail_id"):
            cover = r.get("cover")
            if (not isinstance(cover, dict) or str(cover.get("id")) != str(r["meta"]["_thumbnail_id"])
                    or not isinstance(cover.get("alt"), str) or urlparse(cover.get("url", "")).hostname != "sy3.ru"):
                raise ValueError("Missing/mismatched runtime cover capture: " + str(r["ID"]))
            alt_row = make_row(r, "cover_alt", cover["alt"], src, "RUNTIME_ATTACHMENT_ID_VERIFIED",
                               "Captured attachment #" + str(cover["id"]) + "; actual alt/caption/URL read-only captured; image suitability not visually reviewed")
            alt_row["current_phrase"] = cover["alt"][:180]
            inventory.append(alt_row)
        ctas = [label for href, label in visible.anchors if ("t.me/" in href or "max.ru/" in href or href.startswith("mailto:") or "button" in label.lower())]
        if ctas:
            inventory.append(make_row(r, "stored_cta", " | ".join(ctas), src, mapping,
                                      "Stored CTA; main-copy filter can normalize legacy name/contact; not proof of live visible wording"))
        titles.append((r, set(re.findall(r"[а-яёa-z0-9]{4,}", r["post_title"].lower()))))
    # No semantic duplicate verdict: query/title overlap merely creates a future comparison list.
    stop = {"какие", "почему", "когда", "перед", "после", "чтобы", "нужно", "проверить", "проверка", "сайта", "бизнеса", "работы", "процесса", "данных", "beget", "wordpress", "битрикс24"}
    pairs = []
    for (a, aa), (b, bb) in itertools.combinations(titles, 2):
        aa, bb = aa - stop, bb - stop
        common = aa & bb
        score = len(common) / max(1, len(aa | bb))
        if len(common) >= 2 and score >= .5:
            pairs.append((round(score, 3), int(a["ID"]), int(b["ID"]), a["post_name"], b["post_name"]))
    pairs.sort(reverse=True)
    for surface, path, purpose, sample, severity, state in UI:
        source = safe_file(path)
        raw = (WP / path).read_text()
        values = load("theme/inc/article-support-copy.json") if surface == "article_curated_faq_cta" else None
        if values is not None:
            sample = " ".join(json.dumps(v, ensure_ascii=False) for v in values.values())
        if surface == "navigation_menu_items":
            menus = baseline.get("navigation_menus")
            if not isinstance(menus, list):
                raise ValueError("Runtime navigation capture missing")
            for menu in menus:
                if not isinstance(menu.get("items"), list) or any(urlparse(item.get("url", "")).hostname != "sy3.ru" for item in menu["items"]):
                    raise ValueError("Invalid/out-of-scope captured menu")
            sample = " · ".join(item["title"] for menu in menus for item in menu["items"])
        matches = detect(sample, anchor=surface == "card_cta")
        counts = {k: len(v) for k, v in matches.items()}
        row = {k: "" for k in FIELDS}
        row.update(object_id="ui:" + surface, content_type="theme_ui", status="SOURCE_ONLY", surface=surface,
                   surface_hash=sha(raw), source=source, source_mapping=state, evidence_kind="SOURCE_INSPECTED_RUNTIME_NOT_CAPTURED",
                   audience="Посетитель sy3.ru", purpose=purpose, issues="|".join(counts), severity=severity,
                   current_phrase=sample[:180] if sample and surface != "article_curated_faq_cta" else "",
                   action="NEEDS_FACTS" if "PRIVACY_CRITICAL" in counts else ("REWRITE" if counts else "KEEP"), evidence="Source boundary inspected; no live render/reader test in this inventory",
                   review_state="FLAGGED_CANDIDATE" if counts else "INVENTORIED_NOT_SEMANTICALLY_REVIEWED",
                   characters=len(sample), occurrences=json.dumps(counts, ensure_ascii=False, sort_keys=True))
        if surface == "navigation_menu_items":
            row["surface_hash"] = sha(json.dumps(menus, ensure_ascii=False, sort_keys=True))
            row["evidence_kind"] = "CAPTURED_RUNTIME_MENUS"
            row["evidence"] = f"{len(menus)} current menus / {sum(len(menu['items']) for menu in menus)} current items; importer is seed reference, not runtime source"
        inventory.append(row)
    args.output.mkdir(parents=True, exist_ok=True)
    out = args.output / "CONTENT_INVENTORY.csv"
    if out.resolve() == args.baseline.resolve():
        raise ValueError("Output must not overwrite input")
    with out.open("w", newline="") as f:
        writer = csv.DictWriter(f, FIELDS, lineterminator="\n")
        writer.writeheader()
        writer.writerows(inventory)
    report = {
        "baseline_sha256": sha(args.baseline.read_text()), "objects": len(captured),
        "objects_by_type_status": dict(Counter(r["post_type"] + ":" + r["post_status"] for r in captured)),
        "source_mapping": dict(mapped), "surfaces": len(inventory),
        "surface_counts": dict(Counter(r["surface"] for r in inventory)),
        "captured_cover_alt": {"objects": sum(bool(r.get("cover")) for r in captured), "nonempty": sum(bool((r.get("cover") or {}).get("alt")) for r in captured)},
        "captured_navigation_menus": len(baseline.get("navigation_menus", [])),
        "body_candidates_only": summaries,
        "body_objects_with_candidates": sum(bool(r["issues"]) for r in primary),
        "body_objects_without_lint_candidate_not_accepted": sum(not r["issues"] for r in primary),
        "semantic_full_corpus_review": "NOT_RUN", "reader_test": "NOT_RUN",
        "prepared_pilot_bodies": len(prepared),
        "pilot_review_states": dict(Counter(c["state"] for c in prepared.values())),
        "remaining_objects_without_prepared_copy": len(captured) - len(prepared),
        "body_action_candidates": dict(Counter(r["action"] for r in primary)),
        "exact_query_collision_groups": query_collisions,
        "query_duplicate_candidates": {"objects": len(collided), "occurrences": len(collided), "verdict": "NOT_SEMANTICALLY_REVIEWED"},
        "possible_title_overlap_pairs_not_duplicate_verdict": pairs,
        "personal_site": "REJECTED_BY_SITE_AND_URL_VALIDATION; shared UI boundary annotated",
        "production_or_queue_changes": False,
    }
    # Summary to stdout only: caller may retain privately; raw baseline never copied.
    print(json.dumps(report, ensure_ascii=False, indent=2))


if __name__ == "__main__":
    main()
