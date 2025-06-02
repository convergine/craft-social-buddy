<?php
namespace convergine\socialbuddy\services;

use convergine\socialbuddy\SocialBuddyPlugin;
use Craft;

class Pinterest extends Platform {
    public function __construct() {
        parent::__construct(SocialBuddyPlugin::PLATFORM_PINTEREST);
    }

    public function getConnection() : array {

        $settings = SocialBuddyPlugin::getInstance()->getSettings();
        $apiKey = $settings->apiKey;
        $url = self::API_URL . '/smp/pinterest/connection';
        $ch = curl_init($url);
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ];        
    
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // change API call from POST to GET
        curl_setopt($ch, CURLOPT_POST, false);
        $response = curl_exec($ch);

        // Log the raw response for debugging purposes
        Craft::info('cURL Pinterest connection response: ' . $response, __METHOD__);        
       
        // declare responseData in function scope
        $responseData = [];
    
        if(curl_errno($ch)) {
            Craft::error('cURL /smp/pinterest/connection error: ' . curl_error($ch), __METHOD__);
        } else {
            $responseData = json_decode($response, true);
    
            if(json_last_error() === JSON_ERROR_NONE) {
                if (isset($responseData['accountName'])) {
                    // Ensure specific keys exist before using them
                    $accountName = $responseData['accountName'];
                    $settings->{'pinterestAccountName'} = $accountName;
                    $imageURL = $responseData['accountImageURL'];
                    $settings->{'pinterestAccountImageURL'} = $imageURL;
                    $settings->{'pinterestConnected'} = 1;

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

    public function publishPost($body, $title, $board_name, $image_url, $dominant_color = null) {
        $payload = [
            'body' => $body,
            'extra_params' => [
                'title' => $title,
                'board_name' => $board_name,
                'image_url' => $image_url
            ]
        ];
        if($dominant_color !== null) {
            $payload['extra_params']['dominant_color'] = $dominant_color;
        }
        return $this->post($payload,false);
        //todo maybe check response for success
    }

    /**
     * Fetches Pinterest boards using the access token from settings.
     * @return array
     */
    public function getBoards(): array
    {
        $settings = SocialBuddyPlugin::getInstance()->getSettings();
        $accessToken = $settings->pinterestAccessToken;
        if (empty($accessToken)) {
            Craft::error('Pinterest access token is missing.', __METHOD__);
            return [];
        }
        $url = 'https://api.pinterest.com/v5/boards';
        $ch = curl_init($url);
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            Craft::error('cURL Pinterest boards error: ' . curl_error($ch), __METHOD__);
            curl_close($ch);
            return [];
        }
        curl_close($ch);
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Craft::error('Failed to decode Pinterest boards JSON: ' . json_last_error_msg(), __METHOD__);
            return [];
        }
        return $data['items'] ?? [];
    }
}
