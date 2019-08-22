<?php

declare(strict_types=1);
/**
 * This file is part of Yunhu.
 *
 * @link     https://www.yunhuyj.com/
 * @contact  zhiming.bi@yunhuyj.com
 * @license  http://license.coscl.org.cn/MulanPSL/
 */


namespace Yunhu\YunhuZookeeper;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => [
                \Zookeeper::class => Zookeeper::class,
            ],
            'commands' => [
            ],
            'scan' => [
                'paths' => [
                    __DIR__,
                ],
            ],
            'publish' => [
                [
                    'id' => 'config',
                    'description' => 'The config of zookeeper client.',
                    'source' => __DIR__ . '/../publish/zookeeper.php',
                    'destination' => BASE_PATH . '/config/autoload/zookeeper.php',
                ],
            ],
        ];
    }
}
