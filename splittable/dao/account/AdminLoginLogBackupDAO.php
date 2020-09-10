<?php
namespace Doba\Dao\Account;

use Doba\BaseDAO;
use Doba\Dao\BackupTableDAO;

class AdminLoginLogBackupDAO extends BackupTableDAO {

    protected function __construct() {
        parent::__construct('AdminLoginLogBackup', 
            array(
                'link'=>'account',
                'tbpk'=>\Doba\Map\Account\AdminLoginLogBackup::getTablePk(),
                'tbinfo'=>\Doba\Map\Account\AdminLoginLogBackup::getTableInfo(),
                'splitTableDAO'=>'\Doba\Dao\Account\AdminLoginLogDAO',
                'cacheKeyPrefix'=>'DB_ACCOUNT_ADMIN_LOGIN_LOG_BACKUP',
                'earliestQueryYear'=>2020
            )
        ); 
    }
}