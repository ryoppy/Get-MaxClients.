<?php

//--------------------------------------------------------------------------------------
// Usage: php get_max_client.php
// root only.
// 
// This program is used to get MaxClient of apache.
// Following step.
// 1、get all pid of httpd.
// 2、get average Rss of "/proc/$pid/smaps".
// 3、get average Shared_Clean of "/proc/$pid/smaps".
// 4、get average Shared_Dirty of "/proc/$pid/smaps".
// 5、get memory size by free command.
// 6、math max client by "memorySize / (rssAverage - (Shared_Clean + Shared_Dirty))".
// 7、print max client.
//--------------------------------------------------------------------------------------


$GLOBALS['debug'] = false;


/**
 * Dump data.
 * If $GLOBALS['debug'] is true, dump.
 *
 * @param mixd $value
 * @param string $label
 */
function debugDump ($value, $label = null) {
    if ($GLOBALS['debug']) {
        if ($label) {
            $value = $label . ' : ' . $value;
        }
        var_dump($value);
    }
}

/**
 * command execute. error handling and logging.
 * if command is error, it will end. (exit(1))
 *
 * @param string $arg
 * @return string
 */
function executeCommand ($arg)
{
    $result = `$arg 2>&1`;

    if (substr(`echo $?`, 0, 1) === '0') {
        // success
        return rtrim($result);

    } else {
        // failure
        error_log(sprintf('command failure [%s][%s]', $arg, $result));
        echo 0;
        exit(1);
    }
}


/**
 * This method is run.
 * If you want to see debug data, see this page top.
 *
 */
function run ()
{
    $httpdPidArr = getHttpdPidArr();
    $rss = getRssAverage($httpdPidArr);
    $shr = getShrAverage($httpdPidArr);
    $memorySize = getMemorySize();
    $maxClient = mathMaxClient($shr, $rss, $memorySize);

    debugDump($rss, '$rss');
    debugDump($shr, '$shr');
    debugDump($memorySize, '$memorySize');
    debugDump($maxClient, '$maxClient');
    
    echo sprintf('--------------------------------') . PHP_EOL;
    echo sprintf('memorySize / (rssAverage - shrAverage) = %dKB / (%dKB - %dKB) = %d', $memorySize, $rss, $shr, $maxClient) . PHP_EOL;
    echo sprintf('MaxClient maximum value is %d.', $maxClient) . PHP_EOL;
    echo sprintf('--------------------------------') . PHP_EOL;
}

/**
 * Get httpd pid.
 *
 * @return array
 */
function getHttpdPidArr ()
{
    $httpdPids = executeCommand('pgrep httpd');
    if (empty($httpdPids)) {
        throw new Exception("Not found httpdPids. failure pgrep httpd.");
    }
    return  explode(PHP_EOL, $httpdPids);
}

/**
 * Get rss.
 * This method is browse /proc/$pid/smaps.
 *
 * @return int
 */
function getRss ($pid)
{
    $rss = executeCommand("cat /proc/$pid/smaps | grep Rss | awk '{rss += $2} END {print rss;};'");
    if (empty($rss)) {
        throw new Exception("Rss not found. /proc/$pid/smaps not found?.");
    }
    return (int) $rss;
}

/**
 * Get rss average. 
 *
 * @return int
 */
function getRssAverage ($httpdPidArr)
{
    foreach ($httpdPidArr as $httpdPid) {
        $rssArr[] = $debug = getRss($httpdPid);
    }

    return (int) round(array_sum($rssArr) / count($rssArr));
}

/**
 * Get rss.
 * This method is browse /proc/$pid/smaps.
 *
 * @return int
 */
function getShrClean ($pid)
{
    $shrClean = executeCommand("cat /proc/$pid/smaps | grep Shared_Clean | awk '{shrc += $2} END {print shrc;};'");
    if (empty($shrClean)) {
        throw new Exception("Shared_Clean not found. /proc/$pid/smaps not found?.");
    }
    return (int) $shrClean;
}

/**
 * Get rss.
 * This method is browse /proc/$pid/smaps.
 *
 * @return int
 */
function getShrDirty ($pid)
{
    $shrDirty = executeCommand("cat /proc/$pid/smaps | grep Shared_Dirty | awk '{shrd += $2} END {print shrd;};'");
    if (empty($shrDirty)) {
        throw new Exception("Shared_Dirty not found. /proc/$pid/smaps not found?.");
    }
    return (int) $shrDirty;
}

/**
 * Get shr average.
 * (Shared_Clean + Shared_Dirty) / count
 *
 * @return int
 */
function getShrAverage ($httpdPidArr)
{
    foreach ($httpdPidArr as $httpdPid) {
        $shrClean = getShrClean($httpdPid);
        $shrDirty = getShrDirty($httpdPid);

        debugDump("pid=$httpdPid : shrClean $shrClean");
        debugDump($shrDirty, '$shrDirty');

        $shrArr[] = $shrClean + $shrDirty;
    }

    return (int) round(array_sum($shrArr) / count($shrArr));
}

/**
 * Get memory size.
 * This method is execute free command.
 *
 * @return int
 */
function getMemorySize ()
{
    $result = executeCommand("free | grep Mem | awk '{print $2;};'");
    if (empty($result)) {
        throw new Exception('memory size not found. failure free command...');
    }
    return $result;
}

/**
 * Math MaxClient.
 *
 * @param int $shr
 * @param int $rss
 * @param int $memorySize
 * @return int MaxClient
 */
function mathMaxClient ($shr, $rss, $memorySize)
{
    return (int) round($memorySize / ($rss - $shr));
}


run();