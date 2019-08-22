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

namespace YunhuTest\YunhuZookeeper\Stub;

use Yunhu\YunhuZookeeper\ZookeeperConnection;

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
