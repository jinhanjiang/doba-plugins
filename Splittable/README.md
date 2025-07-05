# 使用数据库分表

面对数据库的数据量几何式增长，如何把不常用的数据做备份及清理，Doba框架通过简单的配置实现数据分表

原理如下：

一般随着时间增长，有些数据就不常用了，我们可以以三个月，或半年一分表
例如：
按季度分表，1-3月数据存在0表， 4-6月数据存在1表，7-9月数据存在2表， 10-12月存在3表， 这样就分了4张表
按年分表，1-6月数据存在0表， 7-12月数据存在1表，这样分了2张表

每年的数据我们按年表也保存一份数据

例如有一个订单库：
按季度分表
Order_0, Order_1, Order_2, Order_3
按年
OrderBackup_19, OrderBackup_20

Order_x, OrderBackup_x， 表结构相同（其他必须包含pid, dateCode[pid:数据行唯一标识， dateCode:数据保存日期]）

我们存储数据时:
当前是2020年5月， 我们把数据保存在Order_1, 和OrderBackup_20表
当前是2019年9月， 我们把数据保存在Order_2, 和OrderBackup_19表

当然Order_x表中的数据，我们可以过一段时间做一次（TRUNCATE）清理，保证数据量少，查询快
OrderBackup_x表就是历史备份表了，不常查询的数据就保存在这张表当中

# 安装

### 安装代码

将当前目录下dao拷贝到[项目]/common/libs/dao/, 结构如下

```
|-autotask
|-doba
|-common
|   |-config
|   |   |-config.php
|   |   |-varconfig.php
|   |
|   |-libs 
|   |   |-dao
|   |   |   |-BackupTableDAO.php
|   |   |   |-SplitDAO.php
|   |   |   |-SplitTableDAO.php
|   |   |   
|   |   |-map
|   |
|   |-plugin
|   |   |-rpc
|   |   |   |-config.php
|   |   |
```

# 用法

### 数据写入


### 数据清理

可以在autotask目录下创建自动脚本script-auto-clean-expire-db-data.php
```
<?php
use Doba\Util;

require(__DIR__.'/autotask-config.php');

define('DAO_PATH', ROOT_PATH."common/libs/dao/");
define('MAP_PATH', ROOT_PATH."common/libs/map/");

// 分表数据留近3个月，超出3个月的数据查询从备份表查询

try{
    $firstDayOfThisMonth = date('Ym01'); 
    // 找出有分表的DAO
    $splitTables = array();
    foreach(glob(MAP_PATH.'**/*Backup.php') as $backupfile) 
    {
        $pos = strpos($backupfile, '/map/');
        if(false === $pos) continue;

        $splitName = basename($backupfile, 'Backup.php');
        $daofile = str_replace(array('/map/', $splitName.'Backup'), array('/dao/', $splitName.'DAO'), $backupfile);

        if(Util::isFile($daofile))
        {
            // 找出命名空间
            $namespaceStr = trim(substr($backupfile, $pos), '/');
            $namespaces = explode('/', $namespaceStr);
            array_shift($namespaces); array_pop($namespaces);
            $namespace = $namespaces ? implode("\\", $namespaces)."\\" : "";

            // 获取到DAO对象
            $splitTable = call_user_func(array("\\Doba\\Dao\\".$namespace.$splitName.'DAO', 'me'));
            $splitTables[(int)$splitTable->getTableCount()][] = $splitTable;
        }
    }

    // 计算保留月份的分表号
    $keepSpCodes = array();
    foreach($splitTables as $tbcnt=>$spTables)
    {
        foreach($spTables as $spTable)
        {
            for($i = 0; $i <= 3; $i++) { // 保留近3个月数据
                $dateCode = date('Ym01', strtotime("first day of {$firstDayOfThisMonth} -{$i} month"));
                $spCode = $spTable->getSpCode(array('dateCode'=>$dateCode));
                if(! in_array($spCode, $keepSpCodes[$tbcnt])) $keepSpCodes[$tbcnt][] = $spCode;
            }
            break;   
        }
    }

    // 执行清表操作
    foreach($splitTables as $tbcnt=>$spTables)
    {
        if(! isset($keepSpCodes[$tbcnt])) continue;
        foreach($spTables as $spTable)
        {
            for($i = 0; $i < $tbcnt; $i ++) {
                if(in_array($i, $keepSpCodes[$tbcnt])) continue;

                $sql = "TRUNCATE TABLE `{$spTable->getOriginalTableName()}{$spTable->getTablSeparate()}{$i}`";
                echo $sql.PHP_EOL;
                // $spTable->query($sql);
            }
            
        }
    }
} catch(\Exception $ex) {
    // 可以记录日志
}

```


