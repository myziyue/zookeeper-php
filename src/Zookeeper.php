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

use Yunhu\YunhuZookeeper\Pool\PoolFactory;
use Hyperf\Utils\Context;

class Zookeeper
{
    /**
     * @var PoolFactory
     */
    protected $factory;

    /**
     * @var string
     */
    protected $poolName = 'default';

    public function __construct(PoolFactory $factory)
    {
        $this->factory = $factory;
    }

    public function __call($name, $arguments)
    {
        // Get a connection from coroutine context or connection pool.
        $hasContextConnection = Context::has($this->getContextKey());
        $connection = $this->getConnection($hasContextConnection);

        try {
            // Execute the command with the arguments.
            $result = $connection->{$name}(...$arguments);
        } finally {
            // Release connection.
            if (!$hasContextConnection) {
                // Release the connection after command executed.
                $connection->release();
            }
        }

        return $result;
    }


    /**
     * Get a connection from coroutine context, or from redis connectio pool.
     * @param mixed $hasContextConnection
     */
    private function getConnection($hasContextConnection): ZookeeperConnection
    {
        $connection = null;
        if ($hasContextConnection) {
            $connection = Context::get($this->getContextKey());
        }
        if (!$connection instanceof ZookeeperConnection) {
            $pool = $this->factory->getPool($this->poolName);
            $connection = $pool->get()->getConnection();
        }
        return $connection;
    }

    /**
     * The key to identify the connection object in coroutine context.
     */
    private function getContextKey(): string
    {
        return sprintf('zookeeper.connection.%s', $this->poolName);
    }
}