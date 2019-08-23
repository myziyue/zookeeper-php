<?php

declare(strict_types=1);
/**
 * This file is part of Myziyue.
 *
 * @link     https://www.myziyue.com/
 * @contact  evan2884@gmail.com
 * @license  http://license.coscl.org.cn/MulanPSL/
 */

namespace Myziyue\Zookeeper;

use Hyperf\Contract\ConnectionInterface;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Pool\Connection as BaseConnection;
use Hyperf\Pool\Exception\ConnectionException;
use Hyperf\Pool\Pool;
use Myziyue\Zookeeper\Exception\InvalidNoExistsPathException;
use Myziyue\Zookeeper\Exception\InvalidZookeeperArgumentException;
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
    public function connectCallBack(int $type, string $event, string $str): void
    {
        $this->logger->debug("Connect state: {$event} | {$this->zkHosts}");
        $options = [$type, $event, $str];
        if (isset($this->connectCallBackFunc)) {
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
     * @param $node
     * @return string
     */
    public function get(string $node): string
    {
        if (!$this->connection->exists($node)) {
            $this->logger->error("Node：{$node} does not exist. | {$this->zkHosts}");
            return "";
        }
        return $this->connection->get($node);
    }

    /**
     * Sets the data associated with a node
     *
     * @param $node
     * @param $value
     */
    public function set(string $node, string $value)
    {
        if (!$this->connection->exists($node)) {
            $this->logger->debug("Node：{$node} does not exist，Start creating nodes. | {$this->zkHosts}");
            $this->makePath($node);
            $this->makeNode($node, $value);
        } else {
            $this->connection->set($node, $value);
        }

    }

    /**
     * 根据路径分隔符，创建节点
     *
     * @param $node
     * @param string $value
     */
    protected function makePath(string $node, string $value = '')
    {
        $parts = explode("/", $node);
        $parts = array_filter($parts);
        $subPath = "";
        while (count($parts) > 1) {
            $subPath .= '/' . array_shift($parts);
            if (!$this->connection->exists($subPath)) {
                $this->makeNode($subPath, $value);
            }
        }
    }

    /**
     * Create a node synchronously
     *
     * @param $node
     * @param $value
     * @param array $options
     * @return mixed
     */
    protected function makeNode(string $node, string $value, array $options = [])
    {
        if (empty($options)) {
            $options = [
                [
                    'perms' => \Zookeeper::PERM_ALL,
                    'scheme' => 'world',
                    'id' => 'anyone'
                ]
            ];
        }
        $this->logger->debug("Create Node : {$node} , value : {$value} | {$this->zkHosts}");
        return $this->connection->create($node, $value, $options);
    }

    /**
     * Lists the children of a node synchronously
     *
     * @param $node
     * @return mixed
     */
    public function getChildren(string $node)
    {
        if (strlen($node) > 1 && preg_match('@/$@', $node)) {
            $node = substr($node, 0, -1);
        }
        return $this->connection->getChildren($node);
    }

    /**
     * @param $node
     * @param callable $callback
     * @return bool
     */
    public function watch(string $node, callable $callback): bool
    {
        if (!is_callable($callback)) {
            throw new InvalidZookeeperArgumentException("Invalid callback function.");
        }

        if ($this->connection->exists($node)) {
            if (!isset($this->watcherCallbackFunc[$node])) {
                $this->watcherCallbackFunc[$node] = [];
            }

            if (!in_array($callback, $this->watcherCallbackFunc[$node])) {
                $this->watcherCallbackFunc[$node][] = $callback;
                $this->connection->get($node, [$this, 'watchCallback']);
                return true;
            }
            return false;
        } else {
            throw new InvalidNoExistsPathException("Node {$node} does not exists.");
        }
    }


    /**
     * Watch Callback
     * @param $type
     * @param $state
     * @param $node
     * @return mixed|null
     */
    public function watchCallback(int $type, string $state, string $node)
    {
        if (!isset($this->watcherCallbackFunc[$node])) {
            throw new InvalidZookeeperArgumentException("Invalid callback function.");
        }

        foreach ($this->watcherCallbackFunc[$node] as $watcherCallback) {
            $this->connection->get($node, [$this, 'watchCallback']);
            return call_user_func($watcherCallback, [$this->connection, $node]);
        }
    }

    /**
     * Cacel Watch Callback
     * @param string $node
     * @param callable $callback
     * @return bool
     */
    public function cacelWatch(string $node, callable $callback = null)
    {
        if (isset($this->watcherCallbackFunc[$node])) {
            if (empty($callback)) {
                $this->watcherCallbackFunc[$node] = [];
                $this->connection->get($node, [$this, 'watchCallback']);
                return true;
            } else {
                $key = array_search($callback, $this->watcherCallbackFunc[$node]);
                if ($key !== false) {
                    unset($this->watcherCallbackFunc[$node][$key]);
                    $this->connection->get($node, [$this, 'watchCallback']);
                    return true;
                } else {
                    return false;
                }
            }
        }
        return false;
    }
}