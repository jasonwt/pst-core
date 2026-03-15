<?php

declare(strict_types=1);

namespace PST\Core\Tests\Functionality;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use PST\Core\Collections\IDictionary;
use PST\Core\Collections\IReadOnlyDictionary;
use PST\Core\Collections\Enumerator;
use PST\Core\Collections\Traits\DictionaryTrait;
use PST\Core\Collections\Traits\ReadOnlyDictionaryTrait;
use PST\Core\Exceptions\KeyNotFoundException;
use PST\Core\Exceptions\NotSupportedException;
use PST\Core\TypeHint;

final class ReadOnlyDictionaryFixture implements IReadOnlyDictionary {
    use ReadOnlyDictionaryTrait;

    public function offsetSet(mixed $offset, mixed $value): void {
        throw new NotSupportedException("Read-only dictionary cannot be mutated.");
    }

    public function offsetUnset(mixed $offset): void {
        throw new NotSupportedException("Read-only dictionary cannot be mutated.");
    }
}

final class DictionaryFixture implements IDictionary {
    use DictionaryTrait;
}

final class DictionaryTraitsSmokeTest extends TestCase {
    public function testReadOnlyTraitInfersTypesAndPreservesItems(): void {
        $dictionary = new ReadOnlyDictionaryFixture([10 => 'a', 20 => 'b']);

        $this->assertSame('int|string', $dictionary->TKey->fullName);
        $this->assertSame('undefined', $dictionary->TValue->fullName);
        $this->assertSame([10 => 'a', 20 => 'b'], $dictionary->getEnumerator()->toArray());
    }

    public function testReadOnlyTraitAcceptsExplicitTypes(): void {
        $dictionary = new ReadOnlyDictionaryFixture([10 => 1, 20 => 2], TypeHint::int(), TypeHint::int());

        $this->assertSame('int', $dictionary->TKey->fullName);
        $this->assertSame('int', $dictionary->TValue->fullName);
        $this->assertSame(2, $dictionary->count);
    }

    public function testReadOnlyTraitRejectsIncompatibleKeyOrValueTypes(): void {
        $this->expectException(InvalidArgumentException::class);
        new ReadOnlyDictionaryFixture(['x' => 1], TypeHint::int(), TypeHint::int());
    }

    public function testOffsetGetAndUnsetThrowKeyNotFoundWithKeyPayload(): void {
        $dictionary = new DictionaryFixture([10 => 'a'], TypeHint::string(), TypeHint::int());

        try {
            $dictionary->offsetGet(20);
            $this->fail('Expected KeyNotFoundException for missing key.');
        } catch (KeyNotFoundException $exception) {
            $this->assertSame(20, $exception->key);
        }

        try {
            $dictionary->offsetUnset(30);
            $this->fail('Expected KeyNotFoundException for missing key.');
        } catch (KeyNotFoundException $exception) {
            $this->assertSame(30, $exception->key);
        }
    }

    public function testDictionaryTraitOffsetSetAddsAndUpdatesValues(): void {
        $dictionary = new DictionaryFixture([10 => 'a'], TypeHint::string(), TypeHint::int());
        $dictionary->allowOffsetInserts = true;

        $dictionary->offsetSet(20, 'b');
        $dictionary->offsetSet(10, 'z');

        $this->assertSame([10 => 'z', 20 => 'b'], $dictionary->getEnumerator()->toArray());
    }

    public function testDictionaryTraitOffsetSetValidatesKeyAndValueTypes(): void {
        $dictionary = new DictionaryFixture([10 => 'a'], TypeHint::string(), TypeHint::int());

        $this->expectException(InvalidArgumentException::class);
        $dictionary->offsetSet('not-int', 'x');
    }

    public function testTryGetValueBehavior(): void {
        $dictionary = new DictionaryFixture([10 => 'a'], TypeHint::string(), TypeHint::int());

        $value = null;
        $this->assertTrue($dictionary->tryGetValue(10, $value));
        $this->assertSame('a', $value);

        $this->assertFalse($dictionary->tryGetValue(20, $value));
        $this->assertNull($value);
    }

