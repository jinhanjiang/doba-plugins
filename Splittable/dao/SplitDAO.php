<?php
namespace Doba\Dao;

use \Exception;
use Doba\BaseDAO;
abstract class SplitDAO extends BaseDAO
{
    /**
     * The table number of the current operation
     * @var int
     */
    protected $spCode = -1;

    /**
     * The table number of the current operation
     * @var int
     */
    protected $tableCount = 0;

    /**
     * construct method
     */
    protected function __construct($tableName, $options=array()) 
    {
        $options['sp'] = isset($options['sp']) ? $options['sp'] : '_';
        $this->tableCount = isset($options['tableCount']) ? $options['tableCount'] : 0;
        parent::__construct($tableName, $options);
    }

    /**
     * Get original table name
     * @return string
     */
    public function getOriginalTableName() {
        return $this->originaltbname;
    }

    /**
     * Get original table separate
     * @return string
     */
    public function getTablSeparate() {
        return $this->sp;
    }

    /**
     * Get original table count
     * @return int
     */
    public function getTableCount() {
        return $this->tableCount;
    }


    /**
     * The date is resolved through the pid(data unique identifier)
     *
     * @param int $pid
     * @return date
     */
    public function pidToDateCode($pid) {
        if(! preg_match('/^1\d{14}$/', $pid)) return 0;
        $fdCode = "20".substr($pid, 1, 2)."0101";
        $dCode = intval(substr($pid, 3, 3));
        return date("Ymd", strtotime("$fdCode +{$dCode} days"));
    }


    /**
     * Gets the current table number by date or pid(data unique identifier)
     * @param  array $params array('pid', 'dateCode')
     * @return int
     */
    abstract public function getSpCode($params);

    /**
     * Multi-table query
     * @param array('selectCase', 'orderBy', 'groupBy', 'limit', 'spCodes', ...)
     */
    public function findsByUnionAll($params)
    {
        $tableName = $this->originaltbname;
        $params['selectCase'] = isset($params['selectCase']) && $params['selectCase'] ? $params['selectCase'] : '*';

        $groupBy = isset($params['groupBy']) ? $params['groupBy'] : '';
        $orderBy = isset($params['orderBy']) ? $params['orderBy'] : '';
        $limit = isset($params['limit']) ? $params['limit'] : '';

        $tempTableName = $this->tbname; $this->tableName = $this->originaltbname;
        $where = $this->where($params);
        $sql = "SELECT {$params['selectCase']} FROM `{$this->tbname}` ".($where ? "WHERE ".$where : "");

        $this->tableName = $tempTableName;

        $prefix = preg_match('/(left|inner|right)\s*join/i', substr($sql, 0, stripos($sql, ' where '))) ? "`a`." : '';
        $sqlQuerys = array();
        //单张表统计后，再做总的统计
        $selectCase = preg_replace(array('/`/', '/\s*count.+as\s(\w+)(\,?)/i'), array('', ' $1$2'), $params['selectCase']);
        if(is_array($params['spCodes']) && count($params['spCodes']) > 0)
        {
            foreach($params['spCodes'] as $spCode)
            {
                $sqlQuerys[] = "SELECT {$selectCase} FROM (".
                    str_replace(
                    '`'.$tableName.'`', 
                    '`'.$tableName.$this->sp.$spCode.'`', 
                    $sql).") AS `T{$spCode}`";  
            }
        }
        else
        {
            $start = ($params['start'] < 0 || $params['start'] > $this->tableCount - 1) ? 0 : (int)$params['start'];
            if($end <= 0 || $end > $this->tableCount || $start > $end) $end = $this->tableCount;
            if($start == $end) $end += 1;

            for($j = $start; $j < $end; $j ++)
            {
                $sqlQuerys[] = "SELECT {$selectCase} FROM (".
                    str_replace(
                        '`'.$tableName.'`', 
                        '`'.$tableName.$this->sp.$j.'`', 
                        $sql).") AS `T{$j}`";
            }
        }
        $newSql = implode(" UNION ALL ", $sqlQuerys);
        $groupByStr = '';
        if(! empty($groupBy)) {
            $groupByStr = "GROUP BY {$groupBy}";
        }
        $orderByStr = '';
        if(! empty($orderBy) && 'NULL' != $orderBy && ! preg_match('/count\(/i', $params['selectCase'])) {
            $orderByStr = "ORDER BY {$orderBy}";
        }
        $limitStr = '';
        if(! empty($limit)) {
            $limitStr = "LIMIT {$limit}";
        }
        // After the statistics of a single table, do the total statistics
        $selectCase = preg_match('/AS\s+`cnt`$/i', $params['selectCase']) 
            ? 'SUM(`cnt`) AS `cnt`' : preg_replace('/\s*count\(/i', ' SUM(', $params['selectCase']);
        $newSql = "SELECT {$selectCase} FROM ({$newSql}) AS `ALLROWS` {$groupByStr} {$orderByStr} {$limitStr}";

        return $this->query($newSql);
    }
}