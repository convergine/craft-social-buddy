<?php

namespace convergine\socialbuddy\queue;

use convergine\socialbuddy\SocialBuddyPlugin;
use Craft;
use craft\queue\BaseJob;

class SubmitPost extends BaseJob {
    public int $entryId;
    public string $platform;

    public function execute($queue) : void {
        $this->setProgress($queue, 0);
        $entry = Craft::$app->entries->getEntryById($this->entryId);
        if(!$entry) {
            return;
        }

        $post = SocialBuddyPlugin::getInstance()->post;
        $text = $post->getText($entry);
        $image = $post->getImage($entry);
        $board = $post->getBoard($entry);

        Craft::info("SocialBuddy Text: " . $text, __METHOD__);
        Craft::info("SocialBuddy Image: " . $image, __METHOD__);
        Craft::info("SocialBuddy Board: " . $board, __METHOD__);

        $this->setProgress($queue, 1);

        switch($this->platform) {
            case 'pinterest':
                $title = $entry->title;
                $board_name = !empty($board) ? $board : SocialBuddyPlugin::getInstance()->getSettings()->pinterestDefaultBoard;
                $result = SocialBuddyPlugin::getInstance()->pinterest->publishPost($text,$title,$board_name,$image);
                Craft::info("SocialBuddy Pinterest Result: " . json_encode($result), __METHOD__);
                break;
            case 'facebook':
                $result = SocialBuddyPlugin::getInstance()->facebook->publishPost($text, $image);
                Craft::info("SocialBuddy Facebook Result: " . json_encode($result), __METHOD__);
                break;
            case 'telegram':
                $result = SocialBuddyPlugin::getInstance()->telegram->publishPost($text, $image);
                Craft::info("SocialBuddy Telegram Result: " . json_encode($result), __METHOD__);
                break;
            case 'all':
                $title = $entry->title;
                $board_name = !empty($board) ? $board : SocialBuddyPlugin::getInstance()->getSettings()->pinterestDefaultBoard;
                $result = SocialBuddyPlugin::getInstance()->pinterest->publishPost($text,$title,$board_name,$image);
                Craft::info("SocialBuddy Pinterest Result: " . json_encode($result), __METHOD__);

                $this->setProgress($queue, 1/3);
                $result = SocialBuddyPlugin::getInstance()->facebook->publishPost($text, $image);
                Craft::info("SocialBuddy Facebook Result: " . json_encode($result), __METHOD__);

                $this->setProgress($queue, 2/3);
                $result = SocialBuddyPlugin::getInstance()->telegram->publishPost($text, $image);
                Craft::info("SocialBuddy Telegram Result: " . json_encode($result), __METHOD__);
                break;
        }
        $this->setProgress($queue, 1);
    }

    protected function defaultDescription() : string {
        return Craft::t('convergine-socialbuddy','Submitting post to '.$this->platform);
    }
}
