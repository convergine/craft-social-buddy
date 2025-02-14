<?php
namespace convergine\socialbuddy\services;

use convergine\socialbuddy\SocialBuddyPlugin;
use Craft;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\helpers\Json;
use ReflectionClass;

class Post {
//    public function publish(?Entry $entry) : void {
//        if(!$entry) {
//            return;
//        }
//        $text = SocialBuddyPlugin::getInstance()->post->getText($entry);
//        $image = SocialBuddyPlugin::getInstance()->post->getImage($entry);
//
//        Craft::info("SocialBuddy Text: " . $text, __METHOD__);
//        Craft::info("SocialBuddy Image: " . $image, __METHOD__);
//
//        $title = $entry->title;
//        $board_name = SocialBuddyPlugin::getInstance()->getSettings()->pinterestDefaultBoard;
//        $result = SocialBuddyPlugin::getInstance()->pinterest->publishPost($text,$title,$board_name,$image);
//        Craft::info("SocialBuddy Pinterest Result: " . json_encode($result), __METHOD__);
//
//        $result = SocialBuddyPlugin::getInstance()->facebook->publishPost($text, $image);
//        Craft::info("SocialBuddy Facebook Result: " . json_encode($result), __METHOD__);
//
//        $result = SocialBuddyPlugin::getInstance()->telegram->publishPost($text, $image);
//        Craft::info("SocialBuddy Telegram Result: " . json_encode($result), __METHOD__);
//    }

    public function getSubmitControl():string {
        return Craft::$app->view->renderTemplate('convergine-socialbuddy/entry/_control.twig',[
            'social' => [
                'all' => 'All',
                'facebook' => 'Facebook',
                'instagram' => 'Instagram',
                'twitter' => 'X(Twitter)',
                'pinterest' => 'Pinterest',
                'linkedin' => 'LinkedIn',
                'telegram' => 'Telegram',
                'medium' => 'Medium',

            ]
        ]);
    }

    public function getAllEntryTypes() : array {
        $entryTypes = array();

        if(version_compare(Craft::$app->getInfo()->version, '5.0', '>=')) {
            $sections = Craft::$app->getEntries()->getAllSections();
        } else {
            $sections = Craft::$app->getSections()->getAllSections() ;
        }

        foreach($sections as $section) {

            // Skip sections of type 'single'
            if ($section->type === 'single') {
                continue;
            }

            foreach($section->getEntryTypes() as $entryType) {
                $obj = (object)[
                    'id' => $entryType->id,
                    'section' => $section->name,
                    'section_handle' => $section->handle,
                    'name' => $entryType->name,
                    'handle' => $entryType->handle,
                    'textFields' => array(),
                    'imageFields' => array(),
                    'selectedTextField' => '',
                    'selectedImageField' => '',
                ];

                foreach($entryType->getFieldLayout()->getCustomFields() as $field) {
                    if($this->isTextField($field)) {
                        $obj->textFields[] = [
                            'id' => $field->id,
                            'name' => $field->name,
                            'handle' => $field->handle
                        ];
                    }
                    if($this->isImageField($field)) {
                        $obj->imageFields[] = [
                            'id' => $field->id,
                            'name' => $field->name,
                            'handle' => $field->handle
                        ];
                    }
                }

                $entryTypes[] = $obj;
            }
        }
        return $entryTypes;
    }

    public function getText(ElementInterface $entry) : string {
        /** @var SettingsModel $settings */
        $settings = SocialBuddyPlugin::getInstance()->getSettings();
        $field = $settings->getTextField('posts', 'post'); // $entry->type->handle
        return strip_tags($entry->$field);

        foreach($entry->getFieldLayout()->getCustomFields() as $field) {
            if($this->isSocialBuddyField($field)) {
                $value = $entry->{$field->handle};
                if(version_compare(Craft::$app->getInfo()->version, '5.0', '<')) {
                    $value = Json::decode($value);
                }
                return strip_tags($value['text'] ?? '');
            }
        }
        return '';
    }

    public function getImage(Element $entry) : null|string {
//        return "https://convergine.nyc3.digitaloceanspaces.com/clients/supremarine/assets/images/_920x720_crop_center-center_none/138699/554757586.webp";
//        /** @var SettingsModel $settings */
        $settings = SocialBuddyPlugin::getInstance()->getSettings();
        $field = $settings->getImageField('posts', 'post'); // $entry->type->handle
        /** @var AssetQuery $asset */
        $asset = $entry->$field;
        return $asset->one()->getUrl();

        foreach($entry->getFieldLayout()->getCustomFields() as $field) {
            if($this->isSocialBuddyField($field)) {
                $value = $entry->{$field->handle};
                if(version_compare(Craft::$app->getInfo()->version, '5.0', '<')) {
                    $value = Json::decode($value);
                }
                $image = $value['image'] ?? null;
                if(!empty($image)) {
                    /** @var Asset $asset */
                    $asset = Craft::$app->getAssets()->getAssetById($image[0]);
                    if($asset) {
                        return $asset->url;
                    }
                }
            }
        }
        return '';
    }

    public function getBoard(ElementInterface $entry) : string {
        foreach($entry->getFieldLayout()->getCustomFields() as $field) {
            if($this->isSocialBuddyField($field)) {
                $value = $entry->{$field->handle};
                if(version_compare(Craft::$app->getInfo()->version, '5.0', '<')) {
                    $value = Json::decode($value);
                }
                return strip_tags($value['board'] ?? '');
            }
        }
        return '';
    }

    private function isSocialBuddyField($field) : bool {
        return in_array((new ReflectionClass($field))->getName(), [
            'convergine\socialbuddy\fields\SocialBuddyField'
        ]);
    }

    private function isTextField($field) : bool {
        return in_array((new ReflectionClass($field))->getName(), [
            'craft\fields\PlainText',
            'craft\redactor\Field',
            'craft\ckeditor\Field'
        ]);
    }

    private function isImageField($field) : bool {
        return in_array((new ReflectionClass($field))->getName(), [
            'craft\fields\Assets'
        ]);
    }
}
