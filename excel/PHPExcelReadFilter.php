<?php
class PHPExcelReadFilter implements PHPExcel_Reader_IReadFilter
{
    public $startRow = 1;
    public $endRow;

    public function setRows($startRow, $chunkSize=1000)
    {
        $this->startRow = $startRow;
        $this->endRow = $startRow + $chunkSize -1;
    }

    public function readCell($column, $row, $worksheetName = '')
    {
        if(! $this->endRow) return true; //如果没有设置表示读取全部
        if(($row == 1) || ($row >= $this->startRow && $row <= $this->endRow)) return true;//读取指定行
        return false;
    }
}