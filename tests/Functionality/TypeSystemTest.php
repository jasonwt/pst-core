<?php

declare(strict_types=1);

namespace PST\Core\Tests\Functionality;

use ArrayIterator;
use Closure;
use PHPUnit\Framework\TestCase;
use ReflectionFunction;
use PST\Core\Exceptions\ArgumentException;
use PST\Core\Exceptions\NotSupportedException;
use PST\Core\Type;
use PST\Core\TypeHint;

interface CallableTypeSystemFixtureInterface {
    public function __invoke(): string;
}

class CallableTypeSystemFixture implements CallableTypeSystemFixtureInterface {
    public function __invoke(): string {
        return 'callable';
    }
}

class NonCallableTypeSystemFixture {}

enum TypeSystemFixtureEnum {
    case Sample;
}

interface IntersectionLeftTypeSystemFixtureInterface {}

interface IntersectionRightTypeSystemFixtureInterface {}

class IntersectionTypeSystemFixture implements IntersectionLeftTypeSystemFixtureInterface, IntersectionRightTypeSystemFixtureInterface {}

class PartialIntersectionTypeSystemFixture implements IntersectionLeftTypeSystemFixtureInterface {}

final class TypeSystemTest extends TestCase {
    public function testTypeFactoryIgnoresCachedAbstractTypeHints(): void {
        Type::clearCaches();

        TypeHint::mixed();
        TypeHint::undefined();
        TypeHint::class();
        TypeHint::enum();
        TypeHint::interface();
        TypeHint::{'callable'}();
        TypeHint::iterable();

        $this->assertNull(Type::typeOf('mixed'));
        $this->assertNull(Type::typeOf('undefined'));
        $this->assertNull(Type::typeOf('class'));
        $this->assertNull(Type::typeOf('enum'));
        $this->assertNull(Type::typeOf('interface'));
        $this->assertNull(Type::typeOf('callable'));
        $this->assertNull(Type::typeOf('iterable'));
    }

    public function testIterableSpecialTypeAcceptsArraysAndTraversables(): void {
        $iterableTypeHint = TypeHint::iterable();
        $arrayType = Type::typeOf('array');
        $iteratorType = Type::ofInstance(new ArrayIterator([1, 2, 3]));
        $stringType = Type::typeOf('string');

        $this->assertTrue($iterableTypeHint->isAssignableFrom($arrayType));
        $this->assertTrue($iterableTypeHint->isAssignableFrom($iteratorType));
        $this->assertTrue($arrayType->isAssignableTo($iterableTypeHint));
        $this->assertTrue($iteratorType->isAssignableTo($iterableTypeHint));
        $this->assertFalse($iterableTypeHint->isAssignableFrom($stringType));
        $this->assertFalse($stringType->isAssignableTo($iterableTypeHint));
    }

    public function testCallableSpecialTypeAcceptsInvokableConcreteTypesOnly(): void {
        $callableTypeHint = TypeHint::{'callable'}();
        $closureType = Type::typeOf(Closure::class);
        $invokableClassType = Type::typeOf(CallableTypeSystemFixture::class);
        $invokableInterfaceType = Type::typeOf(CallableTypeSystemFixtureInterface::class);
        $nonCallableClassType = Type::typeOf(NonCallableTypeSystemFixture::class);
        $arrayType = Type::typeOf('array');
        $stringType = Type::typeOf('string');

        $this->assertTrue($callableTypeHint->isAssignableFrom($closureType));
        $this->assertTrue($callableTypeHint->isAssignableFrom($invokableClassType));
        $this->assertTrue($callableTypeHint->isAssignableFrom($invokableInterfaceType));
        $this->assertTrue($closureType->isAssignableTo($callableTypeHint));
        $this->assertTrue($invokableClassType->isAssignableTo($callableTypeHint));
        $this->assertTrue($invokableInterfaceType->isAssignableTo($callableTypeHint));

        $this->assertFalse($callableTypeHint->isAssignableFrom($nonCallableClassType));
        $this->assertFalse($callableTypeHint->isAssignableFrom($arrayType));
        $this->assertFalse($callableTypeHint->isAssignableFrom($stringType));
        $this->assertFalse($nonCallableClassType->isAssignableTo($callableTypeHint));
        $this->assertFalse($arrayType->isAssignableTo($callableTypeHint));
        $this->assertFalse($stringType->isAssignableTo($callableTypeHint));
    }

