<?php
namespace convergine\socialbuddy\services;

use convergine\socialbuddy\SocialBuddyPlugin;

class Pinterest extends Platform {
    public function __construct() {
        parent::__construct(SocialBuddyPlugin::PLATFORM_PINTEREST);
    }

    public function publishPost($body, $title, $board_name, $image_url, $dominant_color = null) {
        $payload = [
            'body' => $body,
            'extra_params' => [
                'title' => $title,
                'board_name' => $board_name,
                'image_url' => $image_url
            ]
        ];
        if($dominant_color !== null) {
            $payload['extra_params']['dominant_color'] = $dominant_color;
        }
        return $this->post($payload,false);
        //todo maybe check response for success
    }
}
