<?php

declare(strict_types=1);
/**
 * This file is part of Myziyue.
 *
 * @link     https://www.myziyue.com/
 * @contact  zhiming.bi@myziyue.com
 * @license  http://license.coscl.org.cn/MulanPSL/
 */

namespace Myziyue\Zookeeper\Pool;

use Hyperf\Di\Container;
use Psr\Container\ContainerInterface;
use Swoole\Coroutine\Channel;

class PoolFactory
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var Channel[]
     */
    protected $pools = [];

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function getPool(string $name): ZookeeperPool
    {
        if (isset($this->pools[$name])) {
            return $this->pools[$name];
        }

        if ($this->container instanceof Container) {
            $pool = $this->container->make(ZookeeperPool::class, ['name' => $name]);
        } else {
            $pool = new ZookeeperPool($this->container, $name);
        }
        return $this->pools[$name] = $pool;
    }
}