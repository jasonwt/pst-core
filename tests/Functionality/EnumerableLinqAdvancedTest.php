<?php

declare(strict_types=1);

namespace PST\Core\Tests\Functionality;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use PST\Core\Collections\Dictionary;
use PST\Core\Collections\Enumerator;
use PST\Core\Collections\Linq\Traits\Support\OrderedEnumerable;
use PST\Core\Collections\ReadOnlyDictionary;
use PST\Core\Collections\Linq\Accumulator;
use PST\Core\Collections\Linq\Predicate;
use PST\Core\Collections\Linq\Selector;
use PST\Core\Exceptions\InvalidOperationException;
use PST\Core\Exceptions\NotSupportedException;
use PST\Core\TypeHint;

class ElementAccessDefaultFixture {}

final class EnumerableLinqAdvancedTest extends TestCase {
    public function testToDictionaryPreservesKeysAndTypeHintsAndIsMutable(): void {
        $source = new Enumerator([10 => 1, 20 => 2], TypeHint::int(), TypeHint::int());

        $dictionary = $source->toDictionary();

        $this->assertInstanceOf(Dictionary::class, $dictionary);
        $this->assertSame([10 => 1, 20 => 2], $dictionary->toArray());
        $this->assertSame('int', $dictionary->TKey->fullName);
        $this->assertSame('int', $dictionary->TValue->fullName);

        $dictionary->add(30, 3);
        $this->assertSame([10 => 1, 20 => 2, 30 => 3], $dictionary->toArray());
    }

    public function testToReadOnlyDictionaryPreservesKeysAndTypeHintsAndRejectsMutations(): void {
        $source = new Enumerator([10 => 1, 20 => 2], TypeHint::int(), TypeHint::int());

        $dictionary = $source->toReadOnlyDictionary();

        $this->assertInstanceOf(ReadOnlyDictionary::class, $dictionary);
        $this->assertSame([10 => 1, 20 => 2], $dictionary->toArray());
        $this->assertSame('int', $dictionary->TKey->fullName);
        $this->assertSame('int', $dictionary->TValue->fullName);

        $this->expectException(InvalidOperationException::class);
        $dictionary->offsetSet(30, 3);
    }

    public function testToListDropsKeysButPreservesEnumerationOrder(): void {
        $source = new Enumerator([10 => 3, 20 => 1, 30 => 2], TypeHint::int(), TypeHint::int());

        $this->assertSame([3, 1, 2], $source->toList());
    }

    public function testConcatPreservesOrderAndCompatibleTypeHintsAcrossEnumerables(): void {
        $left = new Enumerator([10 => 1, 20 => 2], TypeHint::int(), TypeHint::int());
        $right = new Enumerator([30 => 3, 40 => 4], TypeHint::int(), TypeHint::int());

        $result = $left->concat($right);

        $this->assertSame([10 => 1, 20 => 2, 30 => 3, 40 => 4], $result->toArray());
        $this->assertSame('int', $result->TKey->fullName);
        $this->assertSame('int', $result->TValue->fullName);
    }

    public function testAppendAddsSyntheticKeyAndBroadensValueTypeWhenNeeded(): void {
        $source = new Enumerator([10 => 1, 20 => 2], TypeHint::int(), TypeHint::int());

        $result = $source->append('three');

        $this->assertSame([10 => 1, 20 => 2, 21 => 'three'], $result->toArray());
        $this->assertSame('int', $result->TKey->fullName);
        $this->assertSame('int|string', $result->TValue->fullName);
    }

    public function testPrependAddsSyntheticKeyAndBroadensStringKeysToGeneralKeyHint(): void {
        $source = new Enumerator(['a' => 1, 'b' => 2], TypeHint::int(), TypeHint::string());

        $result = $source->prepend(0);

        $this->assertSame([0 => 0, 'a' => 1, 'b' => 2], $result->toArray());
        $this->assertSame('int|string', $result->TKey->fullName);
        $this->assertSame('int', $result->TValue->fullName);
    }

    public function testZipPairsValuesUntilShortestSequenceAndPreservesFirstKeys(): void {
        $left = new Enumerator([10 => 'a', 20 => 'b', 30 => 'c'], TypeHint::string(), TypeHint::int());
        $right = new Enumerator([100 => 1, 200 => 2], TypeHint::int(), TypeHint::int());

        $result = $left->zip($right);

        $this->assertSame(
            [
                10 => ['a', 1],
                20 => ['b', 2],
            ],
            $result->toArray()
        );
        $this->assertSame('array', $result->TValue->fullName);
        $this->assertSame('int', $result->TKey->fullName);
    }

