<?php

declare(strict_types=1);

namespace Northrook\Tests;

use Fixture\App\AbstractWorker;
use Fixture\App\AnnotatedService;
use Fixture\App\Attribute\Service;
use Fixture\App\Attribute\Tag;
use Fixture\App\ChildAnnotatedService;
use Fixture\App\Deep\NestedService;
use Fixture\App\FinalService;
use Fixture\App\InvokableService;
use Fixture\App\MultilineAttributeService;
use Fixture\App\MultiTaggedService;
use Fixture\App\PlainService;
use Fixture\App\ReadonlyValue;
use Fixture\GlobalLegacy;
use InvalidArgumentException;
use Northrook\ClassFinder;
use Northrook\ClassFinder\ClassInfo;
use Northrook\ClassFinder\ClassScan;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( ClassScan::class )]
final class ClassScanTest extends TestCase
{
    use FixturePaths;

    private ClassFinder $finder;

    protected function setUp() : void
    {
        $this->finder = new ClassFinder( self::fixtureRoot() );
    }

    public function testConstructorRejectsInvalidDirectory() : void
    {
        $this->expectException( InvalidArgumentException::class );
        $this->expectExceptionMessage( 'is not a valid directory' );

        new ClassScan(
            [self::fixtureRoot().'/missing'],
            ['Composer'],
        );
    }

    public function testRecursiveScanFindsNestedClasses() : void
    {
        $scan = $this->finder->scanDirectories( 'classes/App/*' );
        $names = $this->classNames( $scan );

        self::assertContains( NestedService::class, $names );
        self::assertContains( PlainService::class, $names );
    }

    public function testNonRecursiveScanSkipsNestedDirectories() : void
    {
        $scan = $this->finder->scanDirectories( 'classes/App' );
        $names = $this->classNames( $scan );

        self::assertContains( PlainService::class, $names );
        self::assertNotContains( NestedService::class, $names );
    }

    public function testSkipsHiddenPhpFiles() : void
    {
        $scan = $this->finder->scanDirectories( 'classes/App/*' );
        $names = $this->classNames( $scan );

        self::assertNotContains( 'Fixture\\App\\HiddenService', $names );
    }

    public function testSkipsNonPhpFiles() : void
    {
        $scan = $this->finder->scanDirectories( 'classes/App/*' );

        self::assertArrayNotHasKey(
            ClassFinder::normalizePath( self::classesRoot(), 'App/readme.txt' ),
            $scan->getArray(),
        );
    }

    public function testSkipsFilesWithoutClassDefinition() : void
    {
        $scan = $this->finder->scanDirectories( 'classes/App/*' );

        self::assertArrayNotHasKey(
            ClassFinder::normalizePath( self::classesRoot(), 'App/helpers.php' ),
            $scan->getArray(),
        );
    }

    public function testSkipsInterfacesEnumsAndTraits() : void
    {
        $scan = $this->finder->scanDirectories( 'classes/App/*' );
        $names = $this->classNames( $scan );

        self::assertNotContains( 'Fixture\\App\\OnlyInterface', $names );
        self::assertNotContains( 'Fixture\\App\\OnlyEnum', $names );
        self::assertNotContains( 'Fixture\\App\\OnlyTrait', $names );
    }

    public function testSkipsUnreachableClassAfterEarlyReturn() : void
    {
        $scan = $this->finder->scanDirectories( 'classes/App/*' );
        $names = $this->classNames( $scan );

        self::assertNotContains( 'Fixture\\App\\UnreachableService', $names );
    }

    public function testReadsOnlyFirstClassPerFile() : void
    {
        $scan = $this->finder->scanDirectories( 'classes/App/*' );
        $names = $this->classNames( $scan );

        self::assertContains( 'Fixture\\App\\FirstClass', $names );
        self::assertNotContains( 'Fixture\\App\\SecondClass', $names );
    }

    public function testSkipsClassesThatAreNotAutoloadable() : void
    {
        $scan = $this->finder->scanDirectories( 'classes/App/*' );
        $names = $this->classNames( $scan );

        self::assertNotContains( 'Fixture\\Unregistered\\OrphanClass', $names );
    }

