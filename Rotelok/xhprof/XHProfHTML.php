<?php

namespace Rotelok\xhprof {

//  Copyright (c) 2009 Facebook
//
//  Licensed under the Apache License, Version 2.0 (the "License");
//  you may not use this file except in compliance with the License.
//  You may obtain a copy of the License at
//
//      http://www.apache.org/licenses/LICENSE-2.0
//
//  Unless required by applicable law or agreed to in writing, software
//  distributed under the License is distributed on an "AS IS" BASIS,
//  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
//  See the License for the specific language governing permissions and
//  limitations under the License.
//

//
// XHProf: A Hierarchical Profiler for PHP
//
// XHProf has two components:
//
//  * This module is the UI/reporting component, used
//    for viewing results of XHProf runs from a browser.
//
//  * Data collection component: This is implemented
//    as a PHP extension (XHProf).
//
// @author Kannan Muthukkaruppan
//

    class XHProfHTML extends XHProfLib
    {
        private $base_path;
        private $sort_col;
        private $diff_mode;
        private $display_calls;
        private $sortable_columns;
        private $descriptions;
        private $format_cbk;
        private $diff_descriptions;
        private $stats;
        private $pc_stats;
        private $totals;
        private $totals_1;
        private $totals_2;
        private $xhprof_runs_impl;
        private $metrics;
        private $vbar;
        private $vwbar;
        private $vwlbar;
        private $vgbar;
        private $vrbar;
        private $vbbar;

        public function __construct()
        {
            parent::__construct();
            /**
             * Our coding convention disallows relative paths in hrefs.
             * Get the base URL path from the SCRIPT_NAME.
             */
            $this->base_path = rtrim(dirname($_SERVER['SCRIPT_NAME']), "/");

            $this->sort_col = "wt"; // default column to sort on -- wall time

            // default is "single run" report
            $this->diff_mode = false;

            // call count data present?
            $this->display_calls = true;

            // The following column headers are sortable
            $this->sortable_columns = [
                "fn" => 1,
                "ct" => 1,
                "wt" => 1,
                "excl_wt" => 1,
                "ut" => 1,
                "excl_ut" => 1,
                "st" => 1,
                "excl_st" => 1,
                "mu" => 1,
                "excl_mu" => 1,
                "pmu" => 1,
                "excl_pmu" => 1,
                "cpu" => 1,
                "excl_cpu" => 1,
                "samples" => 1,
                "excl_samples" => 1
            ];

            // Textual descriptions for column headers in "single run" mode
            $this->descriptions = [
                "fn" => "Function Name",
                "ct" => "Calls",
                "Calls%" => "Calls%",

                "wt" => "Incl. Wall Time<br />(microsec)",
                "IWall%" => "IWall%",
                "excl_wt" => "Excl. Wall Time<br />(microsec)",
                "EWall%" => "EWall%",

                "ut" => "Incl. User<br />(microsecs)",
                "IUser%" => "IUser%",
                "excl_ut" => "Excl. User<br />(microsec)",
                "EUser%" => "EUser%",

                "st" => "Incl. Sys <br />(microsec)",
                "ISys%" => "ISys%",
                "excl_st" => "Excl. Sys <br />(microsec)",
                "ESys%" => "ESys%",

                "cpu" => "Incl. CPU<br />(microsecs)",
                "ICpu%" => "ICpu%",
                "excl_cpu" => "Excl. CPU<br />(microsec)",
                "ECpu%" => "ECPU%",

                "mu" => "Incl.<br />MemUse<br />(bytes)",
                "IMUse%" => "IMemUse%",
                "excl_mu" => "Excl.<br />MemUse<br />(bytes)",
                "EMUse%" => "EMemUse%",

                "pmu" => "Incl.<br /> PeakMemUse<br />(bytes)",
                "IPMUse%" => "IPeakMemUse%",
                "excl_pmu" => "Excl.<br />PeakMemUse<br />(bytes)",
                "EPMUse%" => "EPeakMemUse%",

                "samples" => "Incl. Samples",
                "ISamples%" => "ISamples%",
                "excl_samples" => "Excl. Samples",
                "ESamples%" => "ESamples%"
            ];

            // Formatting Callback Functions...
            $this->format_cbk = [
                "fn" => "",
                "ct" => "xhprof_count_format",
                "Calls%" => "xhprof_percent_format",

                "wt" => "number_format",
                "IWall%" => "xhprof_percent_format",
                "excl_wt" => "number_format",
                "EWall%" => "xhprof_percent_format",

                "ut" => "number_format",
                "IUser%" => "xhprof_percent_format",
                "excl_ut" => "number_format",
                "EUser%" => "xhprof_percent_format",

                "st" => "number_format",
                "ISys%" => "xhprof_percent_format",
                "excl_st" => "number_format",
                "ESys%" => "xhprof_percent_format",

                "cpu" => "number_format",
                "ICpu%" => "xhprof_percent_format",
                "excl_cpu" => "number_format",
                "ECpu%" => "xhprof_percent_format",

                "mu" => "number_format",
                "IMUse%" => "xhprof_percent_format",
                "excl_mu" => "number_format",
                "EMUse%" => "xhprof_percent_format",

                "pmu" => "number_format",
                "IPMUse%" => "xhprof_percent_format",
                "excl_pmu" => "number_format",
                "EPMUse%" => "xhprof_percent_format",

                "samples" => "number_format",
                "ISamples%" => "xhprof_percent_format",
                "excl_samples" => "number_format",
                "ESamples%" => "xhprof_percent_format"
            ];


            // Textual descriptions for column headers in "diff" mode
            $this->diff_descriptions = [
                "fn" => "Function Name",
                "ct" => "Calls Diff",
                "Calls%" => "Calls<br />Diff%",

                "wt" => "Incl. Wall<br />Diff<br />(microsec)",
                "IWall%" => "IWall<br /> Diff%",
                "excl_wt" => "Excl. Wall<br />Diff<br />(microsec)",
                "EWall%" => "EWall<br />Diff%",

                "ut" => "Incl. User Diff<br />(microsec)",
                "IUser%" => "IUser<br />Diff%",
                "excl_ut" => "Excl. User<br />Diff<br />(microsec)",
                "EUser%" => "EUser<br />Diff%",

                "cpu" => "Incl. CPU Diff<br />(microsec)",
                "ICpu%" => "ICpu<br />Diff%",
                "excl_cpu" => "Excl. CPU<br />Diff<br />(microsec)",
                "ECpu%" => "ECpu<br />Diff%",

                "st" => "Incl. Sys Diff<br />(microsec)",
                "ISys%" => "ISys<br />Diff%",
                "excl_st" => "Excl. Sys Diff<br />(microsec)",
                "ESys%" => "ESys<br />Diff%",

                "mu" => "Incl.<br />MemUse<br />Diff<br />(bytes)",
                "IMUse%" => "IMemUse<br />Diff%",
                "excl_mu" => "Excl.<br />MemUse<br />Diff<br />(bytes)",
                "EMUse%" => "EMemUse<br />Diff%",

                "pmu" => "Incl.<br /> PeakMemUse<br />Diff<br />(bytes)",
                "IPMUse%" => "IPeakMemUse<br />Diff%",
                "excl_pmu" => "Excl.<br />PeakMemUse<br />Diff<br />(bytes)",
                "EPMUse%" => "EPeakMemUse<br />Diff%",

                "samples" => "Incl. Samples Diff",
                "ISamples%" => "ISamples Diff%",
                "excl_samples" => "Excl. Samples Diff",
                "ESamples%" => "ESamples Diff%"
            ];

            // columns that'll be displayed in a top-level report
            $this->stats = [];

            // columns that'll be displayed in a function's parent/child report
            $this->pc_stats = [];

            // Various total counts
            $this->totals = [];
            $this->totals_1  = [];
            $this->totals_2 = [];

            $this->metrics = null;

            $this->vbar = ' class="vbar"';
            $this->vwbar = ' class="vwbar"';
            $this->vwlbar = ' class="vwlbar"';
            $this->vbbar = ' class="vbbar"';
            $this->vrbar = ' class="vrbar"';
            $this->vgbar = ' class="vgbar"';
            $this->xhprof_runs_impl = new XHProfRuns_Default();
        }