    public function testSelectManyPreservesProjectedKeysAndFallsBackWhenDuplicateKeysAppear(): void {
        $source = new Enumerator([10 => [1, 2], 20 => [3]], TypeHint::array(), TypeHint::int());

        $result = $source->selectMany(static fn (array $value, int $key): array => ['shared' => $value[0]]);

        $asArray = $result->toArray();

        $this->assertSame(1, $asArray['shared']);
        $this->assertContains(1, array_values($asArray));
        $this->assertContains(3, array_values($asArray));
    }

    public function testSkipAndTakePreserveTypesAndKeys(): void {
        $source = new Enumerator([10 => 1, 20 => 2, 30 => 3, 40 => 4], TypeHint::int(), TypeHint::int());

        $result = $source->skip(1)->take(2);

        $this->assertSame([20 => 2, 30 => 3], $result->toArray());
        $this->assertSame('int', $result->TValue->fullName);
        $this->assertSame('int', $result->TKey->fullName);
    }

    public function testSkipWhileAndTakeUntilUseValueAndKeyPredicates(): void {
        $source = new Enumerator([10 => 1, 20 => 2, 30 => 3, 40 => 4], TypeHint::int(), TypeHint::int());

        $skipped = $source->skipWhile(static fn (int $value, int $key): bool => $key < 30);
        $taken = $source->takeUntil(static fn (int $value, int $key): bool => $value === 4 && $key === 40);

        $this->assertSame([30 => 3, 40 => 4], $skipped->toArray());
        $this->assertSame([10 => 1, 20 => 2, 30 => 3], $taken->toArray());
    }

    public function testSkipUntilStartsAtFirstMatchingValueOrKey(): void {
        $source = new Enumerator([10 => 1, 20 => 2, 30 => 3, 40 => 4], TypeHint::int(), TypeHint::int());

        $result = $source->skipUntil(static fn (int $value, int $key): bool => $value >= 3 || $key === 30);

        $this->assertSame([30 => 3, 40 => 4], $result->toArray());
        $this->assertSame('int', $result->TValue->fullName);
        $this->assertSame('int', $result->TKey->fullName);
    }

    public function testTakeWhileStopsAtFirstNonMatchingValueOrKey(): void {
        $source = new Enumerator([10 => 1, 20 => 2, 30 => 3, 40 => 4], TypeHint::int(), TypeHint::int());

        $result = $source->takeWhile(static fn (int $value, int $key): bool => $value < 3 && $key <= 20);

        $this->assertSame([10 => 1, 20 => 2], $result->toArray());
        $this->assertSame('int', $result->TValue->fullName);
        $this->assertSame('int', $result->TKey->fullName);
    }

    public function testSkipLastDropsRequestedTailCountWhilePreservingKeys(): void {
        $source = new Enumerator([10 => 1, 20 => 2, 30 => 3, 40 => 4], TypeHint::int(), TypeHint::int());

        $result = $source->skipLast(2);

        $this->assertSame([10 => 1, 20 => 2], $result->toArray());
        $this->assertSame('int', $result->TValue->fullName);
        $this->assertSame('int', $result->TKey->fullName);
    }

    public function testTakeLastReturnsRequestedTailCountWhilePreservingKeys(): void {
        $source = new Enumerator([10 => 1, 20 => 2, 30 => 3, 40 => 4], TypeHint::int(), TypeHint::int());

        $result = $source->takeLast(2);

        $this->assertSame([30 => 3, 40 => 4], $result->toArray());
        $this->assertSame('int', $result->TValue->fullName);
        $this->assertSame('int', $result->TKey->fullName);
    }

    public function testChunkGroupsItemsLazilyIntoPreservedKeyBatches(): void {
        $source = new Enumerator([10 => 1, 20 => 2, 30 => 3, 40 => 4, 50 => 5], TypeHint::int(), TypeHint::int());

        $chunks = $source->chunk(2);

        $this->assertSame(
            [
                0 => [10 => 1, 20 => 2],
                1 => [30 => 3, 40 => 4],
                2 => [50 => 5],
            ],
            $chunks->toArray()
        );
        $this->assertSame('int', $chunks->TKey->fullName);
        $this->assertSame('array', $chunks->TValue->fullName);
    }