    public function testObjectKindSpecialTypeHintsAcceptMatchingConcreteTypes(): void {
        $classType = Type::typeOf(CallableTypeSystemFixture::class);
        $interfaceType = Type::typeOf(CallableTypeSystemFixtureInterface::class);
        $enumType = Type::typeOf(TypeSystemFixtureEnum::class);

        $this->assertTrue(TypeHint::{'class'}()->isAssignableFrom($classType));
        $this->assertFalse(TypeHint::{'class'}()->isAssignableFrom($interfaceType));
        $this->assertFalse(TypeHint::{'class'}()->isAssignableFrom($enumType));

        $this->assertTrue(TypeHint::interface()->isAssignableFrom($interfaceType));
        $this->assertFalse(TypeHint::interface()->isAssignableFrom($classType));
        $this->assertFalse(TypeHint::interface()->isAssignableFrom($enumType));

        $this->assertTrue(TypeHint::enum()->isAssignableFrom($enumType));
        $this->assertFalse(TypeHint::enum()->isAssignableFrom($classType));
        $this->assertFalse(TypeHint::enum()->isAssignableFrom($interfaceType));
    }

    public function testReflectionResolvesCallableAndIterableSpecialTypeHints(): void {
        $callableReflection = new ReflectionFunction(static fn (callable $handler): callable => $handler);
        $iterableReflection = new ReflectionFunction(static fn (iterable $values): iterable => $values);

        $this->assertSame(
            'callable',
            TypeHint::ofReflectionType($callableReflection->getParameters()[0]->getType())->fullName
        );
        $this->assertSame(
            'callable',
            TypeHint::ofReflectionType($callableReflection->getReturnType())->fullName
        );
        $this->assertSame(
            'iterable',
            TypeHint::ofReflectionType($iterableReflection->getParameters()[0]->getType())->fullName
        );
        $this->assertSame(
            'iterable',
            TypeHint::ofReflectionType($iterableReflection->getReturnType())->fullName
        );
    }

    public function testNullableCallableTypeHintAcceptsNullAndInvokableConcreteTypes(): void {
        $nullableCallable = TypeHint::{'callable'}(true);
        $nullType = Type::typeOf('null');
        $closureType = Type::typeOf(Closure::class);
        $stringType = Type::typeOf('string');

        $this->assertTrue($nullableCallable->isAssignableFrom($nullType));
        $this->assertTrue($nullableCallable->isAssignableFrom($closureType));
        $this->assertFalse($nullableCallable->isAssignableFrom($stringType));
    }

    public function testScalarTypeFactoryNormalizesAliasesAndProvidesDefaults(): void {
        $boolType = Type::typeOf('boolean');
        $intType = Type::typeOf('integer');
        $floatType = Type::typeOf('double');
        $stringType = Type::typeOf('string');

        $this->assertSame(Type::typeOf('bool'), $boolType);
        $this->assertSame(Type::typeOf('int'), $intType);
        $this->assertSame(Type::typeOf('float'), $floatType);

        $this->assertSame('bool', $boolType->fullName);
        $this->assertSame('int', $intType->fullName);
        $this->assertSame('float', $floatType->fullName);
        $this->assertSame('string', $stringType->fullName);

        $this->assertFalse($boolType->default());
        $this->assertSame(0, $intType->default());
        $this->assertSame(0.0, $floatType->default());
        $this->assertSame('', $stringType->default());
        $this->assertTrue($boolType->isScalar);
        $this->assertTrue($intType->isNumeric);
        $this->assertTrue($floatType->isNumeric);
    }

