<?php

namespace Doba\Plugin;

use Doba\Util;

class AdapterPlugin extends BasePlugin {

    private static $loadedAdapters = array();

    /*
        可以在Config中定义获取 FTP连接配置

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
     */
    public function __construct(&$plugin){ 
        $this->_install($plugin, $this);
    }

    public function core($params)
    {
        $name = isset($params['name']) ? $params['name'] : '';
        $renew = isset($params['renew']) && $params['renew'] ? true : false;
        $config = isset($params['config']) ? $params['config'] : [];
        $adapterObjectName = ucfirst($name.'Adapter');
        $md5 = md5(json_encode(['objname'=>$adapterObjectName,'config'=>$config]));
        if(! isset(self::$loadedAdapters[$md5]) || $renew) {
            $adapterObject = "\\Doba\\Plugin\\Adapter\\".$adapterObjectName;
            self::$loadedAdapters[$md5] = new $adapterObject(\Config::me()->getAdapterConfig($name), $config);
        }
        return self::$loadedAdapters[$md5];
    }

}