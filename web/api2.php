<?php

require_once __DIR__.'/api/vendor/autoload.php';

use Symfony\Component\HttpFoundation\Request;
use Emma\DataApi\DataApi;
use Emma\Translation\TranslationLoader;

$app = new Silex\Application();
$app['debug'] = true;

$app->register(new Silex\Provider\TranslationServiceProvider(), array(
    'locale_fallback' => 'en',
));

$app['locale'] = 'sv';

$translationLoader = new TranslationLoader();
$app['translator.domains'] = array(
    'messages' => array_merge($translationLoader->translationMessages('sv'), $translationLoader->translationMessages('en'), $translationLoader->translationMessages('fi'))
);

$emmaApi = new DataApi();

//Setup translations

$app->get('/getcompetitions', function(Silex\Application $app) use ($emmaApi) {
    return $app->json($emmaApi->getCompetitions());
});

$app->get('/getlastpassings', function(Silex\Application $app) use ($emmaApi) {
    return $emmaApi->getLastPassings($app);
});

$app->get('/getclasses', function(Silex\Application $app) use ($emmaApi) {
    return $emmaApi->getClasses($app);
});


Request::trustProxyData();
$app->run();