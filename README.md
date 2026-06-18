# PHP Class Finder

Discover autoloadable PHP classes from directories.

- Requires PHP 8.4+
- No runtime dependencies

## Installation

```bash
composer require northrook/php-class-finder
```

## Quick start

Point the finder at your project root, scan one or more directories, then filter by attribute:

```php
use Northrook\ClassFinder;

$finder = new ClassFinder(__DIR__);

$services = $finder
    ->scanDirectories('src/*')
    ->anyAttribute(App\Attribute\Service::class);

foreach ($services as $classInfo) {
    $instance = $classInfo(); // instantiate via __invoke
}
```

`ClassFinder` resolves paths relative to the root directory.

Append `*` for recursive scanning (`src/*`); omit it to scan only the immediate directory (`src`).

## How it works

Walks `.php` files, parses for `class` declaration, only accpting autoloadable classes.

Results are keyed by the absolute file path.

This is lightweight: no full AST parsing, no `require` of scanned files.

Intended for cold-path operations, like bootstrapping DI containers, route discovery, etc.

### What gets included

- `class`, `abstract class`, `final class`, and `readonly` variants
- Classes in the global namespace
- Classes with attributes on preceding lines (including multiline `#[...]`)

### What gets skipped

- Hidden files, prefixed with `.`
- Non-`.php` files
- Interfaces, enums, and traits
- Files with no class definition
- A second `class` in the same file (only the first is read, as per PSR-4)
- Classes that are not autoloadable
- Code after an early `return`, `exit`, or `die`
- Namespaces listed in `excludeNamespaces()` (defaults to `Composer`)

## API

### `ClassFinder`

Entry point. Configure the root path and namespace exclusions, then scan.

```php
$finder = new ClassFinder('/path/to/project');
$finder->excludeNamespaces('Vendor\\Generated', 'Tests');

$scan = $finder->scanDirectories('src/*', 'config');
```

| Member                   | Description                                                        |
|--------------------------|--------------------------------------------------------------------|
| `$rootDirectory`         | Normalized absolute path passed to the constructor                 |
| `$lastScan`              | Most recent `ClassScan`, or `null`                                 |
| `excludeNamespaces(...)` | Replace excluded namespaces; no arguments resets to `['Composer']` |
| `scanDirectories(...)`   | Scan directories relative to the root; returns `ClassScan`         |
| `normalizePath()`        | Collapse mixed separators into an absolute-style path              |

### `ClassScan`

Immutable scan result. Iterable and countable; values are `ClassInfo` instances.

```php
$scan = $finder->scanDirectories('src/*');

$scan->count();
$scan->getArray();                              // [filePath => ClassInfo]
$scan->anyAttribute(Foo::class, Bar::class);    // classes with at least one match
$scan->withAttributes(Foo::class, Bar::class);  // classes with every attribute
```

`anyAttribute()` and `withAttributes()` use exact attribute class names.

For inheritance-aware matching, use `ClassInfo::getAttributes()` on individual results.

### `ClassInfo`

Metadata and reflection helpers for a single class.

```php
use Northrook\ClassFinder\ClassInfo;

$info = ClassInfo::from(SomeService::class);

$info->className;   // FQCN
$info->basename;    // short name
$info->namespace;   // namespace without short name
$info->file;        // absolute path to the defining file
$info->reflection;  // lazy ReflectionClass

$info->hasAttribute(Service::class);   // exact match
$info->getAttributes(Service::class);  // includes subclasses (IS_INSTANCEOF)
$info->getAttribute(Service::class);   // single instance, or null
$info( ...$args );                     // instantiate the class

ClassInfo::basename($class, 'strtolower');
ClassInfo::exists('Some\\Class');
```

## Development

```bash
composer test
composer phpstan
```

## License

BSD-3-Clause. See [LICENSE.md](LICENSE.md).