    public function testOffsetExistsAndContainsKeyReflectStoredKeys(): void {
        $dictionary = new ReadOnlyDictionaryFixture([10 => 'a', 20 => 'b'], TypeHint::string(), TypeHint::int());

        $this->assertTrue($dictionary->offsetExists(10));
        $this->assertFalse($dictionary->offsetExists(30));
        $this->assertTrue($dictionary->containsKey(20));
        $this->assertFalse($dictionary->containsKey(40));
    }

    public function testOffsetExistsRejectsIncompatibleKeyTypes(): void {
        $dictionary = new ReadOnlyDictionaryFixture([10 => 'a'], TypeHint::string(), TypeHint::int());

        $this->expectException(InvalidArgumentException::class);
        $dictionary->offsetExists('not-int');
    }

    public function testContainsValueUsesStrictComparisonAndExposesKeysAndValues(): void {
        $dictionary = new ReadOnlyDictionaryFixture([10 => '1', 20 => 1], TypeHint::mixed(), TypeHint::int());

        $this->assertSame([10, 20], $dictionary->keys);
        $this->assertSame(['1', 1], $dictionary->values);
        $this->assertTrue($dictionary->containsValue('1'));
        $this->assertTrue($dictionary->containsValue(1));
        $this->assertFalse($dictionary->containsValue(1.0));
        $this->assertFalse($dictionary->empty());
    }

    public function testStringKeyDictionaryAcceptsStringKeysAndRejectsIntKeys(): void {
        $dictionary = new DictionaryFixture(['a' => 1], TypeHint::int(), TypeHint::string());
        $dictionary->allowOffsetInserts = true;

        $dictionary->offsetSet('b', 2);
        $this->assertSame(['a' => 1, 'b' => 2], $dictionary->getEnumerator()->toArray());

        $this->expectException(InvalidArgumentException::class);
        $dictionary->offsetSet(10, 3);
    }

    public function testReadOnlyTraitRejectsInvalidKeyTypeHint(): void {
        $this->expectException(InvalidArgumentException::class);
        new ReadOnlyDictionaryFixture(['a' => 1], TypeHint::int(), TypeHint::bool());
    }

    public function testReadOnlyTraitCanConstructFromEnumerableWithCompatibleTypes(): void {
        $source = new Enumerator(['x' => 10, 'y' => 20], TypeHint::int(), TypeHint::string());
        $dictionary = new ReadOnlyDictionaryFixture($source, TypeHint::int(), TypeHint::string());

        $this->assertSame(['x' => 10, 'y' => 20], $dictionary->getEnumerator()->toArray());
        $this->assertSame('string', $dictionary->TKey->fullName);
        $this->assertSame('int', $dictionary->TValue->fullName);
    }

    public function testReadOnlyTraitRejectsEnumerableWithIncompatibleTypes(): void {
        $source = new Enumerator(['x' => 10], TypeHint::int(), TypeHint::string());

        $this->expectException(InvalidArgumentException::class);
        new ReadOnlyDictionaryFixture($source, TypeHint::int(), TypeHint::int());
    }

    public function testDictionaryTraitOffsetSetRejectsIncompatibleValueType(): void {
        $dictionary = new DictionaryFixture([10 => 'a'], TypeHint::string(), TypeHint::int());
        $dictionary->allowOffsetInserts = true;

        $this->expectException(InvalidArgumentException::class);
        $dictionary->offsetSet(20, 123);
    }

    public function testDictionaryEnumeratorRetainsDictionaryTypeInfo(): void {
        $dictionary = new DictionaryFixture(['a' => 1], TypeHint::int(), TypeHint::string());
        $enumerator = $dictionary->getEnumerator();

        $this->assertSame('string', $enumerator->TKey->fullName);
        $this->assertSame('int', $enumerator->TValue->fullName);
    }

    public function testEmptyDictionaryReportsEmptyKeysAndValues(): void {
        $dictionary = new ReadOnlyDictionaryFixture();

        $this->assertTrue($dictionary->empty());
        $this->assertSame([], $dictionary->keys);
        $this->assertSame([], $dictionary->values);
    }
}
