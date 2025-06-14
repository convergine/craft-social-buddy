<?php
namespace convergine\socialbuddy\services;

use convergine\socialbuddy\SocialBuddyPlugin;
use craft\helpers\UrlHelper;
use Craft;


class Instagram extends Platform {
    public function __construct() {
        parent::__construct(SocialBuddyPlugin::PLATFORM_FACEBOOK);
    }

    public function getConnection() : array {

        $settings = SocialBuddyPlugin::getInstance()->getSettings();
        $apiKey = $settings->apiKey;
        $url = self::API_URL . '/smp/platform?platform=instagram&apikey=' . $apiKey;
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
        Craft::info('cURL Instagram connection response: ' . $response, __METHOD__);        
       
        // declare responseData in function scope
        $responseData = [];
    
        if(curl_errno($ch)) {
            Craft::error('cURL /smp/platform?platform=instagram error: ' . curl_error($ch), __METHOD__);
        } else {
            $responseData = json_decode($response, true);
    
            if(json_last_error() === JSON_ERROR_NONE) {
                if (isset($responseData['page_id'])) {
                    // Ensure specific keys exist before using them
                    $accountName = $responseData['page_name'];
                    $settings->{'instagramAccountName'} = $accountName;
                    $imageURL = $responseData['page_picture'];
                    $settings->{'instagramAccountImageURL'} = $imageURL;
                    $settings->{'instagramConnected'} = 1;
                } else {
                    $settings->{'instagramAccountName'} = '';
                    $settings->{'instagramAccountImageURL'} = '';
                    $settings->{'instagramConnected'} = 0;

                }
                Craft::$app->getPlugins()->savePluginSettings(SocialBuddyPlugin::getInstance(), $settings->toArray());
            } else {
                Craft::error("Failed to decode JSON response: " . json_last_error_msg(), __METHOD__);
            }
        }
        curl_close($ch);
    
        return $responseData;
    }    


    public function publishPost($body, $image_url) {
        $payload = [
            'body' => $body,
            'extra_params' => [
                'image_url' => $image_url
            ]
        ];
        return $this->post($payload,false);
        //todo maybe check response for success
    }
}
