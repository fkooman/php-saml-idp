<?php

/*
 * Copyright (c) 2019 François Kooman <fkooman@tuxed.net>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

$localeFileList = glob(dirname(__DIR__).'/locale/*.php');
$tplFileList = glob(dirname(__DIR__).'/views/*.php');

// extract all translatable strings from the PHP templates and put them in an
// array
$sourceStr = [];
foreach ($tplFileList as $tplFile) {
    $phpFile = file_get_contents($tplFile);
    // find all translatable strings in the template...
    preg_match_all("/this->t\\('(.*?)'\\)/", $phpFile, $matches);
    foreach ($matches[1] as $m) {
        if (!in_array($m, $sourceStr, true)) {
            $sourceStr[] = $m;
        }
    }
}

foreach ($localeFileList as $localeFile) {
    $localeData = include $localeFile;

    // check which keys are missing from translation file
    foreach ($sourceStr as $k) {
        if (!array_key_exists($k, $localeData)) {
            // adding them to translation file
            $localeData[$k] = '';
        }
    }

    $deletedList = [];
    // check which translations are there, but are no longer needed...
    foreach ($localeData as $k => $v) {
        if (!in_array($k, $sourceStr, true)) {
            // remove them from the translation file, add them to the
            // "deleted" array
            unset($localeData[$k]);
            $deletedList[$k] = $v;
        }
    }

    // sort the translations
    ksort($localeData);
    ksort($deletedList);

    // create the locale file
    $output = '<?php'.PHP_EOL.PHP_EOL.'return ['.PHP_EOL;
    foreach ($localeData as $k => $v) {
        $k = quoteStr($k);
        $v = quoteStr($v);
        if (empty($v)) {
            $output .= sprintf("    //'%s' => '%s',", $k, $v).PHP_EOL;
        } else {
            $output .= sprintf("    '%s' => '%s',", $k, $v).PHP_EOL;
        }
    }
    // add the deleted entries as comments
    foreach ($deletedList as $k => $v) {
        $k = quoteStr($k);
        $v = quoteStr($v);
        $output .= sprintf("    // [DELETED] '%s' => '%s',", $k, $v).PHP_EOL;
    }
    $output .= '];';

    // write locale file
    file_put_contents($localeFile, $output);
}

function quoteStr($str)
{
    return str_replace("'", "\\'", $str);
}
