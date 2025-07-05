<?php
namespace Doba\Dao\Account;

use Doba\RedisClient;
use Doba\BaseDAO;

class PidGeneratorDAO extends BaseDAO {

    protected function __construct() {
        parent::__construct('PidGenerator', 
            array(
                'link'=>'account',
                'tbpk'=>\Doba\Map\Account\PidGenerator::getTablePk(),
                'tbinfo'=>\Doba\Map\Account\PidGenerator::getTableInfo(),
            )
        ); 
    }
    
    public function insert($params)
    {
        $rc = RedisClient::me();
        if($rc)
        {
            $rcKey = 'DB_ACCOUNT_PID_GENERATEOR';
            $count = 0; $timeout = 3;//3s后锁超时
            while(1) {
                if($rc->setnx($rcKey.'_LOCK', time())) break;
                if($count>=$timeout) break; $count++; sleep(1);
            }
            $id = $rc->getRedis()->incr($rcKey); $step = 1000;
            if($id < $step) {//redis数据丢失
                $lasts = $this->finds(array('orderBy'=>'id DESC', 'limit'=>1)); $lastId = $lasts[0]->id;
                while($lastId % $step != 0) {//数据库预存值可能小于步长
                    $lastId ++;
                }
                $rc->set($rcKey, $lastId); parent::insert(array('id'=>$lastId + $step));//数据库保存2000
            } else if($id >= 98999900) {//id超过最大范围
                parent::query("TRUNCATE TABLE `{$this->originaltbname}`");
                $rc->set($rcKey, $step); parent::insert(array('id'=>$step + $step));//数据库保存2000
            } else if($id % $step == 0) {//超过数据保存最大范围值
                parent::insert(array('id'=>$id + $step, '_INSERT_IGONRE'=>true));//数据库保存id+步长
            }
            $id = $rc->get($rcKey);
            $rc->delKey($rcKey.'_LOCK');
        }
        else
        {
            $id = parent::query("INSERT INTO `{$this->originaltbname}`(`id`) VALUES(0)");
            if($id > 98999900) { 
                parent::query("TRUNCATE TABLE `{$this->originaltbname}`");
            }
        }
        return $id;
    }
}