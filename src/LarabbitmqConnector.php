<?php

declare(strict_types=1);

namespace PhpQueues\LaravelRabbitmq;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Queue\Connectors\ConnectorInterface;
use Illuminate\Queue\Events\WorkerStopping;
use Illuminate\Queue\Queue;
use PhpQueues\RabbitmqTransport\Connection\ConnectionContext;
use PhpQueues\RabbitmqTransport\Connection\Dsn;
use Psr\Log\LoggerInterface;

final class LarabbitmqConnector implements ConnectorInterface
{
    private LoggerInterface $logger;
    private Dispatcher $dispatcher;

    public function __construct(LoggerInterface $logger, Dispatcher $dispatcher)
    {
        $this->logger = $logger;
        $this->dispatcher = $dispatcher;
    }

    public function connect(array $config): Queue
    {
        /** @var array{
         *     connection:array{
         *         scheme:string,
         *         host:string,
         *         port:positive-int,
         *         vhost:string,
         *         user:string,
         *         password:string,
         *         ssl?:array{
         *          cafile:?string,
         *          local_cert:?string,
         *          local_pk:?string
         *        }
         *   },
         *   options: array{delay_type:ConnectionContext::DELAY_TYPE_*, main_exchange:non-empty-string, delayed_exchange?:string|null, default_queue?:string|null},
         *   connector:class-string<\PhpQueues\RabbitmqTransport\Connection\AmqpConnector>
         * } $queueConfig
         */
        $queueConfig = $config;

        $connectorName = $queueConfig['connector'];

        $connector = $connectorName::connect(
            new ConnectionContext(Dsn::fromArray($queueConfig['connection']), $queueConfig['options']['delay_type']),
            $this->logger,
        );

        $this->dispatcher->listen(WorkerStopping::class, function () use ($connector): void {
            $connector->stop();
        });

        return new LarabbitmqQueue(
            $connector,
            new DeliveryOptions(
                $queueConfig['options']['main_exchange'],
                $queueConfig['options']['delayed_exchange'] ?? null,
                $queueConfig['options']['default_queue'] ?? null,
            )
        );
    }
}