        /**
         * Generate references to required stylesheets & javascript.
         *
         * If the calling script (such as index.php) resides in
         * a different location that than 'xhprof_html' directory the
         * caller must provide the URL path to 'xhprof_html' directory
         * so that the correct location of the style sheets/javascript
         * can be specified in the generated HTML.
         *
         * @param null $ui_dir_url_path
         */
        function xhprof_include_js_css($ui_dir_url_path = null)
        {
            if (empty($ui_dir_url_path)) {
                $ui_dir_url_path = rtrim(dirname($_SERVER['SCRIPT_NAME']), "/");
            }

            // style sheets
            echo "<link href='$ui_dir_url_path/css/xhprof.css' rel='stylesheet' " .
                " type='text/css'></link>";
            echo "<link href='$ui_dir_url_path/jquery/jquery.tooltip.css' " .
                " rel='stylesheet' type='text/css'></link>";
            echo "<link href='$ui_dir_url_path/jquery/jquery.autocomplete.css' " .
                " rel='stylesheet' type='text/css'></link>";

            // javascript
            echo "<script src='$ui_dir_url_path/jquery/jquery-1.2.6.js'>" .
                "</script>";
            echo "<script src='$ui_dir_url_path/jquery/jquery.tooltip.js'>" .
                "</script>";
            echo "<script src='$ui_dir_url_path/jquery/jquery.autocomplete.js'>"
                . "</script>";
            echo "<script src='$ui_dir_url_path/js/xhprof_report.js'></script>";
        }


        /*
         * Formats call counts for XHProf reports.
         *
         * Description:
         * Call counts in single-run reports are integer values.
         * However, call counts for aggregated reports can be
         * fractional. This function will print integer values
         * without decimal point, but with commas etc.
         *
         *   4000 ==> 4,000
         *
         * It'll round fractional values to decimal precision of 3
         *   4000.1212 ==> 4,000.121
         *   4000.0001 ==> 4,000
         *
         */
        function xhprof_count_format($num)
        {
            $num = round($num, 3);
            if (round($num) == $num) {
                return number_format($num);
            }
            return number_format($num, 3);
        }

        function xhprof_percent_format($s, $precision = 1)
        {
            return sprintf('%.' . $precision . 'f%%', 100 * $s);
        }

        /**
         * Implodes the text for a bunch of actions (such as links, forms,
         * into a HTML list and returns the text.
         * @param $actions
         * @return string
         */
        function xhprof_render_actions($actions)
        {
            $out = [];
            $out[] = "<div>\n";
            if (count($actions)) {
                $out[] = "<ul class=\"xhprof_actions\">\n";
                foreach ($actions as $action) {
                    $out[] = "\t<li>" . $action . "</li>\n";
                }
                $out[] = "</ul>\n";
            }
            $out[] = "</div>\n";
            return implode('', $out);
        }


