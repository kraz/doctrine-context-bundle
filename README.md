# Doctrine Context Bundle

A Symfony bundle that makes working with multiple Doctrine entity managers or DBAL connections painless. It wraps the standard `doctrine/migrations` commands (and optionally ORM schema commands) so that a single command can target one specific context or fan out across all of them automatically.

## The problem

When a project has more than one entity manager or DBAL connection, running migrations requires repeating the same command once per context, which requires each time specifying the right `--em` or `--conn` flag manually:

```bash
php bin/console doctrine:migrations:migrate --em=shop
php bin/console doctrine:migrations:migrate --em=analytics
php bin/console doctrine:migrations:migrate --em=audit
```

There is also a subtle schema-pollution problem: after running migrations, `doctrine:schema:update` and `doctrine:schema:validate` see the migration metadata table (e.g. `doctrine_migration_versions`) as an unmanaged table and report it as something to drop or as a validation error.

## What this bundle does

- **Context-aware commands**: every `doctrine:migrations:*` command gains `--em` and `--conn` options. Pass one to target a specific context, or omit both to run across all registered contexts in sequence.
- **ORM command integration** *(requires `doctrine/orm`)*: `doctrine:schema:validate` and `doctrine:mapping:info` receive the same fan-out behaviour.
- **Schema filter**: automatically hides the migration metadata table from `doctrine:schema:update` and `doctrine:schema:validate`, so those commands never see it as unmanaged.
- **`--ctx-isolation`**: an extra flag added to every wrapped command. When set, a failure in one context does not abort the remaining contexts.

## Requirements

| Dependency                            | Version             |
|---------------------------------------|---------------------|
| PHP                                   | `>= 8.4`            |
| `doctrine/dbal`                       | `^4.4`              |
| `symfony/console`                     | `^8.0`              |
| `doctrine/doctrine-migrations-bundle` | `^4.0` *(dev)*      |
| `doctrine/orm`                        | `^3.6` *(optional)* |

## Installation

```bash
composer require kraz/doctrine-context-bundle
```

Register the bundle in `config/bundles.php` if you are not using Symfony Flex:

```php
return [
    // ...
    Kraz\DoctrineContextBundle\DoctrineContextBundle::class => ['all' => true],
];
```

## Configuration

Register each entity manager or connection that should be treated as a named context. You may use `entity_managers` (requires `doctrine/orm`), `connections`, or a mix, but not both for the same name.

### With entity managers

```yaml
# config/packages/doctrine_context.yaml
doctrine_context:
    entity_managers:
        default:
            migrations_paths:
                App\Migrations\Default: '%kernel.project_dir%/migrations/default'
            storage:
                table_storage:
                    table_name: doctrine_migration_versions
        shop:
            migrations_paths:
                App\Migrations\Shop: '%kernel.project_dir%/migrations/shop'
            storage:
                table_storage:
                    table_name: doctrine_migration_versions
        analytics:
            migrations_paths:
                App\Migrations\Analytics: '%kernel.project_dir%/migrations/analytics'
            storage:
                table_storage:
                    table_name: doctrine_migration_versions
```

### With DBAL connections only

```yaml
doctrine_context:
    connections:
        default:
            migrations_paths:
                App\Migrations\Default: '%kernel.project_dir%/migrations/default'
            storage:
                table_storage:
                    table_name: doctrine_migration_versions
        shop:
            migrations_paths:
                App\Migrations\Shop: '%kernel.project_dir%/migrations/shop'
```

### Full configuration reference

```yaml
doctrine_context:
    entity_managers:           # or connections:
        <name>:
            migrations_paths:
                <Namespace>: <path>
            migrations:        # individual migration classes to load
                - App\Migrations\SpecialMigration
            storage:
                table_storage:
                    table_name: doctrine_migration_versions
                    version_column_name: ~
                    version_column_length: ~
                    executed_at_column_name: ~
                    execution_time_column_name: ~
            services:          # override doctrine/migrations services
                Doctrine\Migrations\SomeService: my_symfony_service_id
            factories:         # override doctrine/migrations services via callables
                Doctrine\Migrations\SomeService: my_factory_service_id
            all_or_nothing: false
            check_database_platform: true
            custom_template: ~
            organize_migrations: false  # false | BY_YEAR | BY_YEAR_AND_MONTH
```

## Usage

### Run migrations for all contexts

Omitting `--em` / `--conn` fans the command out across every registered context:

```bash
php bin/console doctrine:migrations:migrate --no-interaction
```

Output will be grouped by context:

```
Entity Manager: default
-----------------------

 [notice] Migrating up to ...
  
Entity Manager: shop
--------------------

 [notice] Migrating up to ...

Entity Manager: analytics
-------------------------
 
 [notice] No migrations to execute.
```

### Target a specific context

```bash
# entity manager
php bin/console doctrine:migrations:migrate --em=shop --no-interaction

# DBAL connection
php bin/console doctrine:migrations:migrate --conn=shop --no-interaction
```

### Continue past failures with `--ctx-isolation`

By default, a failure in one context stops execution when executed in non-interactive mode. Use `--ctx-isolation` to continue with the remaining contexts regardless:

```bash
php bin/console doctrine:migrations:migrate --no-interaction --ctx-isolation
```

### All supported commands

Every `doctrine:migrations:*` command supports the context options:

| Command                                     | Description                                 |
|---------------------------------------------|---------------------------------------------|
| `doctrine:migrations:migrate`               | Execute migrations                          |
| `doctrine:migrations:diff`                  | Generate a migration by diffing the schema  |
| `doctrine:migrations:generate`              | Generate a blank migration class            |
| `doctrine:migrations:execute`               | Execute or revert a specific migration      |
| `doctrine:migrations:status`                | Show the migration status                   |
| `doctrine:migrations:list`                  | List available migrations                   |
| `doctrine:migrations:current`               | Show the current migration version          |
| `doctrine:migrations:latest`                | Show the latest available version           |
| `doctrine:migrations:up-to-date`            | Check if the schema is up to date           |
| `doctrine:migrations:rollup`                | Roll up migrations into a single version    |
| `doctrine:migrations:version`               | Manually add/delete versions from the table |
| `doctrine:migrations:dump-schema`           | Dump the schema for a mapping               |
| `doctrine:migrations:sync-metadata-storage` | Sync the metadata storage                   |

When `doctrine/orm` is installed and configured:

| Command                    | Description                                  |
|----------------------------|----------------------------------------------|
| `doctrine:schema:validate` | Validate schema across all entity managers   |
| `doctrine:mapping:info`    | Show mapping info across all entity managers |

### Schema filter

The bundle automatically registers a DBAL schema filter per context that hides the migration metadata table from `doctrine:schema:update` and `doctrine:schema:validate`. This prevents those commands from flagging the migration table as an unmanaged or extra table.

The filter activates only during schema update/validate commands and is otherwise transparent.

## Acknowledgements

The idea behind this bundle is credited to [DoctrineMigrationsMultipleDatabaseBundle](https://github.com/AvaiBookSports/DoctrineMigrationsMultipleDatabaseBundle) which was heavily refactored to support Symfony 8 and some functional enhancements.
## License

This bundle is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.
