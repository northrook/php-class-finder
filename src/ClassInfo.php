<?php

namespace Support;

use ReflectionClass;
use RuntimeException;
use Throwable;
use ArgumentCountError;
use ReflectionAttribute;
use Stringable;
use ValueError;

final readonly class ClassInfo implements Stringable
{
    /** @var ReflectionClass<object> */
    private ReflectionClass $reflection;

    public bool $exists;

    /**
     * @param class-string $className `FQCN` `Namespace\Basename`
     * @param string       $basename  Name of the class without `namespace`
     * @param string       $namespace Namespace without `basename`
     * @param string       $file      Path to the `basename.php` file
     */
    public function __construct(
        public string $className,
        public string $basename,
        public string $namespace,
        public string $file,
    ) {
        $this->exists = \class_exists( $className, false );
    }

    public static function from( string|object $class ) : self
    {
        $className = \is_object( $class ) ? $class::class : $class;
        $exploded  = \explode( '\\', $className );
        $basename  = \array_pop( $exploded );
        $namespace = \implode( '\\', $exploded );

        \assert( \class_exists( $className ) );

        try {
            $file = ( new ReflectionClass( $className ) )->getFileName();
            if ( ! $file ) {
                throw new ValueError( 'Variable $file is empty.' );
            }
        }
        catch ( Throwable $exception ) {
            throw new RuntimeException( 'Unable to load class: '.$exception->getMessage() );
        }

        return new ClassInfo( $className, $basename, $namespace, $file );
    }

    /**
     * @return class-string
     */
    public function __toString() : string
    {
        return $this->className;
    }

    /**
     * Call the class with provided arguments
     *
     * @param mixed ...$arguments
     *
     * @return object
     */
    public function __invoke( mixed ...$arguments ) : object
    {
        return new ( $this->className )( ...$arguments );
    }

    /**
     * @return ReflectionClass<object>
     */
    public function reflect() : ReflectionClass
    {
        try {
            return $this->reflection ??= new ReflectionClass( $this->className );
        }
        catch ( Throwable $e ) {
            throw new RuntimeException( 'ReflectionException: '.$e->getMessage() );
        }
    }

    /**
     * @template T of object
     *
     * @param null|class-string<T> $instanceOf
     *
     * @return ReflectionAttribute<T>[]
     */
    public function getAttributes( ?string $instanceOf = null ) : array
    {
        return $this->reflect()->getAttributes(
            $instanceOf,
            ReflectionAttribute::IS_INSTANCEOF,
        );
    }

    public function hasAttribute( string $instanceOf ) : bool
    {
        return ! empty( $this->reflect()->getAttributes( $instanceOf ) );
    }

    /**
     * Retrieve a single {@see \Attribute}.
     *
     * @template T of object
     *
     * @param class-string<T> $attribute
     *
     * @return null|T
     */
    public function getAttribute(
        string $attribute,
    ) : mixed {
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
