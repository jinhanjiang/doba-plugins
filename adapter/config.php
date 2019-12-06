<?php
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
            if(defined('DEFAULT_ADAPTER_CONFIGS')) {
                $this->adapterConfigs = json_decode(DEFAULT_ADAPTER_CONFIGS, true);
            } else {
                $this->adapterConfigs = array(
                    'ftp'=>array(
                        'default'=>array('host'=>'127.0.0.1', 'port'=>'21', 'user'=>'root', 'pass'=>'')
                    ),
                    'memcache'=>array(
                        'default'=>array('host'=>'127.0.0.1', 'port'=>'11211', 'memcached'=>false, 'user'=>'root', 'pass'=>'')
                    )
                );
            }
        }
     */
    public function __construct(&$plugin){ 
        $this->_install($plugin, $this);
    }

    public function core($params)
    {
        $name = isset($params['name']) ? $params['name'] : 'default';
        $adapterObject = ucfirst($name.'Adapter');
        if(! isset(self::$loadedAdapters[$adapterObject])) {
            require_once(__DIR__.'/IAdapter.php');
            $adapterFile = __DIR__.'/'.$adapterObject.'.php';
            if(! Util::isFile(($adapterFile))) throw new \Exception('Calling class is undefined');
            require_once($adapterFile);
            self::$loadedAdapters[$adapterObject] = new $adapterObject(\Config::me()->getAdapterConfig($name));
        }
        return self::$loadedAdapters[$adapterObject];
    }

}