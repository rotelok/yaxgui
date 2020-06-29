<?php

abstract class Db_Abstract
{
    public $db;
    protected $config;

    public function __construct($config)
    {
        $this->config = $config;
    }

    /**
     * @param  string $field
     * @return string
     */
    abstract public function unixTimestamp($field);

    /**
     * @param  int $days
     * @return string
     */
    abstract public function dateSub($days);

    /**
     * @param  $resultSet
     * @return mixed
     */
    abstract public function getNextAssoc($resultSet);

    abstract public function connect();

    /**
     * @param  string $sql
     * @return string
     */
    abstract public function query($sql);

    /**
     * @param  string $str
     * @return string
     */
    abstract public function escape($str);

    /**
     * @return int
     */
    abstract public function affectedRows();
}
