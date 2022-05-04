<?php

declare(strict_types=1);

namespace PhpQueues\LaravelRabbitmq\Tests;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Queue\Events\WorkerStopping;
use PhpQueues\LaravelRabbitmq\LarabbitmqConnector;
use PhpQueues\RabbitmqTransport\Connection\ConnectionContext;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * @psalm-suppress PropertyNotSetInConstructor
 */
final class LarabbitmqConnectorTest extends TestCase
{
    public function testSubscribedOnWorkerStopping(): void
    {
        $dispatcher = $this->createMock(Dispatcher::class);

        $dispatcher
            ->expects($this->exactly(1))
            ->method('listen')
            ->with(WorkerStopping::class);

        $connector = new LarabbitmqConnector(new NullLogger(), $dispatcher);

        $connector->connect([
            'driver' => 'larabbitmq',
            'connection' => [
                'scheme'   => 'amqp',
                'host'     => 'localhost',
                'port'     => 5673,
                'vhost'    => '/',
                'user'     => 'guest',
                'password' => 'guest',
            ],
            'options' => [
                'delay_type' => ConnectionContext::DELAY_TYPE_DELAYED_EXCHANGE,
                'main_exchange' => 'develop',
                'delayed_exchange' => 'delayed_develop',
                'default_queue' => 'default',
            ],
            'connector' => NullConnector::class,
        ]);
    }
}
