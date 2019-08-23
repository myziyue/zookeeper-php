<?php

declare(strict_types=1);
/**
 * This file is part of Myziyue.
 *
 * @link     https://www.myziyue.com/
 * @contact  zhiming.bi@myziyue.com
 * @license  http://license.coscl.org.cn/MulanPSL/
 */


namespace MyziyueTest\Zookeeper\Stub;

use Myziyue\Zookeeper\ZookeeperConnection;

class ZookeeperConnectionStub extends ZookeeperConnection
{
    public $host;

    public $scheme;

    public $cert;

    public $timeout;

    public function __call($name, $arguments)
    {
        return sprintf('host:%s name:%s argument:%s', $this->host, $name, implode(',', $arguments));
    }

    public function reconnect(): bool
    {
        $this->host = $this->config['host'];
        $this->scheme = $this->config['scheme'];
        $this->cert = $this->config['cert'];
        $this->timeout = $this->config['timeout'];

        return true;
    }

    /**
     * @return array
     */
    public function getConfig(): array
    {
        return $this->config;
    }
}
