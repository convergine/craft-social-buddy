<?php

namespace convergine\socialbuddy\controllers;

use convergine\socialbuddy\SocialBuddyPlugin;
use craft\web\Controller;
use yii\web\Response;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Craft;

class LinkedinController extends Controller
{
    // Allow anonymous access to the LinkedIn auth actions
    protected array|int|bool $allowAnonymous = ['linkedin-auth', 'linkedin-conn'];

    public function beforeAction($action): bool
    {
        // Disable CSRF validation for these actions
        $this->enableCsrfValidation = false;
        return parent::beforeAction($action);
    }

    public function actionLinkedinAuth(): Response
    {
        $request = Craft::$app->getRequest();
        $code = $request->getQueryParam('code');
        $state = $request->getQueryParam('state');
        $error = $request->getQueryParam('error');
        $siteUrl = Craft::$app->getSites()->getCurrentSite()->getBaseUrl();

        // Handle OAuth errors
        if ($error) {
            Craft::error('LinkedIn OAuth error: ' . $error, __METHOD__);
            return $this->renderTemplate('convergine-socialbuddy/auth-error', [
                'error' => 'LinkedIn authorization was denied or failed: ' . $error,
            ]);
        }

        if (!$code) {
            return $this->renderTemplate('convergine-socialbuddy/auth-error', [
                'error' => 'No authorization code received from LinkedIn.',
            ]);
        }

        try {
            // Get plugin settings
            $plugin = SocialBuddyPlugin::getInstance();
            $settings = $plugin->getSettings();

            $clientId = $settings->linkedinClientId;
            $clientSecret = $settings->linkedinClientSecret;
            $redirectUri = $siteUrl . 'admin/convergine-socialbuddy/linkedin-auth';

            // Create HTTP client
            $client = new Client();

            // Step 1: Exchange authorization code for access token
            $tokenResponse = $client->post('https://www.linkedin.com/oauth/v2/accessToken', [
                'form_params' => [
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'redirect_uri' => $redirectUri,
                ],
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
            ]);

            $tokenData = json_decode($tokenResponse->getBody(), true);

            if (!isset($tokenData['access_token'])) {
                throw new \Exception('Failed to obtain access token from LinkedIn');
            }

            $accessToken = $tokenData['access_token'];
            
            /*
            // Step 2: Get user profile to verify token
            $profileResponse = $client->get('https://api.linkedin.com/v2/people/~?projection=(id,firstName,lastName,profilePicture(displayImage~:playableStreams))', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                ],
            ]);

            $profileData = json_decode($profileResponse->getBody(), true);
            */

            // Step 3: Get organization access (for company pages)
            try {
                $orgsResponse = $client->get('https://api.linkedin.com/v2/organizationAcls?q=roleAssignee&role=ADMINISTRATOR&projection=(elements*(organization~(id,localizedName,name,logoV2(original~:playableStreams))))', [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $accessToken,
                    ],
                ]);
            } catch (RequestException $e) {
                // Fallback to simpler projection if the detailed one fails
                Craft::warning('LinkedIn detailed projection failed, trying simpler approach: ' . $e->getMessage(), __METHOD__);
                $orgsResponse = $client->get('https://api.linkedin.com/v2/organizationAcls?q=roleAssignee&role=ADMINISTRATOR', [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $accessToken,
                    ],
                ]);
            }

            $orgsData = json_decode($orgsResponse->getBody(), true);

            // Log the response for debugging
            Craft::info('LinkedIn organizations response: ' . json_encode($orgsData), __METHOD__);

            if (!isset($orgsData['elements']) || empty($orgsData['elements'])) {
                throw new \Exception('No LinkedIn company pages found. Make sure you have admin access to at least one company page.');
            }

            // Use the first organization the user has admin access to
            $firstElement = $orgsData['elements'][0];
            
            // Check if organization~ exists, otherwise try organization
            if (isset($firstElement['organization~'])) {
                $organization = $firstElement['organization~'];
            } elseif (isset($firstElement['organization'])) {
                $organization = $firstElement['organization'];
            } else {
                throw new \Exception('LinkedIn organization data not found in expected format. Response: ' . json_encode($firstElement));
            }
            
            // Get organization ID - could be string or integer
            $organizationId = null;
            if (isset($organization['id'])) {
                $organizationId = (string)$organization['id'];
            } elseif (isset($firstElement['organization'])) {
                // Sometimes the organization ID is directly in the element
                $organizationId = (string)$firstElement['organization'];
            }
            
