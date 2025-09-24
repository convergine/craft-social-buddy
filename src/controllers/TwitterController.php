<?php

namespace convergine\socialbuddy\controllers;

use convergine\socialbuddy\SocialBuddyPlugin;
use craft\web\Controller;
use yii\web\Response;
use Craft;

class TwitterController extends Controller
{
    // Allow anonymous access to the OAuth actions
    protected array|int|bool $allowAnonymous = ['twitter-auth', 'twitter-conn', 'twitter-callback'];

    // Session key to store temporary OAuth data
    private const OAUTH_SESSION_KEY = 'twitter_oauth_data';

    public function beforeAction($action): bool
    {
        // Disable CSRF validation for OAuth actions
        $this->enableCsrfValidation = false;
        return parent::beforeAction($action);
    }

    /**
     * Initiate Twitter OAuth flow by getting request token and redirecting to authorization URL
     */
    public function actionTwitterAuth(): Response
    {
        try {
            $plugin = SocialBuddyPlugin::getInstance();
            $settings = $plugin->getSettings();

            Craft::info('Twitter OAuth: Starting authentication flow', __METHOD__);

            // Check if client credentials are configured
            if (empty($settings->twitterClientId) || empty($settings->twitterClientSecret)) {
                throw new \Exception('Twitter Client ID and Client Secret must be configured in settings.');
            }

            Craft::info('Twitter OAuth: Client credentials configured', __METHOD__);

            // Get request token from Twitter service
            $twitterService = $plugin->twitter;
            $authData = $twitterService->getRequestToken();

            Craft::info('Twitter OAuth: Received auth data: ' . json_encode($authData), __METHOD__);

            if (empty($authData['oauth_token']) || empty($authData['oauth_token_secret'])) {
                throw new \Exception('Failed to obtain request token from Twitter.');
            }

            // Store OAuth data in session
            Craft::$app->getSession()->set(self::OAUTH_SESSION_KEY, [
                'oauth_token' => $authData['oauth_token'],
                'oauth_token_secret' => $authData['oauth_token_secret']
            ]);

            Craft::info('Twitter OAuth: Stored data in session', __METHOD__);

            // Get authorization URL
            $authUrl = $twitterService->getAuthorizationUrl($authData['oauth_token']);

            Craft::info('Twitter OAuth: Authorization URL: ' . $authUrl, __METHOD__);

            // Redirect to Twitter for authorization
            return $this->redirect($authUrl);

        } catch (\Exception $e) {
            Craft::error('Error during Twitter OAuth initiation: ' . $e->getMessage(), __METHOD__);
            
            return $this->renderTemplate('convergine-socialbuddy/auth-error', [
                'error' => $e->getMessage(),
                'platform' => 'Twitter'
            ]);
        }
    }

    /**
     * Handle Twitter OAuth callback
     */
    public function actionTwitterCallback(): Response
    {
        $request = Craft::$app->getRequest();
        $oauthToken = $request->getQueryParam('oauth_token');
        $oauthVerifier = $request->getQueryParam('oauth_verifier');
        $denied = $request->getQueryParam('denied');

        try {
            // Check if user denied authorization
            if ($denied) {
                throw new \Exception('Twitter authorization was denied by user.');
            }

            // Validate callback parameters
            if (empty($oauthToken) || empty($oauthVerifier)) {
                throw new \Exception('Invalid callback parameters from Twitter.');
            }

            // Retrieve stored OAuth data from session
            $sessionData = Craft::$app->getSession()->get(self::OAUTH_SESSION_KEY);
            if (empty($sessionData) || $sessionData['oauth_token'] !== $oauthToken) {
                throw new \Exception('OAuth token mismatch or session expired.');
            }

            // Exchange for access token
            $plugin = SocialBuddyPlugin::getInstance();
            $twitterService = $plugin->twitter;
            
            $accessTokenData = $twitterService->getAccessToken(
                $oauthVerifier,
                $sessionData['oauth_token'],
                $sessionData['oauth_token_secret']
            );

            if (empty($accessTokenData['oauth_token']) || empty($accessTokenData['oauth_token_secret'])) {
                throw new \Exception('Failed to obtain access token from Twitter.');
            }

            // Save access token to settings
            $settings = $plugin->getSettings();
            $settings->twitterAccessToken = $accessTokenData['oauth_token'];
            $settings->twitterAccessTokenSecret = $accessTokenData['oauth_token_secret'];
            $settings->twitterConnected = 1;

            // Get user profile information
            try {
                $userProfile = $twitterService->getUserProfile(
                    $accessTokenData['oauth_token'],
                    $accessTokenData['oauth_token_secret']
                );
                
                if (!empty($userProfile['name'])) {
                    $settings->twitterAccountName = $userProfile['name'];
                }
                
                if (!empty($userProfile['profile_image_url'])) {
                    $settings->twitterAccountImageURL = $userProfile['profile_image_url'];
                }
            } catch (\Exception $e) {
                Craft::warning('Failed to get Twitter user profile: ' . $e->getMessage(), __METHOD__);
            }

            // Save settings
            Craft::$app->getPlugins()->savePluginSettings($plugin, $settings->toArray());

            // Clear session data
            Craft::$app->getSession()->remove(self::OAUTH_SESSION_KEY);

            // Success response
            return $this->renderTemplate('convergine-socialbuddy/_layouts/_fb_success', [
                'platform' => 'Twitter',
                'accountName' => $settings->twitterAccountName ?: 'Twitter Account',
                'message' => 'Twitter account successfully connected!',
                'socialMediaIcon' => 'twitter.svg',
                'socialMediaName' => 'Twitter',
                'linkBack' => '/admin/settings/plugins/convergine-socialbuddy'
            ]);

        } catch (\Exception $e) {
            Craft::error('Error during Twitter OAuth callback: ' . $e->getMessage(), __METHOD__);
            
            // Clear session data on error
            Craft::$app->getSession()->remove(self::OAUTH_SESSION_KEY);
            
            return $this->renderTemplate('convergine-socialbuddy/auth-error', [
                'error' => $e->getMessage(),
                'platform' => 'Twitter'
            ]);
        }
    }

    /**
     * Returns the connected state of Twitter
     */
    public function actionTwitterConn(): Response
    {
        $plugin = SocialBuddyPlugin::getInstance();
        $settings = $plugin->getSettings();

        return $this->asJson([
            'twitterConnected' => $settings->twitterConnected,
            'accountName' => $settings->twitterAccountName,
            'accountImage' => $settings->twitterAccountImageURL
        ]);
    }

    /**
     * Disconnect Twitter account
     */
    public function actionTwitterDisconnect(): Response
    {
        try {
            $plugin = SocialBuddyPlugin::getInstance();
            $settings = $plugin->getSettings();

            // Clear Twitter settings
            $settings->twitterConnected = 0;
            $settings->twitterAccessToken = '';
            $settings->twitterAccessTokenSecret = '';
            $settings->twitterAccountName = '';
            $settings->twitterAccountImageURL = '';

            // Save settings
            Craft::$app->getPlugins()->savePluginSettings($plugin, $settings->toArray());

            return $this->asJson([
                'success' => true,
                'message' => 'Twitter account disconnected successfully.'
            ]);

        } catch (\Exception $e) {
            Craft::error('Error disconnecting Twitter: ' . $e->getMessage(), __METHOD__);
            
            return $this->asJson([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
}