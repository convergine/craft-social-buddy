<?php
namespace convergine\socialbuddy\variables;

use convergine\socialbuddy\SocialBuddyPlugin;

class BuddyVariable
{
    public function medium()
    {
        return SocialBuddyPlugin::getInstance()->medium;
    }

    public function pinterest()
    {
        return SocialBuddyPlugin::getInstance()->pinterest;
    }

    public function facebook()
    {
        return SocialBuddyPlugin::getInstance()->facebook;
    }

    public function instagram()
    {
        return SocialBuddyPlugin::getInstance()->instagram;
    }

    public function twitter()
    {
        return SocialBuddyPlugin::getInstance()->twitter;
    }

    public function linkedin()
    {
        return SocialBuddyPlugin::getInstance()->linkedin;
    }

    public function telegram()
    {
        return SocialBuddyPlugin::getInstance()->telegram;
    }

    public function post()
    {
        return SocialBuddyPlugin::getInstance()->post;
    }
}