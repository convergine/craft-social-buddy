<?php
namespace convergine\socialbuddy\services;

use convergine\socialbuddy\SocialBuddyPlugin;
use Craft;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class Medium extends Platform {
    private const API_BASE_URL = 'https://api.medium.com/v1';
    
    public function __construct() {
        parent::__construct(SocialBuddyPlugin::PLATFORM_MEDIUM);
    }

    public function publishPost($title, $content, $tags = [], $status = 'public') {
        try {
            $accessToken = $this->getAccessToken();
            if (!$accessToken) {
                throw new \Exception('Medium access token not found');
            }

            // Get user ID first
            $userId = $this->getUserId($accessToken);
            if (!$userId) {
                throw new \Exception('Failed to get Medium user ID');
            }

            // Prepare the article data
            $articleData = [
                'title' => $title,
                'contentFormat' => 'markdown',
                'content' => $content,
                'publishStatus' => $status, // 'public', 'draft', or 'unlisted'
                'tags' => array_slice($tags, 0, 3) // Medium allows max 3 tags
            ];

            // Create the article
            $response = $this->makeApiRequest(
                'POST',
                "/users/{$userId}/posts",
                $articleData,
                $accessToken
            );

            if ($response && isset($response['data']['url'])) {
                return [
                    'success' => true,
                    'message' => 'Article published successfully to Medium',
                    'url' => $response['data']['url'],
                    'id' => $response['data']['id']
                ];
            }

            throw new \Exception('Failed to publish article to Medium');

        } catch (\Exception $e) {
            Craft::error('Medium publish error: ' . $e->getMessage(), __METHOD__);
            return [
                'success' => false,
                'message' => 'Failed to publish to Medium: ' . $e->getMessage()
            ];
        }
    }

    private function getUserId($accessToken) {
        try {
            $response = $this->makeApiRequest('GET', '/me', [], $accessToken);
            return $response['data']['id'] ?? null;
        } catch (\Exception $e) {
            Craft::error('Failed to get Medium user ID: ' . $e->getMessage(), __METHOD__);
            return null;
        }
    }

    public function makeApiRequest($method, $endpoint, $data = [], $accessToken = null) {
        $client = new Client();
        
        $options = [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ]
        ];

        if ($method === 'POST' && !empty($data)) {
            $options['json'] = $data;
        }

        try {
            $response = $client->request($method, self::API_BASE_URL . $endpoint, $options);
            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            $errorMessage = $e->getMessage();
            if ($e->hasResponse()) {
                $errorBody = json_decode($e->getResponse()->getBody()->getContents(), true);
                $errorMessage = $errorBody['errors'][0]['message'] ?? $errorMessage;
            }
            throw new \Exception($errorMessage);
        }
    }

    public function getAccessToken() {
        $settings = SocialBuddyPlugin::getInstance()->getSettings();
        return $settings->mediumIntegrationToken ?? null;
    }

    public function isConnected(): bool {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return false;
        }

        // For UI purposes, if we have a token, consider it "connected"
        // Actual validation happens at publish time
        return true;
    }

    public function validateConnection(): bool {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return false;
        }

        try {
            $userId = $this->getUserId($accessToken);
            return !empty($userId);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function disconnect(): bool {
        // Medium doesn't have a revoke endpoint, so we just clear the local token
        $settings = SocialBuddyPlugin::getInstance()->getSettings();
        $settings->mediumIntegrationToken = null;
        $settings->mediumConnected = 0;
        return Craft::$app->plugins->savePluginSettings(SocialBuddyPlugin::getInstance(), $settings->toArray());
    }

    public function getConnectionStatus(): array {
        if ($this->isConnected()) {
            try {
                $accessToken = $this->getAccessToken();
                $userInfo = $this->makeApiRequest('GET', '/me', [], $accessToken);
                
                return [
                    'connected' => true,
                    'username' => $userInfo['data']['username'] ?? 'Unknown',
                    'name' => $userInfo['data']['name'] ?? 'Unknown'
                ];
            } catch (\Exception $e) {
                return ['connected' => false, 'error' => $e->getMessage()];
            }
        }
        
        return ['connected' => false];
    }
}
