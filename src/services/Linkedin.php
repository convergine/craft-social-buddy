<?php
namespace convergine\socialbuddy\services;

use convergine\socialbuddy\SocialBuddyPlugin;
use craft\helpers\UrlHelper;
use Craft;


class Linkedin extends Platform {
    public function __construct() {
        parent::__construct(SocialBuddyPlugin::PLATFORM_LINKEDIN);
    }

    public function getConnection() : array {

        $settings = SocialBuddyPlugin::getInstance()->getSettings();
        $apiKey = $settings->apiKey;
        $url = self::API_URL . '/smp/platform?platform=linkedin&apikey=' . $apiKey;
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
        Craft::info('cURL Linkedin connection response: ' . $response, __METHOD__);        
       
        // declare responseData in function scope
        $responseData = [];
    
        if(curl_errno($ch)) {
            Craft::error('cURL /smp/platform?platform=linkedin error: ' . curl_error($ch), __METHOD__);
        } else {
            $responseData = json_decode($response, true);
    
            if(json_last_error() === JSON_ERROR_NONE) {
                if (isset($responseData['page_name'])) {
                    // Ensure specific keys exist before using them
                    $accountName = $responseData['page_name'];
                    $settings->{'linkedinAccountName'} = $accountName;
                    $imageURL = $responseData['page_picture'];
                    $settings->{'linkedinAccountImageURL'} = $imageURL;
                    $settings->{'linkedinConnected'} = 1;
                } else {
                    $settings->{'linkedinAccountName'} = '';
                    $settings->{'linkedinAccountImageURL'} = '';
                    $settings->{'linkedinConnected'} = 0;

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
        $settings = SocialBuddyPlugin::getInstance()->getSettings();
        
        if (!$settings->isLinkedinEnabled()) {
            Craft::warning("LinkedIn: Not connected, cannot publish post", __METHOD__);
            return ['error' => 'LinkedIn not connected'];
        }

        try {
            $accessToken = $settings->linkedinAccessToken;
            $organizationId = $settings->linkedinPageId;
            
            // Upload images first to get URNs
            $imageUrns = [];
            if (!empty($image_urls)) {
                foreach ($image_urls as $imageUrl) {
                    $urn = $this->uploadImage($imageUrl, $accessToken, $organizationId);
                    if ($urn) {
                        $imageUrns[] = $urn;
                    } else {
                        Craft::warning("LinkedIn: Failed to upload image: " . $imageUrl, __METHOD__);
                    }
                }
            }
            
            // Prepare the post data
            $postData = [
                'author' => 'urn:li:organization:' . $organizationId,
                'lifecycleState' => 'PUBLISHED',
                'specificContent' => [
                    'com.linkedin.ugc.ShareContent' => [
                        'shareCommentary' => [
                            'text' => $body
                        ],
                        'shareMediaCategory' => !empty($imageUrns) ? 'IMAGE' : 'NONE'
                    ]
                ],
                'visibility' => [
                    'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC'
                ]
            ];

            // Add media if we have successfully uploaded images
            if (!empty($imageUrns)) {
                $media = [];
                foreach ($imageUrns as $imageUrn) {
                    $media[] = [
                        'status' => 'READY',
                        'description' => [
                            'text' => $body
                        ],
                        'media' => $imageUrn,
                        'title' => [
                            'text' => 'Social Buddy Post'
                        ]
                    ];
                }
                $postData['specificContent']['com.linkedin.ugc.ShareContent']['media'] = $media;
            }

            // Make the API call to LinkedIn
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://api.linkedin.com/v2/ugcPosts');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
                'X-Restli-Protocol-Version: 2.0.0'
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            if (curl_errno($ch)) {
                $error = curl_error($ch);
                curl_close($ch);
                Craft::error("LinkedIn API cURL error: " . $error, __METHOD__);
                return ['error' => $error];
            }
            
            curl_close($ch);

            $result = json_decode($response, true);
            
            if ($httpCode >= 200 && $httpCode < 300) {
                Craft::info("LinkedIn post published successfully", __METHOD__);
                return ['success' => true, 'response' => $result];
            } else {
                Craft::error("LinkedIn API error (HTTP $httpCode): " . $response, __METHOD__);
                return ['error' => 'LinkedIn API error: ' . ($result['message'] ?? 'Unknown error')];
            }

        } catch (\Exception $e) {
            Craft::error("LinkedIn publish error: " . $e->getMessage(), __METHOD__);
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Upload an image to LinkedIn and return the URN
     */
    private function uploadImage($imageUrl, $accessToken, $organizationId) {
        try {
            // Step 1: Register upload with LinkedIn
            $registerData = [
                'registerUploadRequest' => [
                    'recipes' => ['urn:li:digitalmediaRecipe:feedshare-image'],
                    'owner' => 'urn:li:organization:' . $organizationId,
                    'serviceRelationships' => [
                        [
                            'relationshipType' => 'OWNER',
                            'identifier' => 'urn:li:userGeneratedContent'
                        ]
                    ]
                ]
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://api.linkedin.com/v2/assets?action=registerUpload');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($registerData));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json'
            ]);

            $registerResponse = curl_exec($ch);
            $registerHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($registerHttpCode < 200 || $registerHttpCode >= 300) {
                Craft::error("LinkedIn register upload error (HTTP $registerHttpCode): " . $registerResponse, __METHOD__);
                return null;
            }

            $registerResult = json_decode($registerResponse, true);
            if (!isset($registerResult['value']['uploadMechanism']['com.linkedin.digitalmedia.uploading.MediaUploadHttpRequest']['uploadUrl'])) {
                Craft::error("LinkedIn register upload response missing upload URL: " . $registerResponse, __METHOD__);
                return null;
            }

            $uploadUrl = $registerResult['value']['uploadMechanism']['com.linkedin.digitalmedia.uploading.MediaUploadHttpRequest']['uploadUrl'];
            $asset = $registerResult['value']['asset'];

            // Step 2: Download the image from the URL
            $imageData = file_get_contents($imageUrl);
            if ($imageData === false) {
                Craft::error("LinkedIn: Failed to download image from URL: " . $imageUrl, __METHOD__);
                return null;
            }

            // Step 3: Upload the image data to LinkedIn
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $uploadUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $imageData);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/octet-stream'
            ]);

            $uploadResponse = curl_exec($ch);
            $uploadHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($uploadHttpCode >= 200 && $uploadHttpCode < 300) {
                Craft::info("LinkedIn image uploaded successfully: " . $asset, __METHOD__);
                return $asset;
            } else {
                Craft::error("LinkedIn image upload error (HTTP $uploadHttpCode): " . $uploadResponse, __METHOD__);
                return null;
            }

        } catch (\Exception $e) {
            Craft::error("LinkedIn image upload exception: " . $e->getMessage(), __METHOD__);
            return null;
        }
    }
}
