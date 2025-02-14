<?php

namespace convergine\socialbuddy\controllers;

use convergine\socialbuddy\SocialBuddyPlugin;
use craft\web\Controller;
use yii\web\Response;
use Craft;

class DashboardController extends Controller {


    /**
     * @return Response
     */
    public function actionIndex(): Response {
        $platform = SocialBuddyPlugin::getInstance()->platform;

        $data = [];

        return $this->renderTemplate('convergine-socialbuddy/_layouts/_dashboard', [
            'statistics' => $data,
        ]);
    }
}
