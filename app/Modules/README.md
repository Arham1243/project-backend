# Backend modules

Feature code lives under `app/Modules/<Name>/`. `ModulesServiceProvider` registers every `ServiceProvider.php` in this tree.

## Start a new module

1. Copy `_Skeleton` to `app/Modules/YourModule` (PascalCase folder, e.g. `Inventory`).
2. Rename namespace `App\Modules\_Skeleton` → `App\Modules\YourModule` in all PHP files under the copy.
3. Replace permission slugs (e.g. `example.items` → `inventory.items`) in:
   - `Config/permissions.php`
   - `Routes/api.php` (`role.permission:…` middleware)
4. Implement controllers, models, and routes under your module folder.
5. Register seeders in `Database/Seeders/ModuleSeeder.php` (`public int $order` controls run order in `DatabaseSeeder`).
6. Add new migrations under `Database/Migrations/` (loaded automatically by your `ServiceProvider`).

## Module layout

```
YourModule/
  ServiceProvider.php      # routes + loadMigrationsFrom
  Config/permissions.php   # merged into permission_matrix
  Routes/api.php
  Http/Controllers/
  Database/Migrations/
  Database/Seeders/ModuleSeeder.php
```

After changing permissions, run:

```bash
php artisan permissions:reseed
# or migrate + db:seed as your team normally does
```

## How it wires in

| Piece | Discovery |
|-------|-----------|
| Routes / migrations | Module `ServiceProvider` |
| Permissions | `Administration` merges `Modules/*/Config/permissions.php` |
| Seeders | `DatabaseSeeder` → `ModuleSeederDiscovery` (`*ModuleSeeder.php`, sorted by `$order`) |

## Cross-cutting migrations and seeders

- **Module-owned changes** stay under `app/Modules/<Name>/Database/` (migrations and seeders).
- **Shared schema** with no single owner: prefer `app/Modules/Core/Database/Migrations/`; rare app-wide one-offs may go in [database/migrations/README.md](../../database/migrations/README.md).
- **App-wide seed data** that is not tied to one module: add a class under [database/seeders/](../../database/seeders/) and call it from `DatabaseSeeder` — see [database/seeders/README.md](../../database/seeders/README.md).

## Work on a module branch

- Prefer changes under `app/Modules/YourModule/` only.
- Use prefixed permission names (`yourmodule.entity.action`), consistent with Core / Tickets / Reports / Administration.
- Cross-module use of models/services is allowed but creates coupling; document API changes for other teams.

## Reference

Use `Reports` or `Tickets` as full examples. Use `_Skeleton` as the minimal template.
