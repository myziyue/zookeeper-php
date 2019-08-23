<?php

declare(strict_types=1);
/**
 * This file is part of Myziyue.
 *
 * @link     https://www.myziyue.com/
 * @contact  evan2884@gmail.com
 * @license  http://license.coscl.org.cn/MulanPSL/
 */


namespace MyziyueTest\Zookeeper\Stub;

use Hyperf\Contract\ConnectionInterface;
use Myziyue\Zookeeper\Pool\ZookeeperPool;

class ZookeeperPoolStub extends ZookeeperPool
{
    protected function createConnection(): ConnectionInterface
    {
        return new ZookeeperConnectionStub($this->container, $this, $this->config);
    }
}
