<?php

namespace convergine\socialbuddy\controllers;

use convergine\socialbuddy\SocialBuddyPlugin;
use craft\web\Controller;
use yii\web\Response;
use Craft;

class SettingsController extends Controller {


    // Allow anonymous access to disconnect action
    protected array|int|bool $allowAnonymous = ['disconnect'];

    public $enableCsrfValidation = false; // Add this line to disable CSRF for all actions
    
    // disable CSRF only for specific actions:
    public function beforeAction($action): bool
    {
        if ($action->id === 'disconnect') {
            $this->enableCsrfValidation = false;
        }
        
        return parent::beforeAction($action);
    }

    /**
     * Disconnect a social media platform
     *
     * @return Response
     */
    public function actionDisconnect(): Response
    {
        // Get the platform parameter from the request
        $platform = Craft::$app->getRequest()->getParam('platform');
        
        if (!$platform) {
            return $this->asJson([
                'success' => false,
                'error' => 'Platform parameter is required'
            ]);
        }

        // Get the plugin instance and settings
        $plugin = SocialBuddyPlugin::getInstance();
        $settings = $plugin->getSettings();

        // Clear platform-specific settings based on the platform
        switch ($platform) {
            case 'facebook':
                $settings->facebookConnected = 0;
                $settings->facebookAccountImageURL = '';
                $settings->facebookAccountName = '';
                $settings->facebookAccessToken = '';
                $settings->facebookPageId = '';
                break;
            
            case 'pinterest':
                $settings->pinterestConnected = 0;
                $settings->pinterestAccountImageURL = '';
                $settings->pinterestAccountName = '';
                $settings->pinterestAccessToken = '';
                $settings->pinterestPageId = '';
                $settings->pinterestDefaultBoard = '';
                break;
            
            case 'instagram':
                $settings->instagramConnected = 0;
                $settings->instagramAccountImageURL = '';
                $settings->instagramAccountName = '';
                $settings->instagramAccessToken = '';
                $settings->instagramPageId = '';
                break;
            
            case 'twitter':
                $settings->twitterConnected = 0;
                $settings->twitterAccountImageURL = '';
                $settings->twitterAccountName = '';
                $settings->twitterAccessToken = '';
                $settings->twitterPageId = '';
                break;
            
            case 'linkedin':
                $settings->linkedinConnected = 0;
                $settings->linkedinAccountImageURL = '';
                $settings->linkedinAccountName = '';
                $settings->linkedinAccessToken = '';
                $settings->linkedinPageId = '';
                break;
            
            case 'telegram':
                $settings->telegramChannelAccount = '';
                $settings->telegramBotToken = '';
                break;
            
            case 'medium':
                $settings->mediumConnected = 0;
                $settings->mediumAccessToken = '';
                break;
            
            default:
                return $this->asJson([
                    'success' => false,
                    'error' => 'Unknown platform: ' . $platform
                ]);
        }

        // Save the updated settings
        $saved = Craft::$app->getPlugins()->savePluginSettings($plugin, $settings->toArray());

        if ($saved) {
            Craft::info("SocialBuddy: Disconnected {$platform} successfully", __METHOD__);
            
            // Return success response
            return $this->asJson([
                'success' => true,
                'connected' => false,
                'platform' => $platform,
                'message' => ucfirst($platform) . ' disconnected successfully'
            ]);
        } else {
            Craft::error("SocialBuddy: Failed to save settings after disconnecting {$platform}", __METHOD__);
            
            return $this->asJson([
                'success' => false,
                'error' => 'Failed to save settings'
            ]);
        }
    }


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

        // Check if cron job is properly configured
        $cronLastRun = Craft::$app->getCache()->get('socialbuddy_cron_last_run');
        $cronConfigured = false;
        
        if ($cronLastRun !== false) {
            // Check if cron job ran within the last 2 hours (7200 seconds)
            $cronConfigured = (time() - $cronLastRun) <= 7200;
        }
        
        return $this->renderTemplate('convergine-socialbuddy/settings/_general', [
            'settings' => $settings,
            'cronConfigured' => $cronConfigured,
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
            'siteUrl' => $siteUrl,
            'csrfToken' => Craft::$app->getRequest()->getCsrfToken()
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
        $siteUrl = Craft::$app->getSites()->getCurrentSite()->getBaseUrl();

        return $this->renderTemplate('convergine-socialbuddy/settings/_telegram', [
            'settings' => $settings,
            'siteUrl' => $siteUrl
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
