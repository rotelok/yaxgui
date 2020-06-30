<?php
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

/*
 * This file contains callgraph image generation related XHProf utility
 * functions
 *
 */

// Supported ouput format
$xhprof_legal_image_types = [
    "jpg" => 1,
    "gif" => 1,
    "png" => 1,
    "ps" => 1
];

/**
 * Send an HTTP header with the response. You MUST use this function instead
 * of header() so that we can debug header issues because they're virtually
 * impossible to debug otherwise. If you try to commit header(), SVN will
 * reject your commit.
 *
 * @param string  HTTP header name, like 'Location'
 * @param string  HTTP header value, like 'http://www.example.com/'
 *
 * @return null
 * @return null
 */
function xhprof_http_header($name, $value)
{
    if (!$name) {
        $XHProfLib->xhprof_error('http_header usage');
        return null;
    }

    if (!is_string($value)) {
        $XHProfLib->xhprof_error('http_header value not a string');
    }

    header($name . ': ' . $value);
}

/**
 * Genearte and send MIME header for the output image to client browser.
 *
 * @param $type
 * @param $length
 *
 * @author cjiang
 */
function xhprof_generate_mime_header($type, $length)
{
    $mime = false;
    switch ($type) {
        case 'jpg':
            $mime = 'image/jpeg';
            break;
        case 'gif':
            $mime = 'image/gif';
            break;
        case 'png':
            $mime = 'image/png';
            break;
        case 'ps':
            $mime = 'application/postscript';
            break;
    }

    if ($mime) {
        xhprof_http_header('Content-type', $mime);
        xhprof_http_header('Content-length', (string)$length);
    }
}

/**
 * Generate image according to DOT script. This function will spawn a process
 * with "dot" command and pipe the "dot_script" to it and pipe out the
 * generated image content.
 *
 * @param dot_script, string, the script for DOT to generate the image.
 * @param type, one of the supported image types, see
 *           $xhprof_legal_image_types.
 * @returns, binary content of the generated image on success. empty string on
 *           failure.
 *
 * @return false|string
 * @return false|string
 * @author cjiang
 */
function xhprof_generate_image_by_dot($dot_script, $type)
{
    // get config => yep really dirty - but unobstrusive
    global $_xhprof;

    $errorFile = $_xhprof['dot_errfile'];
    $tmpDirectory = $_xhprof['dot_tempdir'];
    $dotBinary = $_xhprof['dot_binary'];

    // detect windows
    if (stripos(PHP_OS, 'WIN') !== false && stripos(PHP_OS, 'Darwin') === false) {
        return xhprof_generate_image_by_dot_on_win(
            $dot_script,
            $type,
            $errorFile,
            $tmpDirectory,
            $dotBinary
        );
    }

    // parts of the original source
    $descriptorspec = [
        // stdin is a pipe that the child will read from
        0 => ["pipe", "r"],
        // stdout is a pipe that the child will write to
        1 => ["pipe", "w"],
        // stderr is a file to write to
        2 => ["file", $errorFile, "a"]
    ];

    $cmd = ' "' . $dotBinary . '" -T' . $type;

    $process = proc_open($cmd, $descriptorspec, $pipes, $tmpDirectory, []);

    if (is_resource($process)) {
        fwrite($pipes[0], $dot_script);
        fclose($pipes[0]);

        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        proc_close($process);
        if ($output == "" && filesize($errorFile) > 0) {
            die("Error producing callgraph, check $errorFile");
        }
        return $output;
    }
    print "failed to shell execute cmd=\"$cmd\"\n";

    $error = error_get_last();
    if (isset($error['message'])) {
        print($error['message'] . "\n");
    }

    exit();
}

/**
 * Generate image according to DOT script. This function will make the
 * process working on windows boxes (some win-boxes seems to having problems
 * with creating processes via proc_open so we do it the lame win way by
 * creating and writing to temp-files and reading them in again ...
 * not really nice but functional
 *
 * @param dot_script, string, the script for DOT to generate the image.
 * @param type, one of the supported image types, see
 * @param errorFile, string, the file to write errors to
 * @param tmpDirectory, string, the directory for temporary created files
 * @param dotBin, the dot-binary file (e.g. dot.exe)
 * @returns, binary content of the generated image on success.
 *
 * @return false|string
 * @return false|string
 * @author Benjamin Carl <opensource@clickalicious.de>
 */
