<?php
namespace Doba\Map\Account;

class PidGenerator {

    public static function getTablePk() {
        return 'id';
    }

    public static function getTableInfo() {
        return array(
            array('field'=>'id', 'type'=>'int', 'notnull'=>1, 'default'=>NULL, 'pk'=>1, 'autoincremnt'=>1),
        );
    }

    public static function getSQL() {
        $sql = <<<SQL
        CREATE TABLE `PidGenerator` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '自增ID',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='ID生成器'
SQL;
        return $sql;
    }
}