<?php
namespace convergine\socialbuddy\controllers;

use convergine\socialbuddy\SocialBuddyPlugin;
use Craft;
use craft\web\Controller;
use yii\web\Response;

class MediumController extends Controller
{
    protected array|bool|int $allowAnonymous = false;

    public function actionConnect(): Response
    {
        $request = Craft::$app->getRequest();
        $accessToken = $request->getBodyParam('accessToken');

        if (!$accessToken) {
            Craft::$app->getSession()->setError('Medium access token is required');
            return $this->redirect('settings/plugins/craft-social-buddy');
        }

        // Validate the token by making a test API call
        try {
            $mediumService = SocialBuddyPlugin::getInstance()->medium;
            
            // Test the connection directly with the provided token
            $userInfo = $mediumService->makeApiRequest('GET', '/me', [], $accessToken);
            
            if (!isset($userInfo['data']['id'])) {
                throw new \Exception('Invalid Medium access token - no user data found');
            }

            // Get current settings
            $settings = SocialBuddyPlugin::getInstance()->getSettings();
            
            // Save the token and connection info
            $settings->mediumIntegrationToken = $accessToken;
            $settings->mediumConnected = 1;
            $settings->mediumAccountName = $userInfo['data']['username'] ?? 'Unknown';
            
            $success = Craft::$app->plugins->savePluginSettings(
                SocialBuddyPlugin::getInstance(), 
                $settings->toArray()
            );

            if ($success) {
                Craft::$app->getSession()->setNotice('Medium connected successfully');
            } else {
                throw new \Exception('Failed to save Medium settings');
            }

        } catch (\Exception $e) {
            Craft::error('Medium connection failed: ' . $e->getMessage(), __METHOD__);
            Craft::$app->getSession()->setError('Failed to connect to Medium: ' . $e->getMessage());
        }

        return $this->redirect('settings/plugins/craft-social-buddy');
    }

    public function actionDisconnect(): Response
    {
        try {
            $mediumService = SocialBuddyPlugin::getInstance()->medium;
            
            if ($mediumService->disconnect()) {
                Craft::$app->getSession()->setNotice('Medium disconnected successfully');
            } else {
                throw new \Exception('Failed to disconnect Medium');
            }

        } catch (\Exception $e) {
            Craft::error('Medium disconnect failed: ' . $e->getMessage(), __METHOD__);
            Craft::$app->getSession()->setError('Failed to disconnect Medium: ' . $e->getMessage());
        }

        return $this->redirect('settings/plugins/craft-social-buddy');
    }

    public function actionStatus(): Response
    {
        $this->requireAcceptsJson();
        
        try {
            $mediumService = SocialBuddyPlugin::getInstance()->medium;
            $status = $mediumService->getConnectionStatus();
            
            return $this->asJson([
                'success' => true,
                'status' => $status
            ]);

        } catch (\Exception $e) {
            return $this->asJson([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
}