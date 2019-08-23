<?php

declare(strict_types=1);
/**
 * This file is part of Myziyue.
 *
 * @link     https://www.myziyue.com/
 * @contact  evan2884@gmail.com
 * @license  http://license.coscl.org.cn/MulanPSL/
 */

namespace MyziyueTest\Zookeeper;

use Hyperf\Config\Config;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Di\Container;
use MyziyueTest\Zookeeper\Stub\ZookeeperPoolStub;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
class ZookeeperConnectionTest extends TestCase
{
    public function tearDown()
    {
        Mockery::close();
    }

    public function testZookeeperConnectionConfig()
    {
        $pool = $this->getZookeeperPool();

        $config = $pool->get()->getConfig();

        $this->assertSame([
            'host' => '127.0.0.1:2181',
            'scheme' => null,
            'cert' => null,
            'timeout' => 0.0,
            'pool' => [
                'min_connections' => 1,
                'max_connections' => 30,
                'connect_timeout' => 10.0,
                'wait_timeout' => 3.0,
                'heartbeat' => -1,
                'max_idle_time' => 1,
            ],
        ], $config);
    }

    public function testZookeeperConnectionReconnect()
    {
        $pool = $this->getZookeeperPool();

        $connection = $pool->get()->getConnection();
        $this->assertTrue($connection);
        $resut = $connection->reconnect();
        $this->assertTrue(null, $resut);

        $connection->release();
        $connection = $pool->get()->getConnection();
        $this->assertSame(null, $connection);
    }

    private function getZookeeperPool()
    {
        $container = Mockery::mock(Container::class);
        $container->shouldReceive('get')->once()->with(ConfigInterface::class)->andReturn(new Config([
            'Zookeeper' => [
                'default' => [
                    'host' => '127.0.0.1:2181',
                    'scheme' => null,
                    'cert' => null,
                    'pool' => [
                        'min_connections' => 1,
                        'max_connections' => 30,
                        'connect_timeout' => 10.0,
                        'wait_timeout' => 3.0,
                        'heartbeat' => -1,
                        'max_idle_time' => 1,
                    ],
                ],
            ],
        ]));

        return new ZookeeperPoolStub($container, 'default');
    }
}
