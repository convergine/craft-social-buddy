<?php

namespace convergine\socialbuddy\controllers;

use convergine\socialbuddy\SocialBuddyPlugin;
use craft\web\Controller;
use yii\web\Response;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Craft;


class FacebookController extends Controller
{
    // Allow anonymous access to the 'fb-auth' action
    protected array|int|bool $allowAnonymous = ['fb-auth','fb-conn','ig-conn'];

    public function beforeAction($action): bool
    {
        // Disable CSRF validation for this action
        $this->enableCsrfValidation = false;
        return parent::beforeAction($action);
    }


    public function actionFbAuth(): Response
    {
        $request = Craft::$app->getRequest();
        $code = $request->getQueryParam('code');
        $state = $request->getQueryParam('state');

        if ($code) {
            // Fetch your app credentials from settings or environment variables
            $plugin = SocialBuddyPlugin::getInstance();
            $settings = $plugin->getSettings();
            /** @var \convergine\socialbuddy\models\Settings $settings */
            $settings = $plugin->getSettings();

            if ($state == 'F') {
                $clientId = $settings->{'facebookClientId'};
                $clientSecret = $settings->{'facebookClientSecret'};
            } else {
                $clientId = $settings->{'instagramClientId'};
                $clientSecret = $settings->{'instagramClientSecret'};

            }
            $siteUrl = Craft::$app->getSites()->getCurrentSite()->getBaseUrl();
            $redirectUri = $siteUrl . 'admin/convergine-socialbuddy/fb_auth';

            $tokenEndpoint = 'https://graph.facebook.com/v17.0/oauth/access_token';
            $graphApiUrl = 'https://graph.facebook.com/v17.0/';

            try {
                // Create a Guzzle client
                $client = new Client();

                // Step 1: Exchange the authorization code for a short-lived user access token
                $response = $client->get($tokenEndpoint, [
                    'query' => [
                        'client_id' => $clientId,
                        'redirect_uri' => $redirectUri,
                        'client_secret' => $clientSecret,
                        'code' => $code,
                    ],
                ]);

                $data = json_decode($response->getBody(), true);

                if (isset($data['access_token'])) {
                    $userAccessToken = $data['access_token'];

                    // Step 2: Exchange for a long-lived user access token
                    $longLivedTokenResponse = $client->get($tokenEndpoint, [
                        'query' => [
                            'grant_type' => 'fb_exchange_token',
                            'client_id' => $clientId,
                            'client_secret' => $clientSecret,
                            'fb_exchange_token' => $userAccessToken,
                        ],
                    ]);

                    $longLivedTokenData = json_decode($longLivedTokenResponse->getBody(), true);

                    if (isset($longLivedTokenData['access_token'])) {
                        $longLivedUserToken = $longLivedTokenData['access_token'];

                        // Step 3: Get the page access token
                        $pageAccessTokenResponse = $client->get($graphApiUrl . 'me/accounts', [
                            'query' => [
                                'access_token' => $longLivedUserToken,
                            ],
                        ]);

                        $pageAccessTokenData = json_decode($pageAccessTokenResponse->getBody(), true);

                        if (isset($pageAccessTokenData['data']) && !empty($pageAccessTokenData['data'])) {
                            // Get the page access token for the desired page
                            $pages = $pageAccessTokenData['data'];
                            // Replace PAGE_ID with your specific page ID if needed
                            // For now, we'll just use the first page
                            $page = $pages[0];
                            $pageAccessToken = $page['access_token'];
                            $pageId = $page['id'];

                            // Step 4: Get page name and profile image
                            $pageInfoResponse = $client->get($graphApiUrl . $pageId, [
                                'query' => [
                                    'fields' => 'id,name,picture',
                                    'access_token' => $pageAccessToken,
                                ],
                            ]);

                            $pageInfo = json_decode($pageInfoResponse->getBody(), true);

                            $pageName = $pageInfo['name'] ?? 'Unknown';
                            $pagePictureUrl = $pageInfo['picture']['data']['url'] ?? '';

                            // Step 5: Store information in settings
                            if ($state == 'I') {
                                $settings->{'instagramConnected'} = 1;
                                $settings->{'instagramAccessToken'} = $pageAccessToken;
                                $settings->{'instagramPageId'} = $pageId;
                                $settings->{'instagramAccountName'} = $pageName;
                                $settings->{'instagramAccountImageURL'} = $pagePictureUrl;
                            }
                            else {
                                $settings->{'facebookConnected'} = 1;
                                $settings->{'facebookAccessToken'} = $pageAccessToken;
                                $settings->{'facebookPageId'} = $pageId;
                                $settings->{'facebookAccountName'} = $pageName;
                                $settings->{'facebookAccountImageURL'} = $pagePictureUrl;
                            }

                            // Save the updated settings
                            Craft::$app->plugins->savePluginSettings($plugin, $settings->toArray());

                            // You may wish to redirect or render a success template
                            if ($state == 'I') {
                                return $this->renderTemplate('convergine-socialbuddy/_layouts/_fb_success', [
                                    'accountName' => $pageName,
                                    'socialMediaIcon' => 'instagram.svg',
                                    'socialMediaName' => 'Instagram account', // Or append 'Facebook Page' if desired
                                    'linkBack' => $siteUrl . 'admin/convergine-socialbuddy/settings/instagram',                            
                                ]);
                            } else {
                                return $this->renderTemplate('convergine-socialbuddy/_layouts/_fb_success', [
                                    'accountName' => $pageName,
                                    'socialMediaIcon' => 'fb.svg',
                                    'socialMediaName' => 'Facebook page', // Or append 'Facebook Page' if desired
                                    'linkBack' => $siteUrl . 'admin/convergine-socialbuddy/settings/facebook',                            
                                ]);
                            }
                        } else {
                            throw new \Exception('No pages found in your Meta account.');
                        }
                    } else {
                        throw new \Exception('Could not retrieve long-lived user access token.');
                    }
                } else {
                    throw new \Exception('Could not retrieve user access token.');
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

                Craft::error('Error during Facebook OAuth flow: ' . $errorMessage, __METHOD__);

                return $this->renderTemplate('convergine-socialbuddy/auth-error', [
                    'error' => $errorMessage,
                ]);
            } catch (\Exception $e) {
                // Handle other exceptions
                Craft::error('Error during Facebook OAuth flow: ' . $e->getMessage(), __METHOD__);

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
     * Returns the connected state of Facebook
     *
     * @return Response
     */
    public function actionFbConn(): Response
    {
        // Get the plugin instance
        $plugin = SocialBuddyPlugin::getInstance();
        $settings = $plugin->getSettings();
        /** @var \convergine\socialbuddy\models\Settings $settings */

        // Get the 'facebookConnected' setting
        $facebookConnected = $settings->facebookConnected;

        // Return the value as a JSON response
        return $this->asJson([
            'facebookConnected' => $facebookConnected,
        ]);
    }

    /**
     * Returns the connected state of Instagram
     *
     * @return Response
     */
    public function actionIgConn(): Response
    {
        // Get the plugin instance
        $plugin = SocialBuddyPlugin::getInstance();
        $settings = $plugin->getSettings();
        /** @var \convergine\socialbuddy\models\Settings $settings */

        // Get the 'instagramConnected' setting
        $instagramConnected = $settings->instagramConnected;

        // Return the value as a JSON response
        return $this->asJson([
            'instagramConnected' => $instagramConnected,
        ]);
    }



}

