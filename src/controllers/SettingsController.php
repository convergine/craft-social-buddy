<?php

namespace convergine\socialbuddy\controllers;

use convergine\socialbuddy\SocialBuddyPlugin;
use craft\web\Controller;
use yii\web\Response;

class SettingsController extends Controller {
    /**
     * @return Response
     */
    public function actionGeneral(): Response {
        $settings = SocialBuddyPlugin::getInstance()->getSettings();
        return $this->renderTemplate('convergine-socialbuddy/settings/_general', [
            'settings' => $settings,
        ]);
    }

    /**
     * @return Response
     */
    public function actionPinterest(): Response {
        $settings = SocialBuddyPlugin::getInstance()->getSettings();
        return $this->renderTemplate('convergine-socialbuddy/settings/_pinterest', [
            'settings' => $settings,
        ]);
    }

    /**
     * @return Response
     */
    public function actionFacebook(): Response {
        $settings = SocialBuddyPlugin::getInstance()->getSettings();
        return $this->renderTemplate('convergine-socialbuddy/settings/_facebook', [
            'settings' => $settings,
        ]);
    }

    /**
     * @return Response
     */
    public function actionTelegram(): Response {
        $settings = SocialBuddyPlugin::getInstance()->getSettings();
        return $this->renderTemplate('convergine-socialbuddy/settings/_telegram', [
            'settings' => $settings,
        ]);
    }

    /**
     * @return Response
     */
    public function actionFields(): Response {
        $settings = SocialBuddyPlugin::getInstance()->getSettings();
        $entryTypes = SocialBuddyPlugin::getInstance()->post->getAllEntryTypes();
        return $this->renderTemplate('convergine-socialbuddy/settings/_fields', [
            'settings' => $settings,
            'entryTypes' => $entryTypes,
        ]);
    }
}