        /**
         * @param        $content
         * @param        $href
         * @param string $class
         * @param string $id
         * @param string $title
         * @param string $target
         * @param string $onclick
         * @param string $style
         * @param string $access
         * @param string $onmouseover
         * @param string $onmouseout
         * @param string $onmousedown
         * @return string
         */
        function xhprof_render_link(
            $content,
            $href,
            $class = '',
            $id = '',
            $title = '',
            $target = '',
            $onclick = '',
            $style = '',
            $access = '',
            $onmouseover = '',
            $onmouseout = '',
            $onmousedown = ''
        ) {
            if (!$content) {
                return '';
            }

            if ($href) {
                $link = '<a href="' . $href . '"';
            } else {
                $link = '<span';
            }

            if ($class) {
                $link .= ' class="' . $class . '"';
            }
            if ($id) {
                $link .= ' id="' . $id . '"';
            }
            if ($title) {
                $link .= ' title="' . $title . '"';
            }
            if ($target) {
                $link .= ' target="' . $target . '"';
            }
            if ($onclick && $href) {
                $link .= ' onclick="' . $onclick . '"';
            }
            if ($style && $href) {
                $link .= ' style="' . $style . '"';
            }
            if ($access && $href) {
                $link .= ' accesskey="' . $access . '"';
            }
            if ($onmouseover) {
                $link .= ' onmouseover="' . $onmouseover . '"';
            }
            if ($onmouseout) {
                $link .= ' onmouseout="' . $onmouseout . '"';
            }
            if ($onmousedown) {
                $link .= ' onmousedown="' . $onmousedown . '"';
            }

            $link .= '>';
            $link .= $content;
            if ($href) {
                $link .= '</a>';
            } else {
                $link .= '</span>';
            }

            return $link;
        }

        /**
         * Callback comparison operator (passed to usort() for sorting array of
         * tuples) that compares array elements based on the sort column
         * specified in $sort_col (global parameter).
         *
         * @param $a
         * @param $b
         * @return int
         * @author Kannan
         */
        public function sort_cbk($a, $b)
        {
            if ($this->sort_col === "fn") {
                // case insensitive ascending sort for function names
                $left = strtoupper($a["fn"]);
                $right = strtoupper($b["fn"]);

                if ($left == $right) {
                    return 0;
                }
                return ($left < $right) ? -1 : 1;
            }

            // descending sort for all others
            $left = $a[$this->sort_col];
            $right = $b[$this->sort_col];

            // if diff mode, sort by absolute value of regression/improvement
            if ($this->diff_mode) {
                $left = abs($left);
                $right = abs($right);
            }

            if ($left == $right) {
                return 0;
            }
            return ($left > $right) ? -1 : 1;
        }

        /**
         * Initialize the metrics we'll display based on the information
         * in the raw data.
         *
         * @param      $xhprof_data
         * @param      $rep_symbol
         * @param      $sort
         * @param bool $diff_report
         * @author Kannan
         */
        function init_metrics($xhprof_data, $rep_symbol, $sort, $diff_report = false)
        {
            $this->diff_mode = $diff_report;

            if (!empty($sort)) {
                if (array_key_exists($sort, $this->sortable_columns)) {
                    $this->sort_col = $sort;
                } else {
                    print("Invalid Sort Key $sort specified in URL");
                }
            }

            // For C++ profiler runs, walltime attribute isn't present.
            // In that case, use "samples" as the default sort column.
            if (!isset($xhprof_data["main()"]["wt"])) {
                if ($this->sort_col === "wt") {
                    $this->sort_col = "samples";
                }

                // C++ profiler data doesn't have call counts.
                // ideally we should check to see if "ct" metric
                // is present for "main()". But currently "ct"
                // metric is artificially set to 1. So, relying
                // on absence of "wt" metric instead.
                $this->display_calls = false;
            } else {
                $this->display_calls = true;
            }

            // parent/child report doesn't support exclusive times yet.
            // So, change sort hyperlinks to closest fit.
            if (!empty($rep_symbol)) {
                $this->sort_col = str_replace("excl_", "", $this->sort_col);
            }

            if ($this->display_calls) {
                $this->stats = ["fn", "ct", "Calls%"];
            } else {
                $this->stats = ["fn"];
            }

            $this->pc_stats = $this->stats;

            $possible_metrics = $this->xhprof_get_possible_metrics();
            foreach ($possible_metrics as $metric => $desc) {
                if (isset($xhprof_data["main()"][$metric])) {
                    $this->metrics[] = $metric;
                    // flat (top-level reports): we can compute
                    // exclusive metrics reports as well.
                    $this->stats[] = $metric;
                    $this->stats[] = "I" . $desc[0] . "%";
                    $this->stats[] = "excl_" . $metric;
                    $this->stats[] = "E" . $desc[0] . "%";

                    // parent/child report for a function: we can
                    // only breakdown inclusive times correctly.
                    $this->pc_stats[] = $metric;
                    $this->pc_stats[] = "I" . $desc[0] . "%";
                }
            }
        }

        /**
         * Get the appropriate description for a statistic
         * (depending upon whether we are in diff report mode
         * or single run report mode).
         *
         * @param $stat
         * @return mixed|string
         * @author Kannan
         */
        function stat_description($stat)
        {
            if ($this->diff_mode) {
                return $this->diff_descriptions[$stat];
            }

            return $this->descriptions[$stat];
        }


