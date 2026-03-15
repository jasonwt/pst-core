<?php

declare(strict_types=1);

namespace PST\Core\Tests\Functionality;

use PHPUnit\Framework\TestCase;
use PST\Core\Func;
use PST\Core\Exceptions\ArgumentException;
use PST\Core\Exceptions\ArgumentOutOfRangeException;
use PST\Core\Exceptions\InvalidOperationException;
use PST\Core\TypeHint;

class FuncFixtureBase {}

class FuncFixtureChild extends FuncFixtureBase {}

class FuncFixtureGrandChild extends FuncFixtureChild {}

class InvokableFuncFixture {
    public function __invoke(): string {
        return 'invoked';
    }
}

final class FuncTest extends TestCase {
    public function testInvokeExecutesWrappedClosureAndReturnsValue(): void {
        $func = new Func(
            static fn (int $left, int $right): int => $left + $right,
            TypeHint::int(),
            TypeHint::int(),
            TypeHint::int()
        );

        $result = $func(10, 7);

        $this->assertSame(17, $result);
    }

    public function testConstructorStoresReturnTypeAndParameterTypes(): void {
        $func = new Func(
            static fn (string $value): string => strtoupper($value),
            TypeHint::string(),
            TypeHint::string()
        );

        $this->assertSame('string', $func->TReturn->fullName);
        $this->assertCount(1, $func->TParameters);
        $this->assertSame('string', $func->TParameters[0]->fullName);
    }

    public function testInvokeRejectsParameterCountMismatch(): void {
        $func = new Func(
            static fn (mixed $value): mixed => $value,
            TypeHint::mixed(),
            TypeHint::string()
        );

        $this->expectException(ArgumentOutOfRangeException::class);
        $func();
    }

    public function testInvokeRejectsParameterTypeMismatchUsingTypeHintAssignability(): void {
        $func = new Func(
            static fn (mixed $value): mixed => $value,
            TypeHint::mixed(),
            TypeHint::int()
        );

        $this->expectException(ArgumentException::class);
        $func('not-an-int');
    }

    public function testInvokeRejectsReturnTypeMismatchUsingTypeHintAssignability(): void {
        $func = new Func(
            static fn (mixed $value): mixed => 'wrong-return-type',
            TypeHint::int(),
            TypeHint::mixed()
        );

        $this->expectException(InvalidOperationException::class);
        $func(123);
    }

    public function testInvokeAllowsCovariantReturnTypeFromConcreteTypeHints(): void {
        $func = new Func(
            static fn (): mixed => new FuncFixtureChild(),
            TypeHint::{'class'}(FuncFixtureBase::class)
        );

        $result = $func();

        $this->assertInstanceOf(FuncFixtureChild::class, $result);
    }

    public function testInvokeSupportsUnionParameterTypes(): void {
        $func = new Func(
            static fn (mixed $value): mixed => $value,
            TypeHint::mixed(),
            TypeHint::union(TypeHint::int(), TypeHint::string())
        );

        $this->assertSame(42, $func(42));
        $this->assertSame('forty-two', $func('forty-two'));
    }

    public function testInvokeSupportsCallableParametersForInvokableTypes(): void {
        $func = new Func(
            static fn (mixed $value): mixed => $value,
            TypeHint::mixed(),
            TypeHint::{'callable'}()
        );

        $closure = static fn (): string => 'closure';
        $invokableObject = new InvokableFuncFixture();

        $this->assertSame($closure, $func($closure));
        $this->assertSame($invokableObject, $func($invokableObject));
    }

    public function testInvokeRejectsStringCallableValuesForCallableParameters(): void {
        $func = new Func(
            static fn (mixed $value): mixed => $value,
            TypeHint::mixed(),
            TypeHint::{'callable'}()
        );

        $this->expectException(ArgumentException::class);
        $func('strlen');
    }

    public function testIsAssignableToAndFromUseDelegateVarianceRules(): void {
        $source = new Func(
            static fn (FuncFixtureBase $value): FuncFixtureChild => new FuncFixtureChild(),
            TypeHint::{'class'}(FuncFixtureChild::class),
            TypeHint::{'class'}(FuncFixtureBase::class)
        );

        $target = new Func(
            static fn (FuncFixtureChild $value): FuncFixtureBase => new FuncFixtureBase(),
            TypeHint::{'class'}(FuncFixtureBase::class),
            TypeHint::{'class'}(FuncFixtureChild::class)
        );

        $this->assertTrue($source->isAssignableTo($target->TReturn, ...$target->TParameters));
        $this->assertTrue($target->isAssignableFrom($source->TReturn, ...$source->TParameters));

        $this->assertFalse(
            $source->isAssignableTo(
                TypeHint::{'class'}(FuncFixtureGrandChild::class),
                TypeHint::{'class'}(FuncFixtureChild::class)
            )
        );
    }

    public function testConstructorInfersMixedReturnTypeWhenReturnHintIsNull(): void {
        $func = new Func(
            static fn (int $value): mixed => $value,
            null,
            TypeHint::int()
        );

        $this->assertSame('mixed', $func->TReturn->fullName);
    }

    public function testOfInfersCallableTypeHintsFromReflection(): void {
        $func = Func::of(static fn (callable $handler): callable => $handler);

        $this->assertSame('callable', $func->TReturn->fullName);
        $this->assertCount(1, $func->TParameters);
        $this->assertSame('callable', $func->TParameters[0]->fullName);
    }
}
