<?php

declare(strict_types=1);

namespace PhpQueues\LaravelRabbitmq;

final class DeliveryOptions
{
    /**
     * @psalm-readonly
     *
     * @psalm-var non-empty-string
     */
    public string $mainExchange;

    /**
     * @psalm-readonly
     *
     * @psalm-var non-empty-string
     */
    public string $delayedExchange;

    /**
     * @psalm-readonly
     *
     * @psalm-var non-empty-string
     */
    public string $defaultQueue;

    public function __construct(string $mainExchange, ?string $delayedExchange = null, ?string $defaultQueue = null)
    {
        if ('' === $mainExchange) {
            throw new \InvalidArgumentException('Main exchange cannot be empty.');
        }

        $this->mainExchange = $mainExchange;
        $this->delayedExchange = ($delayedExchange === null || $delayedExchange === '') ? "{$mainExchange}_delayed" : $delayedExchange;
        $this->defaultQueue = ($defaultQueue === null || $defaultQueue === '') ? 'default' : $defaultQueue;
    }
}
