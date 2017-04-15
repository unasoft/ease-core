<?php

namespace core\i18n\events;


use yii\i18n\MissingTranslationEvent;

class TranslationEventHandler
{
    /**
     * @param MissingTranslationEvent $event
     */
    public static function handleMissingTranslation(MissingTranslationEvent $event)
    {
        if (YII_DEBUG) {
            $event->translatedMessage = "@MISSING: {$event->category}.{$event->message} FOR LANGUAGE {$event->language} @";
        }
    }
}