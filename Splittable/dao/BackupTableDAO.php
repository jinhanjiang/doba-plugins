<?php
namespace Doba\Dao;

use \Exception;
use Doba\BaseDAO;
use Doba\RedisClient;
use Doba\Dao\SplitDAO;

abstract class BackupTableDAO extends SplitDAO
{
    /**
     * whether the current table exists, if it exists, save a value in the cache, reduce the SQL qeuery
     * current value is the cached key prefix
     *
     * @var string
     */
    private $cacheKeyPrefix = NULL;

    /**
     * original split talbe object
     *
     * @var object
     */
    private $splitTableDAO = NULL;

    /**
     * The earliest year of data storage
     * @var int like: 2018
     */
    private $earliestQueryYear = 0;

    /**
     * construct method
     */
    protected function __construct($tableName, $options=array()) {
        if(isset($options['splitTableDAO'])) $this->splitTableDAO = $options['splitTableDAO'];
        $this->cacheKeyPrefix = isset($options['cacheKeyPrefix']) ? $options['cacheKeyPrefix'] : debug_backtrace()[0]['class'];
        $this->earliestQueryYear = isset($options['earliestQueryYear']) ? $options['earliestQueryYear'] : date('Y');
        parent::__construct($tableName, $options);
    }

    /**
     * Gets split table instance
     * @return object
     */
    protected function getSplitTableDAO() {
        if(! $this->splitTableDAO) throw new Exception('You need to set the split table name.', 99040001);
        return call_user_func(array($this->splitTableDAO, 'me'));
    }

    /**
     * Gets the earliest year of data storage
     * @return int
     */
    public function getEarliestQueryYear() {
        return $this->earliestQueryYear;
    }

    /**
     * Split table by year
     * @param  array $params array('code', 'dateCode')
     * @return int
     */
    public function getSpCode($params) 
    {
        $dateCode = 0;
        if(isset($params['pid'])) {
            $dateCode = $this->pidToDateCode($params['pid']);
        } else if($params['dateCode']) {
            $dateCode = intval($params['dateCode']);
        }
        if($dateCode == 0 || intval(strtotime($dateCode)) == 0) return -1;
        return intval(date('y', strtotime(strval($dateCode))));
    }

    /**
     * whether split table does not exist automatically created
     *
     * @param int $spCode
     * @return bool
     */
    protected function initTable($spCode) {
        $spCode = intval($spCode);
        if(! RedisClient::me()->get($this->cacheKeyPrefix.'_'.$spCode)) {
            parent::query("CREATE TABLE IF NOT EXISTS `{$this->originaltbname}_{$spCode}` LIKE `{$this->originaltbname}`");
            RedisClient::me()->set($this->cacheKeyPrefix.'_'.$spCode, $spCode);
        }
        return true;
    }
    
    /**
     * Gets data through pid
     *
     * @param int
     * @return object
     */
    public function get($pid=0)
    {
        $spCode = $this->getSpCode(array('pid'=>$pid)); 
        if(-1 == $spCode) return NULL;
        $objs = $pid ? $this->finds(
            array(
                'selectCase'=>'*', 
                'limit'=>1, 
                'orderBy'=>'NULL',
                'spCode'=>$spCode, 
                'pid'=>$pid,
            )
        ) : array();
        return ($objs[0]->id > 0) ? $objs[0] : NULL;
    }

    /**
     * Delete data
     */
    public function delete($pid=0) {
        $obj = $this->get($pid);
        if($obj->id > 0) 
        {
            $spCode = $this->getSpCode(array('pid'=>$obj->pid)); 
            if(-1 == $spCode) return 0;
            $this->table($spCode);
            return parent::delete($obj->id);
        }
        return false;
    }

    /**
     * Add data
     *
     * @param array $params 
     * @return int
     */
    public function insert($params)
    {
        $params['dateCode'] = date('Ymd', strtotime($params['dateCode']));
        $spCode = $this->getSpCode($params); if(-1 == $spCode) return 0;

        // 1 检查备份库是否存在，不存在自动创建
        $this->initTable($spCode);

        $this->table($spCode);
        $params['_INSERT_IGONRE'] = true;
        return parent::insert($params);
    }

    /**
     * Modify data
     *
     * @param array $params 
     * @return int
     */
    public function change($pid=0, $params)
    {
        $obj = $this->get($pid);
        if(empty($obj)) return 0;
     
        $spCode = $this->getSpCode(array('pid'=>$pid));   
        if(-1 == $spCode) return NULL;
        $this->table($spCode);
        return parent::change($obj->id, $params);
    }

    /**
     * Batch query data
     */
    public function finds($params) 
    {
        if(is_numeric($params['spCode']) && -1 != $params['spCode']) {
            $this->initTable($params['spCode']);
            $this->table($params['spCode']);
            return parent::finds($params);
        } else if(is_array($params['spCode'])) {
            foreach($params['spCode'] as $spCode) $this->initTable($spCode);
            if(count($params['spCode']) == 1) {
                return $this->finds(array('spCode'=>$params['spCode'][0]) + $params);
            }
            else if(count($params['spCode']) > 1) {
                $params['spCodes'] = $params['spCode']; unset($params['spCode']);
                // return $this->findsByUnionAll($params, $pagination);
            }
        }
        return array();
    }

    public function sync($pid)
    {
       // 1180050000001065
        if(! preg_match('/^1\d{14}$/', $pid)) return false;

        // 1 确主分表编号
        $spCode = $this->getSplitTableDAO()->getSpCode(array('pid'=>$pid));
        $ypCode = $this->getSpCode(array('pid'=>$pid)); 
        if(-1 != $ypCode) $this->initTable($ypCode);

        // 2 查询原表中的数据0 
        $objs = $this->query("SELECT * FROM `{$this->getSplitTableDAO()->getOriginalTableName()}_{$spCode}` WHERE `pid`='{$pid}' LIMIT 1");
        if($objs[0]->id > 0)
        {
            $obj = \Doba\Util::object2array($objs[0]);

            // 3 检查表是否存在
            $spCode = $this->getSpCode(array('pid'=>$obj['pid']));
            if(-1 == $spCode) return NULL;
            $this->initTable($spCode);
            // 4 检查当前数据在缓存表中是否存在
            $backup = $this->get($obj['pid']);
            unset($obj['id']);
            if($backup->id > 0)
            {
                // 5 数据存在,检查是否更新
                $this->change($obj['pid'], $obj);
            }
            else
            {
                // 6 数据在备份表不存在则添加
                $this->insert($obj);
            }
        }
        unset($objs);
        return true;
    }
}