        /**
         * Analyze raw data & generate the profiler report
         * (common for both single run mode and diff mode).
         *
         * @param        $url_params
         * @param        $rep_symbol
         * @param        $sort
         * @param        $run1
         * @param        $run1_desc
         * @param        $run1_data
         * @param int $run2
         * @param string $run2_desc
         * @param array $run2_data
         * @author: Kannan
         */
        function profiler_report(
            $url_params,
            $rep_symbol,
            $sort,
            $run1,
            $run1_desc,
            $run1_data,
            $run2 = 0,
            $run2_desc = "",
            $run2_data = []
        ) {
            // if we are reporting on a specific function, we can trim down
            // the report(s) to just stuff that is relevant to this function.
            // That way compute_flat_info()/compute_diff() etc. do not have
            // to needlessly work hard on churning irrelevant data.
            if (!empty($rep_symbol)) {
                $run1_data = $this->xhprof_trim_run($run1_data, [$rep_symbol]);
                if ($this->diff_mode) {
                    $run2_data = $this->xhprof_trim_run($run2_data, [$rep_symbol]);
                }
            }

            if ($this->diff_mode) {
                $run_delta = $this->xhprof_compute_diff($run1_data, $run2_data);
                $symbol_tab = $this->xhprof_compute_flat_info($run_delta, $this->totals);
                $symbol_tab1 = $this->xhprof_compute_flat_info($run1_data, $this->totals_1 );
                $symbol_tab2 = $this->xhprof_compute_flat_info($run2_data, $this->totals_2);
            } else {
                $symbol_tab = $this->xhprof_compute_flat_info($run1_data, $this->totals);
            }

            $run1_txt = sprintf("<b>Run #%s:</b> %s", $run1, $run1_desc);

            $base_url_params = $this->xhprof_array_unset(
                $this->xhprof_array_unset(
                    $url_params,
                    'symbol'
                ),
                'all'
            );

            $top_link_query_string = "$this->base_path/?" . http_build_query($base_url_params);

            if ($this->diff_mode) {
                $diff_text = "Diff";
                $base_url_params = $this->xhprof_array_unset($base_url_params, 'run1');
                $base_url_params = $this->xhprof_array_unset($base_url_params, 'run2');
                $run1_link = $this->xhprof_render_link(
                    'View Run #' . $run1,
                    "$this->base_path/?" .
                    http_build_query(
                        $this->xhprof_array_set(
                            $base_url_params,
                            'run',
                            $run1
                        )
                    )
                );
                $run2_txt = sprintf(
                    "<b>Run #%s:</b> %s",
                    $run2,
                    $run2_desc
                );

                $run2_link = $this->xhprof_render_link(
                    'View Run #' . $run2,
                    "$this->base_path/?" .
                    http_build_query(
                        $this->xhprof_array_set(
                            $base_url_params,
                            'run',
                            $run2
                        )
                    )
                );
            } else {
                $diff_text = "Run";
            }

            // set up the action links for operations that can be done on this report
            $links = [];


            if ($this->diff_mode) {
                $inverted_params = $url_params;
                $inverted_params['run1'] = $url_params['run2'];
                $inverted_params['run2'] = $url_params['run1'];

                // view the different runs or invert the current diff
                $links [] = $run1_link;
                $links [] = $run2_link;
                $links [] = $this->xhprof_render_link(
                    'Invert ' . $diff_text . ' Report',
                    "$this->base_path/?" .
                    http_build_query($inverted_params)
                );
            }

            // lookup function typeahead form


            /**
             * echo
             * '<dl class=phprof_report_info>' .
             * '  <dt>' . $diff_text . ' Report</dt>' .
             * '  <dd>' . ($this->diff_mode ?
             * $run1_txt . '<br /><b>vs.</b><br />' . $run2_txt :
             * $run1_txt) .
             * '  </dd>' .
             * '  <dt>Tip</dt>' .
             * '  <dd>Click a function name below to drill down.</dd>' .
             * '</dl>' .
             * '<div style="clear: both; margin: 3em 0em;"></div>';
             */
            // data tables
            if (!empty($rep_symbol)) {
                if (!isset($symbol_tab[$rep_symbol])) {
                    echo "<hr>Symbol <b>$rep_symbol</b> not found in XHProf run</b><hr>";
                    return;
                }

                /* single function report with parent/child information */
                if ($this->diff_mode) {
                    $info1 = $symbol_tab1[$rep_symbol] ?? null;
                    $info2 = $symbol_tab2[$rep_symbol] ?? null;
                    $this->symbol_report(
                        $url_params,
                        $run_delta,
                        $symbol_tab[$rep_symbol],
                        $sort,
                        $rep_symbol,
                        $run1,
                        $info1,
                        $run2,
                        $info2
                    );
                } else {
                    $this->symbol_report(
                        $url_params,
                        $run1_data,
                        $symbol_tab[$rep_symbol],
                        $sort,
                        $rep_symbol,
                        $run1
                    );
                }
            } else {
                /* flat top-level report of all functions */
                $this->full_report($url_params, $symbol_tab, $sort, $run1, $run2, $links);
            }
        }

        /**
         * Computes percentage for a pair of values, and returns it
         * in string format.
         * @param $a
         * @param $b
         * @return float|int|string
         */
        function pct($a, $b)
        {
            if ($b == 0) {
                return "N/A";
            }

            return round($a * 1000 / $b) / 10;
        }

        /**
         * Given a number, returns the td class to use for display.
         *
         * For instance, negative numbers in diff reports comparing two runs (run1 & run2)
         * represent improvement from run1 to run2. We use green to display those deltas,
         * and red for regression deltas.
         * @param $num
         * @param $bold
         * @return string
         */
        function get_print_class($num, $bold)
        {
            if ($bold) {
                if ($this->diff_mode) {
                    if ($num <= 0) {
                        $class = $this->vgbar; // green (improvement)
                    } else {
                        $class = $this->vrbar; // red (regression)
                    }
                } else {
                    $class = $this->vbbar; // blue
                }
            } else {
                $class = $this->vbar;  // default (black)
            }

            return $class;
        }

        /**
         * Prints a <td> element with a numeric value.
         * @param      $num
         * @param      $fmt_func
         * @param bool $bold
         * @param null $attributes
         */
        function print_td_num($num, $fmt_func, $bold = false, $attributes = null)
        {
            $class = $this->get_print_class($num, $bold);

            if (!empty($fmt_func)) {
                $num = $fmt_func($num);
            }

            print("<td $attributes $class>$num</td>\n");
        }

        /**
         * Prints a <td> element with a pecentage.
         * @param      $numer
         * @param      $denom
         * @param bool $bold
         * @param null $attributes
         */
        function print_td_pct($numer, $denom, $bold = false, $attributes = null)
        {
            $class = $this->get_print_class($numer, $bold);
            if ($denom == 0) {
                $pct = "N/A%";
            } else {
                $pct = $this->xhprof_percent_format($numer / abs($denom));
            }
            print("<td $attributes $class>$pct</td>\n");
        }

