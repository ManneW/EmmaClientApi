<?php

namespace Emma\DataApi;

use Emma;

class DataApi
{
    /**
     * Returns a list of the competitions
     *
     * @param \Silex\Application $app The current Silex app
     *
     * @return array|\Symfony\Component\HttpFoundation\JsonResponse The competitions
     */
    public function getCompetitions(\Silex\Application $app = null)
    {
        $comps = Emma::GetCompetitions();

        $compsNormalized = array_map(function($comp) {
            return array(
                'id'        => intval($comp['tavid']),
                'name'      => utf8_encode($comp['compName']),
                'organizer' => utf8_encode($comp['organizer']),
                'date'      => date("Y-m-d",strtotime($comp['compDate'])),
            );
        }, $comps);

        $finalComps = array('competitions' => $compsNormalized);

        if (null != $app) {
            return $app->json($finalComps);
        }

        return $finalComps;
    }


    /**
     * Fetches the latest passings registered
     *
     * @param \Silex\Application $app The current app
     * @param int $limit Number of passings to include
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse Json encoded response
     */
    public function getLastPassings(\Silex\Application $app, $limit = 5)
    {
        $req = static::getRequest($app);
        $comp = $req->get('comp');

        if (null == $comp) {
            $app->abort(400, 'No competition specified');
        }

        $currentComp = new Emma($comp);
        $lastPassings = $currentComp->getLastPassings($limit);

        $lastPassingsMapped = array_map(function($pass) use ($app) {
            return array(
                'passtime' => date("H:i:s",strtotime($pass['Changed'])),
                'runnerName' => $pass['Name'],
                'class' => $pass['class'],
                'control' => intval($pass['Control']),
                'controlName' => ($pass['pname'] ?: ''),
                'time' => DataApi::formatTime($pass['Time'], $pass['Status'], $app),
            );
        }, $lastPassings);

        $hash = md5($app->json($lastPassingsMapped));

        if (null != ($lastHash = $req->get('last_hash'))) {
            if ($lastHash == $hash) {
                return $app->json(array('status' => 'NOT MODIFIED'), 304);
            }
        }

        return $app->json(array(
            'status' => 'OK',
            'passings' => $lastPassingsMapped,
            'hash' => $hash
        ));
    }


    /**
     * Returns the classes for a competition
     *
     * The competition ID is given through a query parameter
     * which is available via the request object in the app.
     *
     * @param \Silex\Application $app The current app
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse Json encoded response
     */
    public function getClasses(\Silex\Application $app)
    {
        $req = static::getRequest($app);
        $comp = $req->get('comp');

        if (null == $comp) {
            $app->abort(400, 'No competition specified');
        }

        $currentComp = new Emma($comp);
        $classes = $currentComp->Classes();

        $classesMapped = array_map(function($class) {
            return array(
                'className' => $class['Class'],
            );
        }, $classes);

        $hash = md5($app->json($classesMapped));

        if (null != ($lastHash = $req->get('last_hash'))) {
            if ($lastHash == $hash) {
                return $app->json(array('status' => 'NOT MODIFIED'), 304);
            }
        }

        return $app->json(array(
            'status' => 'OK',
            'classes' => $classesMapped,
            'hash' => $hash
        ));
    }

    public function getClubResults(\Silex\Application $app)
    {
        $req = static::getRequest($app);
        $comp = $req->get('comp');

        if (null == $comp) {
            $app->abort(400, 'No competition specified');
        }

        $currentComp = new Emma($comp);
        $club = $req->get('club');
        //$club = utf8_decode(rawurldecode($req->get('club')));

        if(null == $club) {
            $app->abort(400, 'No club specified');
        }

        $results = $currentComp->getClubResults($comp, $club);

        $unformattedTimes = ($req->get('unformattedTimes') == 'true');


        $helper = new DataApiHelper();

        $resultsMapped = array();
        foreach ($results as $res) {
            $resultsMapped[] = $helper->convertClubResults($res, $app, $unformattedTimes);
        }

        $hash = md5($app->json($resultsMapped));

        if (null != ($lastHash = $req->get('last_hash'))) {
            if ($lastHash == $hash) {
                return $app->json(array('status' => 'NOT MODIFIED'), 304);
            }
        }

        return $app->json(array(
            'status' => 'OK',
            'clubName' => $club,
            'results' => $resultsMapped,
            'hash' => $hash
        ));
    }

