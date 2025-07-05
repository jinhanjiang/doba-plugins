<?php

namespace Doba\Plugin;

use Doba\Util;

use Doba\Plugin\Excel\PHPExcelReadFilter;
use Doba\Plugin\Excel\XLSXWriter;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Settings;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\BaseDrawing;
use PhpOffice\PhpSpreadsheet\Cell\Hyperlink;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ExcelPlugin extends BasePlugin {
    
    public function __construct(&$plugin){ 
        $this->_install($plugin, $this);
    }

    public function getXlsxWriter() {
        return new XLSXWriter();
    }

    /**
     * 读取Excel文件
     *
     * 第一行为标题, 从第二行开始读取
     *
     * @param $prams = array(
     *  'file' => EXCEL文件路径（必填）
     *  'fields' => 每一列，对应第一行的标题, 当前字段可不设置，不设置数据从第一行读取
     *              例如: array('用户ID', '用户名称')
     *
     *  'types'  => 每一列, 对应的数据类型，默认为string, 支持的类型(date, number, html, format)
     *              例如: 第二列为number类型 array(1=>'number')
     *              其中，format, 当传入的数据带公式计算时，当前字段应设置为format
     *
     *  'options' => 其他设置
     *              array(
     *                  'decimal' => 设置数据类型，保留小数位
     *                               例如: 第二列number类型保留4位小数array(1=>4) 
     *
     *                  'pagination' => array(  分页获取Excel中的数据，防止一次读取太多占导致内存占用过大，而读数据失败
     *                                      'callback' => 回调方法， 读到数据传给回调方法
     *
     *                                      'perpage' => 每次分页，获取的记录数
     *
     *                                      'start' => 开始获取数据的行
     *
     *                                      'callbackparams' => 其他参数。会一起提交到callback方法中
     *
     *                                      'auto' => 分页自动读取下一页， 默认为true
     *                                  )
     *              )
     * );
     *
     * @return array
     *
     * 导入的数据：
     * array(
     *     array('用户ID', '用户名称', '出生日期', '工资'),
     *     array('1','XiaoMing', '1988-03-23', '5000.00'), 
     *     array('2', 'XiangHong', '1989-06-17', '6500.00')
     * );
     *
     * 读取数据的格式
     * array(
     *      array('1','XiaoMing', '1988-03-23', '5000.00'), 
     *      array('2', 'XiangHong', '1989-06-17', '6500.00')
     * );
     */
    public function read($params = array())
    {
        $filepath = $params['filename']; 
        
        $fields = is_array($params['fields']) ? $params['fields'] : array(); 
        $types = is_array($params['types']) ? $params['types']: array();
        $options = is_array($params['options']) ? $params['options']: array();

        if(! is_file($filepath)) throw new \Exception("EXCEL file does not exist");

        $allow_types = array('string', 'date', 'number', 'html', 'format');
        if(count($types) > 0)  foreach($types as $type) {
            if(! empty($type) && ! in_array($type, $allow_types)) throw new \Exception("Invalid data type");
        }

        $iputFileType = IOFactory::identify($filepath);
        $objReader = IOFactory::createReader($iputFileType);

        if(is_callable($options['pagination']['callback']))
        {
            $auto = isset($options['pagination']['auto']) ? $options['pagination']['auto'] : true;
            $start = isset($options['pagination']['start']) 
                ? intval($options['pagination']['start']) : 1; $start = $start < 1 ? 1 : $start;
            $perpage = isset($options['pagination']['perpage']) 
                ? intval($options['pagination']['perpage']) : 1000; $perpage = $perpage < 1 ? 10000 : $perpage;

            $callbackparams = is_array($options['pagination']['callbackparams']) 
                ? $options['pagination']['callbackparams'] : array();

            for(; ;$start += $perpage)
            {
                $perf = new PHPExcelReadFilter();
                $perf->setRows($start, $perpage);
                $objReader->setReadFilter($perf);

                $objPHPExcel = $objReader->load($filepath);
                $endRow = $objPHPExcel->getActiveSheet()->getHighestDataRow();//行
                $startRow = $start;

                if($start > 1 && $endRow == 1) break;
                $end = $endRow < $perf->endRow ? $endRow : $perf->endRow;
                if($startRow == 1)
                {
                    $startRow = $this->checkTitleRow(
                        array(
                            'fields'=>$fields,
                            'objPHPExcel'=>$objPHPExcel,
                        )
                    );
                }
                call_user_func_array($options['pagination']['callback'], array(
                    array(
                        'datas'=>$this->parseRow(
                                array(
                                    'startRow'=>$startRow,
                                    'endRow'=>$end,
                                    'types'=>$types,
                                    'options'=>$options,
                                    'objPHPExcel'=>$objPHPExcel,
                                    'resetKey'=>true,
                                )
                            ),
                        'start'=>$start,
                        'end'=>$end,
                        'callbackparams'=>$callbackparams
                    )
                ));
                $objPHPExcel->disconnectWorksheets(); unset($objPHPExcel);
                if(! $auto || $endRow < $perf->endRow) break;
            }
            return array();
        }
        else
        {
            $objPHPExcel = $objReader->load($filepath);
            $startRow = $this->checkTitleRow(
                array(
                    'fields'=>$fields,
                    'objPHPExcel'=>$objPHPExcel,
                )
            );
            return $this->parseRow(
                array(
                    'startRow'=>$startRow,
                    'endRow'=>$objPHPExcel->getActiveSheet()->getHighestDataRow(),
                    'types'=>$types,
                    'options'=>$options,
                    'objPHPExcel'=>$objPHPExcel,
                    'resetKey'=>false,
                )
            );
        }
    }

    /**
     * @param $params array('endRow', 'fields', 'objPHPExcel')
     * @return bool
     */
    private function checkTitleRow($params)
    {
        if(! is_array($params['fields']) || count($params['fields']) == 0) return 1;
        $endRow = $params['objPHPExcel']->getActiveSheet()->getHighestDataRow();//行
        if($endRow < 1) throw new \Exception("Row is empty");
        $column = $params['objPHPExcel']->getActiveSheet()->getHighestDataColumn();//如果有两列，则显示B
        $allColumn = $this->getNumbersFromAlpha($column);//第一列为0
        //判断标题行是否一致
        for($i = 0 ; $i <= $allColumn; $i ++)
        {
            $current = $this->makeAlphaFromNumbers($i).'1';
            $data = $params['objPHPExcel']->getActiveSheet()->getCell($current)->getValue() ;
            if(strtolower(trim($data)) != strtolower(trim($params['fields'][$i]))) {
                throw new \Exception("The title row is wrong ({$data})-({$params['fields'][$i]})");
            }
        }
        return 2;
    }

    /**
     * @param $params array('startRow', 'endRow', 'types', 'options', 'objPHPExcel')
     * @return array
     */
    private function parseRow($params)
    {
        $datas = array();
        $column = $params['objPHPExcel']->getActiveSheet()->getHighestDataColumn();//如果有两列，则显示B
        $allColumn = $this->getNumbersFromAlpha($column);//第一列为0

        for(; $params['startRow'] <= $params['endRow']; $params['startRow'] ++)
        {
            $rowArray = array();
            for($currentColumn = 0; $currentColumn <= $allColumn; $currentColumn ++)
            {
                $current = $this->makeAlphaFromNumbers($currentColumn).$params['startRow'];
                $dtype = isset($params['types'][$currentColumn]) ? $params['types'][$currentColumn] : 'string';

                // getValue()会获取公式本身， getFormattedValue()获取到的是公式计算后的值
                if('format' == $dtype)
                {
                    // 科学记数法转为正确的数值
                    $params['objPHPExcel']->getActiveSheet()->getStyle($current)
                        ->getNumberFormat()
                        ->setFormatCode(
                            NumberFormat::FORMAT_NUMBER
                        );
                    // getFormattedValue()获取到的是公式计算后的值
                    $data = $params['objPHPExcel']->getActiveSheet()->getCell($current)->getFormattedValue();
                    $data = trim($data);
                }
                else 
                {
                    // getValue()会获取公式本身
                    $data = $params['objPHPExcel']->getActiveSheet()->getCell($current)->getValue();
                    $data = trim($data);
                    switch($dtype)
                    {
                        case 'date':
                            if (! empty($data))
                            {
                                //Excel的日期格式
                                if (is_numeric($data)) $data = $this->excelD2DMY(intval($data));
                                if (! strtotime($data)) $data = '';
                            }
                            break;

                        case 'number':
                            if (! is_numeric($data)) $data = '';
                            else {
                                $decimalDigits = isset($params['options']['decimal'][$currentColumn]) 
                                    ? intval($params['options']['decimal'][$currentColumn]) : 0;
                                $data = number_format($data, $decimalDigits, '.', '');
                            }
                            break;

                        case 'html':
                            $data = $this->removeScriptTag($data);
                            break;

                        default://默认为string
                            $data = $this->html2text($data);
                            break;
                    }
                }
                $rowArray[] = $data;
            }
            if($params['resetKey']){
                $datas[$params['startRow']] = $rowArray;
            }  else {
                $datas[] = $rowArray;
            }
        }
        $params['objPHPExcel']->disconnectWorksheets(); unset($params['objPHPExcel']);
        return $datas;
    }

    /**
     * 将数组集写入到Excel中
     *
     * @param $params array(
     *     'filepath' => Excel保存的目录（必填）
     *
     *     'fields' => Excel 第一行标题
     *
     *     'datas' => 数据集合
     *
     *     'options' => array( 其他配置参数
     *
     *          ‘sheet1‘ => 设置Sheet1名字， 默认为:Sheet1
     *
     *          导出图片格式: png jpg jpeg gif
     *          'imgConfig' => array( 导出图片设置：@后面为实际图片地址，例如：@/tmp/1.jpg, 则可导出图片
     *              'imgHeight'=>80,    图片高度
     *              'imgWidth'=>80,     图片宽度
     *              'offsetX'=>15,      图片X偏移距离
     *              'offsetY'=>15,      图片Y偏移距离
     *              'columnWidth'=>15,  单元格宽设置
     *              'rowHeight'=>80,    单元格高设置
     *          ),
     *
     *          设置导出行背景色 (颜色取值范围： black white red darkred blue darkblue green darkgreen yellow darkyellow)
     *          设置单元格，隔行背景色
     *          'bgOddEven' => array('odd'=>'white', 'even'=>'yellow')
     *
     *          设置单元格颜色
     *          'bgRows'  => array(  假如有6条导出数据（一条数据一行），第1，2行无背景色,第3，4行绿背景色,第5行第3列黄背景色,第6行第2,3列黄背景色
     *                       2=>'green',
     *                       3=>array('bg'=>'red'),
     *                       4=>array('bg'=>'yellow', 'col'=>2),
     *                       5=>array('bg'=>'blue', 'col'=>array(1, 2)),
     *                   );
     *     )
     *
     * )
     *
     */
    public function write($params = array())
    {
        $filepath = $params['filepath']; 
        
        $datas = $params['datas']; 
        $fields = is_array($params['fields']) ? $params['fields']: array();
        $options = is_array($params['options']) ? $params['options']: array();

        $rowCnt = ($titleCnt = count($fields)) > 0 ? $titleCnt : count($datas[0]);

        if($rowCnt > 100) throw new \Exception("Field over 100"); //暂时最大导出100列
        if(($dataCnt = count($datas)) > 65535) throw new \Exception("Data over 65535");

        $objPHPExcel = new Spreadsheet();
        $objPHPExcel->setActiveSheetIndex(0);
        $objActSheet = $objPHPExcel->getActiveSheet();
        // https://phpoffice.github.io/PhpSpreadsheet/classes/PhpOffice-PhpSpreadsheet-Worksheet-Worksheet.html#method_setTitle
        //设置当前活动sheet的名称
        $objActSheet->setTitle($options['sheet1'] ? $options['sheet1'] : 'Sheet1');

        $colors = array(
            'black'=>Color::COLOR_BLACK, 
            'white'=>Color::COLOR_WHITE, 
            'red'=>Color::COLOR_RED, 
            'darkred'=>Color::COLOR_DARKRED, 
            'blue'=>Color::COLOR_BLUE, 
            'darkblue'=>Color::COLOR_DARKBLUE, 
            'green'=>Color::COLOR_GREEN, 
            'darkgreen'=>Color::COLOR_DARKGREEN, 
            'yellow'=>Color::COLOR_YELLOW, 
            'darkyellow'=>Color::COLOR_DARKYELLOW
        );
        $bgOdd = $bgEven = ''; $bgRows = array();
        if(isset($options['bgOddEven']))
        {
            $bgOdd = isset($options['bgOddEven']['odd']) ? $options['bgOddEven']['odd'] : 'white';
            $bgEven = isset($options['bgOddEven']['even']) ? $options['bgOddEven']['even'] : 'white';
        }
        else if(is_array($options['bgRows'])) $bgRows = $options['bgRows'];
        if($titleCnt > 0) { 
            array_unshift($datas, $fields); $dataCnt += 1;
        }
        for($i = 0; $i < $dataCnt; $i ++ )
        {
            $col_index = $i + 1; $bgRowNum = $titleCnt > 0 ? ($i - 1) : $i;
            for($j = 0; $j < $rowCnt; $j ++)
            {
                $alpha = $this->makeAlphaFromNumbers($j);
                $col_tag = $alpha.$col_index; //块标记
                $value = $datas[$i][$j];//块值

                // 设置单元格背景色
                $bg = '';
                if($bgEven && $bgOdd) {
                    $bg = ($i%2==0) ? $bgOdd : $bgEven;
                } 
                else if(isset($bgRows[$bgRowNum])) 
                {
                    /*
                    $options['bgRows'] = array(
                        2=>'green',
                        3=>array('bg'=>'red'),
                        4=>array('bg'=>'yellow', 'col'=>2),
                        5=>array('bg'=>'blue', 'col'=>array(1, 2)),
                        6=>array('bg'=>'red', 'cols'=>array(0=>'blue', 3=>'yellow', '5'=>'green'))
                    );
                     */
                    $bgRowConfig = $bgRows[$bgRowNum];
                    if(is_array($bgRowConfig))
                    {
                        if(is_array($bgRowConfig['cols']))
                        {
                            $bg = isset($bgRowConfig['bg']) ? $bgRowConfig['bg'] : 'white';
                            if(isset($bgRowConfig['cols'][$j])) $bg = $bgRowConfig['cols'][$j];
                        }
                        else if(isset($bgRowConfig['col']))
                        {
                            if(is_array($bgRowConfig['col']) && in_array($j, $bgRowConfig['col'])) {
                                $bg = isset($bgRowConfig['bg']) ? $bgRowConfig['bg'] : 'white';
                            }
                            else if($j == intval($bgRowConfig['col'])) {
                                $bg = isset($bgRowConfig['bg']) ? $bgRowConfig['bg'] : 'white';
                            }
                        }
                        else {
                            $bg = isset($bgRowConfig['bg']) ? $bgRowConfig['bg'] : 'white';
                        }
                    }
                    else {
                        $bg = $bgRowConfig;
                    }
                }
                if($bg) 
                {
                    $argb = 'FFFFFFFF';
                    if(isset($colors[$bg])) $argb = $colors[$bg];
                    else if(preg_match('/^ff([a-f0-9]){6}$/i', $bg)) $argb = strtoupper($bg);
                    else if(preg_match('/^([a-f0-9]){6}$/i', $bg)) $argb = 'FF'.strtoupper($bg);
                    // https://phpoffice.github.io/PhpSpreadsheet/classes/PhpOffice-PhpSpreadsheet-Worksheet-Worksheet.html#method_getStyle
                    $objActSheet->getStyle($col_tag)->getFill()->setFillType(Fill::FILL_SOLID);
                    $objActSheet->getStyle($col_tag)->getFill()->getStartColor()->setARGB($argb);
                }

                // http://xxx.xxx.com/tmp/1.jpg
                if(preg_match('/^https?:\/\/.*\.(png|jpg|jpeg|gif)$/', $value))
                {
                    $imgConfig = is_array($options['imgConfig']) ? $options['imgConfig'] : array();

                    // https://phpoffice.github.io/PhpSpreadsheet/classes/PhpOffice-PhpSpreadsheet-Worksheet-BaseDrawing.html#method_setHyperlink
                    $objDrawing = new BaseDrawing();
                    // $objDrawing->setPath($value);
                    $objDrawing->setHyperlink(new Hyperlink($value));
                    // 设置图片要插入的单元格
                    $objDrawing->setCoordinates($col_tag);
                    // 设置宽度高度
                    $objDrawing->setWidthAndHeight(isset($imgConfig['imgHeight']) ? $imgConfig['imgHeight'] : 80, 
                        isset($imgConfig['imgWidth']) ? $imgConfig['imgWidth'] : 80);
                    // 图片偏移距离
                    $objDrawing->setOffsetX(isset($imgConfig['offsetX']) ? $imgConfig['offsetX'] : 15);
                    $objDrawing->setOffsetY(isset($imgConfig['offsetY']) ? $imgConfig['offsetY'] : 15);
                    //单元格宽高设置
                    $objActSheet->getColumnDimension($alpha)->setWidth(isset($imgConfig['columnWidth']) ? $imgConfig['columnWidth'] : 15);  
                    $objActSheet->getRowDimension($col_index)->setRowHeight(isset($imgConfig['rowHeight']) ? $imgConfig['rowHeight'] : 80);

                    $objDrawing->getShadow()->setVisible(false);
                    $objDrawing->setWorksheet($objPHPExcel->getActiveSheet());

                    continue;
                }
                $objActSheet->setCellValueExplicit($col_tag, $value, DataType::TYPE_STRING);
            }
        }

        $objWriter = new Xlsx($objPHPExcel);

        $filename = $this->genFilename();
        $excelfile = preg_replace('/\/$/', '', $filepath).'/'.$filename;
        $objWriter->save($excelfile);

        return $filename;
    }

    /**
     * 获取Excel共多少行
     * @param $file 文件地址
     */
    public function getColRow($file)
    {
        if(! is_file($file)) {
            throw new \Exception("EXCEL file does not exist", 98011301);
        }

        $iputFileType = IOFactory::identify($file);
        $objReader = IOFactory::createReader($iputFileType);

        $allRow = $allColumn = 0;
        for($start = 1, $perpage = 10000; ;$start += $perpage)
        {
            $perf = new PHPExcelReadFilter();
            $perf->setRows($start, $perpage);
            $objReader->setReadFilter($perf);

            $objPHPExcel = $objReader->load($file);
            $endRow = $objPHPExcel->getActiveSheet()->getHighestDataRow();//行

            if($start > 1 && $endRow == 1) break;
            $allRow = $endRow < $perf->endRow ? $endRow : $perf->endRow;

            if($start == 1) {//取得一共有多少列
                $column = $objPHPExcel->getActiveSheet()->getHighestDataColumn();//如果有两列，则显示B
                $allColumn = $this->getNumbersFromAlpha($column);//第一列为0
            }
            $objPHPExcel->disconnectWorksheets(); unset($objPHPExcel);
            if($endRow < $perf->endRow) break;
        }
        return array('column'=>$allColumn, 'row'=>$allRow);
    }

    private function genFilename($suffix = '.xlsx')
    {
        preg_match('/0.([0-9]+) ([0-9]+)/', microtime(), $regs);
        return $regs[2].$regs[1].sprintf('%03d',rand(0,999)).$suffix;
    }

    private function makeAlphaFromNumbers($number)
    {
        $numeric = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
        if($number < strlen($numeric)) return $numeric[$number];
        else
        {
            $devBy = floor($number / strlen($numeric));
            return "" . $this->makeAlphaFromNumbers($devBy - 1) . $this->makeAlphaFromNumbers($number - ($devBy * strlen($numeric)));
        }
    }

    private function getNumbersFromAlpha($alpha)
    {
        $numeric = 0;
        if(preg_match('/^[A-Z]+$/',$alpha))
        {
            do {
                $new_alpha = $this->makeAlphaFromNumbers($numeric);
                if($new_alpha === $alpha) return $numeric;
                $numeric ++;
            } while(true);
        }
        return false;
    }

    // 防止脚本注入
    private function removeScriptTag($text)
    {
        $search = array ("'<script[^>]*?>.*?</script>'si",  // 去掉 javascript
            "'<iframe[^>]*?>.*?</iframe>'si");  //去掉iframe
        $replace = array ('', '');
        $text = preg_replace ($search, $replace, $text);
        return preg_replace_callback("'&#(\d+);'", function($m) { return chr($m[1]); }, $text);
    }

    /**
     * HTML转换成文本
     */
    private function html2text($text)
    {
        if("" == $text) return $text;
        $search = array ("'<script[^>]*?>.*?</script>'si",  // 去掉 javascript
            "'<[\/\!]*?[^<>]*?>'si",           // 去掉 HTML 标记
            "'([\r\n])[\s]+'",                 // 去掉空白字符
            "'&(quot|#34);'i",                 // 替换 HTML 实体
            "'&(amp|#38);'i",
            "'&(lt|#60);'i",
            "'&(gt|#62);'i",
            "'&(nbsp|#160);'i",
            "'&(iexcl|#161);'i",
            "'&(cent|#162);'i",
            "'&(pound|#163);'i",
            "'&(copy|#169);'i");

        $replace = array ("",
            "",
            "\\1",
            "\"",
            "&",
            "<",
            ">",
            " ",
            chr(161),
            chr(162),
            chr(163),
            chr(169));

        $text = preg_replace($search, $replace, $text);
        return preg_replace_callback("'&#(\d+);'", function($m) { return chr($m[1]); }, $text);
    }

    /**
     * 格式化excel日期
     */
    private function excelD2DMY($days)
    {
        if ($days < 1) return "";
        if ($days == 60)  {
            return array('day'=>29, 'month'=>2, 'year'=>1900);
        } 
        else 
        {
            if ($days < 60)  {
                // Because of the 29-02-1900 bug, any serial date
                // under 60 is one off... Compensate.
                ++$days;
            }
            // Modified Julian to DMY calculation with an addition of 2415019
            $l = $days + 68569 + 2415019;
            $n = floor(( 4 * $l ) / 146097);
            $l = $l - floor(( 146097 * $n + 3 ) / 4);
            $i = floor(( 4000 * ( $l + 1 ) ) / 1461001);
            $l = $l - floor(( 1461 * $i ) / 4) + 31;
            $j = floor(( 80 * $l ) / 2447);
            $nDay = $l - floor(( 2447 * $j ) / 80);
            $l = floor($j / 11);
            $nMonth = $j + 2 - ( 12 * $l );
            $nYear = 100 * ( $n - 49 ) + $i + $l;
            return sprintf('%04d-%02d-%02d', $nYear, $nMonth, $nDay);
       }
    }
}