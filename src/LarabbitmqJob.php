<?php

declare(strict_types=1);

namespace PhpQueues\LaravelRabbitmq;

use Illuminate\Container\Container;
use Illuminate\Contracts\Queue\Job as JobContract;
use Illuminate\Queue\Jobs\Job;
use PhpQueues\Transport\Package;

/**
 * @psalm-suppress PropertyNotSetInConstructor
 */
final class LarabbitmqJob extends Job implements JobContract
{
    private Package $package;
    private LarabbitmqQueue $amqp;

    public function __construct(Package $package, LarabbitmqQueue $amqp, string $queue, string $connectionName, Container $container)
    {
        $this->package = $package;
        $this->amqp = $amqp;
        $this->queue = $queue;
        $this->connectionName = $connectionName;
        $this->container = $container;
    }

    /**
     * @psalm-return non-empty-string
     */
    public function getJobId(): string
    {
        return $this->package->id();
    }

    /**
     * {@inheritdoc}
     */
    public function markAsFailed(): void
    {
        parent::markAsFailed();

        $this->package->reject(false);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(): void
    {
        parent::delete();

        if ($this->failed === false) {
            $this->package->ack();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function release($delay = 0): void
    {
        parent::release();

        $this->amqp->laterRaw($delay, $this->package->content(), $this->queue);

        $this->package->ack();
    }

    /**
     * {@inheritdoc}
     */
    public function getRawBody(): string
    {
        return $this->package->content();
    }

    /**
     * {@inheritdoc}
     */
    public function attempts(): int
    {
        return (int) ($this->package->headers()['x-attempts'] ?? 0);
    }
}