    public function testObjectAndArrayTypesExposeExpectedNamesNamespacesAndDefaults(): void {
        $classType = Type::typeOf(NonCallableTypeSystemFixture::class);
        $interfaceType = Type::typeOf(CallableTypeSystemFixtureInterface::class);
        $enumType = Type::typeOf(TypeSystemFixtureEnum::class);
        $arrayType = Type::typeOf('array');

        $this->assertSame('NonCallableTypeSystemFixture', $classType->name);
        $this->assertSame(__NAMESPACE__, $classType->namespace);
        $this->assertTrue($classType->isClass);
        $this->assertTrue($interfaceType->isInterface);
        $this->assertTrue($enumType->isEnum);
        $this->assertTrue($arrayType->isArray);
        $this->assertTrue($arrayType->isIterable);

        $this->assertNull($classType->default());
        $this->assertNull($interfaceType->default());
        $this->assertSame([], $arrayType->default());

        try {
            $enumType->default();
            $this->fail('Expected NotSupportedException for enum defaults.');
        } catch (NotSupportedException) {
        }
    }

    public function testTypeHintDefaultReturnsCanonicalValuesForSupportedHints(): void {
        $callableDefault = TypeHint::{'callable'}()->default();

        $this->assertSame(0, TypeHint::int()->default());
        $this->assertSame(0.0, TypeHint::float()->default());
        $this->assertFalse(TypeHint::bool()->default());
        $this->assertSame('', TypeHint::string()->default());
        $this->assertSame([], TypeHint::array()->default());
        $this->assertSame([], TypeHint::iterable()->default());
        $this->assertNull(TypeHint::{'class'}()->default());
        $this->assertNull(TypeHint::interface()->default());
        $this->assertInstanceOf(Closure::class, $callableDefault);
        $this->assertTrue(is_callable($callableDefault));
    }

    public function testTypeHintDefaultRejectsUnsupportedHints(): void {
        $unsupportedHints = [
            TypeHint::mixed(),
            TypeHint::undefined(),
            TypeHint::enum(),
            TypeHint::key(),
            TypeHint::union(TypeHint::int(), TypeHint::string()),
        ];

        foreach ($unsupportedHints as $typeHint) {
            try {
                $typeHint->default();
                $this->fail("Expected NotSupportedException for '{$typeHint->fullName}' defaults.");
            } catch (NotSupportedException $exception) {
                $this->assertStringContainsString($typeHint->fullName, $exception->getMessage());
            }
        }
    }

    public function testUnionFactoryFlattensDeduplicatesAndCachesNormalizedOrder(): void {
        $union = TypeHint::union(
            TypeHint::string(),
            TypeHint::union(TypeHint::int(), TypeHint::string()),
            TypeHint::{'callable'}()
        );

        $this->assertTrue($union->isUnion);
        $this->assertSame('callable|int|string', $union->fullName);
        $this->assertSame(['callable', 'int', 'string'], array_keys($union->types));
        $this->assertSame(
            $union,
            TypeHint::union(TypeHint::{'callable'}(), TypeHint::int(), TypeHint::string())
        );
    }

    public function testUnionRejectsMixedAndUndefinedSpecialHints(): void {
        foreach ([TypeHint::mixed(), TypeHint::undefined()] as $specialTypeHint) {
            try {
                TypeHint::union(TypeHint::int(), $specialTypeHint);
                $this->fail("Expected ArgumentException for '{$specialTypeHint->fullName}' in a union.");
            } catch (ArgumentException $exception) {
                $this->assertStringContainsString($specialTypeHint->fullName, $exception->getMessage());
            }
        }
    }

    public function testIntersectionFactoryFlattensDeduplicatesAndSupportsAssignableConcreteTypes(): void {
        $leftType = Type::typeOf(IntersectionLeftTypeSystemFixtureInterface::class);
        $rightType = Type::typeOf(IntersectionRightTypeSystemFixtureInterface::class);

        $intersection = TypeHint::intersection(
            $leftType,
            TypeHint::intersection($rightType, $leftType)
        );

        $concreteType = Type::typeOf(IntersectionTypeSystemFixture::class);
        $partialType = Type::typeOf(PartialIntersectionTypeSystemFixture::class);

        $this->assertTrue($intersection->isIntersection);
        $this->assertSame(
            [
                IntersectionLeftTypeSystemFixtureInterface::class,
                IntersectionRightTypeSystemFixtureInterface::class,
            ],
            array_keys($intersection->types)
        );
        $this->assertTrue($intersection->isAssignableFrom($concreteType));
        $this->assertTrue($concreteType->isAssignableTo($intersection));
        $this->assertFalse($intersection->isAssignableFrom($partialType));
        $this->assertFalse($partialType->isAssignableTo($intersection));
    }

