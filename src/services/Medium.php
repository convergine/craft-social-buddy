<?php
namespace convergine\socialbuddy\services;

use convergine\socialbuddy\SocialBuddyPlugin;

class Medium extends Platform {
    public function __construct() {
        parent::__construct(SocialBuddyPlugin::PLATFORM_MEDIUM);
    }

    public function publishPost($body, $image_url) {
        $payload = [
            'body' => $body,
            'image' => $image_url
        ];
        return $this->post($payload,false);
    }
}
