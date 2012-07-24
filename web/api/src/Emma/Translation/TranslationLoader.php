<?php

namespace Emma\Translation;

class TranslationLoader
{
    public function translationMessages($lang = 'sv')
    {
        $file = __DIR__.'/../../../emmalang_'.$lang.'.php';

        if (!file_exists($file)) {
            throw new \Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException($file);
        }

        $translationsLang = require_once($file);

        return array($lang => $translationsLang);
    }
}