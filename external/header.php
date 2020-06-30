<?php
require_once __DIR__ . "/../Rotelok/xhprof/iXHProfRuns.php";
require_once __DIR__ . "/../Rotelok/xhprof/XHProfRuns_Default.php";
if (PHP_SAPI === 'cli') {
    $_SERVER['REMOTE_ADDR'] = null;
    $_SERVER['HTTP_HOST'] = null;
    $_SERVER['REQUEST_URI'] = $_SERVER['SCRIPT_NAME'];
    $_GET = $argv;
}


require_once __DIR__ . '/../xhprof_lib/config.php';


// Only users from authorized IP addresses may control Profiling
if ($controlIPs === false || in_array($_SERVER['REMOTE_ADDR'], $controlIPs) || PHP_SAPI == 'cli') {
    if (isset($_GET['_profile'])) {
        //Give them a cookie to hold status, and redirect back to the same page
        setcookie('_profile', $_GET['_profile']);
        $newURI = str_replace(['_profile=1', '_profile=0'], '', $_SERVER['REQUEST_URI']);
        header("Location: $newURI");
        exit;
    }

    if (isset($_COOKIE['_profile']) && $_COOKIE['_profile'] || PHP_SAPI === 'cli' && ((isset($_SERVER['XHPROF_PROFILE']) && $_SERVER['XHPROF_PROFILE']) || (isset($_ENV['XHPROF_PROFILE']) && $_ENV['XHPROF_PROFILE']))) {
        $_xhprof['display'] = true;
        $_xhprof['doprofile'] = true;
        $_xhprof['type'] = 1;
    }
}


//Certain URLs should never have a link displayed. Think images, xml, etc.
foreach ($exceptionURLs as $url) {
    if (stripos($_SERVER['REQUEST_URI'], $url) !== false) {
        $_xhprof['display'] = false;
        header('X-XHProf-No-Display: Trueness');
        break;
    }
}
unset($exceptionURLs);

//Certain urls should have their POST data omitted. Think login forms, other privlidged info
$_xhprof['savepost'] = true;
foreach ($exceptionPostURLs as $url) {
    if (stripos($_SERVER['REQUEST_URI'], $url) !== false) {
        $_xhprof['savepost'] = false;
        break;
    }
}
unset($exceptionPostURLs);

//Determine wether or not to profile this URL randomly
//Profile weighting, one in one hundred requests will be profiled without being specifically requested
if (($_xhprof['doprofile'] === false) && $weight > 0 && mt_rand(1, $weight) == 1) {
    $_xhprof['doprofile'] = true;
    $_xhprof['type'] = 0;
}
unset($weight);

// Certain URLS should never be profiled.
foreach ($ignoreURLs as $url) {
    if (stripos($_SERVER['REQUEST_URI'], $url) !== false) {
        $_xhprof['doprofile'] = false;
        break;
    }
}
unset($ignoreURLs);

// Certain domains should never be profiled.
foreach ($ignoreDomains as $domain) {
    if (stripos($_SERVER['HTTP_HOST'], $domain) !== false) {
        $_xhprof['doprofile'] = false;
        break;
    }
}
unset($ignoreDomains);

//Display warning if extension not available
if ((extension_loaded("tideways") || extension_loaded("tideways_xhprof")) && $_xhprof['doprofile'] === true) {
    include_once __DIR__ . '/../xhprof_lib/utils/xhprof_lib.php';
    include_once __DIR__ . '/../xhprof_lib/utils/xhprof_runs.php';
    if (extension_loaded("tideways")) {
        if (isset($ignoredFunctions) && is_array($ignoredFunctions) && !empty($ignoredFunctions)) {
            tideways_enable(
                TIDEWAYS_FLAGS_CPU | TIDEWAYS_FLAGS_MEMORY | TIDEWAYS_FLAGS_NO_SPANS,
                ['ignored_functions' => $ignoredFunctions]
            );
        } else {
            tideways_enable(TIDEWAYS_FLAGS_CPU | TIDEWAYS_FLAGS_MEMORY);
        }
    } elseif (extension_loaded("tideways_xhprof")) {
        if (isset($ignoredFunctions) && is_array($ignoredFunctions) && !empty($ignoredFunctions)) {
            tideways_xhprof_enable(
                TIDEWAYS_XHPROF_FLAGS_CPU | TIDEWAYS_XHPROF_FLAGS_MEMORY | TIDEWAYS_XHPROF_FLAGS_NO_SPANS,
                ['ignored_functions' => $ignoredFunctions]
            );
        } else {
            tideways_xhprof_enable(TIDEWAYS_XHPROF_FLAGS_CPU | TIDEWAYS_XHPROF_FLAGS_MEMORY);
        }
    }
} elseif (!(extension_loaded("tideways") || extension_loaded("tideways_xhprof")) && $_xhprof['display'] === true) {
    $message = 'Warning! Unable to profile run, tideways or tideways_xhprof extension not loaded';
    trigger_error($message, E_USER_WARNING);
}

register_shutdown_function(
    static function () {
        global $_xhprof;
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower(
            $_SERVER['HTTP_X_REQUESTED_WITH']
        ) === 'xmlhttprequest') {
            $isAjax = true;
        }

        if ((extension_loaded("tideways") || extension_loaded("tideways_xhprof")) && $_xhprof['doprofile'] === true) {
            $profiler_namespace = $_xhprof['namespace'];  // namespace for your application
            if (extension_loaded("tideways")) {
                $xhprof_data = tideways_disable();
            } elseif (extension_loaded("tideways_xhprof")) {
                $xhprof_data = tideways_xhprof_disable();
            }
            $xhprof_runs = new Rotelok\xhprof\XHProfRuns_Default();
            $run_id = $xhprof_runs->save_run($xhprof_data, $profiler_namespace, null, $_xhprof);
            if ($_xhprof['display'] === true && PHP_SAPI !== 'cli' && !isset($isAjax)) {
                // url to the XHProf UI libraries (change the host name and path)
                $profiler_url = sprintf($_xhprof['url'] . '/index.php?run=%s&source=%s', $run_id, $profiler_namespace);
                echo '<a href="' . $profiler_url . '" target="_blank">Profiler output</a>';
            }
        }
    }
);
