# Doctrine Context Bundle

[![CI](https://github.com/kraz/doctrine-context-bundle/actions/workflows/ci.yml/badge.svg)](https://github.com/kraz/doctrine-context-bundle/actions/workflows/ci.yml)
[![Packagist Version](https://img.shields.io/packagist/v/kraz/doctrine-context-bundle)](https://packagist.org/packages/kraz/doctrine-context-bundle)
[![GitHub license](https://img.shields.io/github/license/kraz/doctrine-context-bundle)](LICENSE)

A Symfony bundle that makes working with multiple Doctrine entity managers or DBAL connections painless. It wraps the standard Doctrine commands so that a single command can target one specific context or fan out across all of them automatically. The only hard dependency is `doctrine/dbal` - ORM and Migrations support are both optional.

## The problem

When a project has more than one entity manager or DBAL connection, running the same operation across all of them requires repeating the command manually once per context:

```bash
php bin/console doctrine:migrations:migrate --em=shop
php bin/console doctrine:migrations:migrate --em=analytics
php bin/console doctrine:migrations:migrate --em=audit
```

There is also a subtle schema-pollution problem: after running migrations, `doctrine:schema:update` and `doctrine:schema:validate` see the migration metadata table (e.g. `doctrine_migration_versions`) as an unmanaged table and report it as something to drop or as a validation error.

## What this bundle does

- ### Database command integration

    The command `doctrine:database:create` fans out across all registered contexts. Works with DBAL alone - no ORM or Migrations required. Accepts `--connection` / `--conn` to target a single context and `--connections` / `--conns` to target a specific subset.

- ### Migrations command integration
    > *requires `doctrine/doctrine-migrations-bundle`*

    Every `doctrine:migrations:*` command gains `--em` / `--ems` and `--conn` / `--conns` options. Pass one to target a single context or a subset, or omit all to run across every registered context in sequence.

- ### ORM command integration
    > *requires `doctrine/orm`*

    The `doctrine:schema:create`, `doctrine:schema:validate`, and `doctrine:mapping:info` receive the same fan-out behaviour, including `--em` / `--ems` for subset selection.

- ### Schema filter

    The migration metadata table remains hidden for commands like `doctrine:schema:update` and `doctrine:schema:validate`, so those commands never see it as unmanaged.

- ### Additional command options for every wrapped command

  - **`--ctx-isolation`**: When set, a failure in one context does not abort the remaining contexts.
  - **`--ctx-all`**: Explicitly runs the command over all registered contexts. Required when `explicit_context` is enabled and no specific context is given.
  - **`--ctx-output-style`**: Control how the context name is being printed to the output.

- ### Bundle configurations about commands execution behavior
  - **`explicit_context`**: When `true`, every wrapped command requires an explicit context via `--em`, `--ems`, `--conn`, `--conns`, `--connection`, `--connections`, or `--ctx-all`. Prevents accidental fan-out in production environments.

## Requirements

| Dependency                            | Version              |
|---------------------------------------|----------------------|
| PHP                                   | `>= 8.4`             |
| `doctrine/doctrine-bundle`            | `^3.2`               |
| `doctrine/doctrine-migrations-bundle` | `^4.0` *(optional)*  |
| `doctrine/orm`                        | `^3.6` *(optional)*  |

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

Register each entity manager or connection that should be treated as a named context. You may use `entity_managers` (requires `doctrine/orm`) or `connections`, but not both for the same name.

The migration-related options (`migrations_paths`, `storage`, `services`, etc.) are only available when `doctrine/doctrine-migrations-bundle` is installed. Without it, a context is configured with just its name.

### DBAL only (no ORM, no Migrations)

```yaml
# config/packages/doctrine_context.yaml
doctrine_context:
    connections:
        default: ~
        shop: ~
        analytics: ~
```

### With DBAL connections and Migrations

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
            storage:
                table_storage:
                    table_name: doctrine_migration_versions
```

### With entity managers (ORM) and Migrations

```yaml
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

### With entity managers (ORM), no Migrations

```yaml
doctrine_context:
    entity_managers:
        default: ~
        shop: ~
        analytics: ~
```

### Requiring explicit context selection

Set `explicit_context: true` to prevent any wrapped command from fanning out over all contexts unless the caller deliberately opts in with `--ctx-all`. Every invocation must target a specific context or pass `--ctx-all` explicitly, making accidental mass operations impossible.

```yaml
doctrine_context:
    explicit_context: true
    entity_managers:
        default: ~
        shop: ~
        analytics: ~
```

With this option active, the following is an error:

```bash
# Error: Explicit context is required. Specify a context via --em, --ems, --conn, --conns, or use --ctx-all to run over all contexts.
php bin/console doctrine:migrations:migrate --no-interaction
```

Provide a context or opt into all:

```bash
# Single context
php bin/console doctrine:migrations:migrate --em=shop --no-interaction

# Subset of contexts
php bin/console doctrine:migrations:migrate --ems=shop,analytics --no-interaction

# All contexts, intentionally
php bin/console doctrine:migrations:migrate --ctx-all --no-interaction
```

### Full configuration reference

The migration-related keys below are only accepted when `doctrine/doctrine-migrations-bundle` is installed.

```yaml
doctrine_context:
    explicit_context: false     # when true, --em/--ems/--conn/--conns/--connection/--connections or --ctx-all is required
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

### Target a subset of contexts

Use the plural form of each option to pass multiple context names. Values can be supplied as separate arguments or as a comma-separated list - both forms are equivalent and may be combined:

```bash
# Separate arguments
php bin/console doctrine:migrations:migrate --ems=shop --ems=analytics --no-interaction

# Comma-separated (same result)
php bin/console doctrine:migrations:migrate --ems=shop,analytics --no-interaction

# DBAL connections
php bin/console doctrine:migrations:migrate --conns=shop,analytics --no-interaction

# ORM schema commands
php bin/console doctrine:schema:create --ems=shop,analytics
```

| Option         | Plural / multi-value form | Applicable commands             |
|----------------|---------------------------|---------------------------------|
| `--em`         | `--ems`                   | ORM and migration commands      |
| `--conn`       | `--conns`                 | Migration and database commands |
| `--connection` | `--connections`           | Database commands               |

### Continue past failures with `--ctx-isolation`

By default, a failure in one context stops execution when executed in non-interactive mode. Use `--ctx-isolation` to continue with the remaining contexts regardless:

```bash
php bin/console doctrine:migrations:migrate --no-interaction --ctx-isolation
```

### Run over all contexts explicitly with `--ctx-all`

`--ctx-all` behaves identically to omitting a specific context (fans out over every registered context), but makes the intent explicit. It is required when `explicit_context: true` is configured and no specific context is given:

```bash
php bin/console doctrine:migrations:migrate --ctx-all --no-interaction
php bin/console doctrine:database:create --ctx-all
```

### Create databases

```bash
# All contexts
php bin/console doctrine:database:create

# All contexts, explicitly
php bin/console doctrine:database:create --ctx-all

# Specific context – all four flags are equivalent
php bin/console doctrine:database:create --connection=shop
php bin/console doctrine:database:create --conn=shop

# Subset of contexts – all four flags are equivalent
php bin/console doctrine:database:create --connections=shop,analytics
php bin/console doctrine:database:create --conns=shop,analytics
```

### All supported commands

Always available (DBAL only):

| Command                      | Description                                         |
|------------------------------|-----------------------------------------------------|
| `doctrine:database:create`   | Create the database for each registered context     |

When `doctrine/doctrine-migrations-bundle` is installed:

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

When `doctrine/orm` is installed:

| Command                    | Description                                  |
|----------------------------|----------------------------------------------|
| `doctrine:schema:create`   | Create schema across all entity managers     |
| `doctrine:schema:validate` | Validate schema across all entity managers   |
| `doctrine:mapping:info`    | Show mapping info across all entity managers |

### Schema filter

When `doctrine/doctrine-migrations-bundle` is installed, the bundle automatically registers a DBAL schema filter per context that hides the migration metadata table from `doctrine:schema:update` and `doctrine:schema:validate`. This prevents those commands from flagging the migration table as an unmanaged or extra table.

The filter activates only during schema update/validate commands and is otherwise transparent.

## Acknowledgements

The idea behind this bundle is credited to [DoctrineMigrationsMultipleDatabaseBundle](https://github.com/AvaiBookSports/DoctrineMigrationsMultipleDatabaseBundle) which was heavily refactored to support Symfony 8 and some functional enhancements.
## License

This bundle is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.