        /**
         * Print "flat" data corresponding to one function.
         *
         * @param $url_params
         * @param $info
         * @param $sort
         * @param $run1
         * @param $run2
         * @author Kannan
         */
        function print_function_info($url_params, $info, $sort, $run1, $run2)
        {
            static $odd_even = 0;
            // Toggle $odd_or_even
            $odd_even = 1 - $odd_even;

            if ($odd_even) {
                print("<tr>");
            } else {
                print('<tr bgcolor="#e5e5e5">');
            }

            $href = "$this->base_path/?" .
                http_build_query(
                    $this->xhprof_array_set(
                        $url_params,
                        'symbol',
                        $info["fn"]
                    )
                );

            print('<td>');
            print($this->xhprof_render_link($info["fn"], $href));
            print("</td>\n");

            if ($this->display_calls) {
                // Call Count..
                $this->print_td_num($info["ct"], $this->format_cbk["ct"], $this->sort_col === "ct");
                $this->print_td_pct($info["ct"], $this->totals["ct"], $this->sort_col === "ct");
            }

            // Other metrics..
            foreach ($this->metrics as $metric) {
                // Inclusive metric
                $this->print_td_num(
                    $info[$metric],
                    $this->format_cbk[$metric],
                    $this->sort_col == $metric
                );
                $this->print_td_pct(
                    $info[$metric],
                    $this->totals[$metric],
                    $this->sort_col == $metric
                );

                // Exclusive Metric
                $this->print_td_num(
                    $info["excl_" . $metric],
                    $this->format_cbk["excl_" . $metric],
                    $this->sort_col == "excl_" . $metric
                );
                $this->print_td_pct(
                    $info["excl_" . $metric],
                    $this->totals[$metric],
                    $this->sort_col == "excl_" . $metric
                );
            }

            print("</tr>\n");
        }

        /**
         * Print non-hierarchical (flat-view) of profiler data.
         *
         * @param $url_params
         * @param $title
         * @param $flat_data
         * @param $sort
         * @param $run1
         * @param $run2
         * @param $limit
         * @author Kannan
         */
        function print_flat_data($url_params, $title, $flat_data, $sort, $run1, $run2, $limit)
        {
            $size = count($flat_data);
            if (!$limit) {              // no limit
                $limit = $size;
                $display_link = "";
            } else {
                $display_link = $this->xhprof_render_link(
                    " [ <b class=bubble>display all </b>]",
                    "$this->base_path/?" .
                    http_build_query(
                        $this->xhprof_array_set(
                            $url_params,
                            'all',
                            1
                        )
                    )
                );
            }

            //Find top $n requests
            $data_copy = $flat_data;
            $data_copy = _aggregateCalls($data_copy, null, $run2);
            usort($data_copy, [__CLASS__,'sortWT']);

            $iterations = 0;
            $colors = [
                '#4572A7',
                '#AA4643',
                '#89A54E',
                '#80699B',
                '#3D96AE',
                '#DB843D',
                '#92A8CD',
                '#A47D7C',
                '#B5CA92',
                '#EAFEBB',
                '#FEB4B1',
                '#2B6979',
                '#E9D6FE',
                '#FECDA3',
                '#FED980'
            ];
            foreach ($data_copy as $datapoint) {
                if (++$iterations > 14) {
                    $function_color[$datapoint['fn']] = $colors[14];
                } else {
                    $function_color[$datapoint['fn']] = $colors[$iterations - 1];
                }
            }

            include __DIR__ . "/Templates/profChart.phtml";
            include __DIR__ . "/Templates/profTable.phtml";
        }

        function sortWT($a, $b)
        {
            if ($a['excl_wt'] == $b['excl_wt']) {
                return 0;
            }
            return ($a['excl_wt'] < $b['excl_wt']) ? 1 : -1;
        }

        /**
         * Generates a tabular report for all functions. This is the top-level report.
         *
         * @param $url_params
         * @param $symbol_tab
         * @param $sort
         * @param $run1
         * @param $run2
         * @param $links
         * @author Kannan
         */
        function full_report($url_params, $symbol_tab, $sort, $run1, $run2, $links)
        {
            $possible_metrics = $this->xhprof_get_possible_metrics();
            if ($this->diff_mode) {
                include __DIR__ . "/Templates/diff_run_header_block.phtml";
            } else {
                include __DIR__ . "/Templates/single_run_header_block.phtml";
            }
            //echo xhprof_render_actions($links);
            $flat_data = [];
            foreach ($symbol_tab as $symbol => $info) {
                $tmp = $info;
                $tmp["fn"] = $symbol;

                $flat_data[] = $tmp;
            }
            usort($flat_data, [__CLASS__,'sort_cbk']);
            print("<br />");
            if (!empty($url_params['all'])) {
                $all = true;
                $limit = 0;    // display all rows
            } else {
                $all = false;
                $limit = 100;  // display only limited number of rows
            }

            $desc = str_replace("<br />", " ", $this->descriptions[$this->sort_col]);

            if ($this->diff_mode) {
                if ($all) {
                    $title = "Total Diff Report: '
               .'Sorted by absolute value of regression/improvement in $desc";
                } else {
                    $title = "Top 100 <i style='color:red'>Regressions</i>/"
                        . "<i style='color:green'>Improvements</i>: "
                        . "Sorted by $desc Diff";
                }
            } elseif ($all) {
                $title = "Sorted by $desc";
            } else {
                $title = "Displaying top $limit functions: Sorted by $desc";
            }
            $this->print_flat_data($url_params, $title, $flat_data, $sort, $run1, $run2, $limit);
        }


        /**
         * Return attribute names and values to be used by javascript tooltip.
         * @param $type
         * @param $metric
         * @return string
         */
        function get_tooltip_attributes($type, $metric)
        {
            return "type='$type' metric='$metric'";
        }

