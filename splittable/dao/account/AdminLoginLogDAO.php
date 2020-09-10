<?php
namespace Doba\Dao\Account;

use Doba\BaseDAO;
use Doba\Dao\SplitTableDAO;

class AdminLoginLogDAO extends SplitTableDAO {

    protected function __construct() {
        parent::__construct('AdminLoginLog', 
            array(
                'link'=>'account',
                'tbpk'=>\Doba\Map\Account\AdminLoginLog::getTablePk(),
                'tbinfo'=>\Doba\Map\Account\AdminLoginLog::getTableInfo(),
                'splitTableType'=>self::SPLIT_BY_HALF_YEAR,
                'backupTableDAO'=>'\Doba\Dao\Account\AdminLoginLogBackupDAO',
                'pidGeneratorDAO'=>'\Doba\Dao\Account\PidGeneratorDAO',
            )
        ); 
    }
    
    public function insert($params) 
    {
        $params['timeCreated'] = isset($params['timeCreated']) 
            ? $params['timeCreated'] : date('Y-m-d H:i:s');
        $params['dateCode'] = date('Ymd', strtotime($params['timeCreated']));
        
        return parent::insert($params);
    }
}