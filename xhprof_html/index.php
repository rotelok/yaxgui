<?php
require_once __DIR__ . "/../Rotelok/fakeAutoloader.php";




if ($controlIPs !== false && !in_array($_SERVER['REMOTE_ADDR'], $controlIPs)) {
    die("You do not have permission to view this page.");
}

unset($controlIPs);

// param name, its type, and default value
$params = [
    'run' => [XHPROF_STRING_PARAM, ''],
    'wts' => [XHPROF_STRING_PARAM, ''],
    'symbol' => [XHPROF_STRING_PARAM, ''],
    'sort' => [XHPROF_STRING_PARAM, 'wt'], // wall time
    'run1' => [XHPROF_STRING_PARAM, ''],
    'run2' => [XHPROF_STRING_PARAM, ''],
    'source' => [XHPROF_STRING_PARAM, 'xhprof'],
    'all' => [XHPROF_UINT_PARAM, 0]
];

// pull values of these params, and create named globals for each param
$XHProfHTML->xhprof_param_init($params);

/* reset params to be a array of variable names to values
   by the end of this page, param should only contain values that need
   to be preserved for the next page. unset all unwanted keys in $params.
 */
foreach ($params as $k => $v) {
    $params[$k] = $$k;

    // unset key from params that are using default values. So URLs aren't
    // ridiculously long.
    if ($params[$k] == $v[1]) {
        unset($params[$k]);
    }
}




$xhprof_runs_impl = new Rotelok\xhprof\XHProfRuns_Default();

$domainFilter = getFilter('domain_filter');
$serverFilter = getFilter('server_filter');

$domainsRS = $xhprof_runs_impl->getDistinct(['column' => 'server name']);
$domainFilterOptions = ["None"];
while ($row = Rotelok\xhprof\XHProfRuns_Default::getNextAssoc($domainsRS)) {
    $domainFilterOptions[] = $row['server name'];
}

$serverRS = $xhprof_runs_impl->getDistinct(['column' => 'server_id']);
$serverFilterOptions = ["None"];
while ($row = Rotelok\xhprof\XHProfRuns_Default::getNextAssoc($serverRS)) {
    $serverFilterOptions[] = $row['server_id'];
}

