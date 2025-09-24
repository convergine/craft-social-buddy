<?php
namespace convergine\socialbuddy\services;

use convergine\socialbuddy\SocialBuddyPlugin;
use craft\helpers\UrlHelper;
use Craft;


class Twitter extends Platform {
    public function __construct() {
        parent::__construct(SocialBuddyPlugin::PLATFORM_TWITTER);
    }

    /**
     * Step 1: Obtain a request token from Twitter
     */
    public function getRequestToken(): array
    {
        $settings = SocialBuddyPlugin::getInstance()->getSettings();
        $clientKey = $settings->twitterClientId;
        $clientSecret = $settings->twitterClientSecret;
        
        if (empty($clientKey) || empty($clientSecret)) {
            throw new \Exception('Twitter Client ID and Client Secret must be configured.');
        }

        $requestTokenUrl = 'https://api.x.com/oauth/request_token';
        $callbackUrl = UrlHelper::siteUrl('admin/convergine-socialbuddy/twitter_callback');
        
        Craft::info('Twitter OAuth: Starting request token flow', __METHOD__);
        Craft::info('Twitter OAuth: Callback URL: ' . $callbackUrl, __METHOD__);
        
        // For testing, let's try with oob first
        if (strpos($callbackUrl, 'localhost') !== false || strpos($callbackUrl, '127.0.0.1') !== false) {
            Craft::info('Twitter OAuth: Using oob for local development', __METHOD__);
            $callbackUrl = 'oob';
        }
        
        Craft::info('Twitter OAuth: Final callback URL: ' . $callbackUrl, __METHOD__);
        
        // Generate OAuth 1.0a parameters
        $oauthParams = [
            'oauth_consumer_key' => $clientKey,
            'oauth_nonce' => $this->generateNonce(),
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp' => time(),
            'oauth_version' => '1.0',
            'oauth_callback' => $callbackUrl
        ];

        // Generate signature
        $oauthParams['oauth_signature'] = $this->generateSignature('POST', $requestTokenUrl, $oauthParams, $clientSecret);

        // Create Authorization header
        $authHeader = $this->buildAuthHeader($oauthParams);
        
        Craft::info('Twitter OAuth: Auth header: ' . $authHeader, __METHOD__);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $requestTokenUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: ' . $authHeader,
            'Content-Type: application/x-www-form-urlencoded'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        Craft::info('Twitter OAuth: Request token response - HTTP Code: ' . $httpCode, __METHOD__);
        Craft::info('Twitter OAuth: Request token response - Body: ' . $response, __METHOD__);
        
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \Exception('cURL error: ' . $error);
        }
        
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new \Exception('Failed to get request token. HTTP Code: ' . $httpCode . ', Response: ' . $response);
        }

        parse_str($response, $responseData);
        parse_str($response, $responseData);
        parse_str($response, $responseData);
        
        Craft::info('Twitter OAuth: Parsed response data: ' . json_encode($responseData), __METHOD__);
        
        if (!isset($responseData['oauth_token']) || !isset($responseData['oauth_token_secret'])) {
            throw new \Exception('Invalid response from Twitter: ' . $response);
        }

        return $responseData;
    }

    /**
     * Step 2: Generate authorization URL
     */
    public function getAuthorizationUrl(string $oauthToken): string
    {
        $url = 'https://api.x.com/oauth/authorize?oauth_token=' . urlencode($oauthToken);
        Craft::info('Twitter OAuth: Generated authorization URL: ' . $url, __METHOD__);
        return $url;
    }

    /**
     * Step 3: Exchange verifier for access token
     */
    public function getAccessToken(string $verifier, string $oauthToken, string $oauthTokenSecret): array
    {
        $settings = SocialBuddyPlugin::getInstance()->getSettings();
        $clientKey = $settings->twitterClientId;
        $clientSecret = $settings->twitterClientSecret;

        $accessTokenUrl = 'https://api.x.com/oauth/access_token';

        $oauthParams = [
            'oauth_consumer_key' => $clientKey,
            'oauth_token' => $oauthToken,
            'oauth_nonce' => $this->generateNonce(),
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp' => time(),
            'oauth_version' => '1.0',
            'oauth_verifier' => $verifier
        ];

        // Generate signature
        $oauthParams['oauth_signature'] = $this->generateSignature(
            'POST', 
            $accessTokenUrl, 
            $oauthParams, 
            $clientSecret, 
            $oauthTokenSecret
        );

        // Create Authorization header
        $authHeader = $this->buildAuthHeader($oauthParams);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $accessTokenUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: ' . $authHeader,
            'Content-Type: application/x-www-form-urlencoded'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \Exception('cURL error: ' . $error);
        }
        
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new \Exception('Failed to get access token. HTTP Code: ' . $httpCode . ', Response: ' . $response);
        }

        parse_str($response, $responseData);
        
        if (!isset($responseData['oauth_token']) || !isset($responseData['oauth_token_secret'])) {
            throw new \Exception('Invalid access token response from Twitter: ' . $response);
        }

        return $responseData;
    }

    /**
     * Get user profile information
     */
    public function getUserProfile(string $accessToken, string $accessTokenSecret): array
    {
        $settings = SocialBuddyPlugin::getInstance()->getSettings();
        $clientKey = $settings->twitterClientId;
        $clientSecret = $settings->twitterClientSecret;

        $userUrl = 'https://api.x.com/1.1/account/verify_credentials.json';

        $oauthParams = [
            'oauth_consumer_key' => $clientKey,
            'oauth_token' => $accessToken,
            'oauth_nonce' => $this->generateNonce(),
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp' => time(),
            'oauth_version' => '1.0'
        ];

        // Generate signature
        $oauthParams['oauth_signature'] = $this->generateSignature(
            'GET', 
            $userUrl, 
            $oauthParams, 
            $clientSecret, 
            $accessTokenSecret
        );

        // Create Authorization header
        $authHeader = $this->buildAuthHeader($oauthParams);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $userUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: ' . $authHeader
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \Exception('cURL error: ' . $error);
        }
        
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new \Exception('Failed to get user profile. HTTP Code: ' . $httpCode . ', Response: ' . $response);
        }

        $userData = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Failed to decode user profile response: ' . json_last_error_msg());
        }

        return $userData;
    }

    /**
     * Generate OAuth nonce
     */
    private function generateNonce(): string
    {
        return md5(microtime() . mt_rand());
    }

    /**
     * Generate OAuth signature
     */
    private function generateSignature(string $method, string $url, array $params, string $consumerSecret, string $tokenSecret = ''): string
    {
        // Remove oauth_signature if present
        unset($params['oauth_signature']);
        
        // Sort parameters
        ksort($params);
        
        // Build parameter string
        $paramString = '';
        foreach ($params as $key => $value) {
            if ($paramString !== '') {
                $paramString .= '&';
            }
            $paramString .= rawurlencode($key) . '=' . rawurlencode($value);
        }
        
        // Build signature base string
        $baseString = rawurlencode($method) . '&' . rawurlencode($url) . '&' . rawurlencode($paramString);
        
        // Build signing key
        $signingKey = rawurlencode($consumerSecret) . '&' . rawurlencode($tokenSecret);
        
        // Generate signature
        return base64_encode(hash_hmac('sha1', $baseString, $signingKey, true));
    }

    /**
     * Build OAuth authorization header
     */
    private function buildAuthHeader(array $params): string
    {
        $authHeader = 'OAuth ';
        $headerParams = [];
        
        foreach ($params as $key => $value) {
            if (strpos($key, 'oauth_') === 0) {
                $headerParams[] = rawurlencode($key) . '="' . rawurlencode($value) . '"';
            }
        }
        
        $authHeader .= implode(', ', $headerParams);
        return $authHeader;
    }

    /**
     * Check Twitter connection status
     */
    public function getConnection(): array
    {
        $settings = SocialBuddyPlugin::getInstance()->getSettings();
        
        if (empty($settings->twitterAccessToken) || empty($settings->twitterAccessTokenSecret)) {
            $settings->twitterConnected = 0;
            $settings->twitterAccountName = '';
            $settings->twitterAccountImageURL = '';
            Craft::$app->getPlugins()->savePluginSettings(SocialBuddyPlugin::getInstance(), $settings->toArray());
            
            return [
                'connected' => false,
                'message' => 'Twitter not connected'
            ];
        }

        try {
            // Verify credentials to check if tokens are still valid
            $userProfile = $this->getUserProfile($settings->twitterAccessToken, $settings->twitterAccessTokenSecret);
            
            $settings->twitterConnected = 1;
            $settings->twitterAccountName = $userProfile['name'] ?? '';
            $settings->twitterAccountImageURL = $userProfile['profile_image_url'] ?? '';
            
            Craft::$app->getPlugins()->savePluginSettings(SocialBuddyPlugin::getInstance(), $settings->toArray());
            
            return [
                'connected' => true,
                'account_name' => $settings->twitterAccountName,
                'account_image' => $settings->twitterAccountImageURL
            ];
            
        } catch (\Exception $e) {
            Craft::error('Twitter connection verification failed: ' . $e->getMessage(), __METHOD__);
            
            $settings->twitterConnected = 0;
            Craft::$app->getPlugins()->savePluginSettings(SocialBuddyPlugin::getInstance(), $settings->toArray());
            
            return [
                'connected' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Upload media to Twitter
     */
    public function uploadMedia(array $imageUrls): array
    {
        $settings = SocialBuddyPlugin::getInstance()->getSettings();
        $clientKey = $settings->twitterClientId;
        $clientSecret = $settings->twitterClientSecret;
        $accessToken = $settings->twitterAccessToken;
        $accessTokenSecret = $settings->twitterAccessTokenSecret;

        $mediaIds = [];

        foreach ($imageUrls as $imageUrl) {
            try {
                // Download image
                $imageContent = file_get_contents($imageUrl);
                if ($imageContent === false) {
                    Craft::warning('Failed to download image: ' . $imageUrl, __METHOD__);
                    continue;
                }

                // Create temporary file
                $tempFile = tempnam(sys_get_temp_dir(), 'twitter_upload_');
                file_put_contents($tempFile, $imageContent);

                $uploadUrl = 'https://upload.twitter.com/1.1/media/upload.json';

                $oauthParams = [
                    'oauth_consumer_key' => $clientKey,
                    'oauth_token' => $accessToken,
                    'oauth_nonce' => $this->generateNonce(),
                    'oauth_signature_method' => 'HMAC-SHA1',
                    'oauth_timestamp' => time(),
                    'oauth_version' => '1.0'
                ];

                // Generate signature for media upload
                $oauthParams['oauth_signature'] = $this->generateSignature(
                    'POST', 
                    $uploadUrl, 
                    $oauthParams, 
                    $clientSecret, 
                    $accessTokenSecret
                );

                $authHeader = $this->buildAuthHeader($oauthParams);

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $uploadUrl);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Authorization: ' . $authHeader
                ]);
                curl_setopt($ch, CURLOPT_POSTFIELDS, [
                    'media' => new \CURLFile($tempFile, 'image/jpeg', 'image.jpg')
                ]);

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                
                // Clean up temporary file
                unlink($tempFile);
                
                if (curl_errno($ch)) {
                    Craft::warning('cURL error uploading media: ' . curl_error($ch), __METHOD__);
                    curl_close($ch);
                    continue;
                }
                
                curl_close($ch);

                if ($httpCode === 200) {
                    $mediaData = json_decode($response, true);
                    if (isset($mediaData['media_id_string'])) {
                        $mediaIds[] = $mediaData['media_id_string'];
                    }
                } else {
                    Craft::warning('Failed to upload media. HTTP Code: ' . $httpCode . ', Response: ' . $response, __METHOD__);
                }

            } catch (\Exception $e) {
                Craft::warning('Error uploading media to Twitter: ' . $e->getMessage(), __METHOD__);
                // Clean up temporary file if it exists
                if (isset($tempFile) && file_exists($tempFile)) {
                    unlink($tempFile);
                }
            }
        }

        return $mediaIds;
    }

    /**
     * Publish a post to Twitter
     */
    public function publishPost($body, $imageUrls = []): array
    {
        $settings = SocialBuddyPlugin::getInstance()->getSettings();
        $clientKey = $settings->twitterClientId;
        $clientSecret = $settings->twitterClientSecret;
        $accessToken = $settings->twitterAccessToken;
        $accessTokenSecret = $settings->twitterAccessTokenSecret;

        if (empty($accessToken) || empty($accessTokenSecret)) {
            return [
                'success' => false,
                'error' => 'Twitter not connected. Please authorize your Twitter account first.'
            ];
        }

        try {
            $mediaIds = [];
            
            // Upload images if provided
            if (!empty($imageUrls) && is_array($imageUrls)) {
                $mediaIds = $this->uploadMedia($imageUrls);
            }

            // Create tweet
            $tweetUrl = 'https://api.x.com/2/tweets';
            
            $payload = ['text' => $body];
            if (!empty($mediaIds)) {
                $payload['media'] = ['media_ids' => $mediaIds];
            }

            $jsonPayload = json_encode($payload);

            $oauthParams = [
                'oauth_consumer_key' => $clientKey,
                'oauth_token' => $accessToken,
                'oauth_nonce' => $this->generateNonce(),
                'oauth_signature_method' => 'HMAC-SHA1',
                'oauth_timestamp' => time(),
                'oauth_version' => '1.0'
            ];

            // Generate signature
            $oauthParams['oauth_signature'] = $this->generateSignature(
                'POST', 
                $tweetUrl, 
                $oauthParams, 
                $clientSecret, 
                $accessTokenSecret
            );

            $authHeader = $this->buildAuthHeader($oauthParams);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $tweetUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: ' . $authHeader,
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            if (curl_errno($ch)) {
                $error = curl_error($ch);
                curl_close($ch);
                throw new \Exception('cURL error: ' . $error);
            }
            
            curl_close($ch);

            if ($httpCode === 201) {
                $responseData = json_decode($response, true);
                Craft::info('Tweet published successfully: ' . json_encode($responseData), __METHOD__);
                
                return [
                    'success' => true,
                    'data' => $responseData,
                    'message' => 'Tweet published successfully'
                ];
            } else {
                $errorData = json_decode($response, true);
                $errorMessage = $errorData['detail'] ?? $errorData['title'] ?? 'Unknown error';
                
                throw new \Exception('Failed to publish tweet. HTTP Code: ' . $httpCode . ', Error: ' . $errorMessage);
            }

        } catch (\Exception $e) {
            Craft::error('Error publishing to Twitter: ' . $e->getMessage(), __METHOD__);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
