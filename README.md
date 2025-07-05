
# 如何创建Doba扩展

Doba框架提供了网站运行的基本骨架，更多更新的功能，需要通过扩展来实现


### 创建扩展

1 Doba项目的 common/plugin/目录下, 创建一个文件， 例如：XiaomingPlugin.php

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
|   |   |-BasePlugin.php
|   |   |-XiaomingPlugin.php
```

2 实现相关功能

用编辑器打开 [项目]/common/plugin/XiaomingPlugin.php

```
<?php

namespace Doba\Plugin;

use Doba\Util;

class XiaominPlugin extends BasePlugin {

    public function __construct(&$plugin){ 
        $this->_install($plugin, $this);
    }

    /*----------在这下面可以 写以public 开头的方法----------*/

    // public 的方法可被调用, protected, private则不能被调用
    public function sayHello($params) {
        echo "Hi, ".$params['name'];
    }
}

```

3 调用插件相关功能

框架启动后。会实例化一下$GLOBALS['plugin'],
```
$GLOBALS['plugin']->call('xiaoming', 'hello', array('name'=>'xiaoming'));
```
其中xiaoming 为扩展名， XiaominPlugin中不带Plugin的值

### 卸载插件

删除插件文件夹即可
例如: 删除 [项目]/common/plugin/XiaominPlugin.php， 即可卸载插件


### 注意项

创建扩展目录时，的名称(例如: xiaoming)

1 扩展目录中XiaominPlugin.php文件里面的类名, 以扩展名首字母大写 + Plugin, 例如: XiaomingPlugin
2 在调用方法时，要用到扩展名, 例如： $GLOBALS['plugin']->call('xiaoming', 
