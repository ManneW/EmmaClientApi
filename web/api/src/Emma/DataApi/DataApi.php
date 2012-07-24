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