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

use http\Exception\InvalidArgumentException;
use Hyperf\Contract\ConnectionInterface;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Pool\Connection as BaseConnection;
use Hyperf\Pool\Exception\ConnectionException;
use Hyperf\Pool\Pool;
use Hyperf\Redis\Exception\InvalidNoExistsPathException;
use Psr\Container\ContainerInterface;

class ZookeeperConnection extends BaseConnection implements ConnectionInterface
{
    /**
     * @var \Zookeeper
     */
    protected $connection;

    /**
     * @var StdoutLoggerInterface
     */
    protected $logger;

    /**
     * @var string
     */
    protected $zkHosts;

    /**
     * @var array
     */
    protected $config = [
        'host' => '127.0.0.1:2181',
        'timeout' => 1000,
    ];

    /**
     * @var null
     */
    private $connectCallBackFunc = null;
    /**
     * @var array
     */
    private $watcherCallbackFunc = [];

    public function __construct(ContainerInterface $container, Pool $pool, array $config)
    {
        parent::__construct($container, $pool);
        $this->config = array_replace($this->config, $config);
        $this->logger = $container->get(StdoutLoggerInterface::class);

        $this->reconnect();
    }

    public function __call($name, $arguments)
    {
        return $this->connection->{$name}(...$arguments);
    }

    public function getActiveConnection()
    {
        if ($this->check()) {
            return $this;
        }
        $this->reconnect();

        return $this;
    }

    public function reconnect(): bool
    {
        $this->zkHosts = $this->config['host'];
        $zkScheme = $this->config['scheme'];
        $zkCert = $this->config['cert'];
        $timeout = $this->config['timeout'];

        try {
            $zookeeper = new \Zookeeper($this->zkHosts, [$this, "connectCallBack"], $timeout);
            \Zookeeper::setDebugLevel(\Zookeeper::LOG_LEVEL_ERROR);
        } catch (\ZookeeperException $ex) {
            throw new ConnectionException("Connection reconnect failed : {$ex->getMessage()} | {$this->zkHosts}");
        }

        if ($zkScheme) {
            $zookeeper->addAuth($zkScheme, $zkCert);
        }

        $this->connection = $zookeeper;
        $this->lastUseTime = microtime(true);

        return true;
    }

    /**
     * Establish callback processing
     *
     * @param $type
     * @param $event
     * @param $string
     */
    public function connectCallBack($type, $event, $string): void
    {
        $this->logger->debug("Connect state: {$event} | {$this->zkHosts}");
        $options = [$type, $event, $string];
        if(isset($this->connectCallBackFunc)) {
            $this->logger->debug("Callback connect state. | {$this->zkHosts}");
            call_user_func($this->connectCallBackFunc, $options);
        }
        $this->logger->debug("Connect callback end. | {$this->zkHosts}");
    }

    public function close(): bool
    {
        return $this->connection->close();
    }

    public function release(): void
    {
        parent::release();
    }

    /**
     * Check if the connection has been established.
     *
     * @return bool
     */
    public function isConnected(): bool
    {
        $ret = $this->connection->getState();
        $this->logger->debug("Zookeeper state : {$ret} | {$this->zkHosts}");
        return \Zookeeper::CONNECTED_STATE == $ret ? TRUE : FALSE;
    }

    /**
     *  Gets the data associated with a node synchronously
     *
     * @param $path
     * @return string
     */
    public function get($path): string
    {
        if(!$this->connection->exists($path)) {
            $this->logger->error("Node：{$path} does not exist. | {$this->zkHosts}");
            return "";
        }
        return $this->connection->get($path);
    }

    /**
     * Sets the data associated with a node
     *
     * @param $path
     * @param $value
     */
    public function set($path, $value)
    {
        if(!$this->connection->exists($path)) {
            $this->logger->debug("Node：{$path} does not exist，Start creating nodes. | {$this->zkHosts}");
            $this->makePath($path);
            $this->makeNode($path, $value);
        } else {
            $this->connection->set($path, $value);
        }

    }

    /**
     * 根据路径分隔符，创建节点
     *
     * @param $path
     * @param string $value
     */
    protected function makePath($path, $value = '')
    {
        $parts = explode("/", $path);
        $parts = array_filter($parts);
        $subPath = "";
        while(count($parts) > 1) {
            $subPath .= '/' . array_shift($parts);
            if(!$this->connection->exists($subPath)) {
                $this->makeNode($subPath, $value);
            }
        }
    }

    /**
     * Create a node synchronously
     *
     * @param $path
     * @param $value
     * @param array $options
     * @return mixed
     */
    protected function makeNode($path, $value, $options = [])
    {
        if(empty($options)) {
            $options = [
                [
                    'perms' => \Zookeeper::PERM_ALL,
                    'scheme' => 'world',
                    'id' => 'anyone'
                ]
            ];
        }
        $this->logger->debug("Create Node : {$path} , value : {$value} | {$this->zkHosts}");
        return $this->connection->create($path, $value, $options);
    }

    /**
     * Lists the children of a node synchronously
     *
     * @param $path
     * @return mixed
     */
    public function getChildren($path)
    {
        if(strlen($path) > 1 && preg_match('@/$@', $path)) {
            $path = substr($path, 0, -1);
        }
        return $this->connection->getChildren($path);
    }

    /**
     * @param $path
     * @param null $callback
     * @return string
     */
    public function watch($path, $callback = null): string
    {
        if(!is_callable($callback)) {
            throw new InvalidArgumentException("Invalid callback function.");
        }

        if($this->connection->exists($path)) {
            if(!isset($this->watcherCallbackFunc[$path])){
                $this->watcherCallbackFunc[$path] = [];
            }

            if(!in_array($callback, $this->watcherCallbackFunc[$path])) {
                $this->watcherCallbackFunc[$path][] = $callback;
                return $this->connection->get($path, [$this, 'watchCallback']);
            }
        }

        throw new InvalidNoExistsPathException("Node {$path} does not exists.");

    }


    /**
     * Watch Callback
     * @param $type
     * @param $state
     * @param $path
     * @return mixed|null
     */
    public function watchCallback($type, $state, $path)
    {
        if(!isset($this->watcherCallbackFunc[$path])){
            throw new InvalidArgumentException("Invalid callback function.");
        }

        if(isset($this->watcherCallbackFunc[$path])) {
            $this->connection->get($path, [$this, 'watchCallback']);
            return call_user_func($this->watcherCallbackFunc[$path], [$this->connection, $path]);
        }
        return null;
    }

    /**
     * Cacel Watch Callback
     * @param $path
     * @param null $callback
     * @return bool
     */
    public function cacelWatch($path, $callback = null)
    {
        if(isset($this->watcherCallbackFunc[$path])) {
            if(empty($callback)) {
                unset($this->watcherCallbackFunc[$path]);
                $this->connection->get($path);
                return true;
            } else {
                $key = array_search($callback, $this->watcherCallbackFunc[$path]);
                if($key !== false) {
                    unset($this->watcherCallbackFunc[$path][$key]);
                    return true;
                } else {
                    return false;
                }
            }
        }
        return false;
    }
}