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


    /**
     * Remove facebook-related settings
     *
     * @return Response
     */
    public function disconnect($settings) {
        $settings->facebookConnected = 0;
        $settings->$facebookAccountImageURL = '';
        $settings->$facebookAccountName = '';
        $settings->$facebookClientId = '';
        $settings->$facebookClientSecret = '';
        $settings->$facebookAccessToken = '';
        $settings->$facebookPageId = '';
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
