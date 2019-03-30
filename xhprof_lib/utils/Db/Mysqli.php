<?php
require_once __DIR__ . '/Abstract.php';

class Db_Mysqli extends Db_Abstract {

    public static function getNextAssoc($resultSet) {
        return mysqli_fetch_assoc($resultSet);
    }

    public static function unixTimestamp($field) {
        return 'UNIX_TIMESTAMP(' . $field . ')';
    }

    public static function dateSub($days) {
        return 'DATE_SUB(CURDATE(), INTERVAL ' . $days . ' DAY)';
    }

    public function connect() {
        $this->linkID = mysqli_connect($this->config['dbhost'], $this->config['dbuser'], $this->config['dbpass'], $this->config['dbname']);
        if ($this->linkID === FALSE) {
            xhprof_error("Could not connect to db");
            throw new Exception("Unable to connect to database");
            return false;
        }
        $this->query("SET NAMES utf8mb4");
    }

    public function query($sql) {
        return mysqli_query($this->linkID, $sql);
    }

    public function escape($str) {
        return mysqli_real_escape_string($this->linkID, $str);
    }

    public function affectedRows() {
        return mysqli_affected_rows($this->linkID);
    }
}