function xhprof_generate_image_by_dot_on_win(
    $dot_script,
    $type,
    $errorFile,
    $tmpDirectory,
    $dotBin
) {
    // assume no error
    $error = false;

    // get unique identifier
    $uid = md5(time());

    // files we handle with
    $files = [
        'dot' => $tmpDirectory . '\\' . $uid . '.dot',
        'img' => $tmpDirectory . '\\' . $uid . '.' . $type
    ];

    // build command for dot.exe
    $cmd = '"' . $dotBin . '" -T' . $type . ' "' . $files['dot'] . '" -o "' . $files['img'] . '"';

    // 1. write dot script temp
    file_put_contents($files['dot'], $dot_script);

    // 2. call dot-binary with temp dot script and write file (out) type
    shell_exec($cmd);

    // 3. read in the img
    $output = file_get_contents($files['img']);
    if ($output == ''
        || !file_exists($files['img'])
        || filesize($files['img']) == 0
    ) {
        $error = true;
    }

    // 4. delete temp files
    foreach ($files as $temp => $file) {
        unlink($file);
    }

    // 5. check for possible error (empty result)
    if ($error) {
        die("Error producing callgraph!");
    }

    // 6. return result
    return $output;
}

/*
 * Get the children list of all nodes.
 */
function xhprof_get_children_table($raw_data)
{
    $children_table = [];
    foreach ($raw_data as $parent_child => $info) {
        [$parent, $child] = xhprof_parse_parent_child($parent_child);
        if (!isset($children_table[$parent])) {
            $children_table[$parent] = [$child];
        } else {
            $children_table[$parent][] = $child;
        }
    }
    return $children_table;
}

/**
 * Generate DOT script from the given raw phprof data.
 *
 * @param      $raw_data
 * @param      $threshold
 * @param      $source
 * @param      $page
 * @param      $func
 * @param      $critical_path
 * @param null $right
 * @param null $left
 * @return string
 * @author cjiang
 */
