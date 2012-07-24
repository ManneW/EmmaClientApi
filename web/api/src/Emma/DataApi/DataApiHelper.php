<?php

namespace Emma\DataApi;

use Emma;

class DataApiHelper
{
    /**
     * @var \Silex\Application
     */
    private $app;

    public function __construct(\Silex\Application $app)
    {
        $this->app = $app;
    }

    public function convertClubResults($res, \Silex\Application $app, $unformattedTimes = false)
    {
        $time = $res['Time'];
        $status = ("" == $time) ? 9 : $res['Status'];

        if ($status == 9 || $status == 10) {
            $cp = "";
        } elseif ($status != 0 || $time < 0) {
            $cp = "-";
        } else {
            $cp = $res['Place'];
        }

        $timeplus = $res['TimePlus'];

        $age = time() - strtotime($res['Changed']);
        $modified = $age < 120 ? 1 : 0;

        if (!$unformattedTimes) {
            $time = DataApi::formatTime($res['Time'], $res['Status'], $app);
            $timeplus = "+" . DataApi::formatTime($timeplus, $res['Status'], $app);
        }

        $start = isset($res['start']) ? $res['start'] : '';

        $newRes = array(
            'place' => $cp,
            'name' => $res['Name'],
            'club' => $res['Club'],
            'class' => $res['Class'],
            'result' => $time,
            'status' => intval($status),
            'timeplus' => $timeplus,
            'start' => intval($start),
        );

        if ($modified) {
            $newRes['DT_RowClass'] = "new_result";
        }

        return $newRes;
    }

    public function addTotalResultsToResultsForClass(Emma $currentComp, $class)
    {
        $results = $currentComp->getAllSplitsForClass($class);
        $total = $currentComp->getTotalResultsForClass($class);

        foreach ($results as $key => $res)
        {
            $id = $res['DbId'];

            $results[$key]["totaltime"] = $total[$id]["Time"];
            $results[$key]["totalstatus"] = $total[$id]["Status"];
            $results[$key]["totalplace"] = $total[$id]["Place"];
            $results[$key]["totalplus"] = $total[$id]["TotalPlus"];
        }

        return $results;
    }

    public function getClassResultsAsArray(Emma $comp, $class, $unformattedTimes)
    {
        $results = $comp->getAllSplitsForClass($class);

        $mappedResults = array();

        $time = $res['Time'];

        foreach ($results as $res) {
            if (!$unformattedTimes) {
                $time = DataApi::formatTime($time, $status, $this->app);
                $timeplus = "+".DataApi::formatTime($timeplus, $status, $this->app);
            }


            $mappedResults[] = array(
                $cp,
                $res['Name'],
                $res['Club'],
                $res['Time'],
                $status,
                ($time-$winnerTime),
                $modified
            );
        }
    }

    public function convertClassResults($results, $splits, \Silex\Application $app, $retTotal, $resultsAsArray, $unformattedTimes = false)
    {
        $lastTime = -9999;
        $place = 1;
        $convertedResults = array();

        $first = true;
        $winnerTime = null;
        foreach ($results as $res) {
            $time = $res['Time'];

            if ($first) {
                $winnerTime = $time;
            }

            $status = $res['Status'];
            $cp = $place;

            $status = ($time == "") ? 9 : $res['Status'];

            if ($status == 9 || $status == 10) {
                $cp = "";
            } elseif ($status != 0 || $time < 0) {
                $cp = "-";
            }

            if ($first) {
                $first = false;
            }

            $timeplus = "";

            if ($time > 0 && $status == 0) {
                $timeplus = $time - $winnerTime;
            }

            $age = time()-strtotime($res['Changed']);
            $modified = ($age < 120) ? 1 : 0;

            if (!$unformattedTimes) {
                $time = DataApi::formatTime($time, $status, $app);
                $timeplus = "+".DataApi::formatTime($timeplus, $status, $app);
            }

            if ($resultsAsArray) {
                $mappedRes = array(
                    $cp,
                    $res['Name'],
                    $res['Club'],
                    $res['Time'],
                    $status,
                    ($time-$winnerTime),
                    $modified
                );
            } else {
                $mappedRes = array(
                    'place' => $cp,
                    'name' => $res['Name'],
                    'club' => $res['Club'],
                    'result' => $time,
                    'status' => $status,
                    'timeplus' => $timeplus,
                );

                if ($retTotal) {
                    $mappedRes = array_merge($mappedRes, array(
                        'totalresult' => $res['totaltime'],
                        'totalstatus' => $res['totalstatus'],
                        'totalplace' => $res['totalplace'],
                        'totalplus' => $res['totalplus'],
                    ));
                }

                if (count($splits) > 0) {
                    $mappedSplits = array();
                    foreach ($splits as $split) {
                        if (isset($res[$split['code'].'_time'])) {
                            $resSplit = $res[$split['code'].'_time'];
                            $mappedSplits[] = array(
                                $split['code'] => $resSplit,
                                $split['code'].'_status' => 0,
                            );

                            $spage = time()-strtotime($res[$split['code'].'_changed']);
                            if ($spage < 120) {
                                $modified = true;
                            }
                        } else {
                            $mappedSplits[] = array(
                                $split['code'] => '',
                                $split['code'].'_status' => 1,
                            );
                        }
                    }

                    $mappedRes['splits'] = $mappedSplits;
                }
            }

            $place += 1;
        }

        return $convertedResults;
    }

    public function convertClassResult($res, \Silex\Application $app, $unformattedTimes = false, $lastTime = -9999)
    {
        if($resultsAsArray)
        {
            $ret .= "[\"$cp\", \"".$res['Name']."\", \"".$res['Club']."\", ".$res['Time'].", ".$status.", ".($time-$winnerTime).",$modified]";
        }
        else
        {
            $ret .= "{\"place\": \"$cp\", \"name\": \"".$res['Name']."\", \"club\": \"".$res['Club']."\", \"result\": \"".$time."\",\"status\" : ".$status.", \"timeplus\": \"$timeplus\" $tot";

            if (count($splits) > 0)
            {
                $ret .= ", \"splits\": {";
                $firstspl = true;
                foreach ($splits as $split)
                {
                    if (!$firstspl)
                        $ret .=",";
                    if (isset($res[$split['code']."_time"]))
                    {
                        $ret .= "\"".$split['code']."\": ".$res[$split['code']."_time"] .",\"".$split['code']."_status\": 0";
                        $spage = time()-strtotime($res[$split['code'].'_changed']);
                        if ($spage < 120)
                            $modified = true;
                    }
                    else
                    {
                        $ret .= "\"".$split['code']."\": \"\",\"".$split['code']."_status\": 1";
                    }

                    $firstspl = false;
                }

                $ret .="}";
            }

            if (isset($res["start"]))
            {
                $ret .= ", \"start\": ".$res["start"];
            }
            else
            {
                $ret .= ", \"start\": \"\"";
            }

            if ($modified)
            {
                $ret .= ", \"DT_RowClass\": \"new_result\"";
            }

            $ret .= "}";
        }
        $first = false;
        $place++;
        $lastTime = $time;
    }
}