        /**
         * Print info for a parent or child function in the
         * parent & children report.
         *
         * @param $info
         * @param $base_ct
         * @param $base_info
         * @param $parent
         * @author Kannan
         */
        function pc_info($info, $base_ct, $base_info, $parent)
        {
            if ($parent) {
                $type = "Parent";
            } else {
                $type = "Child";
            }

            if ($this->display_calls) {
                $mouseoverct = $this->get_tooltip_attributes($type, "ct");
                /* call count */
                $this->print_td_num($info["ct"], $this->format_cbk["ct"], $this->sort_col === "ct", $mouseoverct);
                $this->print_td_pct($info["ct"], $base_ct, $this->sort_col === "ct", $mouseoverct);
            }

            /* Inclusive metric values  */
            foreach ($this->metrics as $metric) {
                $this->print_td_num(
                    $info[$metric],
                    $this->format_cbk[$metric],
                    $this->sort_col == $metric,
                    $this->get_tooltip_attributes($type, $metric)
                );
                $this->print_td_pct(
                    $info[$metric],
                    $base_info[$metric],
                    $this->sort_col == $metric,
                    $this->get_tooltip_attributes($type, $metric)
                );
            }
        }

        function print_pc_array(
            $url_params,
            $results,
            $base_ct,
            $base_info,
            $parent,
            $run1,
            $run2
        ) {
            // Construct section title
            if ($parent) {
                $title = 'Parent function';
            } else {
                $title = 'Child function';
            }
            if (count($results) > 1) {
                $title .= 's';
            }

            print("<tr bgcolor='#e0e0ff'><td>");
            print("<b><i><center>" . $title . "</center></i></b>");
            print("</td></tr>");

            $odd_even = 0;
            foreach ($results as $info) {
                $href = "$this->base_path/?" .
                    http_build_query(
                        $this->xhprof_array_set(
                            $url_params,
                            'symbol',
                            $info["fn"]
                        )
                    );
                $odd_even = 1 - $odd_even;

                if ($odd_even) {
                    print('<tr>');
                } else {
                    print('<tr bgcolor="#e5e5e5">');
                }

                print("<td>" . $this->xhprof_render_link($info["fn"], $href) . "</td>");
                $this->pc_info($info, $base_ct, $base_info, $parent);
                print("</tr>");
            }
        }


        function print_symbol_summary($symbol_info, $stat, $base)
        {
            $val = $symbol_info[$stat];
            $desc = str_replace("<br />", " ", $this->stat_description($stat));

            print("$desc: </td>");
            print(number_format($val));
            print(" (" . pct($val, $base) . "% of overall)");
            if (strncmp($stat, "excl", 4) === 0) {
                $func_base = $symbol_info[str_replace("excl_", "", $stat)];
                print(" (" . pct($val, $func_base) . "% of this function)");
            }
            print("<br />");
        }

