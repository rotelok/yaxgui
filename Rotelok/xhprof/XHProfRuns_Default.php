<?php

namespace Rotelok\xhprof;

/**
 * XHProfRuns_Default is the default implementation of the
 * iXHProfRuns interface for saving/fetching XHProf runs.
 *
 * This modified version of the file uses a MySQL backend to store
 * the data, it also stores additional information outside the run
 * itself (beyond simply the run id) to make comparisons and run
 * location easier
 *
 * @author Kannan
 * @author Paul Reinheimer (http://blog.preinheimer.com)
 */
class XHProfRuns_Default implements iXHProfRuns
{

    public $prefix = 't11_';
    public $run_details;
    /**
     *
     * @var Db_Abstract
     */
    protected $db;

    public function __construct()
    {
        $this->db();
    }

    protected function db()
    {
        global $_xhprof;
        $class = self::getDbClass();
        $this->db = new $class($_xhprof);
        $this->db->connect();
    }

    public static function getDbClass()
    {
        global $_xhprof;

        //return 'Db_' . $_xhprof['dbadapter'];
        return "Rotelok\xhprof\Database\DriverPdo";
    }

    public static function getNextAssoc($resultSet)
    {
        $class = self::getDbClass();
        return $class::getNextAssoc($resultSet);
    }

    /**
     * Obtains the pages that have been the hardest hit over the past N days, utalizing the getRuns() method.
     *
     * @param array $criteria An associative array containing, at minimum, type, days, and limit
     *
     * @return resource The result set reprsenting the results of the query
     */
    public function getHardHit($criteria)
    {
        //call thing to get runs
        $criteria['select'] = "distinct(`{$criteria['type']}`), count(`{$criteria['type']}`) AS `count` , sum(`wt`) as total_wall, avg(`wt`) as avg_wall";
        unset($criteria['type']);
        $criteria['where'] = $this->db->dateSub($criteria['days']) . " <= `timestamp`";
        unset($criteria['days']);
        $criteria['group by'] = "url";
        $criteria['order by'] = "count";
        return $this->getRuns($criteria);
    }

    /**
     * This function gets runs based on passed parameters, column data as key, value as the value. Values
     * are escaped automatically. You may also pass limit, order by, group by, or "where" to add those values,
     * all of which are used as is, no escaping.
     *
     * @param array $stats Criteria by which to select columns
     *
     * @return resource
     */
    public function getRuns($stats)
    {
        if (isset($stats['select'])) {
            $query = "SELECT {$stats['select']} FROM `details` ";
        } else {
            $query = "SELECT * FROM `details` ";
        }

        $skippers = ["limit", "order by", "group by", "where", "select"];
        $hasWhere = false;

        foreach ($stats as $column => $value) {
            if (in_array($column, $skippers)) {
                continue;
            }
            if ($hasWhere === false) {
                $query .= " WHERE ";
                $hasWhere = true;
            } elseif ($hasWhere === true) {
                $query .= "AND ";
            }
            if ($value == '') {
                $query .= $column;
            }

            $value = $this->db->escape($value);
            $query .= " `$column` = '$value' ";
        }

        if (isset($stats['where'])) {
            if ($hasWhere === false) {
                $query .= " WHERE ";
                $hasWhere = true;
            } else {
                $query .= " AND ";
            }
            $query .= $stats['where'];
        }

        if (isset($stats['group by'])) {
            $query .= " GROUP BY `{$stats['group by']}` ";
        }

        if (isset($stats['order by'])) {
            $query .= " ORDER BY `{$stats['order by']}` DESC";
        }

        if (isset($stats['limit'])) {
            $query .= " LIMIT {$stats['limit']} ";
        }

        return $this->db->query($query);
    }

    public function getDistinct($data)
    {
        $sql['column'] = $this->db->escape($data['column']);
        $query = "SELECT DISTINCT(`{$sql['column']}`) FROM `details`";
        return $this->db->query($query);
    }

