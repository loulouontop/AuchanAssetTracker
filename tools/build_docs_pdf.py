#!/usr/bin/env python3
"""Build bilingual client PDFs from doc/en and doc/ro Markdown."""

from __future__ import annotations

import argparse
import html
import shutil
import subprocess
import sys
import tempfile
from datetime import date
from pathlib import Path

try:
    import markdown as md
except ImportError as exc:  # pragma: no cover
    raise SystemExit(
        "Missing dependency: markdown. Install with: pip install markdown"
    ) from exc

ROOT = Path(__file__).resolve().parents[1]
DOC = ROOT / "doc"
OUT_DIR = DOC / "pdf"

SECTIONS = [
    "architecture.md",
    "installation.md",
    "api.md",
    "user-manual.md",
    "testing.md",
    "troubleshooting.md",
]

META = {
    "en": {
        "lang": "en",
        "title": "Auchan Asset Tracker — Documentation",
        "subtitle": "GLPI plugin · Phase 6 deliverables D-03 – D-13",
        "glpi_label": "Target GLPI",
        "date_label": "Document date",
        "lang_label": "Language",
        "lang_name": "English",
        "toc_title": "Contents",
        "footer_left": "Auchan Asset Tracker",
        "cover_note": (
            "Internal / client documentation for installation, operation, "
            "QA templates, and troubleshooting. Content mirrors the functional "
            "plugin behaviour — empty QA tables are templates to fill with real results."
        ),
        "filename": "AuchanAssetTracker-Documentation-EN.pdf",
    },
    "ro": {
        "lang": "ro",
        "title": "Auchan Asset Tracker — Documentație",
        "subtitle": "Plugin GLPI · Livrabile Faza 6 D-03 – D-13",
        "glpi_label": "GLPI țintă",
        "date_label": "Data documentului",
        "lang_label": "Limbă",
        "lang_name": "Română",
        "toc_title": "Cuprins",
        "footer_left": "Auchan Asset Tracker",
        "cover_note": (
            "Documentație internă / client pentru instalare, operare, "
            "șabloane QA și troubleshooting. Conținutul reflectă comportamentul "
            "real al pluginului — tabelele QA goale sunt șabloane de completat cu rezultate reale."
        ),
        "filename": "AuchanAssetTracker-Documentation-RO.pdf",
    },
}

