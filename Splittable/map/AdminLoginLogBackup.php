<?php
namespace Doba\Map\Account;

class AdminLoginLogBackup {

    public static function getTablePk() {
        return 'id';
    }

    public static function getTableInfo() {
        return array(
            array('field'=>'id', 'type'=>'int', 'notnull'=>1, 'default'=>NULL, 'pk'=>1, 'autoincremnt'=>1),
			array('field'=>'pid', 'type'=>'string', 'notnull'=>1, 'default'=>NULL, 'pk'=>0, 'autoincremnt'=>0),
			array('field'=>'adminId', 'type'=>'int', 'notnull'=>1, 'default'=>'0', 'pk'=>0, 'autoincremnt'=>0),
			array('field'=>'ip', 'type'=>'string', 'notnull'=>1, 'default'=>NULL, 'pk'=>0, 'autoincremnt'=>0),
			array('field'=>'loginTime', 'type'=>'string', 'notnull'=>1, 'default'=>NULL, 'pk'=>0, 'autoincremnt'=>0),
			array('field'=>'dateCode', 'type'=>'int', 'notnull'=>1, 'default'=>'0', 'pk'=>0, 'autoincremnt'=>0),
        );
    }

    public static function getSQL() {
        $sql = <<<SQL
        CREATE TABLE `AdminLoginLogBackup` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pid` char(15) NOT NULL COMMENT '唯一标识',
  `adminId` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '管理员编号',
  `ip` varchar(15) NOT NULL COMMENT '登录IP地址',
  `loginTime` datetime NOT NULL COMMENT '登录时间',
  `dateCode` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '日期，例如：20180101',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='管理员登录日志备份表（按年分表）'
SQL;
        return $sql;
    }
}