    /**
     * Returns the class results for a class
     *
     * Extracts the competition and class ID:s, along
     * with various other options, from the request's
     * query parameters.
     *
     * @param \Silex\Application $app
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function getClassResults(\Silex\Application $app)
    {
        $req = static::getRequest($app);
        $comp = $req->get('comp');

        if (null == $comp) {
            $app->abort(400, 'No competition specified');
        }

        if (null == ($class = $req->get('class'))) {
            $app->abort(400, 'No class specified');
        }

        $currentComp = new Emma($comp);
        $results = $currentComp->getAllSplitsForClass($class);
        $splits = $currentComp->getSplitControlsForClass($class);

        $includeTotal = ($req->get('includetotal') == 'true');

        $total = null;
        $retTotal = false;
        if ($includeTotal) {
            $retTotal = true;
            $total = $currentComp->getTotalResultsForClass($class);

            foreach ($results as $key => $res) {
                $id = $res['DbId'];

                $results[$key]["totaltime"] = $total[$id]["Time"];
                $results[$key]["totalstatus"] = $total[$id]["Status"];
                $results[$key]["totalplace"] = $total[$id]["Place"];
                $results[$key]["totalplus"] = $total[$id]["TotalPlus"];
            }
        }


        $ret = array();
        $place = 1;
        $lastTime = -9999;
        $winnerTime = 0;

        $resultsAsArray = ($req->query->has('resultsAsArray'));

        $unformattedTimes = ($req->get('unformattedTimes') == 'true');

        $splitArray = array();
        foreach ($splits as $split)
        {
            $splitArray[] = array(
                'code' => intval($split['code']),
                'name' => $split['name'],
            );
        }

        $first = true;
        $fullRet = array();
        foreach ($results as $res) {
            $time = $res['Time'];

            if ($first) {
                $winnerTime = $time;
            }

            $cp = $place;
            $status = ($time == "") ? 9 : $res['Status'];

            if ($status == 9 || $status == 10) {
                $cp = "";
            } elseif ($status != 0 || $time < 0) {
                $cp = "-";
            } elseif ($time == $lastTime) {
                $cp = "=";
            }

            $timeplus = "";

            if ($time > 0 && $status == 0)
            {
                $timeplus = $time-$winnerTime;
            }

            $age = time()-strtotime($res['Changed']);
            $modified = $age < 120 ? 1:0;

            if (!$unformattedTimes) {
                $time = DataApi::formatTime($time, $status, $app);
                $timeplus = "+".DataApi::formatTime($timeplus, $status, $app);
            }

            $tot = array();
            if ($retTotal) {
                $tot = array(
                    'totalresult' => $res['totaltime'],
                    'totalstatus' => $res['totalstatus'],
                    'totalplace' => $res['totalplace'],
                    'totalplus' => $res['totalplus'],
                );
            }


            if($resultsAsArray) {
                $ret = array(
                    $cp,
                    $res['Name'],
                    $res['Club'],
                    $res['Time'],
                    $status,
                    ($time - $winnerTime),
                    $modified
                );
            } else {
                $ret = array(
                    'place' => strval($cp),
                    'name' => $res['Name'],
                    'club' => $res['Club'],
                    'result' => $time,
                    'status' => intval($status),
                    'timeplus' => $timeplus);

                $ret = array_merge($ret, $tot);

                if (count($splits) > 0) {
                    $splitPassArray = array();
                    foreach ($splits as $split) {
                        if (isset($res[$split['code']."_time"])) {
                            $splitPassArray[strval($split['code'])] = intval($res[$split['code']."_time"]);
                            $splitPassArray[$split['code']."_status"] = 0;

                            $spage = time() - strtotime($res[$split['code'].'_changed']);
                            if ($spage < 120) {
                                $modified = true;
                            }
                        } else {
                            $splitPassArray[strval($split['code'])] = '';
                            $splitPassArray[$split['code']."_status"] = 1;
                        }
                    }
                    $ret['splits'] = $splitPassArray;
                }

                if (isset($res["start"])) {
                    $ret['start'] = intval($res["start"]);
                } else {
                    $ret['start'] = '';
                }

                if ($modified) {
                    $ret["DT_RowClass"] = "new_result";
                }


            }

            $fullRet[] = $ret;

            $first = false;
            $place += 1;
            $lastTime = $time;
        }

        $ret = $fullRet;

        $hash = md5($app->json($ret));
        if ($req->get('last_hash') == $hash) {
            return $app->json(array("status" => "NOT MODIFIED"));
        }

        return $app->json(array(
            'status' => 'OK',
            'className' => $class,
            'splitcontrols' => $splitArray,
            'results' => $ret,
            'hash' => $hash,
        ));
    }



    /**
     * @static
     * @param \Silex\Application $app
     *
     * @return \Symfony\Component\HttpFoundation\Request
     */
    public static function getRequest(\Silex\Application $app)
    {
        return $app['request'];
    }


    public static function runnerStatus(\Silex\Application $app)
    {
        /**
         * @var $transl \Symfony\Component\Translation\Translator
         */
        $transl = $app['translator'];

        return array(
            "1" =>  $transl->trans('_statusdns'),
            "2" => $transl->trans('_statusdnf'),
            "11" => $transl->trans('_statusow'),
            "12" => $transl->trans('_statusmovedup'),
            "9" => $transl->trans('_statusnotstarted'),
            "0" => $transl->trans('_statusok'),
            "3" => $transl->trans('_statusmp'),
            "4" => $transl->trans('_statusdsq'),
            "5" => $transl->trans('_statusot'),
            "9" => "",
            "10" => ""
        );
    }

    public static function formatTime($time, $status, \Silex\Application $app)
    {
        $runnerStatus = static::runnerStatus($app);

        if ($status != "0") {
            return $runnerStatus[$status]; //$status;
        }

        $lang = $app['locale'];

        if ('fi' == $lang) {
            $hours = floor($time / 360000);
            $minutes = floor(($time - $hours * 360000) / 6000);
            $seconds = floor(($time - $hours * 360000 - $minutes * 6000) / 100);

            if ($hours > 0) {
                return $hours . ":" . str_pad("" . $minutes, 2, "0", STR_PAD_LEFT) . ":" . str_pad("" . $seconds, 2, "0", STR_PAD_LEFT);
            }

            return $minutes . ":" . str_pad("" . $seconds, 2, "0", STR_PAD_LEFT);
        }

        $minutes = floor($time / 6000);
        $seconds = floor(($time - $minutes * 6000) / 100);

        return str_pad("" . $minutes, 2, "0", STR_PAD_LEFT) . ":" . str_pad("" . $seconds, 2, "0", STR_PAD_LEFT);
    }
}