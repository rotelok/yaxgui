YaxGUI-NG (Yet another xhprof GUI)
=========

This is a graphical front end designed to store and present the profiling information provided by the tideways Extension.
YaxGUI-NG only supports tideways because all other extensions seems to be abandoned.

I forked the original sfeni/yaxgui that was a fork of preinheimer/xhprof itself a fork of phacility/xhprof
 my main objectives with this fork are:
* Keep a cool tool alive
* Modernization of the codebase 
* Refactor it to a cleaner code style
* Automate the creation of the Database Schema
* Support for an easy to follow instalation guide both of this repository as the tideways_xhprof extension
* Make It robust enought to use in a production enviroment
* A Bonus low priority objective: Revamp the UI (I'm not a front-end guy, and the current UI is good enought for me)



I am currently working on
-----------------

* Cleaning up the code
* Renaming of methods/functions

Done
----

* update to the new tideways_xhprof extension ( tideways 5.0), but keeping compatibility with the older 
tideways 4.0 extension

Project Includes
----------------

* It includes a header.php document you can use with PHP's auto\_prepend\_file directive. 
It sets up profiling by initilizing a few variables, and settting register_shutdown_function with the footer. 
Once started profiles are created when requested with (?\_profile=1), or randomly by setting the $weight variable in the config. 
Profiled pages display a link to their profile results at the bottom of the page, by default this is disabled but can
be enabled for all urls or with a specific blacklist based for specific documents. e.g. pages generating XML, images, etc.

* For tips on including header.php on a nginx + php-fpm install, take a look at: http://www.justincarmony.com/blog/2012/04/23/php-fpm-nginx-php_value-and-multiple-values/

* The GUI is a bit prettier (Thanks to Graham Slater)

* It uses a MySQL backend, the database schema is stored in xhprof\_runs.php 

* There's a frontend to view different runs, compare runs to the same url, etc.

Key Features
-------------

* Listing 25, 50 most recent runs
* Display most expensive (cpu), the longest running, or highest memory usage runs for the day

* It introduces the concept of "Similar" URLs. Consider:
  * http://news.example.com/?story=23
  * http://news.example.com/?story=25
  While the URLs are different, the PHP code execution path is likely identical,
  by tweaking the method in xhprof\_runs.php you can help the frontend be aware
  that these urls are identical.

* Highcharts is used to graph stats over requests for an 
  easy heads up display.

Requirements
------------

Besides, a simple PHP running on your favourite web server you will also need following packages:

* [tideways_xhprof](https://github.com/tideways/php-xhprof-extension)
* php-mysqli or php-pdo
* graphviz (uses `dot` to generate callgraphs)

Installation
-------------

* Install your favourite mix of PHP and web server
* Install MySQL/Mariadb server
* Clone the project to some folder
* Map the sub folder `xhprof_html` to be accessible over HTTP
* Move `xhprof_lib/config.sample.php` to `xhprof_lib/config.php`
* Edit `xhprof_lib/config.php`
 * Update the SQL server configuration
 * Update the URL of the service (should point to `xhprof_html` over HTTP)
 * Update the `dot_binary` configuration - otherwise no call graphs!
 * Update the `controlIPs` variable to enable access.
  * For a development machine you can set this to `false` to disable IP checks.
* Import the DB schema (it is just 1 table)
 * See the SQL at [xhprof_docs/Example_Table.sql](https://raw.githubusercontent.com/rotelok/yaxgui/yaxgui-ng/xhprof_docs/Example_Table.sql)
* Add a PHP configuration to enable the profiling
 * If using Apache you can edit your virtual host configuration
 * Add `php_admin_value auto_prepend_file "/path/to/xhprof/external/header.php"`
* Visit http://your-server/xhprof/xhprof_html/ and be amazed!
 * To get profiler information showing up there visit your page with a `GET` variable `_profile=1`.
 * For example `http://localhost/?_profile=1`
 * Or if you want to store information passively set the weight in config.php to a low number ie:10 will store information for 90% of the accesses
