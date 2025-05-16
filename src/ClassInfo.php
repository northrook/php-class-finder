<?php

namespace Support;

use ReflectionClass;
use RuntimeException;
use Throwable;
use ArgumentCountError;
use ReflectionAttribute;
use Stringable;

final readonly class ClassInfo implements Stringable
{
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
            return new ReflectionClass( $this->className );
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

    /**
     * Retrieve a single {@see \Attribute}.
     *
     * @template T of object
     *
     * @param class-string|object $class
     * @param class-string<T>     $attribute
     *
     * @return null|T
     */
    public static function getAttribute(
        object|string $class,
        string        $attribute,
    ) : mixed {
        try {
            $reflector = new ReflectionClass( $class );
        }
        catch ( Throwable $e ) {
            throw new RuntimeException( 'ReflectionException: '.$e->getMessage() );
        }

        $attributes = $reflector->getAttributes( $attribute );

        if ( empty( $attributes ) ) {
            return null;
        }

        if ( \count( $attributes ) !== 1 ) {
            throw new ArgumentCountError();
        }

        return $attributes[0]->newInstance();
    }
}
