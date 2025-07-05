<?php

class Config {

    private static $instance = array();
    protected function __construct() {}
    public static function me(){
        $class = get_called_class();
        if(! self::$instance[$class]) {
            self::$instance[$class] = new $class();
        }
        return self::$instance[$class];
    }

    protected $adapterConfigs = array();
    public function getAdapterConfig($key='default') {
        if(! $this->adapterConfigs) $this->setAdapterConfigs();
        $adapterConfig = isset($this->adapterConfigs[$key]) ? $this->adapterConfigs[$key] : array();
        if(! $adapterConfig) throw new \Exception('['.$key.'] connection configuration not found');
        return $adapterConfig;
    }

    public function setAdapterConfigs() {
        $this->adapterConfigs = isset($GLOBALS['CONSTANT_DEFAULT_ADAPTER_CONFIGS']) ? $GLOBALS['CONSTANT_DEFAULT_ADAPTER_CONFIGS'] : [
            'ftp'=>[
                'default'=>['host'=>'127.0.0.1', 'port'=>'21', 'user'=>'root', 'pass'=>'']
            ],
            'memcache'=>[
                'default'=>['host'=>'127.0.0.1', 'port'=>'11211', 'memcached'=>false, 'user'=>'root', 'pass'=>'']
            ]
        ];
    }
}