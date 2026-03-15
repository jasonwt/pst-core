<?php

declare(strict_types=1);

namespace PST\Core\Tests\Functionality;

use PHPUnit\Framework\TestCase;
use PST\Core\Collections\Dictionary;
use PST\Core\Collections\Enumerator;
use PST\Core\Collections\ReadOnlyDictionary;
use PST\Core\Exceptions\InvalidOperationException;
use PST\Core\Exceptions\KeyNotFoundException;
use PST\Core\TypeHint;

final class DictionaryClassesIntegrationTest extends TestCase {
    public function testReadOnlyDictionaryRejectsMutations(): void {
        $dictionary = new ReadOnlyDictionary([10 => 'a'], TypeHint::string(), TypeHint::int());

        try {
            $dictionary->offsetSet(10, 'b');
            $this->fail('Expected InvalidOperationException for offsetSet.');
        } catch (InvalidOperationException) {
        }

        $this->expectException(InvalidOperationException::class);
        $dictionary->offsetUnset(10);
    }

    public function testDictionaryOffsetSetRequiresExistingKeyByDefault(): void {
        $dictionary = new Dictionary([10 => 'a'], TypeHint::string(), TypeHint::int());

        $this->expectException(KeyNotFoundException::class);
        $dictionary->offsetSet(20, 'b');
    }

    public function testDictionaryOffsetSetCanInsertWhenEnabled(): void {
        $dictionary = new Dictionary([10 => 'a'], TypeHint::string(), TypeHint::int());
        $dictionary->allowOffsetInserts = true;

        $dictionary->offsetSet(20, 'b');

        $this->assertSame([10 => 'a', 20 => 'b'], $dictionary->getEnumerator()->toArray());
    }

    public function testDictionaryAddTryAddRemoveAndClearFlow(): void {
        $dictionary = new Dictionary([10 => 'a'], TypeHint::string(), TypeHint::int());

        $dictionary->add(20, 'b');
        $this->assertFalse($dictionary->tryAdd(20, 'x'));
        $this->assertTrue($dictionary->tryAdd(30, 'c'));

        $removed = null;
        $this->assertTrue($dictionary->remove(20, $removed));
        $this->assertSame('b', $removed);
        $this->assertFalse($dictionary->remove(999, $removed));

        $dictionary->clear();
        $this->assertTrue($dictionary->empty());
        $this->assertSame([], $dictionary->keys);
    }

    public function testDictionaryCanConstructFromEnumerableWithTypeHints(): void {
        $source = new Enumerator([10 => 1, 20 => 2], TypeHint::int(), TypeHint::int());
        $dictionary = new Dictionary($source, TypeHint::int(), TypeHint::int());

        $this->assertSame([10 => 1, 20 => 2], $dictionary->getEnumerator()->toArray());
        $this->assertSame('int', $dictionary->TKey->fullName);
        $this->assertSame('int', $dictionary->TValue->fullName);
    }
}
