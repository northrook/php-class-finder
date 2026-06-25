<?php

declare(strict_types=1);

namespace Northrook\ClassFinder;

use Countable;
use IteratorAggregate;
use InvalidArgumentException;
use Northrook\ClassFinder;
use RuntimeException;
use Traversable;
use ArrayIterator;
use FilesystemIterator;
use Iterator;
use RecursiveDirectoryIterator;
use SplFileInfo;
use RecursiveIteratorIterator;

/**
 * Immutable scan result produced by {@see ClassFinder::scanDirectories()}.
 *
 * Keys are absolute file paths; values are the {@see ClassInfo} parsed from each file.
 *
 * @implements IteratorAggregate<string, ClassInfo>
 */
final readonly class ClassScan implements Countable, IteratorAggregate
{
    /** @var string[] */
    private array $excludeNamespaces;

    /** @var array<string, bool> `[filePath => recursive]` */
    private array $directories;

    /** @var array<string, ClassInfo> `[filePath => ClassInfo]` */
    private array $foundClasses;

    /**
     * Scan immediately; prefer {@see ClassFinder::scanDirectories()} for path resolution.
     *
     * @param string[] $scanDirectories   absolute directory paths; a trailing `*` enables recursion
     * @param string[] $excludeNamespaces namespaces omitted from results
     */
    public function __construct(
        array $scanDirectories,
        array $excludeNamespaces,
    ) {
        $this->directories       = $this->resolveScanDirectories( $scanDirectories );
        $this->excludeNamespaces = $excludeNamespaces;

        $this->foundClasses = $this->scanFiles();
    }

    /**
     * Classes that declare at least one of the given attributes.
     *
     * @param class-string ...$className
     *
     * @return ClassInfo[]
     */
    public function anyAttribute(
        string ...$className,
    ) : array {
        return \array_values( \array_filter(
            $this->foundClasses,
            static fn( ClassInfo $classInfo ) => \array_any(
                $className,
                fn( $attribute ) => $classInfo->hasAttribute( $attribute ),
            ),
        ) );
    }

    /**
     * Classes that declare every given attribute.
     *
     * @param class-string ...$className
     *
     * @return ClassInfo[]
     */
    public function withAttributes(
        string ...$className,
    ) : array {
        return \array_values( \array_filter(
            $this->foundClasses,
            static fn( ClassInfo $classInfo ) => \array_all(
                $className,
                fn( $attribute ) => $classInfo->hasAttribute( $attribute ),
            ),
        ) );
    }

    /**
     * Number of discovered classes.
     */
    public function count() : int
    {
        return \count( $this->foundClasses );
    }

    /**
     * @return array<string, ClassInfo> `[filePath => ClassInfo]`
     */
    public function getArray() : array
    {
        return $this->foundClasses;
    }

    /**
     * @return ArrayIterator<string, ClassInfo>
     */
    public function getIterator() : Traversable
    {
        return new ArrayIterator( $this->foundClasses );
    }

    /**
     * @return array<string, ClassInfo>
     */
    private function scanFiles() : array
    {
        $found = [];

        foreach ( $this->directories as $path => $recursive ) {
            foreach ( $this->directoryIterator(
                $path,
                $recursive,
            ) as $splFileInfo ) {

                $filePath = $splFileInfo->getPathname();

                // Skip hidden files
                if ( $splFileInfo->getFilename()[0] === '.' ) {
                    continue;
                }

                // Only PHP files
                if ( $splFileInfo->getExtension() !== 'php' ) {
                    continue;
                }

                $classInfo = $this->parseFile( $filePath );

                if ( $classInfo ) {
                    $found[$filePath] = $classInfo;
                }
            }
        }

        return $found;
    }

    private function parseFile(
        string $filePath,
    ) : ?ClassInfo {
        $filePath  = ClassFinder::normalizePath( $filePath );
        $basename  = null;
        $namespace = null;
        $read      = \fopen( $filePath, 'r' );

        if ( $read === false ) {
            throw new RuntimeException(
                "Unable to open file {$filePath}",
            );
        }

        while ( false !== ( $line = \fgets( $read ) ) ) {

            $this->normalizeLine( $line );

            if ( $namespace === null && \str_starts_with( $line, 'namespace ' ) ) {
                // Remove `namespace ` using offset 10, and trailing `;` with -1.
                $namespace = \trim( \substr( $line, 10, -1 ) );
            }

            if ( $this->lineContainsDefinition(
                $line,
                $basename,
            ) ) {
                break;
            }
        }

        \fclose( $read );

        if ( ! $basename ) {
            return null;
        }

        $className = $namespace ? $namespace.'\\'.$basename : $basename;
        $namespace ??= '';

        if ( $this->namespaceExcluded( $className ) ) {
            return null;
        }

        if ( ! ClassInfo::exists( $className ) ) {
            return null;
        }

        return new ClassInfo(
            $className,
            $basename,
            $namespace,
            $filePath,
            validate: false,
        );
    }

    private function namespaceExcluded(
        string $className,
    ) : bool {
        return \array_any(
            $this->excludeNamespaces,
            static fn( $exclude ) => \str_starts_with( $className, $exclude.'\\' ) || $className === $exclude,
        );
    }

    /**
     * @internal
     *
     * @param string $line
     */
    private function normalizeLine(
        string & $line,
    ) : void {
        $line = \trim(
            (string) \preg_replace(
                [
                    '/\s+/',    // Normalize repeated whitespace,
                    '/#\[\h*/', // Normalize #[ Attribute lines
                    '{}',       // Remove braces
                ],
                [' ', '#[', ''],
                $line,
            ),
        );
    }

    /**
     * @param string      $line
     * @param null|string $className
     *
     * @return bool
     */
    private function lineContainsDefinition(
        string  $line,
        ?string & $className,
    ) : bool {
        $opensWith = \strstr( $line, ' ', true );

        // Skip invalid lines
        if ( $opensWith === false ) {
            return false;
        }
        // Break early
        if ( \in_array( $opensWith, ['return', 'exit', 'die'] ) ) {
            return true;
        }

        if ( ! \str_contains( $line, 'class ' ) ) {
            return false;
        }

        foreach ( [
            'final class ',
            'final readonly class ',
            'abstract class ',
            'abstract readonly class ',
            'readonly class ',
            'class ',
        ] as $type ) {
            if ( \str_starts_with( $line, $type ) ) {
                $classString = \substr( $line, \strlen( $type ) );

                // Update &$className by reference
                $className = \strstr( $classString, ' ', true ) ?: $classString;

                return true;
            }
        }

        return false;
    }

    /**
     * @param string[] $scanDirectories
     *
     * @return array<string, bool>
     */
    private function resolveScanDirectories( array $scanDirectories ) : array
    {
        $directories = [];

        foreach ( $scanDirectories as $path ) {
            $recursive = \str_ends_with( $path, '*' );
            $directory = $recursive ? \substr( $path, 0, -1 ) : $path;

            if ( ! \is_dir( $directory ) ) {
                throw new InvalidArgumentException(
                    $directory.' is not a valid directory',
                );
            }

            $directories[$directory] = $recursive;
        }

        return $directories;
    }

    /**
     * @internal
     *
     * @param string $path
     * @param bool   $recursive
     *
     * @return Iterator<int, SplFileInfo>
     */
    private function directoryIterator(
        string $path,
        bool   $recursive = true,
    ) : Iterator {

        $directoryIterator = new RecursiveDirectoryIterator(
            $path,
            FilesystemIterator::SKIP_DOTS,
        );

        return $recursive
                ? new RecursiveIteratorIterator(
                    $directoryIterator,
                    RecursiveIteratorIterator::SELF_FIRST,
                )
                : $directoryIterator;
    }
}
