<?php
use \Doba\RedisClient;

class QueueAdapter extends IAdapter {

    private $redisClient = NULL;
    private $put_position = '_PUT_POSITION';
    private $get_position = '_GET_POSITION';
    private $limit = 100000;
    private $prefix = 'MQ_';

    private $configs = array();
    
    // 传入参数格式
    // array(
    //    'default'=>array('host'=>'127.0.0.1', 'port'=>'6379', 'pass'=>'')
    // )
    public function __construct($configs = array()) {
        $this->configs = $configs;
        $this->start();
    }

    public function start($key = 'default') {
        $this->redisClient = RedisClient::me($key);
        return $this;
    }

    public function reconnect() {
        if(! $this->redisClient) throw new \Exception("Please execute the start method first");
        $this->redisClient->connect();
    }

    // 以下实现相关功能
    public function setPrefix($prefix = 'MQ_') {
        $this->prefix = $prefix;
    }

    public function setLimit($limit = 100000) {
        $this->limit = $limit;
    }

    public function put($qname, $qdata)
    {
        if($this->redisClient->setQKey($this->prefix.$qname)->putQueue($qdata)) {
            $incrKey = $this->prefix.$qname.$this->put_position;
            $incr = $this->redisClient->getRedis()->incr($incrKey);
            if(! $incr || $incr > $this->limit) {
                $this->redisClient->set($incrKey, 0); $this->redisClient->getRedis()->incr($incrKey);
            }
            return true;
        }
        return false;
    }

    public function get($qname)
    {
        if($val = $this->redisClient->setQKey($this->prefix.$qname)->getQueue()) 
        {
            $incrKey = $this->prefix.$qname.$this->get_position;
            $incr = $this->redisClient->getRedis()->incr($incrKey);
            if(! $incr || $incr > $this->limit) {
                $this->redisClient->set($incrKey, 0); $this->redisClient->getRedis()->incr($incrKey);
            }
            return $val;
        }
        return false;
    }
    
    public function size($qname)
    {
        if('' == $qname) return 0;
        return $this->redisClient->getRedis()->lSize($this->prefix.$qname);
    }
    
    public function clear($qname)
    {
        if('' == $qname) return false;
        $qname = $this->prefix.$qname;
        return $this->redisClient->delKey(array($qname, $qname.$this->put_position, $qname.$this->get_position));
    }
    
    public function status($qname)
    {
        if('' == $qname) return array();
        $status['put_position'] = ($put_position = $this->redisClient->getRedis()->
            get($this->prefix.$qname.$this->put_position)) ? $put_position : 0;  
        $status['get_position'] = ($get_position = $this->redisClient->getRedis()->
            get($this->prefix.$qname.$this->get_position)) ? $get_position : 0;  

        $status['unread_queue'] = $this->size($qname);  
        $status['queue_name'] = $qname;
        return $status;  
    }
    
    public function status_normal($qname)
    {  
        $status = $this->status($qname);
        $message  = 'Redis Message Queue' . PHP_EOL;  
        $message .= '-------------------' . PHP_EOL;  
        $message .= 'Message queue name:' . $status['queue_name'] . PHP_EOL;  
        $message .= 'Put position of queue:' . $status['put_position'] . PHP_EOL;  
        $message .= 'Get position of queue:' . $status['get_position'] . PHP_EOL;  
        $message .= 'Number of unread queue:' . $status['unread_queue'] . PHP_EOL;  
  
        return $message;  
    }  
  
    public function status_json($qname) {  
        return json_encode($this->status($qname));
    } 
}