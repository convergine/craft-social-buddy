<?php
namespace convergine\socialbuddy\services;

use convergine\socialbuddy\SocialBuddyPlugin;
use craft\helpers\UrlHelper;
use Craft;

class Platform {
    protected const API_URL = 'https://api-dev.carebots.ai/api';

    protected string $platform;

    public function __construct($platform = '') {
        $this->platform = $platform;
    }

    /**
     * Gets the access token for the platform
     * @return string
     */
    /*
    protected function getAccessToken() : string {
        $settings = SocialBuddyPlugin::getInstance()->getSettings();
        $accessToken = $settings->{$this->platform.'AccessToken'};
        $expiresDate = $settings->{$this->platform.'ExpiresDate'};
        if(empty($expiresDate) || strtotime($expiresDate) < time()) {
            $accessToken = $this->refreshAccessToken();
        }
        return $accessToken;
    }
    */

    /**
     * Create API Key for the first sign-in
     * @return array
     */
    public function createApiKey() : array {

        $settings = SocialBuddyPlugin::getInstance()->getSettings();

        $url = self::API_URL . '/smp/create_apikey';
        $ch = curl_init($url);
        $headers = [
            'Content-Type: application/json'
        ];        
    
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'siteUrl' => UrlHelper::siteHost(),
        ]));
        $response = curl_exec($ch);

        // Log the raw response for debugging purposes
        Craft::info('cURL response: ' . $response, __METHOD__);        
       
        // declare responseData in function scope
        $responseData = [];
    
        if(curl_errno($ch)) {
            Craft::error('cURL error: ' . curl_error($ch), __METHOD__);
        } else {
            $responseData = json_decode($response, true);
    
            if(json_last_error() === JSON_ERROR_NONE) {
                if (isset($responseData['apiKey']) && isset($responseData['license'])) {
                    // Ensure specific keys exist before using them
                    $apiKey = $responseData['apiKey'];
                    $settings->{'apiKey'} = $apiKey;
                    $license = $responseData['license'];
                    $settings->{'license'} = $license;
                    Craft::$app->getPlugins()->savePluginSettings(SocialBuddyPlugin::getInstance(), $settings->toArray());
                } else {
                    Craft::error("Missing expected keys in response data.", __METHOD__);
                }
            } else {
                Craft::error("Failed to decode JSON response: " . json_last_error_msg(), __METHOD__);
            }
        }
        curl_close($ch);
    
        return $responseData;

    }

    // write function to call API to get license level
    public function getLicense() : string {
        /*
        $settings = SocialBuddyPlugin::getInstance()->getSettings();
        $apiKey = $settings->apiKey;
        $url = self::API_URL . '/smp/license?apikey='.$apiKey;
        Craft::info('cURL getLicense API call URL: ' . $url, __METHOD__);
        $ch = curl_init($url);
        $headers = [
            'Content-Type: application/json'
        ];        
    
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // change API call from POST to GET
        curl_setopt($ch, CURLOPT_POST, false);
        $response = curl_exec($ch);

        // Log the raw response for debugging purposes
        Craft::info('cURL getLicense response: ' . $response, __METHOD__);        
       
        // declare responseData in function scope
        $responseData = [];
    
        if(curl_errno($ch)) {
            Craft::error('cURL error: ' . curl_error($ch), __METHOD__);
        } else {
            $responseData = json_decode($response, true);
    
            if(json_last_error() === JSON_ERROR_NONE) {
                if (isset($responseData['license'])) {
                    // Ensure specific keys exist before using them
                    $license = $responseData['license'];
                    $settings->{'license'} = $license;
                    Craft::$app->getPlugins()->savePluginSettings(SocialBuddyPlugin::getInstance(), $settings->toArray());
                } else {
                    Craft::error("Missing expected keys in response data.", __METHOD__);
                }
            } else {
                Craft::error("Failed to decode JSON response: " . json_last_error_msg(), __METHOD__);
            }
        }
        curl_close($ch);
    
        return $responseData['license'];
        */

        $plugin = SocialBuddyPlugin::getInstance();

        if($plugin->is(SocialBuddyPlugin::EDITION_PRO))
        {
            return SocialBuddyPlugin::EDITION_PRO;
        }
        else if($plugin->is(SocialBuddyPlugin::EDITION_LITE))
        {
            return SocialBuddyPlugin::EDITION_LITE;
        }
        else
        {
            return SocialBuddyPlugin::EDITION_FREE;
        }
    }


    function getPageAccessToken() {
        $longLivedUserToken = 'EAAx2XCSMfZAABO2rHIqxTfAtWXTGKZA0rESElwYCZCiqdA7F3uG0FFyIzVyZCxHV8ltZAUIpLoRxdLwrvsHeOoZBOL84oDGRU3W5kfbbFo4l3QPWZCPAL1cgBPXBUnTNQ0zFNSAZCK3SIw0A9K4iCkdH8evBS3Gy7rZBAXuVKaJS2XzZByzZAeIdXdYUP5W';
        $pageId = '100525561649457';
        $graphApiUrl = 'https://graph.facebook.com/v15.0/me/accounts';
    
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $graphApiUrl . '?access_token=' . $longLivedUserToken);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            return ['error' => 'cURL error: ' . curl_error($ch)];
        }
    
        curl_close($ch);
    
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['error' => 'Failed to decode JSON response: ' . json_last_error_msg()];
        }
    
        $pageAccessToken = null;
        foreach ($data['data'] as $page) {
            if ($page['id'] === $pageId) {
                $pageAccessToken = $page['access_token'];
                break;
            }
        }
    
        if (!$pageAccessToken) {
            return ['error' => 'Could not retrieve page access token'];
        }
    
        return ['page_access_token' => $pageAccessToken];
    }    

    // write function to call API to get license level
    public function getStatistics() : array {

        $result = this->getPageAccessToken();

        $pageAccessToken = "";

        if (isset($result['error'])) {
            Craft::error('Error getting page access token: ' . $result['error'], __METHOD__);
        } else {
            $pageAccessToken = $result['page_access_token'];
            echo "Page Access Token: " . $pageAccessToken;
        }        

        $settings = SocialBuddyPlugin::getInstance()->getSettings();
        $accessToken = $pageAccessToken;
        $pageId = $settings->facebookPageId;
    
        $baseUrl = "https://graph.facebook.com/v21.0";
        $metrics = "page_fan_adds,page_fan_removes,page_impressions,page_engaged_users";
    
        // Create the URL for the insights request
        $url = "{$baseUrl}/{$pageId}/insights?metric={$metrics}&access_token={$accessToken}";
    
        // Initialize cURL
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
        // Execute request
        $response = curl_exec($ch);
        $statistics = [
            'total_followers' => 0,
            'new_followers' => 0,
            'number_of_posts' => 0,
            'reach' => 0,
            'engagements' => 0,
        ];
    
        // Check for errors
        if (curl_errno($ch)) {
            Craft::error('cURL error: ' . curl_error($ch), __METHOD__);
        } else {
            $data = json_decode($response, true);
    
            if (json_last_error() === JSON_ERROR_NONE && isset($data['data'])) {
                foreach ($data['data'] as $insight) {
                    $name = $insight['name'];
                    $values = $insight['values'] ?? [];
    
                    switch ($name) {
                        case 'page_fan_adds':
                            $statistics['new_followers'] = end($values)['value'] ?? 0;
                            break;
                        case 'page_impressions':
                            $statistics['reach'] = end($values)['value'] ?? 0;
                            break;
                        case 'page_engaged_users':
                            $statistics['engagements'] = end($values)['value'] ?? 0;
                            break;
                    }
                }
            } else {
                Craft::error("Failed to decode JSON response: " . json_last_error_msg(), __METHOD__);
            }
        }
    
        // Close cURL
        curl_close($ch);
    
        // Separate call to get total followers (fan count)
        $url = "{$baseUrl}/{$pageId}?fields=fan_count&access_token={$accessToken}";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
    
        if (!curl_errno($ch)) {
            $pageData = json_decode($response, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $statistics['total_followers'] = $pageData['fan_count'] ?? 0;
            } else {
                Craft::error("Failed to decode JSON response for fan_count: " . json_last_error_msg(), __METHOD__);
            }
        } else {
            Craft::error('cURL error for fan_count: ' . curl_error($ch), __METHOD__);
        }
    
        curl_close($ch);
    
        return $statistics;

    }



    /**
     * Refreshes the access token for the platform
     * @return string
     */
    private function refreshAccessToken() : string {
        $settings = SocialBuddyPlugin::getInstance()->getSettings();
        $apiToken = $settings->getApiToken($this->platform);
        if(empty($apiToken)) {
            return '';
        }

        $accessToken = '';

        $url = self::API_URL . '/sm/auth';
        $ch = curl_init($url);
        $headers = [
            'Authorization: Basic '.$apiToken,
            'Content-Type: application/json'
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        $response = curl_exec($ch);

        if(curl_errno($ch)) {
            Craft::error('cURL error: ' . curl_error($ch), __METHOD__);
        } else {
            $responseData = json_decode($response, true);

            if(json_last_error() === JSON_ERROR_NONE) {
                $accessToken = $responseData['access_token'];
                $expiresIn = $responseData['expires_in'];

                $settings->{$this->platform.'AccessToken'} = $accessToken;
                $settings->{$this->platform.'ExpiresIn'} = $expiresIn;
                $settings->{$this->platform.'ExpiresDate'} = date('Y-m-d H:i:s', time() + $expiresIn);

                Craft::$app->getPlugins()->savePluginSettings(SocialBuddyPlugin::getInstance(), $settings->toArray());
            } else {
                Craft::error("Failed to decode JSON response: " . json_last_error_msg(), __METHOD__);
            }
        }
        curl_close($ch);

        return $accessToken;
    }

    /**
     * Publishes a post to the platform
     * @param array $payload The payload to send
     * @param bool $is_json Whether the expected response is JSON
     * @return array|string|bool
     */
    protected function post(array $payload, bool $is_json = true) : array|string|bool {
        $accessToken = $this->getAccessToken();
        if(empty($accessToken)) {
            return false;
        }

        $url = self::API_URL . '/sm/post';
        $ch = curl_init($url);
        $headers = [
            'Authorization: Bearer '.$accessToken,
            'Content-Type: application/json'
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array_merge([
            'sm_id' => $this->platform
        ],$payload)));
        $response = curl_exec($ch);

        if(curl_errno($ch)) {
            Craft::error('cURL error: ' . curl_error($ch), __METHOD__);
        } else if($is_json) {
            $responseData = json_decode($response, true);
            if(json_last_error() === JSON_ERROR_NONE) {
                return $responseData;
            } else {
                Craft::error("Failed to decode JSON response: " . json_last_error_msg(), __METHOD__);
            }
        } else {
            return $response;
        }
        curl_close($ch);

        return false;
    }
}