    public function testChunkRejectsNonPositiveSizes(): void {
        $source = new Enumerator([10 => 1], TypeHint::int(), TypeHint::int());

        try {
            $source->chunk(0);
            $this->fail('Expected InvalidArgumentException for zero chunk size.');
        } catch (InvalidArgumentException) {
        }

        $this->expectException(InvalidArgumentException::class);
        $source->chunk(-1);
    }

    public function testContainsUsesStrictComparison(): void {
        $source = new Enumerator(
            [10 => 1, 20 => '1', 30 => 2],
            TypeHint::union(TypeHint::int(), TypeHint::string()),
            TypeHint::int()
        );

        $this->assertTrue($source->contains(1));
        $this->assertTrue($source->contains('1'));
        $this->assertFalse($source->contains(1.0));
        $this->assertFalse($source->contains('2'));
    }

    public function testSequenceEqualComparesKeysValuesAndOrderStrictly(): void {
        $source = new Enumerator(
            [10 => 1, 20 => '2'],
            TypeHint::union(TypeHint::int(), TypeHint::string()),
            TypeHint::int()
        );

        $this->assertTrue($source->sequenceEqual([10 => 1, 20 => '2']));
        $this->assertFalse($source->sequenceEqual([0 => 1, 1 => '2']));
        $this->assertFalse($source->sequenceEqual([10 => 1, 20 => 2]));
        $this->assertFalse($source->sequenceEqual([20 => '2', 10 => 1]));
    }

    public function testQuantifiersWorkWithAndWithoutPredicates(): void {
        $source = new Enumerator([10 => 1, 20 => 2, 30 => 3], TypeHint::int(), TypeHint::int());

        $this->assertTrue($source->any());
        $this->assertTrue($source->any(static fn (int $value, int $key): bool => $key === 20 && $value === 2));
        $this->assertTrue($source->all(static fn (int $value, int $key): bool => $value > 0 && $key >= 10));
        $this->assertSame(2, $source->count(static fn (int $value, int $key): bool => $value >= 2));
    }

    public function testDistinctKeepsFirstOccurrenceKeysAndUsesStrictValueIdentity(): void {
        $firstObject = new ElementAccessDefaultFixture();
        $secondObject = new ElementAccessDefaultFixture();

        $source = new Enumerator(
            [
                10 => 1,
                20 => '1',
                30 => 1,
                40 => $firstObject,
                50 => $firstObject,
                60 => $secondObject,
            ],
            TypeHint::union(TypeHint::int(), TypeHint::string(), TypeHint::{'class'}(ElementAccessDefaultFixture::class)),
            TypeHint::int()
        );

        $result = $source->distinct();

        $this->assertSame(
            [
                10 => 1,
                20 => '1',
                40 => $firstObject,
                60 => $secondObject,
            ],
            $result->toArray()
        );
        $this->assertSame($source->TValue->fullName, $result->TValue->fullName);
        $this->assertSame('int', $result->TKey->fullName);
    }

    public function testDistinctByKeepsFirstValueForEachProjectedKey(): void {
        $source = new Enumerator(
            [
                10 => ['id' => 1, 'label' => 'Alpha'],
                20 => ['id' => 1, 'label' => 'ALPHA'],
                30 => ['id' => 2, 'label' => 'Beta'],
            ],
            TypeHint::array(),
            TypeHint::int()
        );

        $result = $source->distinctBy(static fn (array $value, int $key): int => $value['id']);

        $this->assertSame(
            [
                10 => ['id' => 1, 'label' => 'Alpha'],
                30 => ['id' => 2, 'label' => 'Beta'],
            ],
            $result->toArray()
        );
        $this->assertSame('array', $result->TValue->fullName);
        $this->assertSame('int', $result->TKey->fullName);
    }

    public function testUnionCombinesDistinctValuesAcrossSequencesAndBroadensValueType(): void {
        $left = new Enumerator([10 => 1, 20 => 2], TypeHint::int(), TypeHint::int());
        $right = new Enumerator([30 => 2, 40 => '3'], TypeHint::union(TypeHint::int(), TypeHint::string()), TypeHint::int());

        $result = $left->union($right);

        $this->assertSame([10 => 1, 20 => 2, 40 => '3'], $result->toArray());
        $this->assertSame('int|string', $result->TValue->fullName);
        $this->assertSame('int', $result->TKey->fullName);
    }

