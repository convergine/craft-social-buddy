<?php

namespace convergine\socialbuddy\controllers;

use convergine\socialbuddy\SocialBuddyPlugin;
use craft\web\Controller;
use yii\web\Response;
use Craft;

class SettingsController extends Controller {


    /**
     * @return Response
     */
    public function actionDashboard(): Response {
        $platform = SocialBuddyPlugin::getInstance()->platform;

        return $this->renderTemplate('convergine-socialbuddy/_dashboard', [
            'settings' => $settings,
        ]);
    }


    /**
     * @return Response
     */
    public function actionGeneral(): Response {
        $settings = SocialBuddyPlugin::getInstance()->getSettings();
        $apiKey = $settings->apiKey;
        $platform = SocialBuddyPlugin::getInstance()->platform;

        // check if $apiKey is empty or 0-length string 
        if(empty($apiKey)) {
            $result = $platform->createApiKey();
            // reget settings
            $settings = SocialBuddyPlugin::getInstance()->getSettings();
            Craft::info("SocialBuddy createApiKey Result: " . json_encode($result), __METHOD__);        
        } else {
            // call platform->getLicense() to get current license level and change settings->license
            $license = $platform->getLicense();
            $settings->license = $license;
        }
        
        return $this->renderTemplate('convergine-socialbuddy/settings/_general', [
            'settings' => $settings,
        ]);
    }

    /**
     * @return Response
     */
    public function actionPinterest(): Response {
        $settings = SocialBuddyPlugin::getInstance()->getSettings();
        $platform = SocialBuddyPlugin::getInstance()->platform;
        $license = $platform->getLicense();
        $settings->license = $license;
        $siteUrl = Craft::$app->getSites()->getCurrentSite()->getBaseUrl();

        /*
        if($settings->pinterestConnected == 0) {
            // call Pinterest->getConnection() to get accountName and change settings->license
            $conn = SocialBuddyPlugin::getInstance()->pinterest->getConnection();
            if (isset($conn['accountName'])) {
                $settings->pinterestAccountName = $conn['accountName'];
                $settings->pinterestAccountImageURL = $conn['accountImageURL'];
                $settings->pinterestConnected = 1;
            }
        }
        */

        return $this->renderTemplate('convergine-socialbuddy/settings/_pinterest', [
            'settings' => $settings,
            'siteUrl' => $siteUrl
        ]);
    }

    /**
     * @return Response
     */
    public function actionFacebook(): Response {
        $settings = SocialBuddyPlugin::getInstance()->getSettings();
        $platform = SocialBuddyPlugin::getInstance()->platform;
        $license = $platform->getLicense();
        $settings->license = $license;
        $siteUrl = Craft::$app->getSites()->getCurrentSite()->getBaseUrl();

        return $this->renderTemplate('convergine-socialbuddy/settings/_facebook', [
            'settings' => $settings,
            'siteUrl' => $siteUrl
        ]);
    }

    /**
     * @return Response
     */
    public function actionInstagram(): Response {
        $settings = SocialBuddyPlugin::getInstance()->getSettings();
        $platform = SocialBuddyPlugin::getInstance()->platform;
        $license = $platform->getLicense();
        $settings->license = $license;
        $siteUrl = Craft::$app->getSites()->getCurrentSite()->getBaseUrl();

        return $this->renderTemplate('convergine-socialbuddy/settings/_instagram', [
            'settings' => $settings,
            'siteUrl' => $siteUrl
        ]);
    }

    /**
     * @return Response
     */
    public function actionTwitter(): Response {
        $settings = SocialBuddyPlugin::getInstance()->getSettings();
        $platform = SocialBuddyPlugin::getInstance()->platform;
        $license = $platform->getLicense();
        $settings->license = $license;
        $siteUrl = Craft::$app->getSites()->getCurrentSite()->getBaseUrl();

        return $this->renderTemplate('convergine-socialbuddy/settings/_twitter', [
            'settings' => $settings,
            'siteUrl' => $siteUrl
        ]);
    }


    /**
     * @return Response
     */
    public function actionLinkedin(): Response {
        $settings = SocialBuddyPlugin::getInstance()->getSettings();
        $platform = SocialBuddyPlugin::getInstance()->platform;
        $license = $platform->getLicense();
        $settings->license = $license;
        $siteUrl = Craft::$app->getSites()->getCurrentSite()->getBaseUrl();

        return $this->renderTemplate('convergine-socialbuddy/settings/_linkedin', [
            'settings' => $settings,
            'siteUrl' => $siteUrl
        ]);
    }

    /**
     * @return Response
     */
    public function actionTelegram(): Response {
        $settings = SocialBuddyPlugin::getInstance()->getSettings();
        $platform = SocialBuddyPlugin::getInstance()->platform;
        $license = $platform->getLicense();
        $settings->license = $license;

        return $this->renderTemplate('convergine-socialbuddy/settings/_telegram', [
            'settings' => $settings,
        ]);
    }

    /**
     * @return Response
     */
    public function actionMedium(): Response {
        $settings = SocialBuddyPlugin::getInstance()->getSettings();
        $platform = SocialBuddyPlugin::getInstance()->platform;
        $license = $platform->getLicense();
        $settings->license = $license;

        return $this->renderTemplate('convergine-socialbuddy/settings/_medium', [
            'settings' => $settings,
        ]);
    }


    /**
     * @return Response
     */
    public function actionFields(): Response {
        $settings = SocialBuddyPlugin::getInstance()->getSettings();
        $platform = SocialBuddyPlugin::getInstance()->platform;
        $license = $platform->getLicense();
        $settings->license = $license;

        $entryTypes = SocialBuddyPlugin::getInstance()->post->getAllEntryTypes();
        return $this->renderTemplate('convergine-socialbuddy/settings/_fields', [
            'settings' => $settings,
            'entryTypes' => $entryTypes,
        ]);
    }


    /**
     * Disconnect from Facebook
     *
     * @return Response
     */
    public function actionDisconnectFacebook(): Response {
        $this->requirePostRequest(); // Ensure it's a POST request for safety
        $settings = SocialBuddyPlugin::getInstance()->getSettings();
        
        // Perform disconnection logic here, e.g., clearing Facebook settings
        $settings->facebookAccountName = null;
        $settings->facebookAccountImageURL = null;
        $settings->facebookConnected = 0;

        // Save the updated settings
        Craft::$app->getPlugins()->savePluginSettings(SocialBuddyPlugin::getInstance(), $settings->toArray());

        return $this->asJson(['success' => true]);
    }

    /**
     * Disconnect from Pinterest
     *
     * @return Response
     */
    public function actionDisconnectPinterest(): Response {
        $this->requirePostRequest(); // Ensure it's a POST request for safety
        $settings = SocialBuddyPlugin::getInstance()->getSettings();
        
        // Perform disconnection logic here, e.g., clearing Facebook settings
        $settings->pinterestAccountName = null;
        $settings->pinterestAccountImageURL = null;
        $settings->pinterestConnected = 0;

        // Save the updated settings
        Craft::$app->getPlugins()->savePluginSettings(SocialBuddyPlugin::getInstance(), $settings->toArray());

        return $this->asJson(['success' => true]);
    }


}
