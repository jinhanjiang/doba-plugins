


### Excel分页解析

>如果excel中有上万条数据，一次性读到取内存中，会导致进程死掉，所以可分页读取excel数据
```
/**
 * 每次取100条数据，循环回调parserRow方法
 * array('SPU', 'Remark') , excel 第一行内容第一列为：SPU, 第二列为：Remark (注意区分大小写)
 */
$fields = array('SPU', 'Remark');
\Excel::read(
    __DIR__."/1.xlsx", 
    $fields, 
    array(), 
    array
    (
        'pagination'=>
            array(
                'callback'=>'parserRow',
                'perpage'=>100, 
                'start'=>1,
            )
    )
);
/**
 * 每次取$step条数据
 */
function parserRow($params)
{
    if(($dct = count($params['datas'])) == 0) return false;
    if(($ct = count($params['datas'])) > 0) foreach($params['datas'] as $data)
    {
        list($spu, $remark) = $data;
        // 业务逻辑处理

        file_put_contents(__DIR__.'/1.txt', $spu.PHP_EOL, 8);// 可以记录处理的数据行
    }
    closeDB(); // 防止数据库链接超时，导致程序后续取不到数据
    usleep(mt_rand(1000, 1000000)); //随机休息1000ms-1s
    return true;
}
```

### 导出Excel数据

```
// 1 简单导出 
// 定义导出表头(excel第一行标题)
$fields = array('id', 'Title', 'Picture'); // 可以传空数组，不导出头部信息
$datas = array();

$options = array();
//设置导出图片的相关设置，以下为默认设置
$options['imgConfig'] = array(
    'imgHeight'=>80, //图片高度
    'imgWidth'=>80, //图片宽度
    'offsetX'=>15, // 图片X偏移距离
    'offsetY'=>15, // 图片Y偏移距离
    'columnWidth'=>15, // 单元格宽设置
    'rowHeight'=>80, // 单元格高设置
)
// @后面为实际图片地址，例如：@/root/1.jpg, 则可导出图片


// 设置单元格，隔行背景色
颜色取值范围： black white red darkred blue darkblue green darkgreen yellow darkyellow
$options['bgOddEven'] = array('odd'=>'white', 'even'=>'yellow')

// 设置单元格背景色
$options['bgRows'] = array(
    2=>'green',
    3=>array('bg'=>'green'),
    4=>array('bg'=>'yellow', 'col'=>2),
    5=>array('bg'=>'blue', 'col'=>array(1, 2)),
    6=>array('bg'=>'red', 'cols'=>array(0=>'blue', 3=>'yellow', 6=>'ededed'))
);
// 假如有7条导出数据（一条数据一行）第1，2行无背景色,第3，4行绿背景色,第5行第3列黄背景色,
第6行第2,3列蓝背景色,第7行默认整行为红背景色,第1列蓝背景色,第4列为黄背景色,第6列灰背景色（rgb设置）


$objs = XXXDAO::me()->finds(array('selectCase'=>'*'));
if(is_array($objs)) foreach($objs as $obj)
{
    $datas[] = array(
        $obj->id,
        $obj->title,
        '@'.$obj->pic
    );
}
if (count($datas) > 0){
    $dir = Constant::getConstant('TEMP_PATH').'/'.date('Ymd'); Common::mkdirRecurse($dir);
    $filename = Excel::write($dir, $datas, $fields, $options);
    Common::genHeadDownloadFile($dir.'/'.$filename);
}else{
    throw new Exception('没有要导出的数据');
}


// 2 导出带图片的Excel(图片为本地图片，不能是URL地址)
$fields = array('图片', '商品名'); 
$datas[] = array('@/root/0.jpg', '图片1');
$datas[] = array('@/root/0.jpg', '图片2');

// 图片地址来源淘宝商品图片
// 非必传项
$optinos = array(
    'imgConfig'=>
        array(
            // 图片宽高
            'imgHeight'=>80,
            'imgWidth'=>80,
            // 图片偏移距离
            'offsetX'=>15, 
            'offsetY'=>15,
            // 单元格宽高
            'columnWidth'=>15,
            'rowHeight'=>80
        )
    );
Excel::write(__DIR__, $datas, $fields, $options);
```