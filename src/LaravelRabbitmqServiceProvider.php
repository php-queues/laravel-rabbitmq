<?php

declare(strict_types=1);

namespace PhpQueues\LaravelRabbitmq;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Queue\QueueManager;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;

final class LaravelRabbitmqServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/larabbitmq.php',
            'queue.connections.larabbitmq'
        );
    }

    public function boot(): void
    {
        /** @var QueueManager $queue */
        $queue = $this->app->get('queue');

        $queue->addConnector('larabbitmq', function (): LarabbitmqConnector {
            /** @var Dispatcher $events */
            $events = $this->app->get('events');

            /** @var LoggerInterface $logger */
            $logger = $this->app->get(LoggerInterface::class);

            return new LarabbitmqConnector($logger, $events);
        });
    }
}
