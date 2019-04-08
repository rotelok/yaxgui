<?php

abstract class Db_Abstract {
    public $db;
    protected $config;

    public function __construct($config) {
        $this->config = $config;
    }

    abstract public function unixTimestamp($field);

    abstract public function dateSub($days);

    abstract public function getNextAssoc($resultSet);

    abstract public function connect();

    abstract public function query($sql);

    abstract public function escape($str);

    abstract public function affectedRows();


}
