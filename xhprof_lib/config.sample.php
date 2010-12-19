<?php
$_xhprof = array();

// Change these:
$_xhprof['dbhost'] = 'localhost';
$_xhprof['dbuser'] = 'root';
$_xhprof['dbpass'] = 'password';
$_xhprof['dbname'] = 'xhprof';
$_xhprof['servername'] = 'myserver';
$_xhprof['namespace'] = 'myapp';
$_xhprof['url'] = 'http://url/to/xhprof/xhprof_html';

//Uncomment one of these, platform dependent. You may need to tune for your specific environment, but they're worth a try

//These are good for Windows
/*
$_xhprof['dot_binary']  = 'C:\\Programme\\Graphviz\\bin\\dot.exe';
$_xhprof['dot_tempdir'] = 'C:\\WINDOWS\\Temp';
$_xhprof['dot_errfile'] = 'C:\\WINDOWS\\Temp\\xh_dot.err';
*/

//These are good for linux and its derivatives.
/*
$_xhprof['dot_binary']  = '/usr/bin/dot';
$_xhprof['dot_tempdir'] = '/tmp';
$_xhprof['dot_errfile'] = '/tmp/xh_dot.err';
*/

$exceptionURLs = array();

$exceptionPostURLs = array();
$exceptionPostURLs[] = "login";


$_xhprof['display'] = false;
$_xhprof['doprofile'] = false;

$controlIPs = array();
$controlIPs[] = "127.0.0.1";   //Localhost, you'll want to add your own ip here

$otherURLS = array();

$weight = 100;


  /**
  * The goal of this function is to accept the URL for a resource, and return a "simplified" version
  * thereof. Similar URLs should become identical. Consider:
  * http://example.org/stories.php?id=2323
  * http://example.org/stories.php?id=2324
  * Under most setups these two URLs, while unique, will have an identical execution path, thus it's
  * worthwhile to consider them as identical. The script will store both the original URL and the
  * Simplified URL for display and comparison purposes. A good simplified URL would be:
  * http://example.org/stories.php?id=
  * 
  * @param string $url The URL to be simplified
  * @return string The simplified URL 
  */
  function _urlSimilartor($url)
  {
      //This is an example 
      $url = preg_replace("!\d{4}!", "", $url);
      
      $url = preg_replace("![?&]_profile=\d!", "", $url);
      return $url;
  }
  
  function _aggregateCalls($calls, $rules = null)
  {
    $rules = array(
        'Loading' => 'load::',
        'mysql' => 'mysql_'
        );
    $addIns = array();
    foreach($calls as $index => $call)
    {
        foreach($rules as $rule => $search)
        {
            if (strpos($call['fn'], $search) !== false)
            {
                if (isset($addIns[$search]))
                {
                    unset($call['fn']);
                    foreach($call as $k => $v)
                    {
                        $addIns[$search][$k] += $v;
                    }
                }else
                {
                    $call['fn'] = $rule;
                    $addIns[$search] = $call;
                }
                unset($calls[$index]);  //Remove it from the listing
                break;  //We don't need to run any more rules on this
            }else
            {
                //echo "nomatch for $search in {$call['fn']}<br />\n";
            }
        }
    }
    return array_merge($addIns, $calls);
  }