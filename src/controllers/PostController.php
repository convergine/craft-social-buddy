<?php

namespace convergine\socialbuddy\controllers;

use convergine\socialbuddy\queue\SubmitPost;
use Craft;
use craft\helpers\Queue;
use craft\web\Controller;

class PostController extends Controller {
    public function actionSubmit() {
        $request = Craft::$app->getRequest();

        $entryId = $request->getParam('elementId');
        $platform = $request->getParam('sb_platform');

        Queue::push(
            new SubmitPost([
                'entryId' => $entryId,
                'platform' => $platform,
            ]),10,0
        );

        Craft::$app->session->setSuccess(Craft::t('convergine-socialbuddy','Submitting to '.$platform));
    }
}
