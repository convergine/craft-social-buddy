<?php
namespace convergine\socialbuddy\services;

use convergine\socialbuddy\SocialBuddyPlugin;

class Facebook extends Platform {
    public function __construct() {
        parent::__construct(SocialBuddyPlugin::PLATFORM_FACEBOOK);
    }

    public function publishPost($body, $image_url) {
        $payload = [
            'body' => $body,
            'extra_params' => [
                'image_url' => $image_url
            ]
        ];
        return $this->post($payload,false);
        //todo maybe check response for success
    }
}