            if (!$organizationId) {
                throw new \Exception('LinkedIn organization ID not found. Organization data: ' . json_encode($organization));
            }
            
            // Extract organization name properly - it might be nested in different ways
            $organizationName = 'Unknown Organization';
            
            if (is_array($organization)) {
                if (isset($organization['localizedName'])) {
                    if (is_array($organization['localizedName'])) {
                        $organizationName = $organization['localizedName']['localized']['en_US'] ?? 
                                          $organization['localizedName']['localized']['en'] ?? 
                                          reset($organization['localizedName']['localized']) ?? 
                                          'Unknown Organization';
                    } else {
                        $organizationName = (string)$organization['localizedName'];
                    }
                } elseif (isset($organization['name'])) {
                    if (is_array($organization['name'])) {
                        $organizationName = $organization['name']['localized']['en_US'] ?? 
                                          $organization['name']['localized']['en'] ?? 
                                          reset($organization['name']['localized']) ?? 
                                          'Unknown Organization';
                    } else {
                        $organizationName = (string)$organization['name'];
                    }
                }
            }
            
            // If we still don't have a name, try to fetch it separately
            if ($organizationName === 'Unknown Organization' && $organizationId) {
                try {
                    $orgDetailResponse = $client->get("https://api.linkedin.com/v2/organizations/{$organizationId}?projection=(id,localizedName)", [
                        'headers' => [
                            'Authorization' => 'Bearer ' . $accessToken,
                        ],
                    ]);
                    $orgDetail = json_decode($orgDetailResponse->getBody(), true);
                    if (isset($orgDetail['localizedName'])) {
                        $organizationName = is_array($orgDetail['localizedName']) ? 
                            $orgDetail['localizedName']['localized']['en_US'] ?? 'Organization ' . $organizationId : 
                            (string)$orgDetail['localizedName'];
                    }
                } catch (RequestException $e) {
                    Craft::warning('Failed to fetch organization details: ' . $e->getMessage(), __METHOD__);
                    $organizationName = 'Organization ' . $organizationId;
                }
            }
            
            // Get organization logo
            $organizationLogo = '';
            if (is_array($organization) && isset($organization['logoV2']['original~']['elements'][0]['identifiers'][0]['identifier'])) {
                $organizationLogo = $organization['logoV2']['original~']['elements'][0]['identifiers'][0]['identifier'];
            }

            // Step 4: Save the connection details
            $settings->linkedinConnected = 1;
            $settings->linkedinAccessToken = $accessToken;
            $settings->linkedinPageId = $organizationId;
            $settings->linkedinAccountName = $organizationName;
            $settings->linkedinAccountImageURL = $organizationLogo;

            // Save settings
            Craft::$app->plugins->savePluginSettings($plugin, $settings->toArray());

            Craft::info('LinkedIn connection successful for organization: ' . $organizationName, __METHOD__);

            // Redirect to success page
            return $this->renderTemplate('convergine-socialbuddy/_layouts/_fb_success', [
                'socialMediaIcon' => 'linkedin.svg',
                'socialMediaName' => 'LinkedIn organization',
                'linkBack' => $siteUrl . 'admin/convergine-socialbuddy/settings/linkedin',
            ]);

        } catch (RequestException $e) {
            $errorResponse = $e->getResponse();
            $errorMessage = $e->getMessage();

            if ($errorResponse) {
                $errorBody = $errorResponse->getBody();
                $errorData = json_decode($errorBody, true);
                if (isset($errorData['error_description'])) {
                    $errorMessage = $errorData['error_description'];
                } elseif (isset($errorData['message'])) {
                    $errorMessage = $errorData['message'];
                }
            }

            Craft::error('LinkedIn OAuth error: ' . $errorMessage, __METHOD__);

            return $this->renderTemplate('convergine-socialbuddy/auth-error', [
                'error' => 'LinkedIn connection failed: ' . $errorMessage,
            ]);

        } catch (\Exception $e) {
            Craft::error('LinkedIn OAuth exception: ' . $e->getMessage(), __METHOD__);

            return $this->renderTemplate('convergine-socialbuddy/auth-error', [
                'error' => 'LinkedIn connection failed: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Returns the connected state of LinkedIn
     *
     * @return Response
     */
    public function actionLinkedinConn(): Response
    {
        $plugin = SocialBuddyPlugin::getInstance();
        $settings = $plugin->getSettings();

        return $this->asJson([
            'linkedinConnected' => $settings->linkedinConnected,
        ]);
    }
}