    public function testIntersectionRejectsSpecialHintsAndScalarTypes(): void {
        try {
            TypeHint::intersection(
                TypeHint::{'callable'}(),
                Type::typeOf(IntersectionLeftTypeSystemFixtureInterface::class)
            );
            $this->fail("Expected ArgumentException for 'callable' in an intersection.");
        } catch (ArgumentException $exception) {
            $this->assertStringContainsString('callable', $exception->getMessage());
        }

        $this->expectException(ArgumentException::class);
        TypeHint::intersection(Type::typeOf('int'), Type::typeOf(IntersectionLeftTypeSystemFixtureInterface::class));
    }

    public function testReflectionResolvesUnionAndIntersectionTypeHints(): void {
        $unionReflection = new ReflectionFunction(static fn (int|string $value): int|string => $value);
        $intersectionReflection = new ReflectionFunction(
            static fn (
                IntersectionLeftTypeSystemFixtureInterface&IntersectionRightTypeSystemFixtureInterface $value
            ): IntersectionLeftTypeSystemFixtureInterface&IntersectionRightTypeSystemFixtureInterface => $value
        );

        $unionParameterType = TypeHint::ofReflectionType($unionReflection->getParameters()[0]->getType());
        $unionReturnType = TypeHint::ofReflectionType($unionReflection->getReturnType());
        $intersectionParameterType = TypeHint::ofReflectionType($intersectionReflection->getParameters()[0]->getType());
        $intersectionReturnType = TypeHint::ofReflectionType($intersectionReflection->getReturnType());

        $this->assertSame('int|string', $unionParameterType->fullName);
        $this->assertSame('int|string', $unionReturnType->fullName);
        $this->assertTrue($unionParameterType->isUnion);

        $this->assertTrue($intersectionParameterType->isIntersection);
        $this->assertSame($intersectionParameterType->fullName, $intersectionReturnType->fullName);
        $this->assertSame(
            [
                IntersectionLeftTypeSystemFixtureInterface::class,
                IntersectionRightTypeSystemFixtureInterface::class,
            ],
            array_keys($intersectionParameterType->types)
        );
    }

    public function testReflectionNullTypeDefaultsToUndefined(): void {
        $this->assertSame('undefined', TypeHint::ofReflectionType(null)->fullName);
    }

    public function testTypeFactoryRejectsEmptyNamesAndResources(): void {
        try {
            Type::typeOf('   ');
            $this->fail('Expected ArgumentException for an empty type name.');
        } catch (ArgumentException $exception) {
            $this->assertStringContainsString('cannot be empty', strtolower($exception->getMessage()));
        }

        $this->expectException(NotSupportedException::class);
        Type::typeOf('resource');
    }

    public function testAssignabilityMatrixCoversRepresentativeConcreteSpecialAndCompositeHints(): void {
        Type::clearCaches();

        $typeHints = $this->buildRepresentativeAssignabilityTypeHints();
        $expectedAssignableTargets = $this->buildRepresentativeAssignableTargets();

        $this->assertSame(array_keys($typeHints), array_keys($expectedAssignableTargets));

        foreach ($typeHints as $sourceLabel => $sourceTypeHint) {
            $expectedTargets = array_fill_keys($expectedAssignableTargets[$sourceLabel], true);

            foreach ($typeHints as $targetLabel => $targetTypeHint) {
                $expected = isset($expectedTargets[$targetLabel]);
                $context = "{$sourceLabel} ({$sourceTypeHint->fullName}) -> {$targetLabel} ({$targetTypeHint->fullName})";

                $this->assertSame(
                    $expected,
                    $sourceTypeHint->isAssignableTo($targetTypeHint),
                    "Unexpected isAssignableTo result for {$context}"
                );
                $this->assertSame(
                    $expected,
                    $targetTypeHint->isAssignableFrom($sourceTypeHint),
                    "Unexpected isAssignableFrom result for {$context}"
                );
            }
        }
    }

