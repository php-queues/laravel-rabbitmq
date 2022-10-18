<?php

declare(strict_types=1);

namespace PhpQueues\LaravelRabbitmq;

use Illuminate\Contracts\Queue\Queue as QueueContract;
use Illuminate\Queue\Queue as LaravelQueue;
use Illuminate\Support\Str;
use PhpQueues\RabbitmqTransport\AmqpDestination;
use PhpQueues\RabbitmqTransport\AmqpMessage;
use PhpQueues\RabbitmqTransport\Connection\AmqpConnector;
use PhpQueues\RabbitmqTransport\Delay\AmqpDelayDestination;
use PhpQueues\RabbitmqTransport\Exchange;
use PhpQueues\RabbitmqTransport\Queue;
use PhpQueues\RabbitmqTransport\QueueBinding;

/**
 * @psalm-suppress PropertyNotSetInConstructor
 *
 * @psalm-import-type Properties from ContainsProperties as AmqpProperties
 */
final class LarabbitmqQueue extends LaravelQueue implements QueueContract
{
    private AmqpConnector $connector;
    private DeliveryOptions $deliveryOptions;

    public function __construct(AmqpConnector $connector, DeliveryOptions $deliveryOptions)
    {
        $this->connector = $connector;
        $this->deliveryOptions = $deliveryOptions;
    }

    /**
     * {@inheritdoc}
     */
    public function size($queue = null)
    {
        return 0;
    }

    /**
     * @psalm-suppress RedundantCastGivenDocblockType
     *
     * {@inheritdoc}
     */
    public function bulk($jobs, $data = '', $queue = null)
    {
        $messages = [];

        $queue = $this->getQueue($queue);

        $this->createQueue($queue);

        /** @var object|string $job */
        foreach ((array) $jobs as $job) {
            $messages[] = $this->createMessage(
                $this->createPayload($job, $queue, $data),
                new AmqpDestination($this->deliveryOptions->mainExchange, $queue),
                $job instanceof ContainsProperties ? $job->properties() : [],
            );
        }

        $this->enqueueUsing('', '', $queue, null, function () use ($messages): void {
            $this->connector->producer()->publish(...$messages);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function push($job, $data = '', $queue = null)
    {
        return $this->enqueueUsing(
            $job,
            $this->createPayload($job, $this->getQueue($queue), $data),
            $this->getQueue($queue),
            null,
            function (string $payload, string $queue) use ($job): string {
                /** @var string */
                return $this->pushRaw(
                    $payload,
                    $queue,
                    [
                        'properties' => $job instanceof ContainsProperties
                            ? $job->properties()
                            : [],
                    ]
                );
            }
        );
    }

    /**
     * @param string $payload
     * @param null|string $queue
     * @psalm-param array{properties?:AmqpProperties, attempts?:int} $options
     *
     * @psalm-suppress MoreSpecificImplementedParamType
     *
     * {@inheritdoc}
     */
    public function pushRaw($payload, $queue = null, array $options = [])
    {
        $queue = $this->getQueue($queue);

        $this->createQueue($queue);

        $options['properties']['headers']['x-attempts'] = $options['attempts'] ?? 0;

        $message = $this->createMessage(
            $payload,
            new AmqpDestination($this->deliveryOptions->mainExchange, $queue),
            $options['properties'] ?? [],
        );

        $this->connector->producer()->publish($message);

        return $message->id;
    }

    /**
     * {@inheritdoc}
     */
    public function later($delay, $job, $data = '', $queue = null)
    {
        $queue = $this->getQueue($queue);

        return $this->enqueueUsing(
            $job,
            $this->createPayload($job, $queue, $data),
            $queue,
            $delay,
            function (string $payload, string $queue, int $delay) use ($job): string {
                /** @var string */
                return $this->laterRaw(
                    $delay,
                    $payload,
                    $queue,
                    $job instanceof ContainsProperties ? $job->properties() : [],
                );
            }
        );
    }

    /**
     * {@inheritdoc}
     */
    public function pop($queue = null)
    {
        $queue = $this->getQueue($queue);

        $this->createQueue($queue);

        $package = $this->connector->consumer()->once($queue);

        if ($package !== null) {
            return new LarabbitmqJob($package, $this, $queue, $this->connectionName, $this->container);
        }

        return null;
    }

    /**
     * @psalm-param AmqpProperties $properties
     *
     * @psalm-return non-empty-string
     */
    public function laterRaw(int $delay, string $payload, string $queue, array $properties = []): string
    {
        $ttl = $this->secondsUntil($delay) * 1000;

        if ($ttl <= 0) {
            /** @var non-empty-string */
            return $this->pushRaw($payload, $queue);
        }

        $queue = $this->getQueue($queue);

        $this->createQueue($queue);

        $message = $this->createMessage($payload, new AmqpDestination($this->deliveryOptions->mainExchange, $queue), $properties);

        $this->connector->delayer()->delay(
            $this->connector->producer(),
            $message,
            new AmqpDelayDestination($queue, $queue, $this->deliveryOptions->delayedExchange, '%queue_name%.delay.%ttl%'),
            $ttl,
        );

        return $message->id;
    }

    /**
     * {@inheritdoc}
     */
    protected function createPayloadArray($job, $queue, $data = ''): array
    {
        return array_merge(parent::createPayloadArray($job, $queue, $data), [
            'attempts' => 0,
        ]);
    }

    /**
     * @psalm-return non-empty-string
     */
    private function getQueue(?string $queue = null): string
    {
        /** @psalm-var non-empty-string */
        return ($queue === null || $queue === '') ? $this->deliveryOptions->defaultQueue : $queue;
    }

    /**
     * @psalm-param non-empty-string $queueName
     */
    private function createQueue(string $queueName): void
    {
        $this->connector->configurator()->bindQueue(
            Queue::default($queueName)->makeDurable(),
            new QueueBinding(Exchange::direct($this->deliveryOptions->mainExchange)->makeDurable(), $queueName)
        );
    }

    /**
     * @param AmqpProperties $properties
     */
    private function createMessage(string $body, AmqpDestination $destination, array $properties = []): AmqpMessage
    {
        $id = Str::uuid()->toString();

        return new AmqpMessage(
            $id,
            $body,
            $destination,
            ($properties['headers'] ?? []) + ['content-type' => 'application/json', 'x-trace-id' => $id],
            true,
            $properties['mandatory'] ?? false,
            $properties['immediate'] ?? false,
        );
    }
}
