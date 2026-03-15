<?php

declare(strict_types=1);

namespace PST\Core;

use Stringable;
use ReflectionType;
use ReflectionNamedType;
use ReflectionUnionType;
use ReflectionIntersectionType;

use PST\Core\Exceptions\ArgumentException;
use PST\Core\Exceptions\ArgumentOutOfRangeException;
use PST\Core\Exceptions\NotSupportedException;

abstract class TypeHint extends CoreObject implements Stringable {
    protected static array $_instanceCache = [];
    protected static array $_isAssignableFromCache = [];
    protected static array $_defaultValuesCache  = [];

    private const array SPECIAL_TYPE_HINT_NAME_SET = [
        'mixed' => true,
        'undefined' => true,
        'class' => true,
        'enum' => true,
        'interface' => true,
        'callable' => true,
        'iterable' => true,
    ];

    public const array SPECIAL_TYPE_HINT_NAMES = [
        'mixed',
        'undefined',
        'class',
        'enum',
        'interface',
        'callable',
        'iterable',
    ];

    public readonly string $fullName;

    public array $types {get => $this->getTypes();}
    public bool $isUnion {get => $this->isUnionType();}
    public bool $isIntersection {get => $this->isIntersectionType();}

    protected function __construct(string $fullName) {
        $this->fullName = $fullName;

        parent::__construct();

        static::$_instanceCache[$fullName] = $this;
    }

    public function default(): mixed {
        return static::$_defaultValuesCache[$this->fullName] ??= 
            match(true) {
                $this->isAssignableTo(TypeHint::int()) => 0,
                $this->isAssignableTo(TypeHint::float()) => 0.0,
                $this->isAssignableTo(TypeHint::bool()) => false,
                $this->isAssignableTo(TypeHint::string()) => '',
                $this->isAssignableTo(TypeHint::array()) => [],
                $this->isAssignableTo(TypeHint::interface()) => null,
                $this->isAssignableTo(TypeHint::class()) => null,
                $this->isAssignableTo(TypeHint::iterable()) => [],
                $this->isAssignableTo(TypeHint::callable()) => function() {},
                default => throw new NotSupportedException("Default value is not defined for TypeHint '$this->fullName'"),
            };
    }

    public function __toString(): string {
        return $this->fullName;
    }

    public function isSingle(): bool {
        return !$this->isUnion && !$this->isIntersection;
    }

    public function isSpecial(): bool {
        return $this instanceof SpecialTypeHint;
    }

    protected static function isSpecialTypeName(string $typeName): bool {
        return isset(self::SPECIAL_TYPE_HINT_NAME_SET[$typeName]);
    }

    protected function getTypes(): array {
        return [$this->fullName => $this];
    }

    protected function isUnionType(): bool {
        return false;
    }

    protected function isIntersectionType(): bool {
        return false;
    }

    abstract public function isAssignableFrom(TypeHint $other): bool;
    abstract public function isAssignableTo(TypeHint $other): bool;

    public static function mixed(): TypeHint {
        return static::$_instanceCache['mixed'] ?? new SpecialTypeHint('mixed');
    }

    public static function undefined(): TypeHint {
        return static::$_instanceCache['undefined'] ?? new SpecialTypeHint('undefined');
    }

    public static function key(bool $nullable = false): TypeHint {
        if ($nullable) {
            return static::$_instanceCache['int|null|string'] ??
                new UnionTypeHint(Type::typeOf('int'), Type::typeOf('null'), Type::typeOf('string'));
        }

        return static::$_instanceCache['int|string'] ??
            new UnionTypeHint(Type::typeOf('int'), Type::typeOf('string'));
    }

