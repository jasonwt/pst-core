<?php

declare(strict_types=1);

namespace PST\Core\Tests\Functionality;

use PHPUnit\Framework\TestCase;
use PST\Core\Action;
use PST\Core\Exceptions\ArgumentException;
use PST\Core\Exceptions\ArgumentOutOfRangeException;
use PST\Core\TypeHint;

class ActionFixtureBase {}

class ActionFixtureChild extends ActionFixtureBase {}

final class ActionTest extends TestCase {
    public function testInvokeExecutesWrappedClosure(): void {
        $calls = [];
        $action = new Action(
            static function (string $message) use (&$calls): void {
                $calls[] = $message;
            },
            TypeHint::string()
        );

        $result = $action('hello');

        $this->assertNull($result);
        $this->assertSame(['hello'], $calls);
    }

    public function testConstructorStoresParameterTypesInOrder(): void {
        $action = new Action(
            static function (int $count, bool $enabled): void {},
            TypeHint::int(),
            TypeHint::bool()
        );

        $this->assertCount(2, $action->TParameters);
        $this->assertSame('int', $action->TParameters[0]->fullName);
        $this->assertSame('bool', $action->TParameters[1]->fullName);
    }

    public function testInvokeRejectsParameterCountMismatch(): void {
        $action = new Action(
            static function (mixed $value): void {},
            TypeHint::string()
        );

        $this->expectException(ArgumentOutOfRangeException::class);
        $action();
    }

    public function testInvokeRejectsParameterTypeMismatchUsingTypeHintAssignability(): void {
        $action = new Action(
            static function (mixed $value): void {},
            TypeHint::int()
        );

        $this->expectException(ArgumentException::class);
        $action('not-an-int');
    }

    public function testInvokeAllowsSubtypeArgumentForDeclaredBaseType(): void {
        $calls = 0;
        $action = new Action(
            static function (mixed $value) use (&$calls): void {
                $calls++;
            },
            TypeHint::{'class'}(ActionFixtureBase::class)
        );

        $action(new ActionFixtureChild());

        $this->assertSame(1, $calls);
    }

    public function testInvokeSupportsUnionParameterTypes(): void {
        $calls = [];
        $action = new Action(
            static function (mixed $value) use (&$calls): void {
                $calls[] = $value;
            },
            TypeHint::union(TypeHint::int(), TypeHint::string())
        );

        $action(7);
        $action('seven');

        $this->assertSame([7, 'seven'], $calls);
    }

    public function testIsAssignableToAndFromUseContravariantParameterRules(): void {
        $source = new Action(
            static function (mixed $value): void {},
            TypeHint::{'class'}(ActionFixtureBase::class)
        );

        $target = new Action(
            static function (mixed $value): void {},
            TypeHint::{'class'}(ActionFixtureChild::class)
        );

        $this->assertTrue($source->isAssignableTo(...$target->TParameters));
        $this->assertTrue($target->isAssignableFrom(...$source->TParameters));

        $this->assertFalse($target->isAssignableTo(...$source->TParameters));
        $this->assertFalse($source->isAssignableFrom(...$target->TParameters));
    }
}
