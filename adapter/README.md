
# 常用类扩展

Doba框架提供了网站运行的基本骨架，更多更新的功能，需要通过扩展来实现，例如连接Memcache, Ftp 等

在这里实现了这相第三方功能的扩展


### 安装扩展到Doba项目

1 将 adapter/ 目录拷贝到 Doba项目的 common/plugin/目录下

例如 项目结构如下
```
|-doba
|-common
|   |-config
|   |   |-config.php
|   |   |-varconfig.php
|   |
|   |-libs 
|   |   |-dao
|   |   |-map
|   |
|   |-plugin
|   |   |-rpc
|   |   |   |-config.php
|   |   |
|   |   |-adapter
|   |   |   |-config.php
|   |   |   |-FtpAdapter.php
|   |   |   |-IAdapter.php
|   |   |   |-MemcacheAdapter.php
```

2 设置配置参数

用编辑器打开 [项目]/common/plugin/adapter/config.php, 将构造方法的上的注释， 如下： 拷贝到[项目]/common/config/config.php 中 Config类中

```
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
```

可以在 [项目]/common/config/varconfig.php 中定义重置方法， 例如:

```
define('DEFAULT_ADAPTER_CONFIGS', json_encode(
    array(
        'ftp'=>array(
            'default'=>array(
                'host'=>'127.0.0.1', 
                'port'=>'21', 
                'user'=>'user', 
                'pass'=>'user123'
            )
        ),
        // 如果安装的是memcached，而且有设置帐号，密码, 设置'memcached'=>true,（默认为：false） 
        'memcache'=>array(
            'default'=>array(
                'host'=>'127.0.0.1', 
                'port'=>'11211', 
                'memcached'=>true,
                'user'=>'',
                'pass'=>''
            )   
        )
    )   
));
```


### 方法调用

可在代码中调用, 例如在 DefaultController.php中

1 Memcache调用方法

```
$memcache = $GLOBALS['plugin']->call('adapter', 'core', array('name'=>'memcache'));


// 设置缓存
// 第三个参数是失效时间，传0不失效， 最大默认为：2592000， 30天
// $memcache->set('TEST', 'ABC', 35);

// 获取缓存值
echo $memcache->get('TEST');

// 删除缓存
// $memcache->drop('TEST');

```

2 Ftp调用方法

```
$ftp = $GLOBALS['plugin']->call('adapter', 'core', array('name'=>'ftp'));

// 上传文件
$ftp->put(__DIR__.'/DefaultController.php', 'abc/1.txt');

// 判断服务器文件是否存在
echo $ftp->isfile('abc/1.txt') ? 'Y' : 'N';

// 重命名服务器文件名
$ftp->move('abc/1.txt', 'abc/2.txt');

// 下截文件
$ftp->get(__DIR__.'/1.txt', 'abc/2.txt');

// 删除文件
$ftp->del('abc/2.txt');
```
