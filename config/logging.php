<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Log Channel
    |--------------------------------------------------------------------------
    |
    | This option defines the default log channel that gets used when writing
    | messages to the logs. The name specified in this option should match
    | one of the channels defined in the "channels" configuration array.
    |
    */

    'default' => env('LOG_CHANNEL', 'stack'),

    /*
    |--------------------------------------------------------------------------
    | Log Channels
    |--------------------------------------------------------------------------
    |
    | Here you may configure the log channels for your application. Out of
    | the box, Laravel uses the Monolog PHP logging library. This gives
    | you a variety of powerful log handlers / formatters to utilize.
    |
    | Available Drivers: "single", "daily", "slack", "syslog",
    |                    "errorlog", "custom", "stack"
    |
    */

    'channels' => [
        'stack' => [
            'driver' => 'stack',
            'channels' => ['single'],
        ],
        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/lumen.log'),
            'level' => 'debug',
        ],
        'console' => [
            'driver' => 'single',
            'path'  => storage_path('logs/console.log'),
            'level' => 'debug',
        ],
        'print' => [
            'driver' => 'daily',
            'path'  => storage_path('logs/print/print.log'),
            'level' => 'debug',
        ],
        'close_order' => [
            'driver' => 'daily',
            'path'  => storage_path('logs/order/trade_close.log'),
            'level' => 'debug',
        ],
        'server' => [
            'driver' => 'daily',
            'path'  => storage_path('logs/server/request.log'),
            'level' => 'debug',
        ],
        'pay_notify' => [
            'driver' => 'daily',
            'path'  => storage_path('logs/pay/notify.log'),
            'level' => 'debug',
        ],
        'pay_sign' => [
            'driver' => 'daily',
            'path'  => storage_path('logs/pay/sign.log'),
            'level' => 'debug',
        ],
        'withdraw' => [
            'driver' => 'daily',
            'path'  => storage_path('logs/withdraw/withdraw.log'),
            'level' => 'debug',
        ],
        'refund' => [
            'driver' => 'daily',
            'path'  => storage_path('logs/refund/refund.log'),
            'level' => 'debug',
        ],
        'sms' => [
            'driver' => 'daily',
            'path'  => storage_path('logs/sms/sms.log'),
            'level' => 'debug',
        ],
        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/lumen.log'),
            'level' => 'debug',
            'days' => 7,
        ],
        'slack' => [
            'driver' => 'slack',
            'url' => env('LOG_SLACK_WEBHOOK_URL'),
            'username' => 'Lumen Log',
            'emoji' => ':boom:',
            'level' => 'critical',
        ],
        'syslog' => [
            'driver' => 'syslog',
            'level' => 'debug',
        ],
        'errorlog' => [
            'driver' => 'errorlog',
            'level' => 'debug',
        ],
    ],

];
