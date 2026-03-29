# Drupal Jobs Import Demo

[![Latest Version on Packagist](https://img.shields.io/packagist/v/baikho/drupal-jobs_import_demo.svg)](https://packagist.org/packages/baikho/drupal-jobs_import_demo)
[![Total Downloads](https://img.shields.io/packagist/dt/baikho/drupal-jobs_import_demo.svg)](https://packagist.org/packages/baikho/drupal-jobs_import_demo)
[![MIT Licensed](https://img.shields.io/github/license/baikho/drupal-jobs_import_demo.svg)](https://github.com/baikho/drupal-jobs_import_demo/blob/main/LICENSE.txt)
[![GitHub issues](https://img.shields.io/github/issues/baikho/drupal-jobs_import_demo.svg)](https://github.com/baikho/drupal-jobs_import_demo/issues)
[![GitHub stars](https://img.shields.io/github/stars/baikho/drupal-jobs_import_demo.svg)](https://github.com/baikho/drupal-jobs_import_demo/stargazers)

Drupal module demonstrating a **multilingual XML → vacancy** import using core **Migrate**, **Migrate Plus** (`simple_xml` + HTTP URL source), **Migrate Tools** (Drush `--sync` and related commands), **Ultimate Cron** (packaged hourly job), composite source IDs, self-**migration_lookup** for translations, and **`ImportCronService`** (background Drush import from cron).

---

## Table of contents

1. [Overview](#overview)
2. [Requirements](#requirements)
3. [Installation](#installation)
4. [What this module provides](#what-this-module-provides)
5. [Layout](#layout)
6. [Usage](#usage)
7. [XML fixture](#xml-fixture)
8. [Troubleshooting](#troubleshooting)
9. [License](#license)

---

## Overview

- **Migration** `jobs` imports rows from an XML feed into **`vacancy`** nodes (`translations: true`).
- Each **`<Publication>`** under `Job/ExternalPublication` is one source row; **job id + locale** uniquely identify the row; **migration_lookup** on `job_id` alone reuses one node per job across languages.
- The feed URL comes from **`FeedEndpoint`**: an absolute HTTP URL to **`/jobs-import-demo/demo-feed.xml`** (same XML as `fixtures/job_feed.xml`).
- **`config/optional`** supplies **`vacancy`**, the three fields the migration uses, a minimal **`full`** text format (only if `filter.format.full` is not already in the active config), default form/view displays, and translation settings. Each file is imported **only when that config name is not already present**, so existing sites (e.g. one that already exports **`vacancy`**) are not overwritten.

---

## Requirements

| Area | Details |
|------|---------|
| **Drupal** | 10.3+ or 11.x |
| **Modules** | `migrate`, `migrate_plus`, **`migrate_tools`**, **`ultimate_cron`**, `node`, `user`, `language`, `content_translation`, `link`, `text`, `filter`, `datetime`, `path` |
| **Content** | Either use the optional **`vacancy`** bundle + fields from **`config/optional`**, or provide the same machine names yourself: **`field_description`** (text, **`full`** format), **`field_publication_date`** (datetime), **`field_website`** (link) |

---

## Installation

From your Drupal project root:

```bash
composer require baikho/drupal-jobs_import_demo
drush en jobs_import_demo -y
drush cr
```

---

## What this module provides

| Piece | Purpose |
|-------|---------|
| **Migrate Plus group** `jobs_import_demo` | `drush mim --group=jobs_import_demo` |
| **Migration** `jobs` | YAML in `migrations/jobs.yml` |
| **Source** `job_feed_url` | Extends Migrate Plus `Url`; injects URL from `FeedEndpoint` |
| **Process** `migration_lookup_first_nid` | After `migration_lookup` by shared job id only: if several nids are returned, keep the first so `nid` is a single translation target |
| **Process** `static_map` (core) | Demo map `en_US` / `nl_NL` / `fr_FR` → langcodes; extend `map` in YAML for more locales |
| **Service** `jobs_import_demo.feed_endpoint` | Resolves feed URL (demo HTTP route) |
| **Service** `jobs_import_demo_cron` | `ImportCronService::jobsImportCron()` — background `drush mim …` (non-blocking) |
| **Install config** (`config/install`) | `migrate_plus.migration_group.jobs_import_demo`, `ultimate_cron.job.jobs_import_demo_jobs_cron` (hourly; disable or edit in UI if not wanted) |
| **Optional config** (`config/optional`) | **`node.type.vacancy`**, field storage + instances, **`filter.format.full`** (if missing), **`language.content_settings.node.vacancy`**, default entity displays |
| **Install** | Adds **Dutch** (`nl`) and **French** (`fr`) if missing (fixture includes `nl_NL` and `fr_FR`) |

---

## Layout

```
jobs_import_demo/
├── README.md                 # This file
├── LICENSE.txt
├── jobs_import_demo.info.yml
├── jobs_import_demo.routing.yml
├── jobs_import_demo.services.yml
├── jobs_import_demo.install
├── config/
│   ├── install/
│   │   ├── migrate_plus.migration_group.jobs_import_demo.yml
│   │   └── ultimate_cron.job.jobs_import_demo_jobs_cron.yml
│   └── optional/            # vacancy + fields + full format + displays (skip if names already exist)
├── fixtures/
│   └── job_feed.xml
├── migrations/
│   └── jobs.yml
└── src/
    ├── Controller/
    │   └── DemoJobFeedController.php
    ├── Plugin/
    │   ├── migrate/process/   # MigrationLookupFirstNid
    │   └── migrate/source/    # JobFeedUrl
    └── Service/
        ├── FeedEndpoint.php
        └── ImportCronService.php
```

---

## Usage

### Drush

```bash
drush en jobs_import_demo -y
drush cr
drush mim --group=jobs_import_demo --update
drush ms --group=jobs_import_demo
```

With sync (remove destinations missing from source). The **`--sync`** flag is provided by **Migrate Tools**:

```bash
drush mim --group=jobs_import_demo --update --sync
```

### Cron

- **Ultimate Cron** is a **hard dependency**: on install, config **`Jobs Import Demo — jobs`** is created (hourly). Disable or reschedule it under **Configuration → System → Cron** (Ultimate Cron UI) if needed.
- The job calls **`jobs_import_demo_cron:jobsImportCron()`**, which runs a non-blocking shell to `vendor/bin/drush mim --group=jobs_import_demo --update --sync`.

---

## XML fixture

`fixtures/job_feed.xml` is shaped like a typical HR export:

- Root **`<Jobs>`**, repeated **`<Job id="…" ID="…">`**.
- **`<ExternalPublication>`** contains **`<Publication ID="…" language="…">`** with `Jobname`, `ShortDescription`, `Leadaftertitle`, `Publicationdate`, `URL`.

The migration maps those elements via Migrate Plus **`simple_xml`** and the selectors in `migrations/jobs.yml`. **`publication_id`** is read from **`@ID`** for traceability; source IDs for the map remain **`job_id` + `locale`**.

---

## Troubleshooting

| Issue | Things to check |
|-------|------------------|
| **Migration not found** | `drush cr` after install; confirm `migrate_plus` enabled. |
| **Field / bundle errors** | On a fresh install, enable the module once so **`config/optional`** can create **`vacancy`** and fields; or define the same machine names yourself. The description field must allow the **`full`** text format (optional config can create a minimal **`full`** if it is missing). |
| **Empty source / fetch errors** | Ensure `$base_url` in `settings.php` is reachable from PHP (CLI/Drush) so the demo feed route can be fetched. |
| **Cron never runs** | Ultimate Cron job **Jobs Import Demo — jobs** enabled; Ultimate Cron module running. |
| **`drush` not found in cron** | `ImportCronService` uses `DRUPAL_ROOT/../vendor/bin/drush`; adjust deployment layout if different. |

---

## License

GPL-2.0-or-later (see `LICENSE.txt`).
