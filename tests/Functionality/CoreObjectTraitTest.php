<?php

declare(strict_types=1);

namespace PST\Core\Tests\Functionality;

use PHPUnit\Framework\TestCase;
use PST\Core\CoreObject;
use PST\Core\Type;

final class CoreObjectTraitTest extends TestCase {
    public function testToStringReturnsConcreteClassName(): void {
        $fixture = new CoreObjectFixture();

        $this->assertSame(CoreObjectFixture::class, (string) $fixture);
    }

    public function testGetTypeReturnsConcreteClassType(): void {
        $fixture = new CoreObjectFixture();

        $type = $fixture->getType();

        $this->assertInstanceOf(Type::class, $type);
        $this->assertSame(CoreObjectFixture::class, $type->fullName);
        $this->assertTrue($type->isClass);
    }

    public function testGetHashCodeIsStablePerInstanceAndDistinctAcrossSiblingInstances(): void {
        $first = new CoreObjectHashFixture();
        $second = new CoreObjectHashFixture();

        $firstHash = $first->getHashCode();

        $this->assertIsInt($firstHash);
        $this->assertSame($firstHash, $first->getHashCode());
        $this->assertNotSame($firstHash, $second->getHashCode());
    }
}

final class CoreObjectFixture extends CoreObject {
    public function __construct() {
        parent::__construct();
    }
}

final class CoreObjectHashFixture extends CoreObject {
    public function __construct() {
        parent::__construct();
    }
}