    /**
     * Retreives a run from the database,
     *
     * @param string $run_id unique identifier for the run being requested
     * @param mixed $type
     * @param mixed $run_desc
     *
     * @return mixed
     */
    public function get_run($run_id, $type, &$run_desc)
    {
        $run_id = $this->db->escape($run_id);
        $query = "SELECT * FROM `details` WHERE `id` = '$run_id'";
        $resultSet = $this->db->query($query);
        $data = $this->db->getNextAssoc($resultSet);

        // can't find specified data
        if (empty($data)) {
            return [null, null];
        }
        $contents = json_decode(gzuncompress($data['perfdata']), true);

        //This data isnt' needed for display purposes, there's no point in keeping it in this array
        unset($data['perfdata']);

        // The same function is called twice when diff'ing runs. In this case we'll populate the global scope with an array
        if (is_null($this->run_details)) {
            $this->run_details = $data;
        } else {
            $this->run_details[0] = $this->run_details;
            $this->run_details[1] = $data;
        }

        $run_desc = "XHProf Run (Namespace=$type)";
        $this->getRunComparativeData($data['url'], $data['c_url']);

        return [$contents, $data];
    }

    /**
     * Get comparative information for a given URL and c_url, this information will be used to display stats like how many calls a URL has,
     * average, min, max execution time, etc. This information is pushed into the global namespace, which is horribly hacky.
     *
     * @param string $url
     * @param string $c_url
     *
     * @return array
     */
    public function getRunComparativeData($url, $c_url)
    {
        $url = $this->db->escape($url);
        $c_url = $this->db->escape($c_url);
        //Runs same URL
        //  count, avg/min/max for wt, cpu, pmu
        $query = "SELECT count(`id`), avg(`wt`), min(`wt`), max(`wt`),  avg(`cpu`), min(`cpu`), max(`cpu`), avg(`pmu`), min(`pmu`), max(`pmu`) FROM `details` WHERE `url` = '$url'";
        $rs = $this->db->query($query);
        $row = $this->db->getNextAssoc($rs);
        $row['url'] = $url;

        $row['95(`wt`)'] = $this->calculatePercentile(
            ['count' => $row['count(`id`)'], 'column' => 'wt', 'type' => 'url', 'url' => $url]
        );
        $row['95(`cpu`)'] = $this->calculatePercentile(
            ['count' => $row['count(`id`)'], 'column' => 'cpu', 'type' => 'url', 'url' => $url]
        );
        $row['95(`pmu`)'] = $this->calculatePercentile(
            ['count' => $row['count(`id`)'], 'column' => 'pmu', 'type' => 'url', 'url' => $url]
        );

        global $comparative;
        $comparative['url'] = $row;
        unset($row);

        //Runs same c_url
        //  count, avg/min/max for wt, cpu, pmu
        $query = "SELECT count(`id`), avg(`wt`), min(`wt`), max(`wt`),  avg(`cpu`), min(`cpu`), max(`cpu`), avg(`pmu`), min(`pmu`), max(`pmu`) FROM `details` WHERE `c_url` = '$c_url'";
        $rs = $this->db->query($query);
        $row = $this->db->getNextAssoc($rs);
        $row['url'] = $c_url;
        $row['95(`wt`)'] = $this->calculatePercentile(
            ['count' => $row['count(`id`)'], 'column' => 'wt', 'type' => 'c_url', 'url' => $c_url]
        );
        $row['95(`cpu`)'] = $this->calculatePercentile(
            ['count' => $row['count(`id`)'], 'column' => 'cpu', 'type' => 'c_url', 'url' => $c_url]
        );
        $row['95(`pmu`)'] = $this->calculatePercentile(
            ['count' => $row['count(`id`)'], 'column' => 'pmu', 'type' => 'c_url', 'url' => $c_url]
        );

        $comparative['c_url'] = $row;
        unset($row);
        return $comparative;
    }

