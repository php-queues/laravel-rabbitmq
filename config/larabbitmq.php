<?php

declare(strict_types=1);

use PhpQueues\RabbitmqTransport\Connection\ConnectionContext;

return [
    'driver' => 'larabbitmq',
    'connection' => [
        'scheme'   => 'amqp',
        'host'     => env('LARABBITMQ_HOST', '127.0.0.1'),
        'port'     => env('LARABBITMQ_PORT', 5672),
        'vhost'    => env('LARABBITMQ_VHOST', '/'),
        'user'     => env('LARABBITMQ_USER', 'guest'),
        'password' => env('LARABBITMQ_PASSWORD', 'guest'),
        'ssl'      => [
            'cafile'     => env('LARABBITMQ_SSL_CAFILE'),
            'local_cert' => env('LARABBITMQ_SSL_LOCALCERT'),
            'local_pk'   => env('LARABBITMQ_SSL_LOCALKEY'),
        ],
    ],
    'options' => [
        'delay_type' => env('LARABBITMQ_DELAY_TYPE', ConnectionContext::DELAY_TYPE_DEAD_LETTER),
        'main_exchange' => env('LARABBITMQ_MAIN_EXCHANGE', env('APP_NAME', 'default')),
        'delayed_exchange' => env('LARABBITMQ_DELAYED_EXCHANGE'),
        'default_queue' => env('LARABBITMQ_DEFAULT_QUEUE'),
    ],
    'connector' => PhpQueues\RabbitmqTransport\Connection\AmqpConnector::class, // Replace with the selected driver.
];
