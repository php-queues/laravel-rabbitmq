<?php

declare(strict_types=1);

namespace PhpQueues\LaravelRabbitmq\Tests;

use Illuminate\Container\Container;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\CallQueuedHandler;
use Illuminate\Support\Str;
use PhpQueues\LaravelRabbitmq\DeliveryOptions;
use PhpQueues\LaravelRabbitmq\LarabbitmqQueue;
use PhpQueues\RabbitmqTransport\AmqpDestination;
use PhpQueues\RabbitmqTransport\AmqpMessage;
use PhpQueues\RabbitmqTransport\AmqpOperator;
use PhpQueues\RabbitmqTransport\AmqpProducer;
use PhpQueues\RabbitmqTransport\Connection\ConnectionContext;
use PhpQueues\RabbitmqTransport\Connection\Dsn;
use PhpQueues\RabbitmqTransport\Exchange;
use PhpQueues\RabbitmqTransport\PackagePublisher;
use PhpQueues\RabbitmqTransport\Queue;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * @psalm-suppress PropertyNotSetInConstructor
 */
final class LarabbitmqQueueTest extends TestCase
{
    public function testThatQueueSizeIsAlwaysZero(): void
    {
        self::assertEquals(0, $this->createQueue()->size('events'));
    }

    public function testJobPushed(): void
    {
        $operator = $this->createMock(AmqpOperator::class);

        $operator
            ->expects($this->exactly(1))
            ->method('bindQueue')
            ->with(Queue::default('test')->makeDurable(), Exchange::direct('main')->makeDurable(), 'test');

        $packagePublisher = $this->createMock(PackagePublisher::class);

        $packagePublisher
            ->expects($this->exactly(1))
            ->method('publish');

        $queue = $this->createQueue($operator, new AmqpProducer($packagePublisher));

        $queue->push(new TestJob(), '', 'test');
    }

    public function testJobLaterWithDelayedExchangePlugin(): void
    {
        if (\extension_loaded('json') === false) {
            $this->markTestSkipped('Test requires ext-json to be installed.');
        }

        $id = Uuid::uuid4();

        Str::createUuidsUsing(fn (): UuidInterface => $id);

        $operator = $this->createMock(AmqpOperator::class);

        $operator
            ->expects($this->exactly(1))
            ->method('bindQueue')
            ->with(Queue::default('test')->makeDurable(), Exchange::direct('main')->makeDurable(), 'test');

        $packagePublisher = $this->createMock(PackagePublisher::class);

        $packagePublisher
            ->expects($this->exactly(1))
            ->method('publish')
            ->with(new AmqpMessage($id->toString(), \json_encode([
                'uuid' => $id->toString(),
                'displayName' => TestJob::class,
                'job' => CallQueuedHandler::class.'@call',
                'maxTries' => null,
                'maxExceptions' => null,
                'failOnTimeout' => false,
                'backoff' => null,
                'timeout' => null,
                'retryUntil' => null,
                'data' => [
                    'commandName' => TestJob::class,
                    'command' => \serialize(new TestJob()),
                ],
                'attempts' => 0,
            ]), new AmqpDestination('delayed_main', 'test'), ['content-type' => 'application/json', 'x-trace-id' => $id->toString(), 'x-delay' => 10000], true));

        $queue = $this->createQueue($operator, new AmqpProducer($packagePublisher));

        $queue->later(10, new TestJob(), '', 'test');
    }

    public function testJobLaterWithDeadLetterExchange(): void
    {
        if (\extension_loaded('json') === false) {
            $this->markTestSkipped('Test requires ext-json to be installed.');
        }

        $id = Uuid::uuid4();

        Str::createUuidsUsing(fn (): UuidInterface => $id);

        $operator = $this->createMock(AmqpOperator::class);

        $operator
            ->expects($this->exactly(1))
            ->method('bindQueue')
            ->with(Queue::default('test')->makeDurable(), Exchange::direct('main')->makeDurable(), 'test');

        $packagePublisher = $this->createMock(PackagePublisher::class);

        $packagePublisher
            ->expects($this->exactly(1))
            ->method('publish')
            ->with(new AmqpMessage($id->toString(), \json_encode([
                'uuid' => $id->toString(),
                'displayName' => TestJob::class,
                'job' => CallQueuedHandler::class.'@call',
                'maxTries' => null,
                'maxExceptions' => null,
                'failOnTimeout' => false,
                'backoff' => null,
                'timeout' => null,
                'retryUntil' => null,
                'data' => [
                    'commandName' => TestJob::class,
                    'command' => \serialize(new TestJob()),
                ],
                'attempts' => 0,
            ]), new AmqpDestination('', 'test.delay.10000'), ['content-type' => 'application/json', 'x-trace-id' => $id->toString()], true));

        $queue = $this->createQueue($operator, new AmqpProducer($packagePublisher), ConnectionContext::DELAY_TYPE_DEAD_LETTER);

        $queue->later(10, new TestJob(), '', 'test');
    }

    /**
     * @psalm-param ConnectionContext::DELAY_TYPE_*|null $delayType
     */
    private function createQueue(?AmqpOperator $operator = null, ?AmqpProducer $producer = null, ?string $delayType = null): LarabbitmqQueue
    {
        /** @var NullConnector $nullConnector */
        $nullConnector = NullConnector::connect(new ConnectionContext(Dsn::fromString('amqp://localhost'), $delayType ?: ConnectionContext::DELAY_TYPE_DELAYED_EXCHANGE), new NullLogger());

        if ($operator !== null) {
            $nullConnector = $nullConnector->withOperator($operator);
        }

        if ($producer !== null) {
            $nullConnector = $nullConnector->withProducer($producer);
        }

        $queue = new LarabbitmqQueue($nullConnector, new DeliveryOptions('main', 'delayed_main'));
        $queue->setContainer(new Container());

        return $queue;
    }
}

final class TestJob implements ShouldQueue
{
    public string $queue = 'test';
    public string $connection = 'larabbitmq';

    public function handle(): void
    {
        //
    }
}