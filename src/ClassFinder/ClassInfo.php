<?php

declare(strict_types=1);

namespace Northrook\ClassFinder;

use ArgumentCountError;
use ReflectionAttribute;
use ReflectionClass;
use RuntimeException;
use Stringable;
use Throwable;
use InvalidArgumentException;

/**
 * Metadata and reflection helpers for a single discovered class.
 */
final class ClassInfo implements Stringable
{
    /** @var null|ReflectionClass<object> */
    private ?ReflectionClass $reflectionClass;

    /** @var ReflectionClass<object> Lazily initialized reflection for {@see ClassInfo::$className}. */
    public ReflectionClass $reflection {
        get {
            try {
                return $this->reflectionClass ??= new ReflectionClass( $this->className );
            }
            catch ( Throwable $e ) {
                throw new RuntimeException( 'ReflectionException: '.$e->getMessage() );
            }
        }
    }

    /**
     * @param class-string                 $className  fully qualified class name
     * @param string                       $basename   short class name without namespace
     * @param string                       $namespace  namespace without the short name; empty for global classes
     * @param string                       $file       absolute path to the defining `.php` file
     * @param null|ReflectionClass<object> $reflection optional pre-built reflection instance
     * @param bool                         $validate   when true, {@see exists()} must pass before construction
     */
    public function __construct(
        /** @var class-string */
        public string    $className,
        public string    $basename,
        public string    $namespace,
        public string    $file,
        ?ReflectionClass $reflection = null,
        bool             $validate = true,
    ) {
        $this->reflectionClass = $reflection;

        if ( $validate && ! ClassInfo::exists( $this->className ) ) {
            throw new InvalidArgumentException(
                'Class '.$this->className.' does not exist.',
            );
        }
    }

    /**
     * Build from a class name or object already known to the autoloader.
     *
     * @param class-string|object $class
     *
     * @return ClassInfo
     */
    public static function from( string|object $class ) : self
    {
        $className = \is_object( $class ) ? $class::class : $class;

        if ( ! \class_exists( $className ) ) {
            throw new RuntimeException( 'Class '.$className.' does not exist.' );
        }

        $exploded  = \explode( '\\', $className );
        $basename  = \array_pop( $exploded );
        $namespace = \implode( '\\', $exploded );

        $reflection = new ReflectionClass( $className );
        $file       = $reflection->getFileName();

        if ( ! $file ) {
            throw new InvalidArgumentException(
                'Unable to get file name for '.$className,
            );
        }

        return new ClassInfo( $className, $basename, $namespace, $file, $reflection );
    }

    /**
     * @return class-string
     */
    public function __toString() : string
    {
        return $this->className;
    }

    /**
     * Instantiate {@see $className} with the given constructor arguments.
     * @param mixed ...$arguments
     */
    public function __invoke( mixed ...$arguments ) : object
    {
        return new ( $this->className )( ...$arguments );
    }

    /**
     * Class-level attributes, optionally filtered by name or base attribute type.
     *
     * @template T of object
     *
     * @param null|class-string<T> $instanceOf
     *
     * @return ReflectionAttribute<T>[]
     */
    public function getAttributes(
        ?string $instanceOf = null,
    ) : array {
        return $this->reflection->getAttributes(
            $instanceOf,
            ReflectionAttribute::IS_INSTANCEOF,
        );
    }

    /**
     * Whether the class carries an attribute with the exact given class name.
     *
     * Unlike {@see getAttributes()}, this does not match attribute inheritance.
     *
     * @param class-string $instanceOf
     *
     * @return bool
     */
    public function hasAttribute(
        string $instanceOf,
    ) : bool {
        return ! empty( $this->reflection->getAttributes( $instanceOf ) );
    }

    /**
     * Return the sole matching attribute instance, or `null` when absent.
     *
     * @template T of object
     *
     * @param class-string<T> $attribute
     *
     * @return null|T
     *
     * @throws ArgumentCountError when more than one matching attribute is present
     */
    public function getAttribute(
        string $attribute,
    ) : ?object {
        $attributes = $this->getAttributes( $attribute );

        if ( empty( $attributes ) ) {
            return null;
        }

        if ( \count( $attributes ) !== 1 ) {
            throw new ArgumentCountError();
        }

        return $attributes[0]->newInstance();
    }

    /**
     * Short class name from an FQCN, object, or class-string.
     *
     * ```
     * ClassInfo::basename( \Northrook\Core\Env::class );
     * // => 'Env'
     * ```
     *
     * @param class-string|object $class
     * @param ?callable-string    $filter optional transform applied to the short name
     */
    public static function basename(
        string|object $class,
        ?string       $filter = null,
    ) : string {
        $className  = \is_object( $class ) ? $class::class : $class;
        $namespaced = \explode( '\\', $className );
        $basename   = \end( $namespaced );

        if ( \is_callable( $filter ) ) {
            return $filter( $basename );
        }

        return $basename;
    }

    /**
     * Whether a class is already loaded or can be loaded without error.
     *
     * @phpstan-assert-if-true class-string $className
     * @param class-string|string $className
     */
    public static function exists(
        string $className,
    ) : bool {
        if ( \class_exists( $className, false ) ) {
            return true;
        }

        try {
            return \class_exists( $className );

        }
        catch ( Throwable ) {
            return false;
        }
    }
}