    public static function union(TypeHint ...$typeHints): TypeHint {
        $unionTypes = [];
        $typeHintCount = count($typeHints);

        for ($i = 0; $i < $typeHintCount; $i++) {
            $typeHint = $typeHints[$i];

            if ($typeHint instanceof Type) {
                $unionTypes[$typeHint->fullName] = $typeHint;
            } else if ($typeHint instanceof UnionTypeHint) {
                foreach ($typeHint->types as $unionType) {
                    $typeHints[] = $unionType;
                    $typeHintCount++;
                }
            } else if ($typeHint instanceof IntersectionTypeHint) {
                $unionTypes["(" . $typeHint->fullName . ")"] = $typeHint;
            } else if ($typeHint instanceof SpecialTypeHint) {
                if ($typeHint->fullName === 'mixed' || $typeHint->fullName === 'undefined') {
                    throw new ArgumentException("Cannot use 'mixed' or 'undefined' in a union TypeHint", 'typeHints');
                }

                $unionTypes[$typeHint->fullName] = $typeHint;
            } else {
                throw new NotSupportedException("Unsupported TypeHint subclass in union: " . get_class($typeHint));
            }
        }

        if (count($unionTypes) < 2) {
            throw new ArgumentOutOfRangeException('typeHints', count($unionTypes), 'At least two unique types must be provided for union TypeHint');
        }

        ksort($unionTypes);

        $cacheKey = implode('|', array_keys($unionTypes));

        return static::$_instanceCache[$cacheKey] ?? new UnionTypeHint(...array_values($unionTypes));
    }

    public static function intersection(TypeHint ...$typeHints): TypeHint {
        $intersectionTypes = [];
        $typeHintCount = count($typeHints);

        for ($i = 0; $i < $typeHintCount; $i++) {
            $typeHint = $typeHints[$i];

            if ($typeHint instanceof SpecialTypeHint) {
                throw new ArgumentException("Cannot use 'mixed', 'undefined', 'class', 'enum', 'interface', 'callable', or 'iterable' in an intersection TypeHint", 'typeHints');
            }

            if ($typeHint instanceof IntersectionTypeHint) {
                foreach ($typeHint->types as $intersectionType) {
                    $typeHints[] = $intersectionType;
                    $typeHintCount++;
                }

                continue;
            }

            if ($typeHint instanceof UnionTypeHint) {
                $typeKey = "(" . $typeHint->fullName . ")";

                if (isset($intersectionTypes[$typeKey])) {
                    continue;
                }

                $intersectionTypes[$typeKey] = $typeHint;
            } else if ($typeHint instanceof Type) {
                if (isset($intersectionTypes[$typeHint->fullName])) {
                    continue;
                }

                $intersectionTypes[$typeHint->fullName] = $typeHint;
            } else {
                throw new NotSupportedException("Unsupported TypeHint subclass in intersection: " . get_class($typeHint));
            }

            foreach (array_keys($intersectionTypes) as $intersectionTypeKey) {
                $intersectionType = $intersectionTypes[$intersectionTypeKey];

                if ($intersectionType === $typeHint) {
                    break;
                }

                if ($intersectionType->isAssignableFrom($typeHint)) {
                    unset($intersectionTypes[$intersectionTypeKey]);
                    break;
                }

                if ($intersectionType->isAssignableTo($typeHint)) {
                    array_pop($intersectionTypes);
                    break;
                }
            }
        }

        if (count($intersectionTypes) < 2) {
            throw new ArgumentOutOfRangeException('typeHints', count($intersectionTypes), 'At least two unique types must be provided for intersection TypeHint');
        }

        ksort($intersectionTypes);

        $cacheKey = implode('&', array_keys($intersectionTypes));

        return static::$_instanceCache[$cacheKey] ?? new IntersectionTypeHint(...array_values($intersectionTypes));
    }

    public static function array(bool $nullable = false): TypeHint {
        if ($nullable) {
            return static::$_instanceCache['array|null'] ??
                new UnionTypeHint(Type::typeOf('array'), Type::typeOf('null'));
        }

        return Type::typeOf('array');
    }

    public static function bool(bool $nullable = false): TypeHint {
        if ($nullable) {
            return static::$_instanceCache['bool|null'] ??
                new UnionTypeHint(Type::typeOf('bool'), Type::typeOf('null'));
        }

        return Type::typeOf('bool');
    }

    public static function float(bool $nullable = false): TypeHint {
        if ($nullable) {
            return static::$_instanceCache['float|null'] ??
                new UnionTypeHint(Type::typeOf('float'), Type::typeOf('null'));
        }

        return Type::typeOf('float');
    }

    public static function int(bool $nullable = false): TypeHint {
        if ($nullable) {
            return static::$_instanceCache['int|null'] ??
                new UnionTypeHint(Type::typeOf('int'), Type::typeOf('null'));
        }

        return Type::typeOf('int');
    }