    public function testIntersectReturnsDistinctSharedValuesFromFirstSequence(): void {
        $left = new Enumerator([10 => 1, 20 => 2, 30 => 2, 40 => 3], TypeHint::int(), TypeHint::int());
        $right = new Enumerator([100 => 2, 200 => 3, 300 => 4], TypeHint::int(), TypeHint::int());

        $result = $left->intersect($right);

        $this->assertSame([20 => 2, 40 => 3], $result->toArray());
        $this->assertSame('int', $result->TValue->fullName);
        $this->assertSame('int', $result->TKey->fullName);
    }

    public function testExceptReturnsDistinctValuesMissingFromSecondSequence(): void {
        $left = new Enumerator([10 => 1, 20 => 2, 30 => 2, 40 => 3], TypeHint::int(), TypeHint::int());
        $right = new Enumerator([100 => 2, 200 => 4], TypeHint::int(), TypeHint::int());

        $result = $left->except($right);

        $this->assertSame([10 => 1, 40 => 3], $result->toArray());
        $this->assertSame('int', $result->TValue->fullName);
        $this->assertSame('int', $result->TKey->fullName);
    }

    public function testReversePreservesTypesKeysAndReversesEnumerationOrder(): void {
        $source = new Enumerator([10 => 'a', 20 => 'b', 30 => 'c'], TypeHint::string(), TypeHint::int());

        $result = $source->reverse();

        $this->assertSame([30 => 'c', 20 => 'b', 10 => 'a'], $result->toArray());
        $this->assertSame('string', $result->TValue->fullName);
        $this->assertSame('int', $result->TKey->fullName);
    }

    public function testElementAccessMethodsFollowExpectedSemantics(): void {
        $source = new Enumerator([10 => 1, 20 => 2, 30 => 3], TypeHint::int(), TypeHint::int());

        $this->assertSame(1, $source->first());
        $this->assertSame(3, $source->last());
        $this->assertSame(2, $source->single(static fn (int $value, int $key): bool => $key === 20));
        $this->assertSame(3, $source->elementAt(2));
    }

    public function testElementAccessOrDefaultUsesTypeHintDefaultsWhenNoValueMatches(): void {
        $ints = new Enumerator([], TypeHint::int(), TypeHint::int());
        $objects = new Enumerator([], TypeHint::{'class'}(ElementAccessDefaultFixture::class), TypeHint::int());

        $this->assertSame(0, $ints->firstOrDefault());
        $this->assertSame(0, $ints->lastOrDefault());
        $this->assertSame(0, $ints->singleOrDefault());
        $this->assertSame(0, $ints->elementAtOrDefault(4));
        $this->assertNull($objects->firstOrDefault());
    }

    public function testElementAccessOrDefaultSupportsExplicitFallbacksAndPredicateMisses(): void {
        $source = new Enumerator([10 => 'a', 20 => 'b'], TypeHint::string(), TypeHint::int());
        $objects = new Enumerator([], TypeHint::{'class'}(ElementAccessDefaultFixture::class), TypeHint::int());

        $this->assertSame(
            'fallback',
            $source->firstOrDefault(static fn (string $value, int $key): bool => $key === 99, 'fallback')
        );
        $this->assertSame(
            'fallback',
            $source->lastOrDefault(static fn (string $value, int $key): bool => $key === 99, 'fallback')
        );
        $this->assertSame(
            'fallback',
            $source->singleOrDefault(static fn (string $value, int $key): bool => $key === 99, 'fallback')
        );
        $this->assertSame('fallback', $source->elementAtOrDefault(99, 'fallback'));
        $this->assertNull($objects->elementAtOrDefault(0, null));
    }

    public function testSingleThrowsWhenMoreThanOneMatchExists(): void {
        $source = new Enumerator([10 => 1, 20 => 2, 30 => 3], TypeHint::int(), TypeHint::int());

        $this->expectException(InvalidArgumentException::class);
        $source->single(static fn (int $value, int $key): bool => $value >= 2);
    }

