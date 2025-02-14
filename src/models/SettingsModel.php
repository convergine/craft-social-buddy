<?php
namespace convergine\socialbuddy\models;

use convergine\socialbuddy\SocialBuddyPlugin;
use craft\base\Model;
use craft\helpers\App;

class SettingsModel extends Model {
    public string $mode = 'manual';

    public string $pinterestApiToken = '';
    public string $pinterestAccessToken = '';
    public int $pinterestExpiresIn = 0;
    public string $pinterestExpiresDate = '';
    public string $pinterestDefaultBoard = '';

    public string $facebookApiToken = '';
    public string $facebookAccessToken = '';
    public int $facebookExpiresIn = 0;
    public string $facebookExpiresDate = '';

    public string $telegramApiToken = '';
    public string $telegramAccessToken = '';
    public int $telegramExpiresIn = 0;
    public string $telegramExpiresDate = '';

    public array $textField = [];
    public array $imageField = [];

    /**
     * Returns the API token for the given platform.
     * @param $platform
     * @return string
     */
    public function getApiToken($platform): string {
        return match ($platform) {
            SocialBuddyPlugin::PLATFORM_PINTEREST => App::parseEnv($this->pinterestApiToken),
            SocialBuddyPlugin::PLATFORM_FACEBOOK => App::parseEnv($this->facebookApiToken),
            SocialBuddyPlugin::PLATFORM_TELEGRAM => App::parseEnv($this->telegramApiToken),
            default => '',
        };
    }

    public function isPinterestEnabled(): bool {
        return !empty($this->pinterestApiToken);
    }

    public function isFacebookEnabled(): bool {
        return !empty($this->facebookApiToken);
    }

    public function isTelegramEnabled(): bool {
        return !empty($this->telegramApiToken);
    }

    public function getTextField($handle) : string {
        return $this->textField[$handle] ?? '';
    }

    public function getImageField($handle) : string {
        return $this->imageField[$handle] ?? '';
    }

    public function rules() : array {
        return [
            [['mode'], 'string'],

            [['pinterestApiToken'], 'string'],
            [['pinterestAccessToken'], 'string'],
            [['pinterestExpiresIn'], 'integer'],
            [['pinterestExpiresDate'], 'string'],
            [['pinterestDefaultBoard'], 'string'],

            [['facebookApiToken'], 'string'],
            [['facebookAccessToken'], 'string'],
            [['facebookExpiresIn'], 'integer'],
            [['facebookExpiresDate'], 'string'],

            [['telegramApiToken'], 'string'],
            [['telegramAccessToken'], 'string'],
            [['telegramExpiresIn'], 'integer'],
            [['telegramExpiresDate'], 'string'],
        ];
    }
}
