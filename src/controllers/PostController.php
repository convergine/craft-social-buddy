<?php

namespace convergine\socialbuddy\controllers;

use convergine\socialbuddy\queue\SubmitPost;
use Craft;
use craft\helpers\Queue;
use craft\helpers\Json;
use craft\web\Controller;

class PostController extends Controller {
    public function actionSubmit() {
        $request = Craft::$app->getRequest();

        $entryId = $request->getParam('elementId');
        $platform = $request->getParam('sb_platform');

        // Get the entry to check its published status
        $entry = Craft::$app->entries->getEntryById($entryId);
        if (!$entry) {
            Craft::$app->session->setError(Craft::t('convergine-socialbuddy', 'Entry not found'));
            return;
        }

        // Check if entry is already published (except for 'all' platform)
        if ($platform == 'all' && $this->isAlreadyPublished($entry)) {
            Craft::$app->session->setNotice(Craft::t('convergine-socialbuddy', 'Entry "{title}" has already been published to social media', [
                'title' => $entry->title
            ]));
            return;
        }

        Queue::push(
            new SubmitPost([
                'entryId' => $entryId,
                'platform' => $platform,
            ]),10,0
        );

        Craft::$app->session->setSuccess(Craft::t('convergine-socialbuddy','Post queued for publishing to '.$platform));
    }

    /**
     * Check if the entry has already been published to social media
     */
    private function isAlreadyPublished($entry): bool {
        try {
            foreach($entry->getFieldLayout()->getCustomFields() as $field) {
                if($field instanceof \convergine\socialbuddy\fields\SocialBuddyField) {
                    $fieldValue = $entry->{$field->handle};
                    
                    // Decode field value if it's a string (Craft < 5.0)
                    if(is_string($fieldValue)) {
                        $fieldValue = Json::decode($fieldValue);
                    }
                    
                    // Check if already published
                    $isPublished = isset($fieldValue['published']) && $fieldValue['published'] === true;
                    
                    if ($isPublished) {
                        Craft::info("SocialBuddy: Entry ID {$entry->id} already marked as published", __METHOD__);
                        return true;
                    }
                    
                    break; // Only check the first SocialBuddyField found
                }
            }
        } catch (\Exception $e) {
            Craft::error("SocialBuddy: Error checking published status for entry ID: {$entry->id} - {$e->getMessage()}", __METHOD__);
            // In case of error, assume not published to be safe
            return false;
        }
        
        return false;
    }
}
