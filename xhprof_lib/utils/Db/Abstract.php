<?php

abstract class Db_Abstract {
    public $linkID;
    protected $config;

    public function __construct($config) {
        $this->config = $config;
    }

    public static function unixTimestamp($field) {
        throw new RuntimeException("Method '" . get_called_class() . "::" . __FUNCTION__ . "' not implemented");
    }

    public static function dateSub($days) {
        throw new RuntimeException("Method '" . get_called_class() . "::" . __FUNCTION__ . "' not implemented");
    }

    public static function getNextAssoc($resultSet) {
        throw new RuntimeException("Method '" . get_called_class() . "::" . __FUNCTION__ . "' not implemented");
    }

    abstract public function connect();

    abstract public function query($sql);

    abstract public function escape($str);

    abstract public function affectedRows();


}
