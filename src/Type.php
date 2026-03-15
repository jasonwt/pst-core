<?php

declare(strict_types=1);

namespace PST\Core;

use ReflectionClass;
use PST\Core\Exceptions\ArgumentException;
use PST\Core\Exceptions\NotSupportedException;
use Traversable;

final class Type extends TypeHint {
    public const array SCALER_TYPE_ALIASES = [
        'boolean' => 'bool',
        'integer' => 'int',
        'double' => 'float',
        'string' => 'string',
    ];

    public const array SCALAR_TYPE_ALIASES = self::SCALER_TYPE_ALIASES;

    private const array SCALAR_TYPE_NAME_SET = [
        'bool' => true,
        'int' => true,
        'float' => true,
        'string' => true,
    ];

    private array $_properties = [
        'isCallable' => false,
        'isIterable' => false,
        'isClass' => false,
        'isEnum' => false,
        'isInterface' => false,
    ];

    public readonly string $name;
    public readonly string $namespace;

    public bool $isArray {get => $this->fullName === 'array';}
    public bool $isBoolean {get => $this->fullName === 'bool';}
    public bool $isCallable {get => $this->_properties['isCallable'];}
    public bool $isClass {get => $this->_properties['isClass'];}
    public bool $isEnum {get => $this->_properties['isEnum'];}
    public bool $isFloat {get => $this->fullName === 'float';}
    public bool $isInteger {get => $this->fullName === 'int';}
    public bool $isInterface {get => $this->_properties['isInterface'];}
    public bool $isIterable {get => $this->_properties['isIterable'];}
    public bool $isNull {get => $this->fullName === 'null';}
    public bool $isString {get => $this->fullName === 'string';}

    public bool $isNumeric {get => $this->isInteger || $this->isFloat;}
    public bool $isScalar {get => $this->isBoolean || $this->isInteger || $this->isFloat || $this->isString;}
    public bool $isObjectType {get => $this->isClass || $this->isInterface || $this->isEnum;}

    private function __construct(string $fullName, null|string $objectType = null) {
        if ($objectType !== null) {
            if (static::isInvokableObjectType($fullName)) {
                $this->_properties['isCallable'] = true;
            }

            if (is_a($fullName, Traversable::class, true)) {
                $this->_properties['isIterable'] = true;
            }

            $this->_properties = match($objectType) {
                'Class' => ['isClass' => true],
                'Interface' => ['isInterface' => true],
                'Enum' => ['isEnum' => true],
                default => throw new ArgumentException("Invalid object type: $objectType", 'objectType'),
            } + $this->_properties;
        } else {
            if ($fullName === 'array') {
                $this->_properties['isIterable'] = true;
            }
        }

        if ($this->isObjectType) {
            $fullNameParts = explode('\\', $fullName);
            $this->name = array_pop($fullNameParts);
            $this->namespace = implode('\\', $fullNameParts);
        } else {
            $this->name = $fullName;
            $this->namespace = '';
        }

        parent::__construct($fullName);
    }

    // public function default(): mixed {
    //     return match (true) {
    //         $this->isBoolean => false,
    //         $this->isInteger => 0,
    //         $this->isFloat => 0.0,
    //         $this->isString => '',
    //         default => null,
    //     };
    // }

