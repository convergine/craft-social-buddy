<?php
namespace convergine\socialbuddy\services;

use convergine\socialbuddy\SocialBuddyPlugin;
use craft\helpers\UrlHelper;
use Craft;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;


class Facebook extends Platform {
    public function __construct() {
        parent::__construct(SocialBuddyPlugin::PLATFORM_FACEBOOK);
    }

    public function getConnection() : array {

        $settings = SocialBuddyPlugin::getInstance()->getSettings();
        $apiKey = $settings->apiKey;
        $url = self::API_URL . '/smp/platform?platform=facebook&apikey=' . $apiKey;
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
        Craft::info('cURL Facebook connection response: ' . $response, __METHOD__);        
       
        // declare responseData in function scope
        $responseData = [];
    
        if(curl_errno($ch)) {
            Craft::error('cURL /smp/platform?platform=facebook error: ' . curl_error($ch), __METHOD__);
        } else {
            $responseData = json_decode($response, true);
    
            if(json_last_error() === JSON_ERROR_NONE) {
                if (isset($responseData['page_id'])) {
                    // Ensure specific keys exist before using them
                    $accountName = $responseData['page_name'];
                    $settings->{'facebookAccountName'} = $accountName;
                    $imageURL = $responseData['page_picture'];
                    $settings->{'facebookAccountImageURL'} = $imageURL;
                    $settings->{'facebookConnected'} = 1;
                } else {
                    $settings->{'facebookAccountName'} = '';
                    $settings->{'facebookAccountImageURL'} = '';
                    $settings->{'facebookConnected'} = 0;

                }
                Craft::$app->getPlugins()->savePluginSettings(SocialBuddyPlugin::getInstance(), $settings->toArray());
            } else {
                Craft::error("Failed to decode JSON response: " . json_last_error_msg(), __METHOD__);
            }
        }
        curl_close($ch);
    
        return $responseData;
    }    


    public function publishPost($body, $image_urls) {

        // Ensure $image_urls is an array
        if (!is_array($image_urls)) {
            $image_urls = [$image_urls];
        }

        // Get the plugin settings
        $plugin = SocialBuddyPlugin::getInstance();
        $settings = $plugin->getSettings();
        /** @var \convergine\socialbuddy\models\Settings $settings */

        $pageAccessToken = $settings->facebookAccessToken; // The page access token
        $pageId = $settings->facebookPageId;               // The page ID
        $graphApiUrl = 'https://graph.facebook.com/v17.0/';

        $client = new Client();

        $uploadedPhotoIds = [];

        // Step 1: Upload each photo to Facebook without publishing
        foreach ($image_urls as $image_url) {
            $photoUploadPayload = [
                'url'          => $image_url,
                'published'    => false,
                'access_token' => $pageAccessToken,
            ];

            try {
                $photoResponse = $client->post($graphApiUrl . $pageId . '/photos', [
                    'form_params' => $photoUploadPayload,
                ]);

                $photoData = json_decode($photoResponse->getBody(), true);

                if (isset($photoData['id'])) {
                    $uploadedPhotoIds[] = $photoData['id'];
                } else {
                    $error = $photoData['error']['message'] ?? 'Unknown error';
                    Craft::error('Failed to upload photo: ' . $error, __METHOD__);
                    return false;
                }
            } catch (\Exception $e) {
                // Handle exceptions
                Craft::error('Exception during photo upload: ' . $e->getMessage(), __METHOD__);
                return false;
            }
        }

        if (empty($uploadedPhotoIds)) {
            Craft::error('No photos were uploaded', __METHOD__);
            return false;
        }

        // Step 2: Create the post with the uploaded photo IDs
        $attachedMedia = [];
        foreach ($uploadedPhotoIds as $photoId) {
            $attachedMedia[] = ['media_fbid' => $photoId];
        }

        $postPayload = [
            'message'       => $body,
            'attached_media' => $attachedMedia,
            'access_token'  => $pageAccessToken,
        ];

        try {
            $postResponse = $client->post($graphApiUrl . $pageId . '/feed', [
                'json' => $postPayload,
            ]);

            $postResult = json_decode($postResponse->getBody(), true);

            if (isset($postResult['id'])) {
                Craft::info('Post created successfully! Post ID: ' . $postResult['id'], __METHOD__);
                return true;
            } else {
                $error = $postResult['error']['message'] ?? 'Unknown error';
                Craft::error('Failed to create post: ' . $error, __METHOD__);
                return false;
            }
        } catch (\Exception $e) {
            Craft::error('Exception during post creation: ' . $e->getMessage(), __METHOD__);
            return false;
        }
    }
}
