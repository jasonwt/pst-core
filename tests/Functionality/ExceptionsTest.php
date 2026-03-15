<?php

declare(strict_types=1);

namespace PST\Core\Tests\Functionality;

use PHPUnit\Framework\TestCase;
use PST\Core\Exceptions\ArgumentException;
use PST\Core\Exceptions\ArgumentNullException;
use PST\Core\Exceptions\ArgumentOutOfRangeException;
use PST\Core\Exceptions\Exception;
use PST\Core\Exceptions\InvalidOperationException;
use PST\Core\Exceptions\NotImplementedException;
use PST\Core\Exceptions\NotSupportedException;
use PST\Core\Exceptions\ObjectDisposedException;
use PST\Core\Type;

final class ExceptionsTest extends TestCase {
    public function testBaseExceptionParticipatesInObjectSystem(): void {
        $exception = new Exception('boom');

        $this->assertInstanceOf(\Throwable::class, $exception);
        $this->assertSame('boom', $exception->getMessage());
        $this->assertStringContainsString('boom', (string)$exception);
        $this->assertSame(Exception::class, $exception->getType()->fullName);
    }

    public function testArgumentExceptionTracksParameterName(): void {
        $exception = new ArgumentException('Invalid value.', 'count');

        $this->assertSame('count', $exception->paramName);
        $this->assertStringContainsString("Parameter 'count'", $exception->getMessage());
    }

    public function testArgumentNullExceptionDefaultsAndInheritance(): void {
        $exception = new ArgumentNullException('payload');

        $this->assertInstanceOf(ArgumentException::class, $exception);
        $this->assertSame('payload', $exception->paramName);
        $this->assertStringContainsString('Value cannot be null.', $exception->getMessage());
    }

    public function testArgumentOutOfRangeExceptionCarriesActualValue(): void {
        $exception = new ArgumentOutOfRangeException('index', 99);

        $this->assertSame('index', $exception->paramName);
        $this->assertSame(99, $exception->actualValue);
        $this->assertStringContainsString('out of the range of valid values', $exception->getMessage());
    }

    public function testInvalidOperationFamilyDefaultsAndInheritance(): void {
        $invalidOperation = new InvalidOperationException();
        $notImplemented = new NotImplementedException();
        $notSupported = new NotSupportedException();
        $disposed = new ObjectDisposedException('Stream');

        $this->assertInstanceOf(Exception::class, $invalidOperation);
        $this->assertInstanceOf(Exception::class, $notImplemented);
        $this->assertInstanceOf(Exception::class, $notSupported);
        $this->assertInstanceOf(InvalidOperationException::class, $disposed);
        $this->assertSame('Stream', $disposed->objectName);
        $this->assertStringContainsString('Cannot access a disposed object.', $disposed->getMessage());
        $this->assertStringContainsString("Object name: 'Stream'", $disposed->getMessage());
    }

    public function testAllNewExceptionsResolveAsTypes(): void {
        $exceptions = [
            new Exception(),
            new ArgumentException(),
            new ArgumentNullException(),
            new ArgumentOutOfRangeException(),
            new InvalidOperationException(),
            new NotImplementedException(),
            new NotSupportedException(),
            new ObjectDisposedException(),
        ];

        foreach ($exceptions as $exception) {
            $resolvedType = Type::typeOf($exception::class);

            $this->assertSame($exception::class, $exception->getType()->fullName);
            $this->assertSame($resolvedType?->fullName, $exception->getType()->fullName);
        }
    }
}