    public static function string(bool $nullable = false): TypeHint {
        if ($nullable) {
            return static::$_instanceCache['null|string'] ??
                new UnionTypeHint(Type::typeOf('null'), Type::typeOf('string'));
        }

        return Type::typeOf('string');
    }

    public static function class(null|string $className = null, bool $nullable = false): TypeHint {
        $className = trim($className ?: 'class');

        if ($className === 'class') {
            $singleHint = static::$_instanceCache['class'] ?? new SpecialTypeHint('class');

            if (!$nullable) {
                return $singleHint;
            }

            return static::$_instanceCache['class|null'] ??
                new UnionTypeHint($singleHint, Type::typeOf('null'));
        }

        $classType = Type::typeOf($className);

        if (!$classType || !$classType->isClass) {
            throw new ArgumentException("$className is not a class", 'className');
        }

        if (!$nullable) {
            return $classType;
        }

        $cacheKey = $className < 'null' ? "$className|null" : "null|$className";

        if (isset(static::$_instanceCache[$cacheKey])) {
            return static::$_instanceCache[$cacheKey];
        }

        return $className < 'null'
            ? new UnionTypeHint($classType, Type::typeOf('null'))
            : new UnionTypeHint(Type::typeOf('null'), $classType);
    }

    public static function enum(null|string $enumName = null, bool $nullable = false): TypeHint {
        $enumName = trim($enumName ?: 'enum');

        if ($enumName === 'enum') {
            $singleHint = static::$_instanceCache['enum'] ?? new SpecialTypeHint('enum');

            if (!$nullable) {
                return $singleHint;
            }

            return static::$_instanceCache['enum|null'] ??
                new UnionTypeHint($singleHint, Type::typeOf('null'));
        }

        $enumType = Type::typeOf($enumName);

        if (!$enumType || !$enumType->isEnum) {
            throw new ArgumentException("$enumName is not an enum", 'enumName');
        }

        if (!$nullable) {
            return $enumType;
        }

        $cacheKey = $enumName < 'null' ? "$enumName|null" : "null|$enumName";

        if (isset(static::$_instanceCache[$cacheKey])) {
            return static::$_instanceCache[$cacheKey];
        }

        return $enumName < 'null'
            ? new UnionTypeHint($enumType, Type::typeOf('null'))
            : new UnionTypeHint(Type::typeOf('null'), $enumType);
    }

    public static function interface(null|string $interfaceName = null, bool $nullable = false): TypeHint {
        $interfaceName = trim($interfaceName ?: 'interface');

        if ($interfaceName === 'interface') {
            $singleHint = static::$_instanceCache['interface'] ?? new SpecialTypeHint('interface');

            if (!$nullable) {
                return $singleHint;
            }

            return static::$_instanceCache['interface|null'] ??
                new UnionTypeHint($singleHint, Type::typeOf('null'));
        }

        $interfaceType = Type::typeOf($interfaceName);

        if (!$interfaceType || !$interfaceType->isInterface) {
            throw new ArgumentException("$interfaceName is not an interface", 'interfaceName');
        }

        if (!$nullable) {
            return $interfaceType;
        }

        $cacheKey = $interfaceName < 'null' ? "$interfaceName|null" : "null|$interfaceName";

        if (isset(static::$_instanceCache[$cacheKey])) {
            return static::$_instanceCache[$cacheKey];
        }

        return $interfaceName < 'null'
            ? new UnionTypeHint($interfaceType, Type::typeOf('null'))
            : new UnionTypeHint(Type::typeOf('null'), $interfaceType);
    }

    public static function iterable(bool $nullable = false): TypeHint {
        $singleHint = static::$_instanceCache['iterable'] ?? new SpecialTypeHint('iterable');

        if ($nullable) {
            return static::$_instanceCache['iterable|null'] ??
                new UnionTypeHint($singleHint, Type::typeOf('null'));
        }

        return $singleHint;
    }

    public static function callable(bool $nullable = false): TypeHint {
        $singleHint = static::$_instanceCache['callable'] ?? new SpecialTypeHint('callable');

        if ($nullable) {
            return static::$_instanceCache['callable|null'] ??
                new UnionTypeHint($singleHint, Type::typeOf('null'));
        }

        return $singleHint;
    }
    
