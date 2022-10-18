<?php

declare(strict_types=1);

namespace PhpQueues\LaravelRabbitmq;

/**
 * @psalm-type Properties = array{
 *     mandatory?:bool,
 *     immediate?:bool,
 *     headers?:array<string, mixed>,
 * }
 */
interface ContainsProperties
{
    /**
     * @psalm-return Properties
     */
    public function properties(): array;
}
