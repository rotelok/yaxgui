<?php
require_once __DIR__ . '/Abstract.php';

class Db_Pdo extends Db_Abstract {
    /* @var PDOStatement */
    private $curStmt;
    /** @var PDO */
    public $db;

    public static function getNextAssoc($resultSet) {
        return $resultSet->fetch();
    }

    public static function unixTimestamp($field) {
        return 'UNIX_TIMESTAMP(' . $field . ')';
    }

    public static function dateSub($days) {
        return 'DATE_SUB(CURDATE(), INTERVAL ' . $days . ' DAY)';
    }

    public function connect() {
        $connectionString = $this->config['dbtype'] . ':host=' . $this->config['dbhost'] . ';dbname=' . $this->config['dbname'].";charset=utf8mb4";
        $this->db = new PDO($connectionString, $this->config['dbuser'], $this->config['dbpass']);
        if ($this->db !== FALSE) {
            $this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        }
        else {
            xhprof_error("Could not connect to db");
            throw new RuntimeException("Unable to connect to database");
        }
    }

    public function query($sql) {
        $this->curStmt = $this->db->query($sql);
        return $this->curStmt;
    }

    public function escape($str) {
        $str = $this->db->quote($str);
        //Dirty trick, PDO::quote add quote around values (you're beautiful => 'you\'re beautiful')
        // which are already added in xhprof_runs.php
        $str = substr($str, 0, -1);
        return substr($str, 1);
    }

    public function affectedRows() {
        if ($this->curStmt === false) {
            return 0;
        }
        return $this->curStmt->rowCount();
    }
}