    private function buildRepresentativeAssignabilityTypeHints(): array {
        $leftInterfaceType = Type::typeOf(IntersectionLeftTypeSystemFixtureInterface::class);
        $rightInterfaceType = Type::typeOf(IntersectionRightTypeSystemFixtureInterface::class);

        return [
            'bool' => TypeHint::bool(),
            'int' => TypeHint::int(),
            'string' => TypeHint::string(),
            'array' => TypeHint::array(),
            'null' => Type::typeOf('null'),
            'closure' => Type::typeOf(Closure::class),
            'callable_class' => Type::typeOf(CallableTypeSystemFixture::class),
            'callable_interface' => Type::typeOf(CallableTypeSystemFixtureInterface::class),
            'plain_class' => Type::typeOf(NonCallableTypeSystemFixture::class),
            'enum_type' => Type::typeOf(TypeSystemFixtureEnum::class),
            'iterable_class' => Type::typeOf(ArrayIterator::class),
            'left_interface' => $leftInterfaceType,
            'right_interface' => $rightInterfaceType,
            'intersection_class' => Type::typeOf(IntersectionTypeSystemFixture::class),
            'left_only_class' => Type::typeOf(PartialIntersectionTypeSystemFixture::class),
            'mixed' => TypeHint::mixed(),
            'undefined' => TypeHint::undefined(),
            'callable' => TypeHint::{'callable'}(),
            'iterable' => TypeHint::iterable(),
            'class' => TypeHint::{'class'}(),
            'interface' => TypeHint::interface(),
            'enum' => TypeHint::enum(),
            'int|string' => TypeHint::union(TypeHint::int(), TypeHint::string()),
            'callable|null' => TypeHint::{'callable'}(true),
            'left&right' => TypeHint::intersection($leftInterfaceType, $rightInterfaceType),
        ];
    }

    private function buildRepresentativeAssignableTargets(): array {
        return [
            'bool' => ['bool', 'mixed', 'undefined'],
            'int' => ['int', 'mixed', 'undefined', 'int|string'],
            'string' => ['string', 'mixed', 'undefined', 'int|string'],
            'array' => ['array', 'mixed', 'undefined', 'iterable'],
            'null' => ['null', 'mixed', 'undefined', 'callable|null'],
            'closure' => ['closure', 'mixed', 'undefined', 'callable', 'class', 'callable|null'],
            'callable_class' => ['callable_class', 'callable_interface', 'mixed', 'undefined', 'callable', 'class', 'callable|null'],
            'callable_interface' => ['callable_interface', 'mixed', 'undefined', 'callable', 'interface', 'callable|null'],
            'plain_class' => ['plain_class', 'mixed', 'undefined', 'class'],
            'enum_type' => ['enum_type', 'mixed', 'undefined', 'enum'],
            'iterable_class' => ['iterable_class', 'mixed', 'undefined', 'iterable', 'class'],
            'left_interface' => ['left_interface', 'mixed', 'undefined', 'interface'],
            'right_interface' => ['right_interface', 'mixed', 'undefined', 'interface'],
            'intersection_class' => ['intersection_class', 'left_interface', 'right_interface', 'mixed', 'undefined', 'class', 'left&right'],
            'left_only_class' => ['left_only_class', 'left_interface', 'mixed', 'undefined', 'class'],
            'mixed' => ['mixed', 'undefined'],
            'undefined' => ['mixed', 'undefined'],
            'callable' => ['mixed', 'undefined', 'callable', 'callable|null'],
            'iterable' => ['mixed', 'undefined', 'iterable'],
            'class' => ['mixed', 'undefined', 'class'],
            'interface' => ['mixed', 'undefined', 'interface'],
            'enum' => ['mixed', 'undefined', 'enum'],
            'int|string' => ['mixed', 'undefined', 'int|string'],
            'callable|null' => ['mixed', 'undefined', 'callable|null'],
            'left&right' => ['left_interface', 'right_interface', 'mixed', 'undefined', 'interface', 'left&right'],
        ];
    }
}
