<?php

declare(strict_types=1);

namespace Northrook\Tests;

use InvalidArgumentException;
use Northrook\ClassFinder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass( ClassFinder::class )]
final class ClassFinderTest extends TestCase
{
    use FixturePaths;

    public function testConstructorRejectsMissingRootDirectory() : void
    {
        $this->expectException( InvalidArgumentException::class );
        $this->expectExceptionMessage( "does not exist" );

        new ClassFinder( self::fixtureRoot().'/missing' );
    }

    public function testConstructorAcceptsExistingRootDirectory() : void
    {
        $finder = new ClassFinder( self::fixtureRoot() );

        self::assertSame(
            ClassFinder::normalizePath( self::fixtureRoot() ),
            $finder->rootDirectory,
        );
    }

    #[DataProvider( 'normalizePathProvider' )]
    public function testNormalizePath(
        string $expectedSuffix,
        string $path,
        ?string $append = null,
    ) : void {
        $normalized = ClassFinder::normalizePath( $path, $append );

        self::assertStringEndsWith( $expectedSuffix, $normalized );
        self::assertStringStartsWith( DIRECTORY_SEPARATOR, $normalized );
    }

    /**
     * @return iterable<string, array{string, string, ?string}>
     */
    public static function normalizePathProvider() : iterable
    {
        yield 'mixed separators' => ['foo/bar', '/tmp//foo\\bar', null];
        yield 'append segment' => ['project/src', '/tmp/project', 'src'];
        yield 'append with backslash' => ['root/nested', '/root', 'nested'];
    }

    public function testExcludeNamespacesResetsToComposerDefault() : void
    {
        $finder = new ClassFinder( self::fixtureRoot() );
        $finder->excludeNamespaces( 'Fixture\\CustomExclude' );

        $scan = $finder->excludeNamespaces()->scanDirectories( 'classes/*' );
        $names = \array_map(
            static fn( $info ) => $info->className,
            \array_values( $scan->getArray() ),
        );

        self::assertNotContains( 'Composer\\Fixture\\ExcludedStub', $names );
        self::assertContains( 'Fixture\\CustomExclude\\CustomExcluded', $names );
    }

    public function testExcludeNamespacesReplacesTheEntireList() : void
    {
        $finder = new ClassFinder( self::fixtureRoot() );
        $finder->excludeNamespaces( 'Fixture\\CustomExclude' );

        $scan = $finder->scanDirectories( 'classes/*' );
        $names = \array_map(
            static fn( $info ) => $info->className,
            \array_values( $scan->getArray() ),
        );

        self::assertContains( 'Composer\\Fixture\\ExcludedStub', $names );
        self::assertNotContains( 'Fixture\\CustomExclude\\CustomExcluded', $names );
    }

    public function testExcludeNamespacesAcceptsCustomNamespaces() : void
    {
        $finder = new ClassFinder( self::fixtureRoot() );
        $finder->excludeNamespaces( 'Fixture\\CustomExclude' );

        $scan = $finder->scanDirectories( 'classes/App/*' );
        $classNames = \array_map( 'strval', $scan->getArray() );

        self::assertNotContains( 'Fixture\\CustomExclude\\CustomExcluded', $classNames );
    }

    public function testScanDirectoriesStoresLastScan() : void
    {
        $finder = new ClassFinder( self::fixtureRoot() );
        $scan   = $finder->scanDirectories( 'classes/App/*' );

        self::assertSame( $scan, $finder->lastScan );
    }

    public function testScanDirectoriesResolvesPathsRelativeToRoot() : void
    {
        $finder = new ClassFinder( self::fixtureRoot() );
        $scan   = $finder->scanDirectories( 'classes/App' );

        $paths = \array_keys( $scan->getArray() );

        self::assertNotEmpty( $paths );
        self::assertTrue(
            \array_all(
                $paths,
                static fn( string $path ) => \str_starts_with(
                    $path,
                    ClassFinder::normalizePath( self::classesRoot(), 'App' ),
                ),
            ),
        );
    }
}
