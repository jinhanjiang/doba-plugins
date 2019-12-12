# 引入第三方代码

使用Excel功能，需要引用第三方的代码，下载地址如下：

https://github.com/PHPOffice/PHPExcel/releases

# 安装

### 下载第三方代码

例如：我们下载PHPExcel-1.8.2.tar.gz , 解压后将Classes/下的所有拷贝到[项目]/common/plugin/excel/, 结构如下

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
|   |   |-excel
|   |   |   |-config.php
|   |   |   |-PHPExcel
|   |   |   |   |-Autoloader.php
|   |   |   |   |-更多文件及目录
|   |   |   |
|   |   |   |-PHPExcel.php
```

# 用法

### 读取Excel数据

1 简单读取数据
```
$datas = $GLOBALS['plugin']->call('excel', 'read', array('file'=>__DIR__.'/1.xlsx'));
print_r($datas);
```


2 Excel第一行带标题
```
$datas = $GLOBALS['plugin']->call('excel', 'read', 
    array(
        'file'=>__DIR__.'/1.xlsx',
        'fields'=>array('用户ID', '用户名称', '出生日期', '工资')
    )
);
print_r($datas);
```
如果Excel中第一行数据和传入的fields值不一致，将抛出异常


3 导入数据类型格式化
```
$datas = $GLOBALS['plugin']->call('excel', 'read', 
    array(
        'file'=>__DIR__.'/1.xlsx',
        'fields'=>array('用户ID', '用户名称', '出生日期', '工资'),
        'types'=>array('number', 'string', 'number', 'number'),
    )
);
print_r($datas);
```

4 分页读取数据

如果excel中数据量比较大。一次加截入内存，会导致内存溢出而导入操作失败

```
/**
 * 每次取$step条数据
 * @param $params 传入的值结构
 * 
 * array(
 *       'datas'=>$this->parseRow(
 *               array(
 *                   'startRow'=>$startRow,
 *                   'endRow'=>$end,
 *                   'types'=>$types,
 *                   'options'=>$options,
 *                   'objPHPExcel'=>$objPHPExcel,
 *                   'resetKey'=>true,
 *               )
 *           ),
 *       'start'=>$start,
 *       'end'=>$end,
 *       'callbackparams'=>$callbackparams
 *   )
 */
function parserRow($params)
{
    if(($dct = count($params['datas'])) == 0) return false;
    if(($ct = count($params['datas'])) > 0) foreach($params['datas'] as $data)
    {
        list($userId, $name, $birthday, $salary) = $data;
        // 业务逻辑处理

        file_put_contents(__DIR__.'/1.txt', $userId.PHP_EOL, 8);// 可以记录处理的数据行
    }
    usleep(mt_rand(1000, 1000000)); //随机休息1000ms-1s
    return true;
}

/**
 * 每次取100条数据，循环回调parserRow方法
 */
$GLOBALS['plugin']->call('excel', 'read', 
    array (
        'file'=>__DIR__.'/1.xlsx',
        'fields'=>array('用户ID', '用户名称', '出生日期', '工资'),
        'options'=>array(
            'pagination'=>array(
                'callback'=>'parserRow',
                'perpage'=>100, 
                'callbackparams' => array('groupId'=>1)
            )
        )
    )
);
```


### 写数据到Excel

1 简单导出 
```
// 定义导出表头(excel第一行标题)
$fields = array('用户ID', '用户名称', '出生日期', '工资', '头像'); // 可以传空数组[array()]，不导出头部信息

// 导出图片以@开头存在的图片文件
$datas = array(
  array('1','XiaoMing', '1988-03-23', '5000.00', '@/tmp/1.jpg'), 
  array('2', 'XiangHong', '1989-06-17', '6500.00', '@/tmp/2.jpg')
);

$GLOBALS['plugin']->call('excel', 'write', 
    array (
        'filepath'=>__DIR__,
        'fields'=>$fields,
        'datas'=>$datas
    )
);
```

2 导出单元格带背景色

>颜色取值范围: black white red darkred blue darkblue green darkgreen yellow darkyellow

奇数行，白色背景， 偶数行，黄色背景
```
$GLOBALS['plugin']->call('excel', 'write', 
    array (
        'filepath'=>__DIR__,
        'titleFields'=>$fields,
        'datas'=>$datas,
        'options'=>array(
            'bgOddEven'=>array(
                'odd'=>'white', 'even'=>'yellow'
            )
        )
    )
);

```

假如有6条导出数据（一条数据一行），第1行无背景色,第2行绿背景色,第三行红色背景,第5行第3列黄色背景色,第6行第2,3列蓝色背景色
```
$GLOBALS['plugin']->call('excel', 'write', 
    array (
        'filepath'=>__DIR__,
        'titleFields'=>$fields,
        'datas'=>$datas,
        'options'=>array(
            'bgRows'=>array(
                2=>'green',
                3=>array('bg'=>'red'),
                4=>array('bg'=>'yellow', 'col'=>2),
                5=>array('bg'=>'blue', 'col'=>array(1, 2)),
            )
        )
    )
);
```

3 导出数据带图片

可设置导出图片宽，高， 及单元格大小
注意：导出图片为[@图片路径], 即在服务器上的本地图片， 不能是以URL格式的图片

```
$GLOBALS['plugin']->call('excel', 'write', 
    array (
        'filepath'=>__DIR__,
        'titleFields'=>$fields,
        'datas'=>$datas,
        'options'=>array(
            'imgConfig'=>array(
                'imgHeight'=>80,    图片高度
                'imgWidth'=>80,     图片宽度
                'offsetX'=>15,      图片X偏移距离
                'offsetY'=>15,      图片Y偏移距离
                'columnWidth'=>15,  单元格宽设置
                'rowHeight'=>80,    单元格高设置
            )
        )
    )
);
```