<?php

require_once __DIR__.'/vendor/autoload.php';

use Symfony\Component\HttpFoundation\Request;
use Emma\DataApi\DataApi;
use Emma\Translation\TranslationLoader;

$app = new Silex\Application();
$app['debug'] = true;

//Setup translations
$app->register(new Silex\Provider\TranslationServiceProvider(), array(
    'locale_fallback' => 'en',
));

$app['locale'] = 'sv';

$translationLoader = new TranslationLoader();
$app['translator.domains'] = array(
    'messages' => array_merge($translationLoader->translationMessages('sv'), $translationLoader->translationMessages('en'), $translationLoader->translationMessages('fi'))
);

//Setup routing
$app->register(new Silex\Provider\UrlGeneratorServiceProvider());

$emmaApi = new DataApi();

$app->get('/competitions', function(Silex\Application $app) use ($emmaApi) {
    return $app->json($emmaApi->getCompetitions());
})->bind('competitions');

$app->get('/lastpassings', function(Silex\Application $app) use ($emmaApi) {
    return $emmaApi->getLastPassings($app);
})->bind('lastpassings');

$app->get('/getclasses', function(Silex\Application $app) use ($emmaApi) {
    return $emmaApi->getClasses($app);
});

$app->get('/getclubresults', function(\Silex\Application $app) use ($emmaApi) {
    return $emmaApi->getClubResults($app);
});

$app->get('/getclassresults', function(\Silex\Application $app) use ($emmaApi) {
    return $emmaApi->getClassResults($app);
});

//Fallback route for legacy query usage
$app->get('', function(\Silex\Application $app) {
    /**
     * @var $request \Symfony\Component\HttpFoundation\Request
     */
    $request = $app['request'];

    if (null == ($method = $request->get('method'))) {
        $app->abort(400, 'No method specified');
    }

    switch ($method) {
        case 'getcompetitions':
            return $app->redirect($app['url_generator']->generate('competitions'));
        case 'getlastpassings':
            return $app->redirect($app['url_generator']->generate('lastpassings'));
    }

    $app->abort(400);
});


Request::trustProxyData();
$app->run();