    public function testSingleOrDefaultStillThrowsWhenMoreThanOneMatchExists(): void {
        $source = new Enumerator([10 => 1, 20 => 2, 30 => 3], TypeHint::int(), TypeHint::int());

        $this->expectException(InvalidArgumentException::class);
        $source->singleOrDefault(static fn (int $value, int $key): bool => $value >= 2);
    }

    public function testElementAccessOrDefaultRejectsExplicitDefaultsOfWrongType(): void {
        $source = new Enumerator([10 => 1], TypeHint::int(), TypeHint::int());

        $this->expectException(InvalidArgumentException::class);
        $source->firstOrDefault(null, 'not-an-int');
    }

    public function testElementAccessOrDefaultPropagatesUnsupportedTypeDefaults(): void {
        $source = new Enumerator([], TypeHint::union(TypeHint::int(), TypeHint::string()), TypeHint::int());

        $this->expectException(NotSupportedException::class);
        $source->firstOrDefault();
    }

    public function testPredicateKeyTypeMustMatchEnumerableKeyType(): void {
        $source = new Enumerator([10 => 1, 20 => 2], TypeHint::int(), TypeHint::int());
        $predicate = new Predicate(
            static fn (int $value, string $key): bool => $value > 0 && $key !== '',
            TypeHint::int(),
            TypeHint::string()
        );

        $this->expectException(InvalidArgumentException::class);
        $source->any($predicate);
    }

    public function testAggregateSupportsClosureAccumulatorWithTwoParams(): void {
        $source = new Enumerator([10 => 1, 20 => 2, 30 => 3], TypeHint::int(), TypeHint::int());

        $sum = $source->aggregate(
            0,
            static fn (int $accumulated, int $item): int => $accumulated + $item
        );

        $this->assertSame(6, $sum);
    }

    public function testAggregateSupportsClosureAccumulatorWithIndexAndResultSelector(): void {
        $source = new Enumerator([10 => 2, 20 => 3], TypeHint::int(), TypeHint::int());

        $result = $source->aggregate(
            0,
            static fn (int $accumulated, int $item, int $index): int => $accumulated + ($item * ($index + 1)),
            static fn (int $state): string => 'total=' . $state
        );

        $this->assertSame('total=8', $result);
    }

    public function testAggregateSupportsAccumulatorObjectAndSelectorObject(): void {
        $source = new Enumerator([10 => 1, 20 => 4], TypeHint::int(), TypeHint::int());
        $accumulator = new Accumulator(
            static fn (int $accumulated, int $item, int $index): int => $accumulated + $item + $index,
            TypeHint::int(),
            TypeHint::int(),
            TypeHint::int(),
            TypeHint::int()
        );
        $resultSelector = new Selector(
            static fn (int $state, int $key): int => $state,
            TypeHint::int(),
            TypeHint::int(),
            TypeHint::int()
        );

        $result = $source->aggregate(0, $accumulator, $resultSelector);

        $this->assertSame(6, $result);
    }

    public function testSumAndAverageSupportNumericSequencesAndSelectors(): void {
        $source = new Enumerator([10 => 2, 20 => 4, 30 => 6], TypeHint::int(), TypeHint::int());
        $projected = new Enumerator(
            [
                10 => ['points' => 1.5],
                20 => ['points' => 2.5],
            ],
            TypeHint::array(),
            TypeHint::int()
        );

        $this->assertSame(12, $source->sum());
        $this->assertSame(4.0, $source->average());
        $this->assertSame(4.0, $projected->sum(static fn (array $value, int $key): float => $value['points']));
        $this->assertSame(2.0, $projected->average(static fn (array $value, int $key): float => $value['points']));
        $this->assertSame(2, $source->min());
        $this->assertSame(6, $source->max());
        $this->assertSame(1.5, $projected->min(static fn (array $value, int $key): float => $value['points']));
        $this->assertSame(2.5, $projected->max(static fn (array $value, int $key): float => $value['points']));
    }

