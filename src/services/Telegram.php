<?php
namespace convergine\socialbuddy\services;

use convergine\socialbuddy\SocialBuddyPlugin;
use Craft;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class Telegram extends Platform {
    public function __construct() {
        parent::__construct(SocialBuddyPlugin::PLATFORM_TELEGRAM);
    }

    public function publishPost($body, $image_urls) {
        // Get the plugin settings
        $plugin = SocialBuddyPlugin::getInstance();
        $settings = $plugin->getSettings();

        $botToken = $settings->telegramBotToken;
        $channelAccount = $settings->telegramChannelAccount;

        if (empty($botToken) || empty($channelAccount)) {
            Craft::error('Telegram bot token or channel account is missing', __METHOD__);
            return false;
        }

        // Ensure we have the @ prefix for the channel
        $chatId = '@' . ltrim($channelAccount, '@');

        $client = new Client();
        $telegramApiUrl = "https://api.telegram.org/bot{$botToken}/";

        try {
            // Ensure $image_urls is an array
            if (!is_array($image_urls)) {
                $image_urls = [$image_urls];
            }

            // If we have images, send them with the message
            if (!empty($image_urls) && !empty($image_urls[0])) {
                if (count($image_urls) === 1) {
                    // Single image - send as photo with caption
                    $response = $client->post($telegramApiUrl . 'sendPhoto', [
                        'form_params' => [
                            'chat_id' => $chatId,
                            'photo' => $image_urls[0],
                            'caption' => $body,
                            'parse_mode' => 'HTML'
                        ]
                    ]);
                } else {
                    // Multiple images - send as media group
                    $media = [];
                    foreach ($image_urls as $index => $imageUrl) {
                        $media[] = [
                            'type' => 'photo',
                            'media' => $imageUrl,
                            'caption' => $index === 0 ? $body : ''
                        ];
                    }

                    $response = $client->post($telegramApiUrl . 'sendMediaGroup', [
                        'form_params' => [
                            'chat_id' => $chatId,
                            'media' => json_encode($media)
                        ]
                    ]);
                }
            } else {
                // Text only message
                $response = $client->post($telegramApiUrl . 'sendMessage', [
                    'form_params' => [
                        'chat_id' => $chatId,
                        'text' => $body,
                        'parse_mode' => 'HTML'
                    ]
                ]);
            }

            $result = json_decode($response->getBody(), true);

            if (isset($result['ok']) && $result['ok'] === true) {
                Craft::info('Telegram message sent successfully', __METHOD__);
                return true;
            } else {
                $error = $result['description'] ?? 'Unknown error';
                Craft::error('Telegram API error: ' . $error, __METHOD__);
                return false;
            }

        } catch (RequestException $e) {
            $errorResponse = $e->getResponse();
            $errorMessage = $e->getMessage();

            if ($errorResponse) {
                $errorBody = $errorResponse->getBody();
                $errorData = json_decode($errorBody, true);
                $errorMessage = $errorData['description'] ?? $errorMessage;
            }

            Craft::error('Telegram API request error: ' . $errorMessage, __METHOD__);
            return false;

        } catch (\Exception $e) {
            Craft::error('Telegram API exception: ' . $e->getMessage(), __METHOD__);
            return false;
        }
    }
}