    public function testExcludesComposerNamespaceByDefault() : void
    {
        $scan = $this->finder->scanDirectories( 'classes/*' );
        $names = $this->classNames( $scan );

        self::assertNotContains( 'Composer\\Fixture\\ExcludedStub', $names );
    }

    public function testDiscoversVariousClassModifiers() : void
    {
        $scan = $this->finder->scanDirectories( 'classes/App/*' );
        $names = $this->classNames( $scan );

        self::assertContains( AbstractWorker::class, $names );
        self::assertContains( ReadonlyValue::class, $names );
        self::assertContains( FinalService::class, $names );
    }

    public function testDiscoversGlobalNamespaceClass() : void
    {
        $scan = $this->finder->scanDirectories( 'classes' );
        $names = $this->classNames( $scan );

        self::assertContains( GlobalLegacy::class, $names );
    }

    public function testParsesMultilineAttributes() : void
    {
        $scan = $this->finder->scanDirectories( 'classes/App/*' );
        $names = $this->classNames( $scan );

        self::assertContains( MultilineAttributeService::class, $names );

        $info = $this->infoFor( $scan, MultilineAttributeService::class );
        self::assertTrue( $info->hasAttribute( Service::class ) );
    }

    public function testAnyAttributeReturnsClassesWithAtLeastOneMatch() : void
    {
        $scan = $this->finder->scanDirectories( 'classes/App/*' );

        $matches = $scan->anyAttribute( Service::class, Tag::class );
        $names   = \array_map( 'strval', $matches );

        self::assertContains( AnnotatedService::class, $names );
        self::assertContains( MultiTaggedService::class, $names );
        self::assertNotContains( PlainService::class, $names );
    }

    public function testWithAttributesRequiresEveryAttribute() : void
    {
        $scan = $this->finder->scanDirectories( 'classes/App/*' );

        $both = $scan->withAttributes( Service::class, Tag::class );
        $names = \array_map( 'strval', $both );

        self::assertSame( [MultiTaggedService::class], $names );
    }

    public function testGetAttributesMatchesAttributeInheritance() : void
    {
        $scan = $this->finder->scanDirectories( 'classes/App/*' );
        $info = $this->infoFor( $scan, ChildAnnotatedService::class );

        self::assertNotEmpty( $info->getAttributes( Service::class ) );
        self::assertFalse( $info->hasAttribute( Service::class ) );
    }

    public function testContainerStyleAutodiscoveryWorkflow() : void
    {
        $finder = new ClassFinder( self::fixtureRoot() );
        $services = $finder
            ->excludeNamespaces( 'Fixture\\CustomExclude' )
            ->scanDirectories( 'classes/App/*' )
            ->anyAttribute( Service::class );

        $names = \array_map( 'strval', $services );

        self::assertContains( AnnotatedService::class, $names );
        self::assertContains( MultilineAttributeService::class, $names );
        self::assertContains( MultiTaggedService::class, $names );
        self::assertNotContains( PlainService::class, $names );
        self::assertNotContains( InvokableService::class, $names );
    }

    public function testCountAndIterationExposeDiscoveredClasses() : void
    {
        $scan = $this->finder->scanDirectories( 'classes/App/*' );

        self::assertGreaterThan( 0, $scan->count() );
        self::assertCount( $scan->count(), \iterator_to_array( $scan ) );
        self::assertSame( $scan->count(), \count( $scan->getArray() ) );

        foreach ( $scan as $path => $info ) {
            self::assertIsString( $path );
            self::assertInstanceOf( ClassInfo::class, $info );
            self::assertSame( $path, $info->file );
        }
    }

    /**
     * @return list<class-string>
     */
    private function classNames( ClassScan $scan ) : array
    {
        return \array_map(
            static fn( ClassInfo $info ) => $info->className,
            \array_values( $scan->getArray() ),
        );
    }

    private function infoFor( ClassScan $scan, string $className ) : ClassInfo
    {
        foreach ( $scan->getArray() as $info ) {
            if ( $info->className === $className ) {
                return $info;
            }
        }

        self::fail( 'Class not found in scan: '.$className );
    }
}
