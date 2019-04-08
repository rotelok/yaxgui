<?php
require_once __DIR__ . '/Abstract.php';

class Db_Mysqli extends Db_Abstract {
    /* @var mysqli */
    protected $db;

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
        $this->db = new mysqli($this->config['dbhost'], $this->config['dbuser'], $this->config['dbpass'], $this->config['dbname']);
        if ($this->db->connect_error) {
            xhprof_error("Could not connect to db");
            throw new RuntimeException("Unable to connect to database");
        } else {
            $this->db->query("SET NAMES utf8mb4");
        }
    }

    public function query($sql) {
        return $this->db->query($this->db, $sql);
    }

    public function escape($str) {
        return $this->db->real_escape_string($str);
    }

    public function affectedRows() {
        return $this->db->affected_rows;
    }
}
