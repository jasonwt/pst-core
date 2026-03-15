<?php

declare(strict_types=1);

namespace PST\Core\Tests\Functionality;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use PST\Core\Collections\Enumerator;
use PST\Core\Collections\Linq\Traits\Support\OrderedEnumerable;
use PST\Core\Collections\Linq\Predicate;
use PST\Core\Collections\Linq\Selector;
use PST\Core\TypeHint;

final class EnumerableLinqSmokeTest extends TestCase {
    public function testEnumeratorExposesProvidedKeyAndValueTypes(): void {
        $enumerator = new Enumerator([10 => 1, 20 => 2], TypeHint::int(), TypeHint::int());

        $this->assertSame('int', $enumerator->TValue->fullName);
        $this->assertSame('int', $enumerator->TKey->fullName);
        $this->assertSame([10 => 1, 20 => 2], iterator_to_array($enumerator));
    }

    public function testWherePreservesKeysAndSourceTypes(): void {
        $source = new Enumerator([10 => 1, 20 => 2, 30 => 3], TypeHint::int(), TypeHint::int());

        $result = $source->where(static fn (int $value, int $key): bool => $value >= 2 && $key >= 20);

        $this->assertSame([20 => 2, 30 => 3], $result->toArray());
        $this->assertSame('int', $result->TValue->fullName);
        $this->assertSame('int', $result->TKey->fullName);
    }

    public function testSelectChangesValueTypeAndPreservesKeyType(): void {
        $source = new Enumerator([10 => 1, 20 => 2], TypeHint::int(), TypeHint::int());
        $selector = new Selector(
            static fn (int $value, int $key): string => $key . ':' . $value,
            TypeHint::string(),
            TypeHint::int(),
            TypeHint::int()
        );

        $result = $source->select($selector);

        $this->assertSame([10 => '10:1', 20 => '20:2'], $result->toArray());
        $this->assertSame('string', $result->TValue->fullName);
        $this->assertSame('int', $result->TKey->fullName);
    }

    public function testWhereRejectsPredicateWithIncompatibleKeyType(): void {
        $source = new Enumerator([10 => 1, 20 => 2], TypeHint::int(), TypeHint::int());
        $predicate = new Predicate(
            static fn (int $value, string $key): bool => $value > 0 && $key !== '',
            TypeHint::int(),
            TypeHint::string()
        );

        $this->expectException(InvalidArgumentException::class);
        $source->where($predicate);
    }

    public function testOrderBySortsByProjectionAndPreservesOriginalKeys(): void {
        $source = new Enumerator([10 => 3, 20 => 1, 30 => 2], TypeHint::int(), TypeHint::int());

        $ordered = $source->orderBy(static fn (int $value, int $key): int => $value);

        $this->assertInstanceOf(OrderedEnumerable::class, $ordered);
        $this->assertSame([20 => 1, 30 => 2, 10 => 3], $ordered->toArray());
        $this->assertSame('int', $ordered->TValue->fullName);
        $this->assertSame('int', $ordered->TKey->fullName);
    }

    public function testOrderByRejectsSelectorWithIncompatibleKeyType(): void {
        $source = new Enumerator([10 => 3, 20 => 1], TypeHint::int(), TypeHint::int());
        $selector = new Selector(
            static fn (int $value, string $key): int => $value,
            TypeHint::int(),
            TypeHint::int(),
            TypeHint::string()
        );

        $this->expectException(InvalidArgumentException::class);
        $source->orderBy($selector);
    }
}
