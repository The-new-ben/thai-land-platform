# Content migration execution ledger

This directory contains the evidence-first execution ledger for the remaining Thai-Land content migration.

## Scope

- 35 legacy public surfaces that are not managed by the current platform release
- 63 remaining draft records after excluding released draft 841
- 11 registered planned hubs
- 6 ordered release waves

The builder derives every record from the frozen public inventories, draft inventories, ownership registry and managed-route evidence. It does not change production and it does not copy a WordPress body into the repository.

## Source-material truth

The inventories contain identity, metrics, hashes and reviewed dispositions. They do not contain the complete page or draft bodies. Unreviewed legacy records therefore use `live_html_retrieval_required`, and unreviewed drafts use `draft_body_retrieval_required`. The urgent source review stores only identity, length, digest and editorial decisions. It never stores a source body, backup or site export.

The authenticated review separated draft 498 from the visitor cannabis-law owner. Its opening-a-cannabis-business query now has an explicit candidate owner under Business, and none of its superseded 2022 claims can enter the visitor guide.

## Completion contract

A public route, hub or integrated draft cannot be marked complete until its release receipt, live URL, content digest, canonical, breadcrumb, internal-link, structured-data, indexability, desktop and mobile evidence fields are populated. A discard requires a disposition receipt. The validator rejects a complete status without the required evidence.

## Commands

Check the committed ledger:

```powershell
python scripts/build_content_migration_ledger.py
```

Rebuild it after a reviewed source inventory or ownership change:

```powershell
python scripts/build_content_migration_ledger.py --write
```

Run the dependency-free contract tests:

```powershell
python tests/content-migration-ledger.test.py
```

## Files

- `migration-ledger.2026-08-10.json` is the generated execution ledger.
- `migration-ledger.schema.json` defines its machine-readable shape.
- `urgent-source-review.2026-08-10.json` records the bounded authenticated source review without storing bodies.
- `scripts/build_content_migration_ledger.py` builds and checks deterministic output.
- `tests/content-migration-ledger.test.py` proves source parity, ownership, hierarchy, release sequencing and completion gates.
