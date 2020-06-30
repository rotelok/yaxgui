<?php

namespace Rotelok\xhprof\Database {

    use mysqli;
    use mysqli_result;

    class DriverMysqli extends Abstraction
    {
        /* @var mysqli */
        protected $db;

        /**
         * @param mysqli_result $resultSet
         * @return array|null
         */
        public function getNextAssoc($resultSet)
        {
            return $resultSet->fetch_assoc();
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
            $this->db = new mysqli(
                $this->config['dbhost'],
                $this->config['dbuser'],
                $this->config['dbpass'],
                $this->config['dbname']
            );
            if ($this->db->connect_error) {
                xhprof_error("Could not connect to db");
                throw new RuntimeException("Unable to connect to database");
            }
            $this->db->query("SET NAMES utf8mb4");
        }

        /**
         * @param string $sql
         * @return bool|mysqli_result
         */
        public function query($sql)
        {
            return $this->db->query($this->db, $sql);
        }

        /**
         * @param string $str
         * @return string
         */
        public function escape($str)
        {
            return $this->db->real_escape_string($str);
        }

        /**
         * @return int
         */
        public function affectedRows()
        {
            return $this->db->affected_rows;
        }
    }
}
