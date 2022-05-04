<?php

declare(strict_types=1);

namespace PhpQueues\LaravelRabbitmq\Tests;

use PhpQueues\LaravelRabbitmq\DeliveryOptions;
use PHPUnit\Framework\TestCase;

/**
 * @psalm-suppress PropertyNotSetInConstructor
 */
final class DeliveryOptionsTest extends TestCase
{
    public function testEmptyMainExchange(): void
    {
        self::expectException(\InvalidArgumentException::class);
        new DeliveryOptions('');
    }

    public function testDefaultOptions(): void
    {
        $options = new DeliveryOptions('develop');
        self::assertEquals('develop', $options->mainExchange);
        self::assertEquals('develop_delayed', $options->delayedExchange);
        self::assertEquals('default', $options->defaultQueue);
    }

    public function testCustomOptions(): void
    {
        $options = new DeliveryOptions('develop', 'scheduler', 'test');
        self::assertEquals('develop', $options->mainExchange);
        self::assertEquals('scheduler', $options->delayedExchange);
        self::assertEquals('test', $options->defaultQueue);
    }
}
