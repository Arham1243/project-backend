# Seeders

## Default: module seeders

Seed data for a feature belongs under:

```
app/Modules/<Module>/Database/Seeders/
```

Each module exposes a `ModuleSeeder.php` (or another class named `*ModuleSeeder.php`) that calls its inner seeders. `DatabaseSeeder` discovers those classes via `ModuleSeederDiscovery` and runs them sorted by `public int $order` on each class.

Example: [RolesModuleSeeder.php](../../app/Modules/Administration/Database/Seeders/RolesModuleSeeder.php).

When adding a new module, register seeders in that module’s `ModuleSeeder.php`. See [app/Modules/README.md](../../app/Modules/README.md).

## Cross-cutting seed data (not owned by one module)

Add a seeder class in this folder (e.g. `PlatformSetupSeeder.php`) and **register it explicitly** in [DatabaseSeeder.php](./DatabaseSeeder.php):

```php
public function run(): void
{
    // $this->call(PlatformSetupSeeder::class); // before modules if needed

    foreach (ModuleSeederDiscovery::moduleSeederClasses() as $seederClass) {
        $this->call($seederClass);
    }
}
```

Use a short comment in `DatabaseSeeder` when you have multiple app-level seeders so run order stays obvious.

Arbitrary `database/seeders/*.php` files are **not** auto-discovered—only module `*ModuleSeeder.php` under `app/Modules/*/Database/Seeders/` is.
