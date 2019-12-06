<?php
abstract class IAdapter {
    abstract public function start($key = 'default');
}

/*
<?php
class XXXAdapter extends IAdapter {

    private $configs = array();
    
    // 传入参数格式
    // array(
    //    'default'=>array('host'=>'127.0.0.1', 'port'=>'3306', 'user'=>'root', 'pass'=>'')
    // )
    public function __construct($configs = array()) {
        $this->configs = $configs;
        $this->start();
    }

    public function start($key = 'default') {
        $key = isset($this->configs['key']) ? $this->configs['key'] : 'default';
        if(isset($this->configs[$key]['host'])) $this->host = $this->configs[$key]['host'];
        if(isset($this->configs[$key]['port'])) $this->port = $this->configs[$key]['port'];
        if(isset($this->configs[$key]['user'])) $this->user = $this->configs[$key]['user'];
        if(isset($this->configs[$key]['pass'])) $this->pass = $this->configs[$key]['pass'];
        return $this;
    }

    // 以下实现相关功能

}
*/