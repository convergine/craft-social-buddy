<?php
namespace convergine\socialbuddy\services;

use convergine\socialbuddy\SocialBuddyPlugin;
use Craft;

abstract class Platform {
    private const API_URL = 'https://api-dev.carebots.ai/api';

    protected string $platform;

    public function __construct($platform) {
        $this->platform = $platform;
    }

    /**
     * Gets the access token for the platform
     * @return string
     */
    protected function getAccessToken() : string {
        $settings = SocialBuddyPlugin::getInstance()->getSettings();
        $accessToken = $settings->{$this->platform.'AccessToken'};
        $expiresDate = $settings->{$this->platform.'ExpiresDate'};
        if(empty($expiresDate) || strtotime($expiresDate) < time()) {
            $accessToken = $this->refreshAccessToken();
        }
        return $accessToken;
    }

    /**
     * Refreshes the access token for the platform
     * @return string
     */
    private function refreshAccessToken() : string {
        $settings = SocialBuddyPlugin::getInstance()->getSettings();
        $apiToken = $settings->getApiToken($this->platform);
        if(empty($apiToken)) {
            return '';
        }

        $accessToken = '';

        $url = self::API_URL . '/sm/auth';
        $ch = curl_init($url);
        $headers = [
            'Authorization: Basic '.$apiToken,
            'Content-Type: application/json'
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        $response = curl_exec($ch);

        if(curl_errno($ch)) {
            Craft::error('cURL error: ' . curl_error($ch), __METHOD__);
        } else {
            $responseData = json_decode($response, true);

            if(json_last_error() === JSON_ERROR_NONE) {
                $accessToken = $responseData['access_token'];
                $expiresIn = $responseData['expires_in'];

                $settings->{$this->platform.'AccessToken'} = $accessToken;
                $settings->{$this->platform.'ExpiresIn'} = $expiresIn;
                $settings->{$this->platform.'ExpiresDate'} = date('Y-m-d H:i:s', time() + $expiresIn);

                Craft::$app->getPlugins()->savePluginSettings(SocialBuddyPlugin::getInstance(), $settings->toArray());
            } else {
                Craft::error("Failed to decode JSON response: " . json_last_error_msg(), __METHOD__);
            }
        }
        curl_close($ch);

        return $accessToken;
    }

    /**
     * Publishes a post to the platform
     * @param array $payload The payload to send
     * @param bool $is_json Whether the expected response is JSON
     * @return array|string|bool
     */
    protected function post(array $payload, bool $is_json = true) : array|string|bool {
        $accessToken = $this->getAccessToken();
        if(empty($accessToken)) {
            return false;
        }

        $url = self::API_URL . '/sm/post';
        $ch = curl_init($url);
        $headers = [
            'Authorization: Bearer '.$accessToken,
            'Content-Type: application/json'
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array_merge([
            'sm_id' => $this->platform
        ],$payload)));
        $response = curl_exec($ch);

        if(curl_errno($ch)) {
            Craft::error('cURL error: ' . curl_error($ch), __METHOD__);
        } else if($is_json) {
            $responseData = json_decode($response, true);
            if(json_last_error() === JSON_ERROR_NONE) {
                return $responseData;
            } else {
                Craft::error("Failed to decode JSON response: " . json_last_error_msg(), __METHOD__);
            }
        } else {
            return $response;
        }
        curl_close($ch);

        return false;
    }
}
