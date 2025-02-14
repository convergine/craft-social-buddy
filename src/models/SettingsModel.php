<?php
namespace convergine\socialbuddy\models;

use convergine\socialbuddy\SocialBuddyPlugin;
use craft\base\Model;
use craft\helpers\App;

class SettingsModel extends Model {
    public string $mode = 'manual';
    public string $license = 'free';
    public string $apiKey = '';        

    public int $pinterestConnected = 1;
    public string $pinterestDefaultBoard = '';
    public string $pinterestAccountImageURL = '';
    public string $pinterestAccountName = '';
    public string $pinterestClientId = '';
    public string $pinterestClientSecret = '';
    public string $pinterestAccessToken = '';
    public string $pinterestPageId = '';


    public int $facebookConnected = 1;
    public string $facebookAccountImageURL = '';
    public string $facebookAccountName = '';
    public string $facebookClientId = '';
    public string $facebookClientSecret = '';
    public string $facebookAccessToken = '';
    public string $facebookPageId = '';

    public int $instagramConnected = 1;
    public string $instagramAccountImageURL = '';
    public string $instagramAccountName = '';
    public string $instagramClientId = '';
    public string $instagramClientSecret = '';
    public string $instagramAccessToken = '';
    public string $instagramPageId = '';


    public int $twitterConnected = 1;
    public string $twitterAccountName = '';
    public string $twitterAccountImageURL = '';
    public string $twitterClientId = '';
    public string $twitterClientSecret = '';
    public string $twitterAccessToken = '';
    public string $twitterPageId = '';


    public string $telegramChannelAccount = '';
    public string $telegramBotToken = '';

    public int $mediumConnected = 1;
    public string $mediumIntegrationToken = '';

    public int $linkedinConnected = 1;
    public string $linkedinAccountName = '';
    public string $linkedinAccountImageURL = '';
    public string $linkedinClientId = '';
    public string $linkedinClientSecret = '';
    public string $linkedinAccessToken = '';
    public string $linkedinPageId = '';


    public array $textField = [];
    public array $imageField = [];
    public array $isEnabled = [];




    /**
     * Returns the API token for the given platform.
     * @param $platform
     * @return string
     */
    public function getApiToken($platform): string {
        return match ($platform) {
            // SocialBuddyPlugin::PLATFORM_PINTEREST => App::parseEnv($this->pinterestApiToken),
            // SocialBuddyPlugin::PLATFORM_FACEBOOK => App::parseEnv($this->facebookApiToken),
            // SocialBuddyPlugin::PLATFORM_TELEGRAM => App::parseEnv($this->telegramApiToken),
            default => '',
        };
    }

    public function isPinterestEnabled(): bool {
        return $this->pinterestConnected > 0;
    }

    public function isFacebookEnabled(): bool {
        return $this->facebookConnected > 0;
    }

    public function isInstagramEnabled(): bool {
        return $this->instagramConnected > 0;
    }

    public function isTwitterEnabled(): bool {
        return $this->twitterConnected > 0;
    }

    public function isTelegramEnabled(): bool {
        return !empty($this->telegramChannelAccount);
    }

    public function isMediumEnabled(): bool {
        return $this->mediumConnected > 0;
    }

    public function isLinkedinEnabled(): bool {
        return $this->linkedinConnected > 0;
    }

    public function getTextField($section, $handle) : string {
        return $this->textField[$section . '-' . $handle] ?? '';
    }

    public function getImageField($section, $handle) : string {
        return $this->imageField[$section . '-' . $handle] ?? '';
    }

    public function getEnabled($section, $handle) : bool {
        return $this->isEnabled[$section . '-' . $handle] ?? false;
    }


    public function rules() : array {
        return [
            [['mode'], 'string'],

            [['pinterestDefaultBoard'], 'string'],
            [['pinterestAccountImageURL'], 'string'],
            [['pinterestAccountName'], 'string'],

            [['facebookAccountImageURL'], 'string'],
            [['facebookAccountName'], 'string'],

            [['instagramAccountImageURL'], 'string'],
            [['instagramAccountName'], 'string'],

            [['twitterAccountImageURL'], 'string'],
            [['twitterAccountName'], 'string'],

            [['linkedinAccountImageURL'], 'string'],
            [['linkedinAccountName'], 'string'],            

            [['telegramChannelAccount'], 'string'],
            [['telegramBotToken'], 'string']

        ];
    }
}
