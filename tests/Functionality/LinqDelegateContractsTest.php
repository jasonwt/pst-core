<?php

declare(strict_types=1);

namespace PST\Core\Tests\Functionality;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use PST\Core\Collections\Enumerator;
use PST\Core\Collections\Linq\Accumulator;
use PST\Core\Collections\Linq\Comparer;
use PST\Core\Collections\Linq\Predicate;
use PST\Core\Collections\Linq\Selector;
use PST\Core\Type;
use PST\Core\TypeHint;

final class LinqDelegateContractsTest extends TestCase {
    public function testSelectorDefaultsKeyTypeToKeyHint(): void {
        $selector = new Selector(
            static fn (int $item, int $key): string => $key . ':' . $item,
            TypeHint::string(),
            TypeHint::int()
        );

        $this->assertSame('int|string', $selector->TKey->fullName);
        $this->assertSame('int', $selector->TItem->fullName);
    }

    public function testSelectorRejectsNonKeyCompatibleKeyType(): void {
        $this->expectException(InvalidArgumentException::class);
        new Selector(
            static fn (int $item, bool $key): string => $item . ':' . (int) $key,
            TypeHint::string(),
            TypeHint::int(),
            TypeHint::bool()
        );
    }

    public function testPredicateOfInfersTypesAndSupportsInvocation(): void {
        $predicate = Predicate::of(static fn (int $item, int $key): bool => $item === $key);

        $this->assertSame('bool', $predicate->TReturn->fullName);
        $this->assertSame('int', $predicate->TItem->fullName);
        $this->assertSame('int', $predicate->TKey->fullName);
        $this->assertTrue($predicate(10, 10));
    }

    public function testPredicateOfRejectsNonBoolReturnType(): void {
        $this->expectException(InvalidArgumentException::class);
        Predicate::of(static fn (int $item, int $key): int => $item + $key);
    }

    public function testComparerDefaultsParameterTypesToUndefinedAndReturnTypeToInt(): void {
        $comparer = new Comparer(static fn (mixed $left, mixed $right): int => 0);

        $this->assertSame('int', $comparer->TReturn->fullName);
        $this->assertSame('undefined', $comparer->TParameters[0]->fullName);
        $this->assertSame('undefined', $comparer->TParameters[1]->fullName);
        $this->assertSame(0, $comparer('a', 'b'));
    }

    public function testAccumulatorWorksAsThreeParameterFunc(): void {
        $accumulator = new Accumulator(
            static fn (int $accumulated, int $item, int $index): int => $accumulated + $item + $index,
            TypeHint::int(),
            TypeHint::int(),
            TypeHint::int(),
            TypeHint::int()
        );

        $this->assertSame('int', $accumulator->TReturn->fullName);
        $this->assertSame('int', $accumulator->TAccumulated->fullName);
        $this->assertSame('int', $accumulator->TItem->fullName);
        $this->assertSame('int', $accumulator->TIndex?->fullName);
        $this->assertSame(6, $accumulator(1, 2, 3));
    }

    public function testAccumulatorDefaultsReturnTypeToAccumulatedStateAndAllowsTwoParameterForm(): void {
        $accumulator = new Accumulator(
            static fn (int $accumulated, int $item): int => $accumulated + $item,
            null,
            TypeHint::int(),
            TypeHint::int()
        );

        $this->assertSame('int', $accumulator->TReturn->fullName);
        $this->assertSame('int', $accumulator->TAccumulated->fullName);
        $this->assertSame('int', $accumulator->TItem->fullName);
        $this->assertNull($accumulator->TIndex);
        $this->assertSame(7, $accumulator(3, 4));
    }

    public function testAccumulatorOfInfersTypesAndSupportsInvocation(): void {
        $accumulator = Accumulator::of(
            static fn (int $accumulated, int $item, int $index): int => $accumulated + $item + $index
        );

        $this->assertSame('int', $accumulator->TReturn->fullName);
        $this->assertSame('int', $accumulator->TAccumulated->fullName);
        $this->assertSame('int', $accumulator->TItem->fullName);
        $this->assertSame('int', $accumulator->TIndex?->fullName);
        $this->assertSame(9, $accumulator(2, 3, 4));
    }

    public function testAccumulatorRejectsNonIntCompatibleIndexType(): void {
        $this->expectException(InvalidArgumentException::class);
        new Accumulator(
            static fn (int $accumulated, int $item, bool $index): int => $accumulated + $item + (int) $index,
            TypeHint::int(),
            TypeHint::int(),
            TypeHint::int(),
            TypeHint::bool()
        );
    }

    public function testEnumeratorRejectsNonKeyCompatibleKeyTypeHint(): void {
        $this->expectException(InvalidArgumentException::class);
        new Enumerator([10 => 1], TypeHint::int(), TypeHint::bool());
    }

    public function testEnumeratorRejectsNullValueTypeHint(): void {
        $this->expectException(InvalidArgumentException::class);
        new Enumerator([10 => 1], Type::typeOf('null'), TypeHint::int());
    }
}