    public function isAssignableFrom(TypeHint $other): bool {
        $thisFullName = $this->fullName;
        $otherFullName = $other->fullName;

        if ($thisFullName === $otherFullName) {
            return true;
        }

        if (!$this->isClass && !$this->isInterface) {
            return false;
        }

        $cacheKey = "$thisFullName<=$otherFullName";

        if (isset(static::$_isAssignableFromCache[$cacheKey])) {
            return static::$_isAssignableFromCache[$cacheKey];
        }

        if ($other instanceof self) {
            return static::$_isAssignableFromCache[$cacheKey] =
                ($other->isClass || $other->isInterface) &&
                is_a($other->fullName, $this->fullName, true);
        }

        if ($other instanceof UnionTypeHint) {
            foreach ($other->types as $otherSubType) {
                if (!$this->isAssignableFrom($otherSubType)) {
                    return static::$_isAssignableFromCache[$cacheKey] = false;
                }
            }

            return static::$_isAssignableFromCache[$cacheKey] = true;
        }

        if ($other instanceof IntersectionTypeHint) {
            foreach ($other->types as $otherSubType) {
                if ($this->isAssignableFrom($otherSubType)) {
                    return static::$_isAssignableFromCache[$cacheKey] = true;
                }
            }

            return static::$_isAssignableFromCache[$cacheKey] = false;
        }

        if ($other instanceof SpecialTypeHint) {
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

        if ($other instanceof self) {
            return static::$_isAssignableFromCache[$cacheKey] =
                ($this->isClass || $this->isInterface) &&
                ($other->isClass || $other->isInterface) &&
                is_a($this->fullName, $other->fullName, true);
        }

        if ($other instanceof UnionTypeHint) {
            foreach ($other->types as $otherSubType) {
                if ($this->isAssignableTo($otherSubType)) {
                    return static::$_isAssignableFromCache[$cacheKey] = true;
                }
            }

            return static::$_isAssignableFromCache[$cacheKey] = false;
        }

        if ($other instanceof IntersectionTypeHint) {
            foreach ($other->types as $otherSubType) {
                if (!$this->isAssignableTo($otherSubType)) {
                    return static::$_isAssignableFromCache[$cacheKey] = false;
                }
            }

            return static::$_isAssignableFromCache[$cacheKey] = true;
        }

        if ($other instanceof SpecialTypeHint) {
            return static::$_isAssignableFromCache[$cacheKey] =
                ($this->isCallable && $otherFullName === 'callable') ||
                ($this->isInterface && $otherFullName === 'interface') ||
                ($this->isClass && $otherFullName === 'class') ||
                ($this->isEnum && $otherFullName === 'enum') ||
                ($this->isIterable && $otherFullName === 'iterable');
        }

        throw new NotSupportedException("Unsupported TypeHint subclass: " . get_class($other));
    }

    public static function typeOf(string $typeName): null|static {
        if (($typeName = trim($typeName)) === '') {
            throw new ArgumentException('Type name cannot be empty.', 'typeName');
        }

        if ($typeName === 'resource') {
            throw new NotSupportedException('Cannot create Type from resource.');
        }

        if (strtolower($typeName) === 'null') {
            return static::getCachedConcreteType('null') ?? new static('null');
        }

        if ($typeName === 'array') {
            return static::getCachedConcreteType('array') ?? new static('array');
        }

        $normalizedName = self::SCALAR_TYPE_ALIASES[$typeName] ?? $typeName;

        if (($cachedType = static::getCachedConcreteType($normalizedName)) !== null) {
            return $cachedType;
        }

        return match (true) {
            isset(self::SCALAR_TYPE_NAME_SET[$normalizedName]) => new static($normalizedName),
            enum_exists($normalizedName) => new static($normalizedName, 'Enum'),
            interface_exists($normalizedName) => new static($normalizedName, 'Interface'),
            class_exists($normalizedName) => new static($normalizedName, 'Class'),
            default => null,
        };
    }

    public static function ofInstance(mixed $instance): null|static {
        return is_object($instance)
            ? static::typeOf($instance::class)
            : static::typeOf(gettype($instance));
    }

    private static function getCachedConcreteType(string $fullName): null|static {
        $cachedType = static::$_instanceCache[$fullName] ?? null;

        return $cachedType instanceof static
            ? $cachedType
            : null;
    }

    private static function isInvokableObjectType(string $fullName): bool {
        $reflectionType = new ReflectionClass($fullName);

        if (!$reflectionType->hasMethod('__invoke')) {
            return false;
        }

        return $reflectionType->getMethod('__invoke')->isPublic();
    }

    public static function clearCaches(): void {
        parent::clearCaches();
    }
}