    public function testSumRejectsNonNumericSequencesAndAggregateExtremesRejectInvalidInput(): void {
        $strings = new Enumerator([10 => 'a'], TypeHint::string(), TypeHint::int());
        $empty = new Enumerator([], TypeHint::int(), TypeHint::int());
        $arrays = new Enumerator([10 => ['x' => 1], 20 => ['x' => 2]], TypeHint::array(), TypeHint::int());

        try {
            $strings->sum();
            $this->fail('Expected InvalidArgumentException for summing non-numeric values.');
        } catch (InvalidArgumentException) {
        }

        try {
            $arrays->min();
            $this->fail('Expected InvalidArgumentException for min on non-comparable values.');
        } catch (InvalidArgumentException) {
        }

        try {
            $empty->average();
            $this->fail('Expected InvalidArgumentException for average on an empty sequence.');
        } catch (InvalidArgumentException) {
        }

        $this->expectException(InvalidArgumentException::class);
        $empty->max();
    }

    public function testOrderByThenByAppliesSecondarySortAndKeepsStability(): void {
        $source = new Enumerator(
            [
                10 => ['group' => 2, 'score' => 1, 'id' => 'a'],
                20 => ['group' => 1, 'score' => 2, 'id' => 'b'],
                30 => ['group' => 1, 'score' => 1, 'id' => 'c'],
                40 => ['group' => 1, 'score' => 1, 'id' => 'd'],
            ],
            TypeHint::array(),
            TypeHint::int()
        );

        $ordered = $source
            ->orderBy(static fn (array $item, int $key): int => $item['group'])
            ->thenBy(static fn (array $item, int $key): int => $item['score']);

        $this->assertInstanceOf(OrderedEnumerable::class, $ordered);
        $orderedKeys = array_keys($ordered->toArray());

        $this->assertSame([30, 40, 20, 10], $orderedKeys);
    }

    public function testOrderByDescendingAndThenByDescendingWorkTogether(): void {
        $source = new Enumerator(
            [
                10 => ['group' => 1, 'score' => 1],
                20 => ['group' => 2, 'score' => 2],
                30 => ['group' => 2, 'score' => 1],
            ],
            TypeHint::array(),
            TypeHint::int()
        );

        $ordered = $source
            ->orderByDescending(static fn (array $item, int $key): int => $item['group'])
            ->thenByDescending(static fn (array $item, int $key): int => $item['score']);

        $this->assertInstanceOf(OrderedEnumerable::class, $ordered);
        $this->assertSame([20, 30, 10], array_keys($ordered->toArray()));
    }

    public function testOrderBySupportsCustomComparerClosure(): void {
        $source = new Enumerator([10 => 'bbb', 20 => 'a', 30 => 'cc'], TypeHint::string(), TypeHint::int());

        $ordered = $source->orderBy(
            static fn (string $value, int $key): int => strlen($value),
            static fn (int $left, int $right): int => $right <=> $left
        );

        $this->assertSame([10, 30, 20], array_keys($ordered->toArray()));
    }

    public function testSelectManyThrowsWhenSelectorReturnsNonIterable(): void {
        $source = new Enumerator([10 => 1], TypeHint::int(), TypeHint::int());

        $this->expectException(InvalidArgumentException::class);
        $source->selectMany(static fn (int $value, int $key): int => $value)->toArray();
    }

    public function testSkipAndTakeThrowOnNegativeCount(): void {
        $source = new Enumerator([10 => 1], TypeHint::int(), TypeHint::int());

        try {
            $source->skip(-1);
            $this->fail('Expected InvalidArgumentException for negative skip.');
        } catch (InvalidArgumentException) {
        }

        $this->expectException(InvalidArgumentException::class);
        $source->take(-1);
    }

    public function testElementAccessThrowsWhenNotFoundOrOutOfRange(): void {
        $source = new Enumerator([10 => 1], TypeHint::int(), TypeHint::int());

        try {
            $source->first(static fn (int $value, int $key): bool => false);
            $this->fail('Expected InvalidArgumentException for first with no match.');
        } catch (InvalidArgumentException) {
        }

        try {
            $source->last(static fn (int $value, int $key): bool => false);
            $this->fail('Expected InvalidArgumentException for last with no match.');
        } catch (InvalidArgumentException) {
        }

        $this->expectException(InvalidArgumentException::class);
        $source->elementAt(2);
    }

    public function testAggregateRejectsResultSelectorClosureWithWrongParameterCount(): void {
        $source = new Enumerator([10 => 1, 20 => 2], TypeHint::int(), TypeHint::int());

        $this->expectException(InvalidArgumentException::class);
        $source->aggregate(
            0,
            static fn (int $acc, int $item): int => $acc + $item,
            static fn (int $state, int $index): int => $state + $index
        );
    }
}
