<?php

require_once __DIR__ . '/Abstract.php';

class Db_Pdo extends Db_Abstract
{
    /**
     * @var PDO
     */
    public $db;
    /* @var PDOStatement */
    private $curStmt;

    /**
     * @param PDOStatement $resultSet
     * @return mixed
     */
    public function getNextAssoc($resultSet)
    {
        return $resultSet->fetch();
    }

    /**
     * @param string $field
     * @return string
     */
    public function unixTimestamp($field)
    {
        return 'UNIX_TIMESTAMP(' . $field . ')';
    }

    /**
     * @param int $days
     * @return string
     */
    public function dateSub($days)
    {
        return 'DATE_SUB(CURDATE(), INTERVAL ' . $days . ' DAY)';
    }

    public function connect()
    {
        $connectionString = $this->config['dbtype'] . ':host=' . $this->config['dbhost'] . ';dbname=' . $this->config['dbname'] . ";charset=utf8mb4";
        $this->db = new PDO($connectionString, $this->config['dbuser'], $this->config['dbpass']);
        if ($this->db === false) {
            xhprof_error("Could not connect to db");
            throw new RuntimeException("Unable to connect to database");
        }
        $this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    /**
     * @param string $sql
     * @return false|PDOStatement
     */
    public function query($sql)
    {
        $this->curStmt = $this->db->query($sql);
        return $this->curStmt;
    }

    /**
     * @param string $str
     * @return string
     */
    public function escape($str)
    {
        $str = $this->db->quote($str);
        //Dirty trick, PDO::quote add quote around values (you're beautiful => 'you\'re beautiful')
        // which are already added in xhprof_runs.php
        return trim($str, "'");
    }

    public function affectedRows()
    {
        if ($this->curStmt === false) {
            return 0;
        }
        return $this->curStmt->rowCount();
    }
}