CSS = """
@page {
  size: A4;
  margin: 18mm 16mm 20mm 16mm;
  @bottom-left {
    content: "Auchan Asset Tracker";
    font-family: "Segoe UI", "Helvetica Neue", Arial, sans-serif;
    font-size: 8.5pt;
    color: #667085;
  }
  @bottom-right {
    content: counter(page);
    font-family: "Segoe UI", "Helvetica Neue", Arial, sans-serif;
    font-size: 8.5pt;
    color: #667085;
  }
}
@page :first {
  margin: 0;
  @bottom-left { content: none; }
  @bottom-right { content: none; }
}

:root {
  --auchan-red: #e30613;
  --ink: #1a1f36;
  --muted: #5b6475;
  --line: #d9dee7;
  --soft: #f6f7f9;
  --code-bg: #f0f2f5;
}

* { box-sizing: border-box; }
html, body {
  margin: 0;
  padding: 0;
  color: var(--ink);
  font-family: "Segoe UI", "Helvetica Neue", Arial, sans-serif;
  font-size: 10.5pt;
  line-height: 1.45;
  -webkit-print-color-adjust: exact;
  print-color-adjust: exact;
}

/* Cover */
.cover {
  min-height: 100vh;
  padding: 28mm 22mm;
  background:
    linear-gradient(165deg, #1a1f36 0%, #252b45 55%, #1a1f36 100%);
  color: #fff;
  page-break-after: always;
  position: relative;
  overflow: hidden;
}
.cover::before {
  content: "";
  position: absolute;
  inset: 0 auto 0 0;
  width: 10mm;
  background: var(--auchan-red);
}
.cover::after {
  content: "";
  position: absolute;
  right: -40mm;
  top: -20mm;
  width: 120mm;
  height: 120mm;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(227,6,19,0.35), transparent 70%);
}
.cover-inner { position: relative; z-index: 1; max-width: 160mm; }
.brand {
  font-size: 12pt;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  color: #ffb4b8;
  font-weight: 600;
  margin-bottom: 18mm;
}
.cover h1 {
  font-size: 28pt;
  line-height: 1.15;
  font-weight: 700;
  margin: 0 0 6mm;
}
.cover .subtitle {
  font-size: 13pt;
  color: #d7dbe7;
  margin: 0 0 14mm;
}
.meta-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 4mm 8mm;
  max-width: 130mm;
  margin-bottom: 14mm;
}
.meta-item label {
  display: block;
  font-size: 8.5pt;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: #9aa3b5;
  margin-bottom: 1mm;
}
.meta-item span {
  font-size: 12pt;
  font-weight: 600;
}
.cover-note {
  border-left: 3px solid var(--auchan-red);
  padding: 3mm 0 3mm 5mm;
  color: #cfd5e3;
  font-size: 10pt;
  max-width: 145mm;
}

/* Body */
.doc-body { padding: 0; }
.toc {
  page-break-after: always;
}
.toc h2 {
  color: var(--auchan-red);
  border-bottom: 2px solid var(--auchan-red);
  padding-bottom: 2mm;
  margin-top: 0;
}
.toc ol {
  padding-left: 6mm;
}
.toc li {
  margin: 2.5mm 0;
}
.toc a {
  color: var(--ink);
  text-decoration: none;
}

h1, h2, h3, h4 {
  color: var(--ink);
  page-break-after: avoid;
}
h1 {
  font-size: 18pt;
  border-bottom: 2.5px solid var(--auchan-red);
  padding-bottom: 2mm;
  margin: 0 0 5mm;
}
h2 {
  font-size: 13.5pt;
  margin: 7mm 0 3mm;
  color: #22283f;
}
h3 {
  font-size: 11.5pt;
  margin: 5mm 0 2mm;
}
h4 {
  font-size: 10.5pt;
  margin: 4mm 0 2mm;
}
p { margin: 0 0 3mm; }
ul, ol { margin: 0 0 3.5mm; padding-left: 5.5mm; }
li { margin: 1mm 0; }
strong { font-weight: 650; }
a { color: var(--auchan-red); }

section.chapter {
  page-break-before: always;
}
section.chapter:first-of-type {
  page-break-before: auto;
}

table {
  width: 100%;
  border-collapse: collapse;
  margin: 0 0 4mm;
  font-size: 9.3pt;
  page-break-inside: auto;
}
thead { display: table-header-group; }
tr { page-break-inside: avoid; }
th, td {
  border: 1px solid var(--line);
  padding: 1.8mm 2.2mm;
  vertical-align: top;
  text-align: left;
}
th {
  background: var(--soft);
  font-weight: 650;
  color: #22283f;
}
tr:nth-child(even) td { background: #fafbfc; }

code {
  font-family: "Cascadia Code", "Consolas", "Courier New", monospace;
  font-size: 8.8pt;
  background: var(--code-bg);
  padding: 0.3mm 1.2mm;
  border-radius: 2px;
}
pre {
  background: #1a1f36;
  color: #e8ecf5;
  padding: 3.5mm 4mm;
  border-radius: 3px;
  overflow-x: auto;
  font-size: 8.4pt;
  line-height: 1.4;
  page-break-inside: avoid;
  margin: 0 0 4mm;
}
pre code {
  background: transparent;
  color: inherit;
  padding: 0;
}
blockquote {
  margin: 0 0 4mm;
  padding: 2mm 0 2mm 4mm;
  border-left: 3px solid var(--auchan-red);
  color: var(--muted);
}
hr {
  border: 0;
  border-top: 1px solid var(--line);
  margin: 6mm 0;
}
"""


def md_to_html(text: str) -> str:
    return md.markdown(
        text,
        extensions=["tables", "fenced_code", "nl2br", "sane_lists"],
    )


def first_heading(text: str) -> str:
    for line in text.splitlines():
        if line.startswith("# "):
            return line[2:].strip()
    return "Section"