$criteria = [];
if (!is_null($domainFilter)) {
    $criteria['server name'] = $domainFilter;
}
if (!is_null($serverFilter)) {
    $criteria['server_id'] = $serverFilter;
}
$_xh_header = "";
if (isset($_GET['run1']) || isset($_GET['run'])) {
    include __DIR__ . "/../Rotelok/xhprof/Templates/header.phtml";
    $XHProfHTML->displayXHProfReport(
        $params,
        $source,
        $run,
        $wts,
        $symbol,
        $sort,
        $run1,
        $run2
    );
} elseif (isset($_GET['geturl'])) {
    $last = $_GET['last'] ?? 100;
    $last = (int)$last;
    $criteria['url'] = $_GET['geturl'];
    $criteria['limit'] = $last;
    $criteria['order by'] = 'timestamp';
    $rs = $xhprof_runs_impl->getUrlStats($criteria);
    [$header, $body] = showChart($rs, true);
    $_xh_header .= $header;

    include __DIR__ . "/../Rotelok/xhprof/Templates/header.phtml";
    $rs = $xhprof_runs_impl->getRuns($criteria);
    include __DIR__ . "/../Rotelok/xhprof/Templates/emptyBody.phtml";

    $url = htmlentities($_GET['geturl'], ENT_QUOTES, "UTF-8");
    displayRuns($rs, "Runs with URL: $url");
} elseif (isset($_GET['getcurl'])) {
    $last = $_GET['last'] ?? 100;
    $last = (int)$last;
    $criteria['c_url'] = $_GET['getcurl'];
    $criteria['limit'] = $last;
    $criteria['order by'] = 'timestamp';

    $rs = $xhprof_runs_impl->getUrlStats($criteria);
    [$header, $body] = showChart($rs, true);
    $_xh_header .= $header;
    include __DIR__ . "/../Rotelok/xhprof/Templates/header.phtml";

    $url = htmlentities($_GET['getcurl'], ENT_QUOTES, "UTF-8");
    $rs = $xhprof_runs_impl->getRuns($criteria);
    include __DIR__ . "/../Rotelok/xhprof/Templates/emptyBody.phtml";
    displayRuns($rs, "Runs with Simplified URL: $url");
} elseif (isset($_GET['getruns'])) {
    include __DIR__ . "/../Rotelok/xhprof/Templates/header.phtml";
    $days = (int)$_GET['days'];

    switch ($_GET['getruns']) {
        case "cpu":
            $load = "cpu";
            break;
        case "wt":
            $load = "wt";
            break;
        case "pmu":
            $load = "pmu";
            break;
    }

    $criteria['order by'] = $load;
    $criteria['limit'] = "500";
    $criteria['where'] = "DATE_SUB(CURDATE(), INTERVAL $days DAY) <= `timestamp`";
    $rs = $xhprof_runs_impl->getRuns($criteria);
    displayRuns($rs, "Worst runs by $load");
} elseif (isset($_GET['hit'])) {
    include __DIR__ . "/../Rotelok/xhprof/Templates/header.phtml";
    $last = $_GET['hit'] ?? 25;
    $last = (int)$last;
    $days = $_GET['days'] ?? 1;
    $days = (int)$days;
    if (isset($_GET['type']) && ($_GET['type'] === 'url' or $_GET['type'] = 'curl')) {
        $type = $_GET['type'];
    } else {
        $type = 'url';
    }

    $criteria['limit'] = $last;
    $criteria['days'] = $days;
    $criteria['type'] = $type;
    $resultSet = $xhprof_runs_impl->getHardHit($criteria);

    echo "<div class=\"runTitle\">Hardest Hit</div>\n";
    echo "<table id=\"box-table-a\" class=\"tablesorter\" summary=\"Stats\"><thead><tr><th>URL</th><th>Hits</th><th class=\"{sorter: 'numeric'}\">Total Wall Time</th><th>Avg Wall Time</th></tr></thead>";
    echo "<tbody>\n";
    while ($row = Rotelok\xhprof\XHProfRuns_Default::getNextAssoc($resultSet)) {
        $url = urlencode($row['url']);
        $html['url'] = htmlentities($row['url'], ENT_QUOTES, 'UTF-8');
        echo "\t<tr><td><a href=\"?geturl={$url}\">{$html['url']}</a></td><td>{$row['count']}</td><td>" . number_format(
            $row['total_wall']
        ) . " ms</td><td>" . number_format($row['avg_wall']) . " ms</td></tr>\n";
    }
    echo "</tbody>\n";
    echo "</table>\n";
    echo <<<CODESE
    <script type="text/javascript">
    $(document).ready(function() { 
      $.tablesorter.addParser({ 
	  id: 'pretty', 
	  is: function(s) { 
	      return false; 
	  }, 
	  format: function(s) {
	      s = s.replace(/ ms/g,"");
	      return s.replace(/,/g,"");
	  }, 
	  // set type, either numeric or text 
	  type: 'numeric' 
      });
      $(function() { 
	  $("table").tablesorter({ 
	      headers: { 
		  2: { 
		      sorter:'pretty' 
		  },
		  3: {
		      sorter:'pretty'
		  }
	      }
	  }); 
      });
    }); 
    </script>
CODESE;
} else {
    include __DIR__ . "/../Rotelok/xhprof/Templates/header.phtml";
    $last = $_GET['last'] ?? 25;
    $last = (int)$last;
    $criteria['order by'] = "timestamp";
    $criteria['limit'] = $last;
    $rs = $xhprof_runs_impl->getRuns($criteria);
    displayRuns($rs, "Last $last Runs");
}

require __DIR__ . "/../Rotelok/xhprof/Templates/footer.phtml";
