<?php

declare(strict_types=1);

namespace PhpQueues\LaravelRabbitmq\Tests;

use PhpQueues\RabbitmqTransport\AmqpMessage;
use PhpQueues\RabbitmqTransport\AmqpOperator;
use PhpQueues\RabbitmqTransport\AmqpProducer;
use PhpQueues\RabbitmqTransport\Connection\AmqpConnector;
use PhpQueues\RabbitmqTransport\TransportConfigurator;
use PhpQueues\Transport\Consumer;
use PhpQueues\Transport\Producer;

/**
 * @psalm-suppress PropertyNotSetInConstructor
 */
final class NullConnector extends AmqpConnector
{
    private AmqpOperator $operator;

    /**
     * @var AmqpProducer<AmqpMessage>
     */
    private AmqpProducer $producer;
    private Consumer $consumer;

    public function withOperator(AmqpOperator $operator): NullConnector
    {
        $this->operator = $operator;

        return $this;
    }

    public function withProducer(AmqpProducer $producer): NullConnector
    {
        $this->producer = $producer;

        return $this;
    }

    public function withConsumer(Consumer $consumer): NullConnector
    {
        $this->consumer = $consumer;

        return $this;
    }

    public function configurator(): TransportConfigurator
    {
        return new TransportConfigurator($this->operator);
    }

    /**
     * {@inheritdoc}
     */
    public function producer(): Producer
    {
        return $this->producer;
    }

    public function consumer(): Consumer
    {
        return $this->consumer;
    }

    public function stop(): void
    {
    }
}