        /**
         * Generates a report for a single function/symbol.
         *
         * @param      $url_params
         * @param      $run_data
         * @param      $symbol_info
         * @param      $sort
         * @param      $rep_symbol
         * @param      $run1
         * @param null $symbol_info1
         * @param int $run2
         * @param null $symbol_info2
         * @author Kannan
         */
        function symbol_report(
            $url_params,
            $run_data,
            $symbol_info,
            $sort,
            $rep_symbol,
            $run1,
            $symbol_info1 = null,
            $run2 = 0,
            $symbol_info2 = null
        ) {
            $possible_metrics = $this->xhprof_get_possible_metrics();
            if ($this->diff_mode) {
                $diff_text = "<b>Diff</b>";
                $regr_impr = "<i style='color:red'>Regression</i>/<i style='color:green'>Improvement</i>";
            } else {
                $diff_text = "";
                $regr_impr = "";
            }
            if ($this->diff_mode) {
                $base_url_params = $this->xhprof_array_unset(
                    $this->xhprof_array_unset(
                        $url_params,
                        'run1'
                    ),
                    'run2'
                );
                $href1 = "$this->base_path?"
                    . http_build_query($this->xhprof_array_set($base_url_params, 'run', $run1));
                $href2 = "$this->base_path?"
                    . http_build_query($this->xhprof_array_set($base_url_params, 'run', $run2));

                print("<h3 align=center>$regr_impr summary for $rep_symbol<br /><br /></h3>");
                print('<table border=1 cellpadding=2 cellspacing=1 width="30%" '
                    . 'rules=rows bordercolor="#bdc7d8" align=center>' . "\n");
                print('<tr bgcolor="#bdc7d8" align=right>');
                print("<th align=left>$rep_symbol</th>");
                print("<th $this->vwbar><a href=" . $href1 . ">Run #$run1</a></th>");
                print("<th $this->vwbar><a href=" . $href2 . ">Run #$run2</a></th>");
                print("<th $this->vwbar>Diff</th>");
                print("<th $this->vwbar>Diff%</th>");
                print('</tr>');
                print('<tr>');

                if ($this->display_calls) {
                    print("<td>Number of Function Calls</td>");
                    $this->print_td_num($symbol_info1["ct"], $this->format_cbk["ct"]);
                    $this->print_td_num($symbol_info2["ct"], $this->format_cbk["ct"]);
                    $this->print_td_num(
                        $symbol_info2["ct"] - $symbol_info1["ct"],
                        $this->format_cbk["ct"],
                        true
                    );
                    $this->print_td_pct(
                        $symbol_info2["ct"] - $symbol_info1["ct"],
                        $symbol_info1["ct"],
                        true
                    );
                    print('</tr>');
                }


                foreach ($this->metrics as $metric) {
                    $m = $metric;

                    // Inclusive stat for metric
                    print('<tr>');
                    print("<td>" . str_replace("<br />", " ", $this->descriptions[$m]) . "</td>");
                    $this->print_td_num($symbol_info1[$m], $this->format_cbk[$m]);
                    $this->print_td_num($symbol_info2[$m], $this->format_cbk[$m]);
                    $this->print_td_num($symbol_info2[$m] - $symbol_info1[$m], $this->format_cbk[$m], true);
                    $this->print_td_pct($symbol_info2[$m] - $symbol_info1[$m], $symbol_info1[$m], true);
                    print('</tr>');

                    // AVG (per call) Inclusive stat for metric
                    print('<tr>');
                    print("<td>" . str_replace("<br />", " ", $this->descriptions[$m]) . " per call </td>");
                    $avg_info1 = 'N/A';
                    $avg_info2 = 'N/A';
                    if ($symbol_info1['ct'] > 0) {
                        $avg_info1 = ($symbol_info1[$m] / $symbol_info1['ct']);
                    }
                    if ($symbol_info2['ct'] > 0) {
                        $avg_info2 = ($symbol_info2[$m] / $symbol_info2['ct']);
                    }
                    $this->print_td_num($avg_info1, $this->format_cbk[$m]);
                    $this->print_td_num($avg_info2, $this->format_cbk[$m]);
                    $this->print_td_num($avg_info2 - $avg_info1, $this->format_cbk[$m], true);
                    $this->print_td_pct($avg_info2 - $avg_info1, $avg_info1, true);
                    print('</tr>');

                    // Exclusive stat for metric
                    $m = "excl_" . $metric;
                    print('<tr style="border-bottom: 1px solid black;">');
                    print("<td>" . str_replace("<br />", " ", $this->descriptions[$m]) . "</td>");
                    $this->print_td_num($symbol_info1[$m], $this->format_cbk[$m]);
                    $this->print_td_num($symbol_info2[$m], $this->format_cbk[$m]);
                    $this->print_td_num($symbol_info2[$m] - $symbol_info1[$m], $this->format_cbk[$m], true);
                    $this->print_td_pct($symbol_info2[$m] - $symbol_info1[$m], $symbol_info1[$m], true);
                    print('</tr>');
                }

                print('</table>');
            }

            print("<br /><h4><center>");
            print("Parent/Child $regr_impr report for <b>$rep_symbol</b>");

            $callgraph_href = "$this->base_path/callgraph.php?"
                . http_build_query($this->xhprof_array_set($url_params, 'func', $rep_symbol));

            print(" <a href='$callgraph_href'>[View Callgraph $diff_text]</a><br />");

            print("</center></h4><br />");

            print('<table border=1 cellpadding=2 cellspacing=1 width="90%" '
                . 'rules=rows bordercolor="#bdc7d8" align=center>' . "\n");
            print('<tr bgcolor="#bdc7d8" align=right>');

            foreach ($this->pc_stats as $stat) {
                $desc = $this->stat_description($stat);
                if (array_key_exists($stat, $this->sortable_columns)) {
                    $href = "$this->base_path/?" .
                        http_build_query(
                            $this->xhprof_array_set(
                                $url_params,
                                'sort',
                                $stat
                            )
                        );
                    $header = $this->xhprof_render_link($desc, $href);
                } else {
                    $header = $desc;
                }

                if ($stat === "fn") {
                    print("<th align=left><nobr>$header</th>");
                } else {
                    print("<th " . $this->vwbar . "><nobr>$header</th>");
                }
            }
            print("</tr>");

            print("<tr bgcolor='#e0e0ff'><td>");
            print("<b><i><center>Current Function</center></i></b>");
            print("</td></tr>");

            print("<tr>");
            // make this a self-reference to facilitate copy-pasting snippets to e-mails
            print("<td><a href=''>$rep_symbol</a></td>");

            if ($this->display_calls) {
                // Call Count
                $this->print_td_num($symbol_info["ct"], $this->format_cbk["ct"]);
                $this->print_td_pct($symbol_info["ct"], $this->totals["ct"]);
            }

            // Inclusive Metrics for current function
            foreach ($this->metrics as $metric) {
                $this->print_td_num($symbol_info[$metric], $this->format_cbk[$metric], $this->sort_col == $metric);
                $this->print_td_pct($symbol_info[$metric], $this->totals[$metric], $this->sort_col == $metric);
            }
            print("</tr>");

            print("<tr bgcolor='#ffffff'>");
            print("<td style='text-align:right;color:blue'>"
                . "Exclusive Metrics $diff_text for Current Function</td>");

            if ($this->display_calls) {
                // Call Count
                print("<td $this->vbar></td>");
                print("<td $this->vbar></td>");
            }

            // Exclusive Metrics for current function
            foreach ($this->metrics as $metric) {
                $this->print_td_num(
                    $symbol_info["excl_" . $metric],
                    $this->format_cbk["excl_" . $metric],
                    $this->sort_col == $metric,
                    $this->get_tooltip_attributes("Child", $metric)
                );
                $this->print_td_pct(
                    $symbol_info["excl_" . $metric],
                    $symbol_info[$metric],
                    $this->sort_col == $metric,
                    $this->get_tooltip_attributes("Child", $metric)
                );
            }
            print("</tr>");

            // list of callers/parent functions
            $results = [];
            if ($this->display_calls) {
                $base_ct = $symbol_info["ct"];
            } else {
                $base_ct = 0;
            }
            foreach ($this->metrics as $metric) {
                $base_info[$metric] = $symbol_info[$metric];
            }
            foreach ($run_data as $parent_child => $info) {
                [$parent, $child] = $this->xhprof_parse_parent_child($parent_child);
                if (($child == $rep_symbol) && $parent) {
                    $info_tmp = $info;
                    $info_tmp["fn"] = $parent;
                    $results[] = $info_tmp;
                }
            }
            usort($results, [__CLASS__,'sort_cbk']);

            if (count($results) > 0) {
                $this->print_pc_array(
                    $url_params,
                    $results,
                    $base_ct,
                    $base_info,
                    true,
                    $run1,
                    $run2
                );
            }

            // list of callees/child functions
            $results = [];
            $base_ct = 0;
            foreach ($run_data as $parent_child => $info) {
                [$parent, $child] = $this->xhprof_parse_parent_child($parent_child);
                if ($parent == $rep_symbol) {
                    $info_tmp = $info;
                    $info_tmp["fn"] = $child;
                    $results[] = $info_tmp;
                    if ($this->display_calls) {
                        $base_ct += $info["ct"];
                    }
                }
            }
            usort($results, [__CLASS__,'sort_cbk']);

            if (count($results)) {
                $this->print_pc_array(
                    $url_params,
                    $results,
                    $base_ct,
                    $base_info,
                    false,
                    $run1,
                    $run2
                );
            }

            print("</table>");

            // These will be used for pop-up tips/help.
            // Related javascript code is in: xhprof_report.js
            print("\n");
            print('<script language="javascript">' . "\n");
            print("var func_name = '\"" . $rep_symbol . "\"';\n");
            print("var total_child_ct  = " . $base_ct . ";\n");
            if ($this->display_calls) {
                print("var func_ct   = " . $symbol_info["ct"] . ";\n");
            }
            print("var func_metrics = new Array();\n");
            print("var metrics_col  = new Array();\n");
            print("var metrics_desc  = new Array();\n");
            if ($this->diff_mode) {
                print("var diff_mode = true;\n");
            } else {
                print("var diff_mode = false;\n");
            }
            $column_index = 3; // First three columns are Func Name, Calls, Calls%
            foreach ($this->metrics as $metric) {
                print("func_metrics[\"" . $metric . "\"] = " . round($symbol_info[$metric]) . ";\n");
                print("metrics_col[\"" . $metric . "\"] = " . $column_index . ";\n");
                print("metrics_desc[\"" . $metric . "\"] = \"" . $possible_metrics[$metric][2] . "\";\n");

                // each metric has two columns..
                $column_index += 2;
            }
            print('</script>');
            print("\n");
        }