    public static function ofReflectionType(null|ReflectionType $reflectionType): TypeHint {
        if ($reflectionType === null) {
            return static::undefined();
        }

        if ($reflectionType instanceof ReflectionUnionType) {
            $types = [];

            foreach ($reflectionType->getTypes() as $reflectionSubType) {
                $subType = static::ofReflectionType($reflectionSubType);
                $types[$subType->fullName] = $subType;
            }

            if ($reflectionType->allowsNull()) {
                $types['null'] = Type::typeOf('null');
            }

            return static::union(...array_values($types));
        }

        if ($reflectionType instanceof ReflectionIntersectionType) {
            $types = [];

            foreach ($reflectionType->getTypes() as $reflectionSubType) {
                $subType = static::ofReflectionType($reflectionSubType);
                $types[$subType->fullName] = $subType;
            }

            $intersectionType = static::intersection(...array_values($types));

            return $reflectionType->allowsNull()
                ? static::union($intersectionType, Type::typeOf('null'))
                : $intersectionType;
        }

        if ($reflectionType instanceof ReflectionNamedType) {
            $typeName = $reflectionType->getName();

            $typeInstance = Type::typeOf($typeName) ?? (
                static::isSpecialTypeName($typeName)
                    ? (static::$_instanceCache[$typeName] ?? new SpecialTypeHint($typeName))
                    : null
            );

            if ($typeInstance === null) {
                throw new NotSupportedException("Unsupported type '$typeName' in ReflectionNamedType");
            }

            return $reflectionType->allowsNull() &&
                $typeInstance->fullName !== 'mixed' &&
                $typeInstance->fullName !== 'undefined' &&
                $typeInstance->fullName !== 'null'
                    ? static::union($typeInstance, Type::typeOf('null'))
                    : $typeInstance;
        }

        throw new NotSupportedException('Unsupported ReflectionType subclass: ' . get_class($reflectionType));
    }

    public static function clearCaches(): void {
        static::$_instanceCache = [];
        static::$_isAssignableFromCache = [];
        static::$_defaultValuesCache = [];
    }
}

final class SpecialTypeHint extends TypeHint {
    public const array NAMES = TypeHint::SPECIAL_TYPE_HINT_NAMES;

    public function __construct(string $name) {
        if (!TypeHint::isSpecialTypeName($name)) {
            throw new ArgumentException(
                "Invalid SpecialTypeHint name: '$name'. Must be one of: " . implode(', ', self::NAMES),
                'name'
            );
        }

        parent::__construct($name);
    }

    public function default(): mixed {
        return parent::default();
    }

    public function isAssignableFrom(TypeHint $other): bool {
        $thisFullName = $this->fullName;
        $otherFullName = $other->fullName;

        if ($thisFullName === $otherFullName || $thisFullName === 'mixed' || $thisFullName === 'undefined') {
            return true;
        }

        $cacheKey = "$thisFullName<=$otherFullName";

        if (isset(static::$_isAssignableFromCache[$cacheKey])) {
            return static::$_isAssignableFromCache[$cacheKey];
        }

        if ($other instanceof self) {
            return static::$_isAssignableFromCache[$cacheKey] = false;
        }

        return static::$_isAssignableFromCache[$cacheKey] = $other->isAssignableTo($this);
    }

    public function isAssignableTo(TypeHint $other): bool {
        $thisFullName = $this->fullName;
        $otherFullName = $other->fullName;

        if ($thisFullName === $otherFullName || $otherFullName === 'mixed' || $otherFullName === 'undefined') {
            return true;
        }

        $cacheKey = "$otherFullName<=$thisFullName";

        if (isset(static::$_isAssignableFromCache[$cacheKey])) {
            return static::$_isAssignableFromCache[$cacheKey];
        }

        if ($other instanceof self) {
            return static::$_isAssignableFromCache[$cacheKey] = false;
        }

        return static::$_isAssignableFromCache[$cacheKey] = $other->isAssignableFrom($this);
    }
}

final class UnionTypeHint extends TypeHint {
    private array $_types = [];

    protected function getTypes(): array {
        return $this->_types;
    }

    protected function isUnionType(): bool {
        return true;
    }

