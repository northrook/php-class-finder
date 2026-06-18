<?php

declare(strict_types=1);

namespace Northrook;

use Northrook\ClassFinder\ClassScan;
use InvalidArgumentException;
use Stringable;

/**
 * Discovers PSR-4 classes under a project root directory.
 *
 * Configure the root path and namespace exclusions.
 *
 * Call {@see scanDirectories()} to produce a {@see ClassScan} result.
 */
final class ClassFinder
{
    /** @var string[] */
    private array $excludeNamespaces;

    /** The most recent result of {@see scanDirectories()}, if any. */
    public private(set) ?ClassScan $lastScan = null;

    /** Absolute, normalized path passed to the constructor. */
    public readonly string $rootDirectory;

    /**
     * @param string   $rootDirectory     absolute path to the project root
     * @param string[] $excludeNamespaces namespaces omitted from scan results
     */
    public function __construct(
        string $rootDirectory,
        array  $excludeNamespaces = ['Composer'],
    ) {
        $this->rootDirectory     = self::normalizePath( $rootDirectory );
        $this->excludeNamespaces = $excludeNamespaces;

        if ( ! \is_dir( $this->rootDirectory ) ) {
            throw new InvalidArgumentException(
                $this::class.": provided root directory '{$this->rootDirectory}' does not exist",
            );
        }
    }

    /**
     * Replace the list of namespaces excluded from scan results.
     *
     * - Matches the namespace itself or any class beneath it.
     * - Passing no arguments resets the list to `['Composer']`.
     */
    public function excludeNamespaces(
        string ...$namespaces,
    ) : self {

        $this->excludeNamespaces = empty( $namespaces )
        ? ['Composer']
        : $namespaces;

        return $this;
    }

    /**
     * Scan one or more directories relative to {@see $rootDirectory}.
     *
     * - Only `.php` files are considered; only the first `class` per file is read.
     * - A trailing `*` marks a directory for recursive scanning, e.g. `src/*`.
     * - Discovered classes must be loadable via the autoloader.
     */
    public function scanDirectories(
        string ...$directories,
    ) : ClassScan {
        return $this->lastScan = new ClassScan(
            \array_map(
                fn( $directory ) => self::normalizePath(
                    $this->rootDirectory,
                    $directory,
                ),
                $directories,
            ),
            $this->excludeNamespaces,
        );
    }

    /**
     * Collapse mixed directory separators and return an absolute-style path.
     *
     * @internal
     */
    public static function normalizePath(
        string|Stringable      $path,
        null|string|Stringable $append = null,
    ) : string {
        $separator  = DIRECTORY_SEPARATOR;
        $string     = $append ? "{$path}{$separator}{$append}" : "{$path}";
        $normalized = \str_replace( ['\\', '/'], DIRECTORY_SEPARATOR, $string );
        $fragments  = \array_filter(
            \explode( DIRECTORY_SEPARATOR, $normalized ),
            static fn( $value ) => $value !== '',
        );

        return DIRECTORY_SEPARATOR.\implode( DIRECTORY_SEPARATOR, $fragments );
    }
}