function xhprof_generate_dot_script(
    $raw_data,
    $threshold,
    $source,
    $page,
    $func,
    $critical_path,
    $right = null,
    $left = null
) {
    $max_width = 5;
    $max_height = 3.5;
    $max_fontsize = 35;
    $max_sizing_ratio = 20;

    $totals = [];
    $XHProfLib = new \Rotelok\xhprof\XHProfLib();
    $sym_table = $XHProfLib->xhprof_compute_flat_info($raw_data, $totals);

    if ($critical_path) {
        $children_table = xhprof_get_children_table($raw_data);
        $node = "main()";
        $path = [];
        $path_edges = [];
        $visited = [];
        while ($node) {
            $visited[$node] = true;
            if (isset($children_table[$node])) {
                $max_child = null;
                foreach ($children_table[$node] as $child) {
                    if (isset($visited[$child])) {
                        continue;
                    }
                    if ($max_child === null
                        || abs(
                            $raw_data[$XHProfLib->xhprof_build_parent_child_key(
                                $node,
                                $child
                            )]["wt"]
                        ) > abs(
                            $raw_data[$XHProfLib->xhprof_build_parent_child_key(
                                $node,
                                $max_child
                            )]["wt"]
                        )
                    ) {
                        $max_child = $child;
                    }
                }
                if ($max_child !== null) {
                    $path[$max_child] = true;
                    $path_edges[$XHProfLib->xhprof_build_parent_child_key($node, $max_child)] = true;
                }
                $node = $max_child;
            } else {
                $node = null;
            }
        }
    }

    // if it is a benchmark callgraph, we make the benchmarked function the root.
    if ($source === "bm" && array_key_exists("main()", $sym_table)) {
        $total_times = $sym_table["main()"]["ct"];
        $remove_funcs = [
            "main()",
            "hotprofiler_disable",
            "call_user_func_array",
            "xhprof_disable"
        ];

        foreach ($remove_funcs as $cur_del_func) {
            if (array_key_exists($cur_del_func, $sym_table)
                && $sym_table[$cur_del_func]["ct"] == $total_times
            ) {
                unset($sym_table[$cur_del_func]);
            }
        }
    }

    // use the function to filter out irrelevant functions.
    if (!empty($func)) {
        $interested_funcs = [];
        foreach ($raw_data as $parent_child => $info) {
            [$parent, $child] = $XHProfLib->xhprof_parse_parent_child($parent_child);
            if ($parent == $func || $child == $func) {
                $interested_funcs[$parent] = 1;
                $interested_funcs[$child] = 1;
            }
        }
        foreach ($sym_table as $symbol => $info) {
            if (!array_key_exists($symbol, $interested_funcs)) {
                unset($sym_table[$symbol]);
            }
        }
    }

    $result = "digraph call_graph {\n";

    // Filter out functions whose exclusive time ratio is below threshold, and
    // also assign a unique integer id for each function to be generated. In the
    // meantime, find the function with the most exclusive time (potentially the
    // performance bottleneck).
    $cur_id = 0;
    $max_wt = 0;
    foreach ($sym_table as $symbol => $info) {
        if (empty($func) && abs($info["wt"] / $totals["wt"]) < $threshold) {
            unset($sym_table[$symbol]);
            continue;
        }
        if ($max_wt == 0 || $max_wt < abs($info["excl_wt"])) {
            $max_wt = abs($info["excl_wt"]);
        }
        $sym_table[$symbol]["id"] = $cur_id;
        $cur_id++;
    }

    // Generate all nodes' information.
    foreach ($sym_table as $symbol => $info) {
        if ($info["excl_wt"] == 0) {
            $sizing_factor = $max_sizing_ratio;
        } else {
            $sizing_factor = $max_wt / abs($info["excl_wt"]);
            if ($sizing_factor > $max_sizing_ratio) {
                $sizing_factor = $max_sizing_ratio;
            }
        }
        $fillcolor = (($sizing_factor < 1.5) ?
            ", style=filled, fillcolor=red" : "");

        // highlight nodes along critical path.
        if ($critical_path && !$fillcolor && array_key_exists($symbol, $path)) {
            $fillcolor = ", style=filled, fillcolor=yellow";
        }

        $fontsize = ", fontsize="
            . (int)($max_fontsize / (($sizing_factor - 1) / 10 + 1));

        $width = ", width=" . sprintf("%.1f", $max_width / $sizing_factor);
        $height = ", height=" . sprintf("%.1f", $max_height / $sizing_factor);

        if ($symbol === "main()") {
            $shape = "octagon";
            $name = "Total: " . ($totals["wt"] / 1000.0) . " ms\\n";
            $name .= addslashes($page ?? $symbol);
        } else {
            $shape = "box";
            $name = addslashes($symbol) . "\\nInc: " . sprintf("%.3f", $info["wt"] / 1000) .
                " ms (" . sprintf("%.1f%%", 100 * $info["wt"] / $totals["wt"]) . ")";
        }
        if ($left === null) {
            $label = ", label=\"" . $name . "\\nExcl: "
                . sprintf("%.3f", $info["excl_wt"] / 1000.0) . " ms ("
                . sprintf("%.1f%%", 100 * $info["excl_wt"] / $totals["wt"])
                . ")\\n" . $info["ct"] . " total calls\"";
        } elseif (isset($left[$symbol], $right[$symbol])) {
            $label = ", label=\"" . addslashes($symbol) .
                "\\nInc: " . sprintf("%.3f", $left[$symbol]["wt"] / 1000.0)
                . " ms - "
                . sprintf("%.3f", $right[$symbol]["wt"] / 1000.0) . " ms = "
                . sprintf("%.3f", $info["wt"] / 1000.0) . " ms" .
                "\\nExcl: "
                . sprintf("%.3f", $left[$symbol]["excl_wt"] / 1000.0)
                . " ms - " . sprintf("%.3f", $right[$symbol]["excl_wt"] / 1000.0)
                . " ms = " . sprintf("%.3f", $info["excl_wt"] / 1000.0) . " ms" .
                "\\nCalls: " . sprintf("%.3f", $left[$symbol]["ct"]) . " - "
                . sprintf("%.3f", $right[$symbol]["ct"]) . " = "
                . sprintf("%.3f", $info["ct"]) . "\"";
        } elseif (isset($left[$symbol])) {
            $label = ", label=\"" . addslashes($symbol) .
                "\\nInc: " . sprintf("%.3f", $left[$symbol]["wt"] / 1000.0)
                . " ms - 0 ms = " . sprintf("%.3f", $info["wt"] / 1000.0)
                . " ms" . "\\nExcl: "
                . sprintf("%.3f", $left[$symbol]["excl_wt"] / 1000.0)
                . " ms - 0 ms = "
                . sprintf("%.3f", $info["excl_wt"] / 1000.0) . " ms" .
                "\\nCalls: " . sprintf("%.3f", $left[$symbol]["ct"]) . " - 0 = "
                . sprintf("%.3f", $info["ct"]) . "\"";
        } else {
            $label = ", label=\"" . addslashes($symbol) .
                "\\nInc: 0 ms - "
                . sprintf("%.3f", $right[$symbol]["wt"] / 1000.0)
                . " ms = " . sprintf("%.3f", $info["wt"] / 1000.0) . " ms" .
                "\\nExcl: 0 ms - "
                . sprintf("%.3f", $right[$symbol]["excl_wt"] / 1000.0)
                . " ms = " . sprintf("%.3f", $info["excl_wt"] / 1000.0) . " ms" .
                "\\nCalls: 0 - " . sprintf("%.3f", $right[$symbol]["ct"])
                . " = " . sprintf("%.3f", $info["ct"]) . "\"";
        }
        $result .= "N" . $sym_table[$symbol]["id"];
        $result .= "[shape=$shape " . $label . $width
            . $height . $fontsize . $fillcolor . "];\n";
    }

    // Generate all the edges' information.
    foreach ($raw_data as $parent_child => $info) {
        [$parent, $child] = $XHProfLib->xhprof_parse_parent_child($parent_child);

        if (isset($sym_table[$parent], $sym_table[$child]) && (empty($func)
                || (!empty($func) && ($parent == $func || $child == $func)))
        ) {
            $label = $info["ct"] == 1 ? $info["ct"] . " call" : $info["ct"] . " calls";

            $headlabel = $sym_table[$child]["wt"] > 0 ?
                sprintf(
                    "%.1f%%",
                    100 * $info["wt"]
                    / $sym_table[$child]["wt"]
                )
                : "0.0%";

            $taillabel = ($sym_table[$parent]["wt"] > 0) ?
                sprintf(
                    "%.1f%%",
                    100 * $info["wt"] /
                    ($sym_table[$parent]["wt"] - $sym_table["$parent"]["excl_wt"])
                )
                : "0.0%";

            $linewidth = 1;
            $arrow_size = 1;

            if ($critical_path
                && isset($path_edges[$XHProfLib->xhprof_build_parent_child_key($parent, $child)])
            ) {
                $linewidth = 10;
                $arrow_size = 2;
            }

            $result .= "N" . $sym_table[$parent]["id"] . " -> N"
                . $sym_table[$child]["id"];
            $result .= "[arrowsize=$arrow_size, style=\"setlinewidth($linewidth)\","
                . " label=\""
                . $label . "\", headlabel=\"" . $headlabel
                . "\", taillabel=\"" . $taillabel . "\" ]";
            $result .= ";\n";
        }
    }
    $result .= "\n}";

    return $result;
}