def build_html(lang: str, glpi: str, doc_date: str) -> str:
    meta = META[lang]
    parts: list[str] = []
    toc_items: list[str] = []

    for idx, filename in enumerate(SECTIONS, start=1):
        path = DOC / lang / filename
        if not path.exists():
            raise FileNotFoundError(path)
        raw = path.read_text(encoding="utf-8")
        title = first_heading(raw)
        anchor = f"sec-{idx}"
        toc_items.append(
            f'<li><a href="#{anchor}">{html.escape(title)}</a></li>'
        )
        body = md_to_html(raw)
        # Drop the duplicated H1 from markdown body; chapter header already shows it.
        body = body.replace(f"<h1>{html.escape(title)}</h1>", "", 1)
        # markdown may not escape the same way — also strip raw h1 if unescaped match
        if body.lstrip().startswith("<h1>"):
            end = body.find("</h1>")
            if end != -1:
                body = body[end + 5 :].lstrip()
        parts.append(
            f'<section class="chapter" id="{anchor}">'
            f"<h1>{html.escape(title)}</h1>{body}</section>"
        )

    cover = f"""
<section class="cover">
  <div class="cover-inner">
    <div class="brand">Auchan Romania · IT Asset Tracker</div>
    <h1>{html.escape(meta['title'])}</h1>
    <p class="subtitle">{html.escape(meta['subtitle'])}</p>
    <div class="meta-grid">
      <div class="meta-item"><label>{html.escape(meta['glpi_label'])}</label><span>{html.escape(glpi)}</span></div>
      <div class="meta-item"><label>{html.escape(meta['date_label'])}</label><span>{html.escape(doc_date)}</span></div>
      <div class="meta-item"><label>{html.escape(meta['lang_label'])}</label><span>{html.escape(meta['lang_name'])}</span></div>
    </div>
    <p class="cover-note">{html.escape(meta['cover_note'])}</p>
  </div>
</section>
"""

    toc = (
        f'<section class="toc"><h2>{html.escape(meta["toc_title"])}</h2>'
        f'<ol>{"".join(toc_items)}</ol></section>'
    )

    return f"""<!DOCTYPE html>
<html lang="{meta['lang']}">
<head>
<meta charset="utf-8"/>
<title>{html.escape(meta['title'])}</title>
<style>{CSS}</style>
</head>
<body>
{cover}
<div class="doc-body">
{toc}
{"".join(parts)}
</div>
</body>
</html>
"""


def find_chrome() -> str | None:
    for candidate in (
        "google-chrome",
        "google-chrome-stable",
        "chromium",
        "chromium-browser",
        "/usr/local/bin/google-chrome",
        "/usr/bin/google-chrome",
    ):
        path = shutil.which(candidate) if not candidate.startswith("/") else candidate
        if path and Path(path).exists():
            return path
    return None


def html_to_pdf(html_doc: str, pdf_path: Path, base_url: str) -> None:
    """Prefer WeasyPrint (CSS page numbers); fall back to Chrome headless."""
    pdf_path.parent.mkdir(parents=True, exist_ok=True)
    try:
        from weasyprint import HTML

        HTML(string=html_doc, base_url=base_url).write_pdf(pdf_path)
        return
    except Exception as exc:  # noqa: BLE001 — try Chrome next
        sys.stderr.write(f"WeasyPrint unavailable ({exc}); trying Chrome…\n")

    chrome = find_chrome()
    if not chrome:
        raise SystemExit("Neither WeasyPrint nor Chrome/Chromium available for PDF.")

    with tempfile.NamedTemporaryFile("w", suffix=".html", encoding="utf-8", delete=False) as fh:
        fh.write(html_doc)
        html_path = Path(fh.name)
    try:
        cmd = [
            chrome,
            "--headless=new",
            "--no-sandbox",
            "--disable-gpu",
            "--no-pdf-header-footer",
            f"--print-to-pdf={pdf_path}",
            f"file://{html_path.resolve()}",
        ]
        result = subprocess.run(cmd, capture_output=True, text=True)
        if result.returncode != 0 or not pdf_path.exists():
            sys.stderr.write(result.stdout)
            sys.stderr.write(result.stderr)
            raise SystemExit(f"PDF render failed for {pdf_path.name}")
    finally:
        html_path.unlink(missing_ok=True)


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--lang", choices=("en", "ro", "all"), default="all")
    parser.add_argument("--glpi", default="11.0.2")
    parser.add_argument("--date", default=date.today().isoformat())
    parser.add_argument("--keep-html", action="store_true")
    args = parser.parse_args()

    langs = ["en", "ro"] if args.lang == "all" else [args.lang]
    OUT_DIR.mkdir(parents=True, exist_ok=True)

    for lang in langs:
        html_doc = build_html(lang, args.glpi, args.date)
        if args.keep_html:
            preview = OUT_DIR / f"preview-{lang}.html"
            preview.write_text(html_doc, encoding="utf-8")
        pdf_file = OUT_DIR / META[lang]["filename"]
        html_to_pdf(html_doc, pdf_file, base_url=str(DOC / lang))
        print(f"Built {pdf_file.relative_to(ROOT)} ({pdf_file.stat().st_size} bytes)")


if __name__ == "__main__":
    main()
