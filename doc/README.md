# Auchan Asset Tracker — documentation (Phase 6)

Plugin: **AuchanAssetTracker** · Version **0.5.1** · GLPI **11.0.2**

| Lang | Markdown | PDF |
| --- | --- | --- |
| English | [en/](en/) | [pdf/AuchanAssetTracker-Documentation-EN.pdf](pdf/AuchanAssetTracker-Documentation-EN.pdf) |
| Romanian | [ro/](ro/) | [pdf/AuchanAssetTracker-Documentation-RO.pdf](pdf/AuchanAssetTracker-Documentation-RO.pdf) |

Rebuild PDFs after editing Markdown:

```bash
python3 tools/build_docs_pdf.py
```

## Deliverables map

| ID | What | File (EN / RO) |
| --- | --- | --- |
| D-01 | Plugin source | This Git repo (`setup.php`, `hook.php`, `inc/`, `front/`, `install/`, …) |
| D-02 | Installable package | Build with `tools/build_package.sh` → `.tar.gz` for Setup → Plugins |
| D-03 | Architecture / data model | `architecture.md` |
| D-04 | Install / upgrade / rollback | `installation.md` |
| D-05 | API / hooks / QR URL | `api.md` |
| D-06 / D-07 / D-08 | User manuals (all roles) | `user-manual.md` |
| D-09 / D-10 / D-11 / D-12 | Tests, QA, defects, regression | `testing.md` |
| D-13 | Debug / troubleshooting | `troubleshooting.md` |

D-10 / D-11 tables are **templates** — fill only with real QA results.
