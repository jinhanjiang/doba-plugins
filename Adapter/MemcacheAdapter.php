<?php

namespace Doba\Plugin\Adapter;

class MemcacheAdapter extends IAdapter {

    private $conn = null;
    private $configs = array();

    private $isMemcached = false;

    private $host = '127.0.0.1';
    private $port = 11211;
    private $user = '';
    private $pass = '';

    /*
        传入参数格式
         array(
            'default'=>array('host'=>'127.0.0.1', 'port'=>'11211', 'memcached'=>false, 'user'=>'root', 'pass'=>'')
        )
     */
    public function __construct($configs = array()) {
        $this->configs = $configs;
        $this->start();
    }

    public function start($key = 'default') {
        $key = isset($this->configs['key']) ? $this->configs['key'] : 'default';
        $this->isMemcached = isset($this->configs[$key]['memcached']) && true === $this->configs[$key]['memcached'] ? true : false;
        if($this->isMemcached) {
            if(! extension_loaded('memcached')) throw new \Exception('memcached extension not loaded');
        } else {
            if(! extension_loaded('memcache')) throw new \Exception('memcache extension not loaded');
        }
        if(isset($this->configs[$key]['host'])) $this->host = $this->configs[$key]['host'];
        if(isset($this->configs[$key]['port'])) $this->port = $this->configs[$key]['port'];
        if(isset($this->configs[$key]['user'])) $this->user = $this->configs[$key]['user'];
        if(isset($this->configs[$key]['pass'])) $this->pass = $this->configs[$key]['pass'];
        return $this;
    }

    public function getMemcache()
    {
        if(! $this->conn) 
        {
            if($this->isMemcached) 
            {
                $this->conn = new \Memcached();
                $this->conn->addServer($this->host, $this->port);  
                if($this->user) {
                    $this->conn->setOption(Memcached::OPT_BINARY_PROTOCOL, true);
                    $this->conn->setSaslAuthData($this->user, $this->pass); //鉴权
                }
            }
            else
            {
                $this->conn = new \Memcache();
                $this->conn->addServer($this->host, $this->port);  
                $this->conn->setCompressThreshold(2000, 0.2); //如果内容超过2k大小，自动以0.2的压缩比进行压缩
            }
        }
        return $this->conn;
    }

    /**
     * 设置缓存
     * @param $key 缓存键值
     * @param $val 缓存值
     * 
     * 2592000 30天
     */
    public function set($key, $val, $ttl=0) 
    {
        $ttl = $ttl > 0 && $ttl > 2592000 ? 2592000 : $ttl;
        return $this->isMemcached 
            ? $this->getMemcache()->set($key, $val, $ttl) 
            : $this->getMemcache()->set($key, $val, 0, $ttl);
    }

    /**
     * 设置缓存
     * @param $key 缓存键值
     * @param $val 缓存值
     */
    public function get($key) {
        return $this->getMemcache()->get($key);
    }

    public function drop($key) {
        return $this->getMemcache()->delete($key, 0);
    }

    /**
     * 关闭链接
     */
    public function close() {
        if($this->conn) {
           $this->isMemcached ? $this->conn->quit() : $this->conn->close();  $this->conn = null;
        }
    }

    /**
     * 关闭链接
     */
    public function __destruct() {
        $this->close();
    }
}