    public function __construct(TypeHint ...$types) {
        $thisTypes = [];

        foreach ($types as $type) {

            if ($type instanceof Type) {
                $thisTypes[$type->fullName] = $type;
            } else if ($type instanceof SpecialTypeHint) {
                if ($type->fullName === 'mixed' || $type->fullName === 'undefined') {
                    throw new ArgumentException("Cannot use 'mixed' or 'undefined' in a UnionTypeHint", 'types');
                }

                $thisTypes[$type->fullName] = $type;
            } else if ($type instanceof self) {
                throw new ArgumentException('Nested unions must be flattened before construction', 'types');
            } else if ($type instanceof IntersectionTypeHint) {
                $thisTypes["(" . $type->fullName . ")"] = $type;
            } else {
                throw new NotSupportedException("Unsupported TypeHint subclass in UnionTypeHint: " . get_class($type));
            }
        }

        if (count($thisTypes) < 2) {
            throw new ArgumentOutOfRangeException('types', count($thisTypes), 'At least two unique types must be provided for UnionTypeHint');
        }

        $this->_types = $thisTypes;

        parent::__construct(implode('|', array_keys($thisTypes)));
    }

    public function isAssignableFrom(TypeHint $other): bool {
        $thisFullName = $this->fullName;
        $otherFullName = $other->fullName;

        if ($thisFullName === $otherFullName) {
            return true;
        }

        $cacheKey = "$thisFullName<=$otherFullName";

        if (isset(static::$_isAssignableFromCache[$cacheKey])) {
            return static::$_isAssignableFromCache[$cacheKey];
        }

        if ($other instanceof Type || $other instanceof SpecialTypeHint) {
            foreach ($this->_types as $thisSubType) {
                if ($thisSubType->isAssignableFrom($other)) {
                    return static::$_isAssignableFromCache[$cacheKey] = true;
                }
            }

            return static::$_isAssignableFromCache[$cacheKey] = false;
        }

        if ($other instanceof self) {
            foreach ($other->types as $otherSubType) {
                if (!$this->isAssignableFrom($otherSubType)) {
                    return static::$_isAssignableFromCache[$cacheKey] = false;
                }
            }

            return static::$_isAssignableFromCache[$cacheKey] = true;
        }

        if ($other instanceof IntersectionTypeHint) {
            foreach ($this->_types as $thisSubType) {
                if ($thisSubType->isAssignableFrom($other)) {
                    return static::$_isAssignableFromCache[$cacheKey] = true;
                }
            }

            return static::$_isAssignableFromCache[$cacheKey] = false;
        }

        throw new NotSupportedException("Unsupported TypeHint subclass: " . get_class($other));
    }

    public function isAssignableTo(TypeHint $other): bool {
        $thisFullName = $this->fullName;
        $otherFullName = $other->fullName;

        if ($thisFullName === $otherFullName || $otherFullName === 'mixed' || $otherFullName === 'undefined') {
            return true;
        }

        $cacheKey = "$otherFullName<=$thisFullName";

        if (isset(static::$_isAssignableFromCache[$cacheKey])) {
            return static::$_isAssignableFromCache[$cacheKey];
        }

        if ($other instanceof Type || $other instanceof SpecialTypeHint) {
            foreach ($this->_types as $thisSubType) {
                if (!$thisSubType->isAssignableTo($other)) {
                    return static::$_isAssignableFromCache[$cacheKey] = false;
                }
            }

            return static::$_isAssignableFromCache[$cacheKey] = true;
        }

        if ($other instanceof self) {
            foreach ($this->_types as $thisSubType) {
                if (!$thisSubType->isAssignableTo($other)) {
                    return static::$_isAssignableFromCache[$cacheKey] = false;
                }
            }

            return static::$_isAssignableFromCache[$cacheKey] = true;
        }

        if ($other instanceof IntersectionTypeHint) {
            foreach ($this->_types as $thisSubType) {
                if (!$thisSubType->isAssignableTo($other)) {
                    return static::$_isAssignableFromCache[$cacheKey] = false;
                }
            }

            return static::$_isAssignableFromCache[$cacheKey] = true;
        }

        throw new NotSupportedException("Unsupported TypeHint subclass: " . get_class($other));
    }
}

final class IntersectionTypeHint extends TypeHint {
    private array $_types = [];

    protected function getTypes(): array {
        return $this->_types;
    }

    protected function isIntersectionType(): bool {
        return true;
    }

