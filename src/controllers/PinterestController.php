<?php

namespace convergine\socialbuddy\controllers;

use convergine\socialbuddy\SocialBuddyPlugin;
use craft\web\Controller;
use yii\web\Response;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Craft;


class PinterestController extends Controller
{
    // Allow anonymous access to the 'pin-auth', 'pin-conn', and 'pin-disconnect' actions
    protected array|int|bool $allowAnonymous = ['pin-auth','pin-conn','pin-disconnect'];

    public function beforeAction($action): bool
    {
        // Disable CSRF validation for this action
        $this->enableCsrfValidation = false;
        return parent::beforeAction($action);
    }

    public function actionPinAuth(): Response
    {
        $request = Craft::$app->getRequest();
        $code = $request->getQueryParam('code');
        $state = $request->getQueryParam('state');

        if ($code) {
            // Fetch your app credentials from settings
            $plugin = SocialBuddyPlugin::getInstance();
            $settings = $plugin->getSettings();
            /** @var \convergine\socialbuddy\models\Settings $settings */
            
            $clientId = $settings->{'pinterestClientId'};
            $clientSecret = $settings->{'pinterestClientSecret'};
            
            $siteUrl = Craft::$app->getSites()->getCurrentSite()->getBaseUrl();
            $redirectUri = $siteUrl . 'admin/convergine-socialbuddy/pin_auth';            
            $tokenEndpoint = 'https://api.pinterest.com/v5/oauth/token';

            try {
                // Create a Guzzle client
                $client = new Client();// Create Basic Auth header with base64 encoding
                $authStr = "{$clientId}:{$clientSecret}";
                $authBase64 = base64_encode($authStr);
                
                // Exchange the authorization code for an access token using Basic Auth
                $response = $client->post($tokenEndpoint, [
                    'headers' => [
                        'Authorization' => "Basic {$authBase64}",
                        'Content-Type' => 'application/x-www-form-urlencoded'
                    ],
                    'form_params' => [
                        'grant_type' => 'authorization_code',
                        'code' => $code,
                        'redirect_uri' => $redirectUri
                    ],
                ]);

                $data = json_decode($response->getBody(), true);

                if (isset($data['access_token'])) {
                    $accessToken = $data['access_token'];
                    
                    // Store the access token in settings
                    $settings->pinterestAccessToken = $accessToken;
                      // Get user information
                    $response = $client->get('https://api.pinterest.com/v5/user_account', [
                        'headers' => [
                            'Authorization' => 'Bearer ' . $accessToken,
                        ],
                    ]);
                    
                    $userData = json_decode($response->getBody(), true);
                    
                    if (isset($userData['username'])) {
                        $settings->pinterestAccountName = $userData['username'];
                        $settings->pinterestAccountImageURL = $userData['profile_image'] ?? '';
                        $settings->pinterestConnected = 1;
                        
                        // Save settings
                        Craft::$app->getPlugins()->savePluginSettings(SocialBuddyPlugin::getInstance(), $settings->toArray());
                        
                        // Get site URL for link back
                        $siteUrl = Craft::$app->getSites()->getCurrentSite()->getBaseUrl();
                        
                        // Redirect to success page
                        return $this->renderTemplate('convergine-socialbuddy/_layouts/_fb_success', [
                            'socialMediaIcon' => 'pinterest.svg',
                            'socialMediaName' => 'Pinterest',
                            'linkBack' => $siteUrl . 'admin/convergine-socialbuddy/settings/pinterest',
                        ]);
                    } else {
                        throw new \Exception('Could not retrieve user information.');
                    }
                } else {
                    throw new \Exception('Could not retrieve access token.');
                }

            } catch (RequestException $e) {
                // Handle HTTP request exceptions
                $errorResponse = $e->getResponse();
                $errorMessage = $e->getMessage();

                if ($errorResponse) {
                    $errorBody = $errorResponse->getBody();
                    $errorData = json_decode($errorBody, true);
                    $errorMessage = $errorData['error']['message'] ?? $errorMessage;
                }

                Craft::error('Error during Pinterest OAuth flow: ' . $errorMessage, __METHOD__);

                return $this->renderTemplate('convergine-socialbuddy/auth-error', [
                    'error' => $errorMessage,
                ]);
            } catch (\Exception $e) {
                // Handle other exceptions
                Craft::error('Error during Pinterest OAuth flow: ' . $e->getMessage(), __METHOD__);

                return $this->renderTemplate('convergine-socialbuddy/auth-error', [
                    'error' => $e->getMessage(),
                ]);
            }
        } else {
            // No authorization code received
            return $this->renderTemplate('convergine-socialbuddy/auth-error', [
                'error' => 'No authorization code received.',
            ]);
        }
    }

    /**
     * Returns the connected state of Pinterest
     *
     * @return Response
     */
    public function actionPinConn(): Response
    {
        // Get the plugin instance
        $plugin = SocialBuddyPlugin::getInstance();
        $settings = $plugin->getSettings();
        /** @var \convergine\socialbuddy\models\Settings $settings */

        // Get the 'pinterestConnected' setting
        $pinterestConnected = $settings->pinterestConnected;

        // Return the value as a JSON response
        return $this->asJson([
            'pinterestConnected' => $pinterestConnected,
            'accountName' => $settings->pinterestAccountName,
            'accountImageURL' => $settings->pinterestAccountImageURL,
        ]);
    }

    /**
     * Disconnect Pinterest account
     *
     * @return Response
     */
    public function actionPinDisconnect(): Response
    {
        try {
            // Get the plugin instance
            $plugin = SocialBuddyPlugin::getInstance();
            $settings = $plugin->getSettings();
            /** @var \convergine\socialbuddy\models\Settings $settings */

            // Clear Pinterest connection data
            $settings->pinterestConnected = 0;
            $settings->pinterestAccountName = '';
            $settings->pinterestAccountImageURL = '';
            $settings->pinterestAccessToken = '';
            $settings->pinterestPageId = '';
            
            // Save settings
            Craft::$app->getPlugins()->savePluginSettings($plugin, $settings->toArray());
            
            Craft::info("Pinterest account disconnected successfully", __METHOD__);
            
            return $this->asJson([
                'success' => true,
                'message' => 'Pinterest account disconnected successfully'
            ]);
            
        } catch (\Exception $e) {
            Craft::error("Error disconnecting Pinterest: " . $e->getMessage(), __METHOD__);
            
            return $this->asJson([
                'success' => false,
                'error' => 'Failed to disconnect Pinterest account'
            ]);
        }
    }
}

