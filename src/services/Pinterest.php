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
        $settings = SocialBuddyPlugin::getInstance()->getSettings();
        
        if (!$settings->isPinterestEnabled()) {
            Craft::warning("Pinterest: Not connected, cannot publish post", __METHOD__);
            return ['error' => 'Pinterest not connected'];
        }

        // Validate inputs
        if (empty($body) || empty($title) || empty($image_url)) {
            Craft::error("Pinterest: Missing required fields - body, title, or image_url", __METHOD__);
            return ['error' => 'Missing required fields: body, title, and image_url are required'];
        }

        if (!filter_var($image_url, FILTER_VALIDATE_URL)) {
            Craft::error("Pinterest: Invalid image URL: " . $image_url, __METHOD__);
            return ['error' => 'Invalid image URL provided'];
        }

        try {
            $accessToken = $settings->pinterestAccessToken;
            
            // First, find the board ID by board name
            $boardId = $this->getBoardIdByName($board_name);
            if (!$boardId) {
                Craft::error("Pinterest: Board '{$board_name}' not found or no boards available", __METHOD__);
                return ['error' => "Board '{$board_name}' not found. Please check your board name or create a board first."];
            }
            
            // Prepare the pin data according to Pinterest API v5 specification
            $pinData = [
                'board_id' => $boardId,
                'title' => substr($title, 0, 100), // Pinterest title limit
                'description' => substr($body, 0, 500), // Pinterest description limit
                'media_source' => [
                    'source_type' => 'image_url',
                    'url' => $image_url
                ]
            ];
            
            // Add dominant color if provided (optional)
            if ($dominant_color !== null && preg_match('/^#[0-9A-Fa-f]{6}$/', $dominant_color)) {
                $pinData['dominant_color'] = $dominant_color;
            }

            // Make the API call to Pinterest with proper headers
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://api.pinterest.com/v5/pins');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($pinData));
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
                'User-Agent: SocialBuddy-CraftCMS/1.0'
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            if (curl_errno($ch)) {
                $error = curl_error($ch);
                curl_close($ch);
                Craft::error("Pinterest API cURL error: " . $error, __METHOD__);
                return ['error' => 'Network error: ' . $error];
            }
            
            curl_close($ch);

            $result = json_decode($response, true);
            
            if ($httpCode >= 200 && $httpCode < 300) {
                Craft::info("Pinterest pin created successfully with ID: " . ($result['id'] ?? 'unknown'), __METHOD__);
                return ['success' => true, 'response' => $result];
            } else {
                // Handle specific Pinterest API errors
                $errorMessage = 'Unknown error';
                if (isset($result['message'])) {
                    $errorMessage = $result['message'];
                } elseif (isset($result['error_description'])) {
                    $errorMessage = $result['error_description'];
                }
                
                // Handle rate limiting
                if ($httpCode === 429) {
                    $errorMessage = 'Pinterest API rate limit exceeded. Please try again later.';
                    Craft::warning("Pinterest API rate limit exceeded", __METHOD__);
                } elseif ($httpCode === 401) {
                    $errorMessage = 'Pinterest authentication failed. Please reconnect your account.';
                    Craft::error("Pinterest authentication failed - token may be expired", __METHOD__);
                } elseif ($httpCode === 403) {
                    $errorMessage = 'Pinterest access forbidden. Check your app permissions.';
                    Craft::error("Pinterest access forbidden", __METHOD__);
                }
                
                Craft::error("Pinterest API error (HTTP $httpCode): " . $response, __METHOD__);
                return ['error' => 'Pinterest API error: ' . $errorMessage];
            }

        } catch (\Exception $e) {
            Craft::error("Pinterest publish exception: " . $e->getMessage(), __METHOD__);
            return ['error' => 'Failed to publish to Pinterest: ' . $e->getMessage()];
        }
    }

    /**
     * Get board ID by board name with improved error handling
     */
    private function getBoardIdByName($boardName): ?string {
        if (empty($boardName)) {
            Craft::warning("Pinterest: Empty board name provided", __METHOD__);
            return null;
        }
        
        $boards = $this->getBoards();
        
        if (empty($boards)) {
            Craft::error("Pinterest: No boards found or unable to fetch boards", __METHOD__);
            return null;
        }
        
        // First pass: exact match
        foreach ($boards as $board) {
            if (isset($board['name']) && $board['name'] === $boardName) {
                Craft::info("Pinterest: Found exact board match for '{$boardName}' with ID: {$board['id']}", __METHOD__);
                return $board['id'];
            }
        }
        
        // Second pass: case-insensitive match
        foreach ($boards as $board) {
            if (isset($board['name']) && strcasecmp($board['name'], $boardName) === 0) {
                Craft::info("Pinterest: Found case-insensitive board match for '{$boardName}' with ID: {$board['id']}", __METHOD__);
                return $board['id'];
            }
        }
        
        // If not found and we're not already trying the default board, try default board
        $settings = SocialBuddyPlugin::getInstance()->getSettings();
        $defaultBoard = $settings->pinterestDefaultBoard;
        
        if ($boardName !== $defaultBoard && !empty($defaultBoard)) {
            Craft::warning("Pinterest: Board '{$boardName}' not found, trying default board '{$defaultBoard}'", __METHOD__);
            return $this->getBoardIdByName($defaultBoard);
        }
        
        // If still not found, try the first available board
        if (!empty($boards)) {
            $firstBoard = $boards[0];
            if (isset($firstBoard['id']) && isset($firstBoard['name'])) {
                Craft::warning("Pinterest: Using first available board '{$firstBoard['name']}' as fallback", __METHOD__);
                return $firstBoard['id'];
            }
        }
        
        Craft::error("Pinterest: No suitable board found for '{$boardName}'", __METHOD__);
        return null;
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
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            Craft::error('cURL Pinterest boards error: ' . curl_error($ch), __METHOD__);
            curl_close($ch);
            return [];
        }
        
        curl_close($ch);
        
        if ($httpCode !== 200) {
            Craft::error("Pinterest boards API error (HTTP $httpCode): " . $response, __METHOD__);
            return [];
        }
        
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Craft::error('Failed to decode Pinterest boards JSON: ' . json_last_error_msg(), __METHOD__);
            return [];
        }
        
        return $data['items'] ?? [];
    }

    /**
     * Get all board names for dropdown/validation purposes
     * @return array
     */
    public function getBoardNames(): array
    {
        $boards = $this->getBoards();
        $boardNames = [];
        
        foreach ($boards as $board) {
            if (isset($board['name'])) {
                $boardNames[] = $board['name'];
            }
        }
        
        return $boardNames;
    }

    /**
     * Validate if a board exists
     * @param string $boardName
     * @return bool
     */
    public function boardExists(string $boardName): bool
    {
        $boardNames = $this->getBoardNames();
        return in_array($boardName, $boardNames, true);
    }
}