    public function __construct(TypeHint ...$types) {
        $thisTypes = [];

        foreach ($types as $type) {

            if ($type instanceof SpecialTypeHint) {
                throw new ArgumentException("Cannot use 'mixed', 'undefined', 'class', 'enum', 'interface', 'callable', or 'iterable' in an IntersectionTypeHint", 'types');
            }

            if ($type instanceof Type) {
                if (!$type->isClass && !$type->isInterface) {
                    throw new ArgumentException('Only class and interface types can be used in an IntersectionTypeHint', 'types');
                }

                $thisTypes[$type->fullName] = $type;
            } else if ($type instanceof self) {
                throw new ArgumentException('Nested intersections must be flattened before construction', 'types');
            } else if ($type instanceof UnionTypeHint) {
                $thisTypes["(" . $type->fullName . ")"] = $type;
            } else {
                throw new NotSupportedException("Unsupported TypeHint subclass in IntersectionTypeHint: " . get_class($type));
            }
        }

        if (count($thisTypes) < 2) {
            throw new ArgumentOutOfRangeException('types', count($thisTypes), 'At least two unique types must be provided for IntersectionTypeHint');
        }

        $this->_types = $thisTypes;

        parent::__construct(implode('&', array_keys($thisTypes)));
    }

    public function isAssignableFrom(TypeHint $other): bool {
        $thisFullName = $this->fullName;
        $otherFullName = $other->fullName;

        if ($thisFullName === $otherFullName) {
            return true;
        }

        $cacheKey = "$thisFullName<=$otherFullName";

        if (isset(static::$_isAssignableFromCache[$cacheKey])) {
            return static::$_isAssignableFromCache[$cacheKey];
        }

        if ($other instanceof Type || $other instanceof SpecialTypeHint) {
            foreach ($this->_types as $thisSubType) {
                if (!$thisSubType->isAssignableFrom($other)) {
                    return static::$_isAssignableFromCache[$cacheKey] = false;
                }
            }

            return static::$_isAssignableFromCache[$cacheKey] = true;
        }

        if ($other instanceof UnionTypeHint) {
            foreach ($other->types as $otherSubType) {
                if (!$this->isAssignableFrom($otherSubType)) {
                    return static::$_isAssignableFromCache[$cacheKey] = false;
                }
            }

            return static::$_isAssignableFromCache[$cacheKey] = true;
        }

        if ($other instanceof self) {
            foreach ($other->types as $otherSubType) {
                if ($this->isAssignableFrom($otherSubType)) {
                    return static::$_isAssignableFromCache[$cacheKey] = true;
                }
            }

            return static::$_isAssignableFromCache[$cacheKey] = false;
        }

        throw new NotSupportedException("Unsupported TypeHint subclass: " . get_class($other));
    }

    public function isAssignableTo(TypeHint $other): bool {
        $thisFullName = $this->fullName;
        $otherFullName = $other->fullName;

        if ($thisFullName === $otherFullName || $otherFullName === 'mixed' || $otherFullName === 'undefined') {
            return true;
        }

        $cacheKey = "$otherFullName<=$thisFullName";

        if (isset(static::$_isAssignableFromCache[$cacheKey])) {
            return static::$_isAssignableFromCache[$cacheKey];
        }

        if ($other instanceof Type || $other instanceof SpecialTypeHint) {
            foreach ($this->_types as $thisSubType) {
                if ($thisSubType->isAssignableTo($other)) {
                    return static::$_isAssignableFromCache[$cacheKey] = true;
                }
            }

            return static::$_isAssignableFromCache[$cacheKey] = false;
        }

        if ($other instanceof UnionTypeHint) {
            foreach ($this->_types as $thisSubType) {
                if ($thisSubType->isAssignableTo($other)) {
                    return static::$_isAssignableFromCache[$cacheKey] = true;
                }
            }

            return static::$_isAssignableFromCache[$cacheKey] = false;
        }

        if ($other instanceof self) {
            foreach ($other->types as $otherSubType) {
                if (!$this->isAssignableTo($otherSubType)) {
                    return static::$_isAssignableFromCache[$cacheKey] = false;
                }
            }

            return static::$_isAssignableFromCache[$cacheKey] = true;
        }

        throw new NotSupportedException("Unsupported TypeHint subclass: " . get_class($other));
    }
}
