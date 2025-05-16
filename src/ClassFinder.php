<?php

declare(strict_types=1);

namespace Support;

use Countable;
use IteratorAggregate;
use Stringable;
use SplFileInfo;
use Traversable;
use ArrayIterator;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use InvalidArgumentException;

/**
 * @implements \IteratorAggregate<string, ClassInfo>
 */
final class ClassFinder implements Countable, IteratorAggregate
{
    /** @var array<string, ClassInfo> `[hashPath => ClassInfo]` */
    private array $found = [];

    /** @var array<string, bool> `[filePath => recursive]` */
    protected array $scan = [];

    /** @var array<class-string, string> `[className => basename]` */
    protected array $withAttributes = [];

    protected ?bool $requireAllAttributes = null;

    /**
     * @param string|string[]|Stringable|Stringable[] $directories
     *
     * @return static
     */
    public static function scan(
        string|Stringable|array $directories,
    ) : static {
        $finder = new self();

        foreach ( (array) $directories as $directory ) {
            $path = (string) $directory;
            $finder->inDirectory( $path, ! \str_ends_with( $path, '^' ) );
        }

        return $finder;
    }

    /**
     * @param string|Stringable $path
     * @param null|bool         $recursive
     *
     * @return $this
     */
    public function inDirectory(
        string|Stringable $path,
        ?bool             $recursive = null,
    ) : self {
        $path = (string) $path;
        $recursive ??= ! \str_ends_with( $path, '^' );
        $this->scan[$path] = $recursive;

        return $this;
    }

    /**
     * @param class-string[] $attribute
     * @param bool           $requireAll
     *
     * @return self
     */
    public function withAttribute(
        string|array $attribute,
        bool         $requireAll = false,
    ) : self {
        $this->requireAllAttributes ??= $requireAll;

        foreach ( (array) $attribute as $className ) {
            if ( \class_exists( $className ) ) {
                $this->withAttributes[$className] = $this::basename( $className );
            }
            else {
                throw new InvalidArgumentException( 'Attribute Class '.$className.' does not exist' );
            }
        }
        return $this;
    }

    /**
     * @return $this
     */
    public function scanFiles() : self
    {
        foreach ( $this->scan as $path => $recursive ) {
            $this->parseFiles( ...$this->scanDirectories( $path, $recursive ) );
        }
        return $this;
    }

    /**
     * @param SplFileInfo ...$files
     *
     * @return $this
     */
    public function parseFiles( SplFileInfo ...$files ) : self
    {
        foreach ( $files as $file ) {
            $this->parseDiscoveredFile( $file );
        }

        return $this;
    }

    /**
     * @return int
     */
    public function count() : int
    {
        return \count( $this->found );
    }

    /**
     * @return array<string, ClassInfo>
     */
    public function getArray() : array
    {
        return $this->scanFiles()->found;
    }

    /**
     * @return Traversable<string, ClassInfo>
     */
    public function getIterator() : Traversable
    {
        return new ArrayIterator( $this->scanFiles()->found );
    }

    private function parseDiscoveredFile( SplFileInfo $path ) : void
    {
        $filePath  = $this->normalPath( $path );
        $basename  = null;
        $namespace = null;

        $read = \fopen( $filePath, 'r' )
                ?: throw new RuntimeException( "Unable to open file {$filePath}" );

        while ( false !== ( $line = \fgets( $read ) ) ) {
            $this->normalizeLine( $line );

            if ( $namespace === null && \str_starts_with( $line, 'namespace ' ) ) {
                // Remove `namespace ` using offset 10, and trailing `;` with -1.
                $namespace = \trim( \substr( $line, 10, -1 ) );
            }

            if ( $this->lineContainsDefinition( $line, $basename ) ) {
                break;
            }
        }

        \fclose( $read );

        if ( ! $basename ) {
            return;
        }

        $className = $namespace ? $namespace.'\\'.$basename : $basename;
        $namespace ??= '';

        if ( ! \class_exists( $className ) ) {
            return;
        }

        $hashedPath = \hash( 'xxh64', $filePath );

        $this->found[$hashedPath] ??= new ClassInfo(
            $className,
            $basename,
            $namespace,
            $filePath,
        );
    }

    private function normalPath( SplFileInfo $from ) : string
    {
        return \strtr( $from->getPathname(), '\\', '/' );
    }

    private function normalizeLine( string &$line ) : void
    {
        $line = \trim(
            (string) \preg_replace(
                [
                    '/\s+/',    // Normalize repeated whitespace,
                    '/#\[\h*/', // Normalize #[ Attribute lines
                ],
                [' ', '#['],
                $line,
            ),
        );
    }

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
     * @param string|Stringable $path
     * @param bool              $recursive
     *
     * @return SplFileInfo[]
     */
    private function scanDirectories(
        string|Stringable $path,
        bool              $recursive = true,
    ) : array {
        $found = [];

        $directoryIterator = new RecursiveDirectoryIterator(
            (string) $path,
            FilesystemIterator::SKIP_DOTS,
        );

        if ( $recursive ) {
            $directoryIterator = new RecursiveIteratorIterator(
                $directoryIterator,
                RecursiveIteratorIterator::SELF_FIRST,
            );
        }

        foreach ( $directoryIterator as $item ) {
            \assert( $item instanceof SplFileInfo );

            if ( $item->getFilename()[0] === '.' ) {
                continue;
            }

            if ( $item->getExtension() === 'php' ) {
                $found[] = $item;
            }
        }

        return $found;
    }

    /**
     * # Get the class name of a provided class, or the calling class.
     *
     * - Will use the `debug_backtrace()` to get the calling class if no `$class` is provided.
     *
     * ```
     * $class = new \Northrook\Core\Env();
     * classBasename( $class );
     * // => 'Env'
     * ```
     *
     * @param class-string|object|string $class
     * @param ?callable-string           $filter {@see \strtolower} by default
     *
     * @return string
     */
    public static function basename( string|object $class, ?string $filter = 'strtolower' ) : string
    {
        $className  = \is_object( $class ) ? $class::class : $class;
        $namespaced = \explode( '\\', $className );
        $basename   = \end( $namespaced );

        if ( \is_callable( $filter ) ) {
            return $filter( $basename );
        }

        return $basename;
    }
}