        /**
         * Generate the profiler report for a single run.
         *
         * @param      $url_params
         * @param      $xhprof_data
         * @param      $run_desc
         * @param      $rep_symbol
         * @param      $sort
         * @param      $run
         * @param null $run_details
         * @author Kannan
         */
        function profiler_single_run_report(
            $url_params,
            $xhprof_data,
            $run_desc,
            $rep_symbol,
            $sort,
            $run,
            $run_details = null
        ) {
            $this->init_metrics($xhprof_data, $rep_symbol, $sort);

            $this->profiler_report(
                $url_params,
                $rep_symbol,
                $sort,
                $run,
                $run_desc,
                $xhprof_data,
                $run_details
            );
        }


        /**
         * Generate the profiler report for diff mode (delta between two runs).
         *
         * @param $url_params
         * @param $xhprof_data1
         * @param $run1_desc
         * @param $xhprof_data2
         * @param $run2_desc
         * @param $rep_symbol
         * @param $sort
         * @param $run1
         * @param $run2
         * @author Kannan
         */
        function profiler_diff_report(
            $url_params,
            $xhprof_data1,
            $run1_desc,
            $xhprof_data2,
            $run2_desc,
            $rep_symbol,
            $sort,
            $run1,
            $run2
        ) {
            // Initialize what metrics we'll display based on data in Run2
            $this->init_metrics($xhprof_data2, $rep_symbol, $sort, true);

            $this->profiler_report(
                $url_params,
                $rep_symbol,
                $sort,
                $run1,
                $run1_desc,
                $xhprof_data1,
                $run2,
                $run2_desc,
                $xhprof_data2
            );
        }


        /**
         * Generate a XHProf Display View given the various URL parameters
         * as arguments. The first argument is an object that implements
         * the iXHProfRuns interface.
         *
         * @param array $url_params Array of non-default URL params.
         *
         * @param string $source Category/type of the run. The source in
         *                                 combination with the run id uniquely
         *                                 determines a profiler run.
         *
         * @param string $run run id, or comma separated sequence of
         *                                 run ids. The latter is used if an
         *                                 aggregate report of the runs is
         *                                 desired.
         *
         * @param string $wts Comma separate list of integers.
         *                                 Represents the weighted ratio in
         *                                 which which a set of runs will
         *                                 be aggregated. [Used only for
         *                                 aggregate reports.]
         *
         * @param string $symbol Function symbol. If non-empty then the
         *                                 parent/child view of this function is
         *                                 displayed. If empty, a flat-profile
         *                                 view of the functions is displayed.
         *
         * @param        $sort
         * @param string $run1 Base run id (for diff reports)
         *
         * @param string $run2 New run id (for diff reports)
         */
        function displayXHProfReport(
            $url_params,
            $source,
            $run,
            $wts,
            $symbol,
            $sort,
            $run1,
            $run2
        ) {
            if ($run) {                              // specific run to display?
                // run may be a single run or a comma separate list of runs
                // that'll be aggregated. If "wts" (a comma separated list
                // of integral weights is specified), the runs will be
                // aggregated in that ratio.
                //
                $runs_array = explode(",", $run);

                if (count($runs_array) == 1) {
                    global $run_details;
                    [$xhprof_data, $run_details] = $this->xhprof_runs_impl->get_run(
                        $runs_array[0],
                        $source,
                        $description
                    );
                } else {
                    if (!empty($wts)) {
                        $wts_array = explode(",", $wts);
                    } else {
                        $wts_array = null;
                    }
                    $data = $this->xhprof_aggregate_runs(
                        $this->xhprof_runs_impl,
                        $runs_array,
                        $wts_array,
                        $source
                    );
                    $xhprof_data = $data['raw'];
                    $description = $data['description'];
                }

                if (!$xhprof_data) {
                    echo "Given XHProf Run not found.";
                    return;
                }


                $this->profiler_single_run_report(
                    $url_params,
                    $xhprof_data,
                    $description,
                    $symbol,
                    $sort,
                    $run,
                    $run_details
                );
            } elseif ($run1 && $run2) {                  // diff report for two runs
                [$xhprof_data1, $run_details1] = $this->xhprof_runs_impl->get_run($run1, $source, $description1);
                [$xhprof_data2, $run_details2] = $this->xhprof_runs_impl->get_run($run2, $source, $description2);

                $this->profiler_diff_report(
                    $url_params,
                    $xhprof_data1,
                    $description1,
                    $xhprof_data2,
                    $description2,
                    $symbol,
                    $sort,
                    $run1,
                    $run2
                );
            } else {
                echo "No XHProf runs specified in the URL.";
            }
        }
    }
}