    protected function calculatePercentile($details)
    {
        $limit = (int)($details['count'] / 20);
        $query = "SELECT `{$details['column']}` as `value` FROM `details` WHERE `{$details['type']}` = '{$details['url']}' ORDER BY `{$details['column']}` DESC LIMIT $limit, 1";
        $rs = $this->db->query($query);
        $row = $this->db->getNextAssoc($rs);
        return $row['value'];
    }

    /**
     * Get stats (pmu, ct, wt) on a url or c_url
     *
     * @param array $data An associative array containing the limit you'd like to set for the queyr, as well as either c_url or url for the desired element.
     *
     * @return resource result set from the database query
     */
    public function getUrlStats($data)
    {
        $data['select'] = '`id`, ' . $this->db->unixTimestamp('timestamp') . ' as `timestamp`, `pmu`, `wt`, `cpu`';
        return $this->getRuns($data);
    }

    /**
     * Save the run in the database.
     *
     * @param string $xhprof_data
     * @param mixed $type
     * @param string $run_id
     * @param mixed $xhprof_details
     *
     * @return string
     */
    public function save_run($xhprof_data, $type, $run_id = null, $xhprof_details = null)
    {
        global $_xhprof;

        $sql = [];
        if ($run_id === null) {
            $run_id = $this->gen_run_id($type);
        }

        $sql['get'] = $this->db->escape(json_encode($_GET));
        $sql['cookie'] = $this->db->escape(json_encode($_COOKIE));

        //This code has not been tested
        if (isset($_xhprof['savepost']) && $_xhprof['savepost']) {
            $sql['post'] = $this->db->escape(json_encode($_POST));
        } else {
            $sql['post'] = $this->db->escape(json_encode(["Skipped" => "Post data omitted by rule"]));
        }
        $sql['pmu'] = $xhprof_data['main()']['pmu'] ?? 0;
        $sql['wt'] = $xhprof_data['main()']['wt'] ?? 0;
        $sql['cpu'] = $xhprof_data['main()']['cpu'] ?? 0;
        $sql['data'] = $this->db->escape(gzcompress(json_encode($xhprof_data), 2));

        $sname = $_SERVER['SERVER_NAME'] ?? '';
        $url = $_SERVER['PHP_SELF'];
        if (isset($_SERVER['REQUEST_URI'])) {
            $scheme = "";
            if (isset($_SERVER['REQUEST_SCHEME'])) {
                $scheme = $_SERVER['REQUEST_SCHEME'] . '://';
            }
            $url = $scheme . $sname . $_SERVER['REQUEST_URI'];
        }

        $sql['url'] = $this->db->escape($url);
        $sql['c_url'] = $this->db->escape(_urlSimilartor($url));
        $sql['servername'] = $this->db->escape($sname);
        $sql['type'] = (int)($xhprof_details['type'] ?? 0);
        $sql['timestamp'] = $this->db->escape($_SERVER['REQUEST_TIME']);
        $sql['server_id'] = $this->db->escape($_xhprof['servername']);
        $sql['aggregateCalls_include'] = getenv('xhprof_aggregateCalls_include') ?: '';

        $query = "INSERT INTO `details` (`id`, `url`, `c_url`, `timestamp`, `server name`, `perfdata`, `type`, `cookie`, `post`, `get`, `pmu`, `wt`, `cpu`, `server_id`, `aggregateCalls_include`) VALUES('$run_id', '{$sql['url']}', '{$sql['c_url']}', FROM_UNIXTIME('{$sql['timestamp']}'), '{$sql['servername']}', '{$sql['data']}', '{$sql['type']}', '{$sql['cookie']}', '{$sql['post']}', '{$sql['get']}', '{$sql['pmu']}', '{$sql['wt']}', '{$sql['cpu']}', '{$sql['server_id']}', '{$sql['aggregateCalls_include']}')";

        $this->db->query($query);
        if ($this->db->affectedRows() == 1) {
            return $run_id;
        }

        global $_xhprof;
        if ($_xhprof['display'] === true) {
            echo "Failed to insert: $query <br>\n";
        }
        return -1;
    }

    private function gen_run_id($namespace)
    {
        return uniqid($namespace . "-", true);
    }
}
