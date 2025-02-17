<?php
namespace convergine\socialbuddy\services;

use convergine\socialbuddy\SocialBuddyPlugin;
use craft\helpers\UrlHelper;
use Craft;


class Instagram extends Platform {
    public function __construct() {
        parent::__construct(SocialBuddyPlugin::PLATFORM_FACEBOOK);
    }


    /**
     * Remove instagram-related settings
     *
     * @return Response
     */
    public function disconnect($settings) {
        $settings->instagramConnected = 0;
        $settings->$instagramAccountImageURL = '';
        $settings->$instagramAccountName = '';
        $settings->$instagramClientId = '';
        $settings->$instagramClientSecret = '';
        $settings->$instagramAccessToken = '';
        $settings->$instagramPageId = '';
    }    


    public function publishPost($body, $image_url) {
        // Ensure $image_urls is an array
        if (!is_array($image_urls)) {
            $image_urls = [$image_urls, $image_urls];
        }

        // Get the plugin settings
        $plugin = SocialBuddyPlugin::getInstance();
        $settings = $plugin->getSettings();
        /** @var \convergine\socialbuddy\models\Settings $settings */

        $longLivedUserToken = $settings->instagramAccessToken; // Long-lived user access token
        $pageId = $settings->instagramPageId; // Facebook Page ID
        $graphApiUrl = 'https://graph.facebook.com/v17.0/';

        $client = new Client();

        // Step 1: Retrieve the Instagram Business Account ID
        try {
            $response = $client->get($graphApiUrl . $pageId, [
                'query' => [
                    'fields' => 'instagram_business_account',
                    'access_token' => $longLivedUserToken,
                ],
            ]);

            $responseData = json_decode($response->getBody(), true);

            if (!isset($responseData['instagram_business_account']['id'])) {
                Craft::error('Failed to retrieve Instagram Business Account ID', __METHOD__);
                return false;
            }

            $instagramBusinessAccountId = $responseData['instagram_business_account']['id'];
        } catch (\Exception $e) {
            Craft::error('Exception retrieving Instagram Business Account ID: ' . $e->getMessage(), __METHOD__);
            return false;
        }

        // Step 2: Upload each image to Instagram as a media container
        $mediaContainers = [];
        foreach ($image_urls as $image_url) {
            try {
                $response = $client->post($graphApiUrl . $instagramBusinessAccountId . '/media', [
                    'form_params' => [
                        'image_url' => $image_url,
                        'is_carousel_item' => 'true',
                        'access_token' => $longLivedUserToken,
                    ],
                ]);

                $responseData = json_decode($response->getBody(), true);

                if (isset($responseData['id'])) {
                    $mediaContainers[] = $responseData['id'];
                } else {
                    $error = $responseData['error']['message'] ?? 'Unknown error';
                    Craft::error('Failed to upload image: ' . $error, __METHOD__);
                    return false;
                }
            } catch (\Exception $e) {
                Craft::error('Exception during image upload: ' . $e->getMessage(), __METHOD__);
                return false;
            }
        }

        if (empty($mediaContainers)) {
            Craft::error('No images were uploaded', __METHOD__);
            return false;
        }

        // Step 3: Create a carousel container
        try {
            $response = $client->post($graphApiUrl . $instagramBusinessAccountId . '/media', [
                'form_params' => [
                    'media_type' => 'CAROUSEL',
                    'children' => implode(',', $mediaContainers),
                    'caption' => $caption,
                    'access_token' => $longLivedUserToken,
                ],
            ]);

            $responseData = json_decode($response->getBody(), true);

            if (!isset($responseData['id'])) {
                $error = $responseData['error']['message'] ?? 'Unknown error';
                Craft::error('Failed to create carousel container: ' . $error, __METHOD__);
                return false;
            }

            $carouselId = $responseData['id'];
        } catch (\Exception $e) {
            Craft::error('Exception during carousel creation: ' . $e->getMessage(), __METHOD__);
            return false;
        }

        // Step 4: Publish the carousel
        try {
            $response = $client->post($graphApiUrl . $instagramBusinessAccountId . '/media_publish', [
                'form_params' => [
                    'creation_id' => $carouselId,
                    'access_token' => $longLivedUserToken,
                ],
            ]);

            $responseData = json_decode($response->getBody(), true);

            if (isset($responseData['id'])) {
                Craft::info('Carousel published successfully! Post ID: ' . $responseData['id'], __METHOD__);
                return true;
            } else {
                $error = $responseData['error']['message'] ?? 'Unknown error';
                Craft::error('Failed to publish carousel: ' . $error, __METHOD__);
                return false;
            }
        } catch (\Exception $e) {
            Craft::error('Exception during carousel publishing: ' . $e->getMessage(), __METHOD__);
            return false;
        }
    }
}
