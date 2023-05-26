# zookeeper PHP客户端 -- Hyperf框架组件

zookeeper PHP客户端 -- Hyperf框架组件

## Zookeeper扩展安装

> 特别提醒： 本例以CentOS7为演示系统，其他操作系统可参考对应系统版本的安装说明 

1. 下载并编译安装zookeeper源码包
---

> 官方网址： https://zookeeper.apache.org/

> 这里我们以`apache-zookeeper-3.5.5.tar.gz`为例
编译安装需要依赖：cppunit-devel 包，其他通用编译依赖的包这里不做赘述了

```bash
# yum install -y cppunit-devel
#  wget https://dlcdn.apache.org/zookeeper/zookeeper-3.5.5/apache-zookeeper-3.5.5.tar.gz

#  tar zxvf apache-zookeeper-3.5.5.tar.gz
# cd apache-zookeeper-3.5.5/zookeeper-client/zookeeper-client-c/

# ACLOCAL="aclocal -I /usr/local/share/aclocal" autoreconf -if
# ./configure --prefix=/opt/lib/zookeeper-client-c-3.5.5

#  make && make install
```

2. 下载并编译安装zookeeper的PHP扩展包

> 扩展包下载地址： https://pecl.php.net/package/zookeeper

> 这里我们以：zookeeper-0.6.4.tgz 为例。

```bash
# wget https://pecl.php.net/get/zookeeper-0.6.4.tgz

# tar zxvf zookeeper-0.6.4.tgz
# cd zookeeper-0.6.4

# phpize
# ./configure --with-libzookeeper-dir=/opt/lib/zookeeper-client-c-3.5.5/
#  make && make install

#  echo "extension=zookeeper.so" >> /opt/php8/etc/php.ini
```


## 使用方法

```shell
# composer require myziyue/zookeeper-php
```