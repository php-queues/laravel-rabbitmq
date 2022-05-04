<?php

declare(strict_types=1);

namespace PhpQueues\LaravelRabbitmq\Tests;

use Illuminate\Container\Container;
use PhpQueues\LaravelRabbitmq\DeliveryOptions;
use PhpQueues\LaravelRabbitmq\LarabbitmqJob;
use PhpQueues\LaravelRabbitmq\LarabbitmqQueue;
use PhpQueues\RabbitmqTransport\AmqpOperator;
use PhpQueues\RabbitmqTransport\AmqpProducer;
use PhpQueues\RabbitmqTransport\Connection\ConnectionContext;
use PhpQueues\RabbitmqTransport\Connection\Dsn;
use PhpQueues\RabbitmqTransport\PackagePublisher;
use PhpQueues\Transport\Package;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * @psalm-suppress PropertyNotSetInConstructor
 */
final class LarabbitmqJobTest extends TestCase
{
    public function testJobDeleted(): void
    {
        $package = $this->createMock(Package::class);

        $package
            ->expects($this->exactly(1))
            ->method('ack');

        $job = new LarabbitmqJob($package, $this->createQueue(), 'test', 'larrabitmq', new Container());

        $job->delete();
    }

    public function testJobMarkAsFailed(): void
    {
        $package = $this->createMock(Package::class);

        $package
            ->expects($this->exactly(1))
            ->method('reject');

        $job = new LarabbitmqJob($package, $this->createQueue(), 'test', 'larrabitmq', new Container());

        $job->markAsFailed();
    }

    public function testJobReleased(): void
    {
        $package = $this->createMock(Package::class);

        $package
            ->expects($this->exactly(1))
            ->method('ack');

        $operator = $this->createMock(AmqpOperator::class);

        $operator
            ->expects($this->exactly(1))
            ->method('bindQueue');

        $packagePublisher = $this->createMock(PackagePublisher::class);

        $packagePublisher
            ->expects($this->exactly(1))
            ->method('publish');

        $job = new LarabbitmqJob($package, $this->createQueue($operator, new AmqpProducer($packagePublisher)), 'test', 'larrabitmq', new Container());

        $job->release(1000);
    }

    private function createQueue(?AmqpOperator $operator = null, ?AmqpProducer $producer = null): LarabbitmqQueue
    {
        /** @var NullConnector $nullConnector */
        $nullConnector = NullConnector::connect(new ConnectionContext(Dsn::fromString('amqp://localhost'), ConnectionContext::DELAY_TYPE_DELAYED_EXCHANGE), new NullLogger());

        if ($operator !== null) {
            $nullConnector = $nullConnector->withOperator($operator);
        }

        if ($producer !== null) {
            $nullConnector = $nullConnector->withProducer($producer);
        }

        return new LarabbitmqQueue($nullConnector, new DeliveryOptions('main'));
    }
}
