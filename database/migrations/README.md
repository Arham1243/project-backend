# Migrations

This folder is intentionally empty (see `.gitkeep`). **Do not add routine feature migrations here.**

## Default: module migrations

Put schema changes in the module that owns the feature:

```
app/Modules/<Module>/Database/Migrations/
```

Each module `ServiceProvider` loads that path with `loadMigrationsFrom`. Create a file with:

```bash
php artisan make:migration <description> --path=app/Modules/<Module>/Database/Migrations
```

Replace `<Module>` with the PascalCase folder name (e.g. `Reports`, `Tickets`).

For new modules and layout, see [app/Modules/README.md](../../app/Modules/README.md).

## Cross-cutting schema (no single module owns it)

1. **Preferred:** [app/Modules/Core/Database/Migrations/](../../app/Modules/Core/Database/Migrations/) — shared platform tables and changes that span the app but are still “infrastructure” (companies baseline lives here).
2. **Escape hatch:** this folder (`database/migrations/`) for rare app-wide one-offs (e.g. a data fix touching several modules). Laravel registers this path by default, so `php artisan migrate` picks up files here. Use a clear name prefix such as `platform_` or `global_` in the migration filename so reviewers can spot non-module migrations.

```bash
php artisan make:migration platform_<description>
```

## Ordering

All migration paths share one `migrations` table. Run order is determined by the timestamp prefix on each file, across modules and this folder.

## Squashed baseline (fresh installs)

Platform schema for new environments lives in a **single** Core migration:

`app/Modules/Core/Database/Migrations/2026_09_17_000000_project_baseline_schema.php`

Do not reintroduce rename/data-fix migration chains for tables already defined there. Use:

```bash
php artisan migrate:fresh --seed
php artisan permissions:reseed
```

Existing databases that already ran older migration files are not upgraded by removing those files; use a fresh database or `migrate:fresh` in local/dev only.
