<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://doc.hyperf.io
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf-cloud/hyperf/blob/master/LICENSE
 */

namespace YunhuTest\YunhuZookeeper;

use Hyperf\Config\Config;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Di\Container;
use Hyperf\Utils\ApplicationContext;
use Mockery;
use PHPUnit\Framework\TestCase;
use Yunhu\YunhuZookeeper\Pool\PoolFactory;
use Yunhu\YunhuZookeeper\Pool\ZookeeperPool;
use Yunhu\YunhuZookeeper\Zookeeper;
use YunhuTest\YunhuZookeeper\Stub\ZookeeperPoolStub;

/**
 * @internal
 * @coversNothing
 */
class ZookeeperTest extends TestCase
{
    public function tearDown()
    {
        Mockery::close();
    }

    public function testRedisConnect()
    {
        $zookeeper = new \Zookeeper($this->zkHosts);
        $this->assertTrue($zookeeper);

        $class = new \ReflectionClass($zookeeper);
        $params = $class->getMethod('connect')->getParameters();
        [$host, $scheme, $cert, $timeout, $retryInterval] = $params;
        $this->assertSame('host', $host->getName());
        $this->assertSame('scheme', $scheme->getName());
        $this->assertSame('cert', $cert->getName());
        $this->assertSame('timeout', $timeout->getName());
        $this->assertSame('retry_interval', $retryInterval->getName());
    }

    public function testRedisSelect()
    {
        $zookeeper = $this->getRedis();

        $res = $zookeeper->set('\xxxx', 'yyyy');
        $this->assertSame('name:set argument:\xxxx,yyyy', $res);

        $res = $zookeeper->get('\xxxx');
        $this->assertSame('name:get argument:\xxxx', $res);

        $res = parallel([function () use ($zookeeper) {
            return $zookeeper->get('\xxxx');
        }]);

        $this->assertSame('name:get argument:\xxxx', $res[0]);
    }

    private function getRedis()
    {
        $container = Mockery::mock(Container::class);
        $container->shouldReceive('get')->once()->with(ConfigInterface::class)->andReturn(new Config([
            'redis' => [
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
                        'max_idle_time' => 60,
                    ],
                ],
            ],
        ]));
        $pool = new ZookeeperPoolStub($container, 'default');
        $container->shouldReceive('make')->once()->with(ZookeeperPool::class, ['name' => 'default'])->andReturn($pool);

        ApplicationContext::setContainer($container);

        $factory = new PoolFactory($container);

        return new Zookeeper($factory);
    }
}
