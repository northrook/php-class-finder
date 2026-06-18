<?php

declare(strict_types=1);

namespace Northrook\Tests;

use ArgumentCountError;
use Fixture\App\AnnotatedService;
use Fixture\App\Attribute\RepeatableTag;
use Fixture\App\Attribute\Service;
use Fixture\App\DuplicateAttributeService;
use Fixture\App\InvokableService;
use Fixture\App\PlainService;
use InvalidArgumentException;
use Northrook\ClassFinder\ClassInfo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass( ClassInfo::class )]
final class ClassInfoTest extends TestCase
{
    public function testConstructorValidatesAutoloadedClassByDefault() : void
    {
        $this->expectException( InvalidArgumentException::class );
        $this->expectExceptionMessage( 'does not exist' );

        new ClassInfo(
            'Fixture\\Missing\\Service',
            'Service',
            'Fixture\\Missing',
            '/tmp/Service.php',
        );
    }

    public function testConstructorCanSkipValidation() : void
    {
        $info = new ClassInfo(
            'Fixture\\Missing\\Service',
            'Service',
            'Fixture\\Missing',
            '/tmp/Service.php',
            validate: false,
        );

        self::assertSame( 'Fixture\\Missing\\Service', $info->className );
    }

    public function testFromBuildsMetadataFromClassString() : void
    {
        $info = ClassInfo::from( PlainService::class );

        self::assertSame( PlainService::class, $info->className );
        self::assertSame( 'PlainService', $info->basename );
        self::assertSame( 'Fixture\\App', $info->namespace );
        self::assertStringEndsWith( 'PlainService.php', $info->file );
    }

    public function testFromBuildsMetadataFromObject() : void
    {
        $info = ClassInfo::from( new InvokableService( 'demo' ) );

        self::assertSame( InvokableService::class, $info->className );
    }

    public function testFromRejectsUnknownClass() : void
    {
        $this->expectException( RuntimeException::class );
        $this->expectExceptionMessage( 'does not exist' );

        ClassInfo::from( 'Fixture\\Missing\\Service' );
    }

    public function testToStringReturnsFqcn() : void
    {
        $info = ClassInfo::from( PlainService::class );

        self::assertSame( PlainService::class, (string) $info );
    }

    public function testInvokeInstantiatesClass() : void
    {
        $info = ClassInfo::from( InvokableService::class );

        $instance = $info( 'wired' );

        self::assertInstanceOf( InvokableService::class, $instance );
        self::assertSame( 'wired', $instance->label );
    }

    public function testHasAttributeUsesExactAttributeName() : void
    {
        $info = ClassInfo::from( AnnotatedService::class );

        self::assertTrue( $info->hasAttribute( Service::class ) );
    }

    public function testGetAttributesMatchesInheritedAttributeTypes() : void
    {
        $info = ClassInfo::from( AnnotatedService::class );

        self::assertNotEmpty( $info->getAttributes( Service::class ) );
    }

    public function testGetAttributeReturnsSingleInstance() : void
    {
        $info = ClassInfo::from( AnnotatedService::class );

        self::assertInstanceOf( Service::class, $info->getAttribute( Service::class ) );
    }

    public function testGetAttributeReturnsNullWhenMissing() : void
    {
        $info = ClassInfo::from( PlainService::class );

        self::assertNull( $info->getAttribute( Service::class ) );
    }

    public function testGetAttributeThrowsWhenMultipleMatches() : void
    {
        $info = ClassInfo::from( DuplicateAttributeService::class );

        $this->expectException( ArgumentCountError::class );

        $info->getAttribute( RepeatableTag::class );
    }

    public function testBasenameExtractsShortName() : void
    {
        self::assertSame(
            'PlainService',
            ClassInfo::basename( PlainService::class ),
        );
    }

    public function testBasenameAcceptsCallableFilter() : void
    {
        self::assertSame(
            'plainservice',
            ClassInfo::basename( PlainService::class, 'strtolower' ),
        );
    }

    public function testExistsReturnsTrueForAutoloadedClass() : void
    {
        self::assertTrue( ClassInfo::exists( PlainService::class ) );
    }

    public function testExistsReturnsFalseForMissingClass() : void
    {
        self::assertFalse( ClassInfo::exists( 'Fixture\\Missing\\Service' ) );
    }

    public function testReflectionIsLazyAndReusable() : void
    {
        $info = ClassInfo::from( PlainService::class );

        self::assertSame( PlainService::class, $info->reflection->getName() );
        self::assertSame( $info->reflection, $info->reflection );
    }
}