function xhprof_render_diff_image(
    $xhprof_runs_impl,
    $run1,
    $run2,
    $type,
    $threshold,
    $source
) {
    $total1 = [];
    $total2 = [];

    [$raw_data1, $a] = $xhprof_runs_impl->get_run($run1, $source, $desc_unused);
    [$raw_data2, $b] = $xhprof_runs_impl->get_run($run2, $source, $desc_unused);

    $XHProfLib = new Rotelok\xhprof\XHProfLib();

    // init_metrics($raw_data1, null, null);
    $children_table1 = xhprof_get_children_table($raw_data1);
    $children_table2 = xhprof_get_children_table($raw_data2);
    $symbol_tab1 = $XHProfLib->xhprof_compute_flat_info($raw_data1, $total1);
    $symbol_tab2 = $XHProfLib->xhprof_compute_flat_info($raw_data2, $total2);
    $run_delta = $XHProfLib->xhprof_compute_diff($raw_data1, $raw_data2);
    $script = xhprof_generate_dot_script(
        $run_delta,
        $threshold,
        $source,
        null,
        null,
        true,
        $symbol_tab1,
        $symbol_tab2
    );
    $content = xhprof_generate_image_by_dot($script, $type);

    xhprof_generate_mime_header($type, strlen($content));
    echo $content;
}

/**
 * Generate image content from phprof run id.
 *
 * @param object $xhprof_runs_impl An object that implements
 *                                                                                            the iXHProfRuns interface
 * @param        $run_id
 * @param        $type
 * @param        $threshold
 * @param        $func
 * @param        $source
 * @param        $critical_path
 * @return false|string
 * @author cjiang
 */
function xhprof_get_content_by_run(
    $xhprof_runs_impl,
    $run_id,
    $type,
    $threshold,
    $func,
    $source,
    $critical_path
) {
    if (!$run_id) {
        return "";
    }

    [$raw_data, $a] = $xhprof_runs_impl->get_run($run_id, $source, $description);
    if (!$raw_data) {
        xhprof_error("Raw data is empty");
        return "";
    }

    $script = xhprof_generate_dot_script(
        $raw_data,
        $threshold,
        $source,
        $description,
        $func,
        $critical_path
    );

    return xhprof_generate_image_by_dot($script, $type);
}

/**
 * Generate image from phprof run id and send it to client.
 *
 * @param object $xhprof_runs_impl An object that implements
 *                                                                                            the iXHProfRuns interface
 * @param        $run_id
 * @param        $type
 * @param        $threshold
 * @param        $func
 * @param        $source
 * @param        $critical_path
 * @author cjiang
 */
function xhprof_render_image(
    $xhprof_runs_impl,
    $run_id,
    $type,
    $threshold,
    $func,
    $source,
    $critical_path
) {
    $content = xhprof_get_content_by_run(
        $xhprof_runs_impl,
        $run_id,
        $type,
        $threshold,
        $func,
        $source,
        $critical_path
    );
    if (!$content) {
        print "Error: either we can not find profile data for run_id " . $run_id
            . " or the threshold " . $threshold . " is too small or you do not"
            . " have 'dot' image generation utility installed.";
        exit();
    }

    xhprof_generate_mime_header($type, strlen($content));
    echo $content;
}
