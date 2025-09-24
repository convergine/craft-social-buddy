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

    /**
     * Crop an image to 4:5 portrait ratio (center crop) and save to a temp file.
     * Returns the local file path of the cropped image.
     */
    private function cropToPortrait($imageUrl): ?string
    {
        $raw = @file_get_contents($imageUrl);
        if ($raw === false) return null;
        $src = @imagecreatefromstring($raw);
        if (!$src) return null;
        $width = imagesx($src);
        $height = imagesy($src);
        // Target aspect ratio: 4:5 (portrait)
        $targetRatio = 4 / 5;
        $srcRatio = $width / $height;
        if ($srcRatio > $targetRatio) {
            // Too wide: crop left/right
            $newWidth = intval($height * $targetRatio);
            $x = intval(($width - $newWidth) / 2);
            $y = 0;
            $cropWidth = $newWidth;
            $cropHeight = $height;
        } else {
            // Too tall: crop top/bottom (rare for landscape images)
            $newHeight = intval($width / $targetRatio);
            $x = 0;
            $y = intval(($height - $newHeight) / 2);
            $cropWidth = $width;
            $cropHeight = $newHeight;
        }
        $dst = imagecreatetruecolor($cropWidth, $cropHeight);
        imagecopy($dst, $src, 0, 0, $x, $y, $cropWidth, $cropHeight);
        $tmpFile = tempnam(sys_get_temp_dir(), 'igportrait_') . '.jpg';
        imagejpeg($dst, $tmpFile, 90);
        imagedestroy($src);
        imagedestroy($dst);
        return $tmpFile;
    }

    public function publishPost($body, $image_urls) {
        // Ensure $image_urls is an array
        if (!is_array($image_urls)) {
            $image_urls = [$image_urls, $image_urls];
        }

        // Crop images to portrait and upload to temp
        $croppedImagePaths = [];
        foreach ($image_urls as $url) {
            $cropped = $this->cropToPortrait($url);
            if ($cropped) {
                $croppedImagePaths[] = $cropped;
            } else {
                // fallback to original if crop fails
                $croppedImagePaths[] = $url;
            }
        }

        // You need to make these temp files accessible via a URL for the Facebook API.
        // For this example, we'll assume you have a function to upload to a public location and return the URL.
        $publicUrls = [];
        foreach ($croppedImagePaths as $pathOrUrl) {
            if (filter_var($pathOrUrl, FILTER_VALIDATE_URL)) {
                $publicUrls[] = $pathOrUrl;
            } else {
                // Implement your own uploader here, e.g., to S3 or your public uploads dir
                // $publicUrls[] = $this->uploadToPublic($pathOrUrl);
                // For now, fallback to original URL
                $publicUrls[] = $image_urls[0];
            }
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
        foreach ($publicUrls as $image_url) {
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
                    'caption' => $caption ?? '',
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
