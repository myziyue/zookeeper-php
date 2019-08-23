<?php

declare(strict_types=1);
/**
 * This file is part of Myziyue.
 *
 * @link     https://www.myziyue.com/
 * @contact  zhiming.bi@myziyue.com
 * @license  http://license.coscl.org.cn/MulanPSL/
 */

return [
    'default' => [
        'host' => env('ZK_HOST', '127.0.0.1:2181'),
        'scheme' => env('ZK_SCHEME', null),
        'cert' => env('ZK_CERT', null),
        'timeout' => 1000,
        'pool' => [
            'min_connections' => 1,
            'max_connections' => 10,
            'connect_timeout' => 10.0,
            'wait_timeout' => 3.0,
            'heartbeat' => -1,
            'max_idle_time' => (float)env('ZK_MAX_IDLE_TIME', 60),
        ],
    ],
];
