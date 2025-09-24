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
    private array $config;

    public function __construct() {
        $this->config = SocialBuddyPlugin::getInstance()->getSettings()->toArray();
    }

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
        $settings = SocialBuddyPlugin::getInstance()->getSettings();
        
        // Build social networks array based on connected status
        $social = [];
        
        if ($settings->isFacebookEnabled()) {
            $social['facebook'] = 'Facebook';
        }
        
        if ($settings->isInstagramEnabled()) {
            $social['instagram'] = 'Instagram';
        }
        
        if ($settings->isTwitterEnabled()) {
            $social['twitter'] = 'X(Twitter)';
        }
        
        if ($settings->isPinterestEnabled()) {
            $social['pinterest'] = 'Pinterest';
        }
        
        if ($settings->isLinkedinEnabled()) {
            $social['linkedin'] = 'LinkedIn';
        }
        
        if ($settings->isTelegramEnabled()) {
            $social['telegram'] = 'Telegram';
        }
        
        if ($settings->isMediumEnabled()) {
            $social['medium'] = 'Medium';
        }
        
        // Only add 'All' option if at least one platform is enabled
        if (!empty($social)) {
            $social = ['all' => 'All'] + $social;
        }
        
        return Craft::$app->view->renderTemplate('convergine-socialbuddy/entry/_control.twig',[
            'social' => $social
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
        foreach($entry->getFieldLayout()->getCustomFields() as $field) {
            if($this->isSocialBuddyField($field)) {
                $value = $entry->{$field->handle};
                if(version_compare(Craft::$app->getInfo()->version, '5.0', '<')) {
                    $value = Json::decode($value);
                }
                $text = strip_tags($value['text'] ?? '');
                
                // If text is empty, use fallback from settings
                if (empty($text)) {
                    $text = $this->getFallbackText($entry);
                }
                
                return $text;
            }
        }
        
        // If no SocialBuddy field found, use fallback from settings
        return $this->getFallbackText($entry);
    }

    public function getImage(Element $entry) : array {
        Craft::info("SocialBuddy getImage: Starting image retrieval for entry ID: " . $entry->id, __METHOD__);
        
        foreach($entry->getFieldLayout()->getCustomFields() as $field) {
            Craft::info("SocialBuddy getImage: Checking field: " . $field->handle . " (type: " . get_class($field) . ")", __METHOD__);
            
            if($this->isSocialBuddyField($field)) {
                Craft::info("SocialBuddy getImage: Found SocialBuddyField: " . $field->handle, __METHOD__);
                
                $value = $entry->{$field->handle};
                if(version_compare(Craft::$app->getInfo()->version, '5.0', '<')) {
                    $value = Json::decode($value);
                }
                
                Craft::info("SocialBuddy getImage: Field value: " . json_encode($value), __METHOD__);
                
                $images = $value['image'] ?? null;
                Craft::info("SocialBuddy getImage: Images data: " . json_encode($images), __METHOD__);
                $imageUrls = [];
                
                if(!empty($images) && is_array($images)) {
                    Craft::info("SocialBuddy getImage: Processing " . count($images) . " image(s)", __METHOD__);
                    
                    foreach($images as $index => $imageId) {
                        Craft::info("SocialBuddy getImage: Processing image " . ($index + 1) . " with ID: " . $imageId, __METHOD__);
                        
                        /** @var Asset $asset */
                        $asset = Craft::$app->getAssets()->getAssetById($imageId);
                        if($asset) {
                            $imageUrls[] = $asset->url;
                            Craft::info("SocialBuddy getImage: Added image URL: " . $asset->url, __METHOD__);
                        } else {
                            Craft::warning("SocialBuddy getImage: Asset not found for ID: " . $imageId, __METHOD__);
                        }
                    }
                }
                
                // If no images found in custom field, use fallback from settings
                if (empty($imageUrls)) {
                    Craft::info("SocialBuddy getImage: No images in custom field, trying fallback", __METHOD__);
                    $imageUrls = $this->getFallbackImages($entry);
                }
                
                Craft::info("SocialBuddy getImage: Returning " . count($imageUrls) . " image URLs: " . json_encode($imageUrls), __METHOD__);
                return $imageUrls;
            }
        }
        
        // If no SocialBuddy field found, use fallback from settings
        Craft::info("SocialBuddy getImage: No SocialBuddyField found, using fallback from settings", __METHOD__);
        return $this->getFallbackImages($entry);
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

    public function getPostfix(ElementInterface $entry) : string {
        foreach($entry->getFieldLayout()->getCustomFields() as $field) {
            if($this->isSocialBuddyField($field)) {
                $value = $entry->{$field->handle};
                if(version_compare(Craft::$app->getInfo()->version, '5.0', '<')) {
                    $value = Json::decode($value);
                }
                $postfix = $value['postfix'] ?? '';
                
                // If postfix is empty, use default
                if (empty(trim(strip_tags($postfix)))) {
                    $postfix = 'Click here to learn more: <a href="'. $entry->getUrl() . '" target="_blank">' . $entry->getUrl() . '</a>';
                }
                
                // For social media, convert HTML links to plain text format
                // Convert <a href="url">text</a> to just the URL for platforms like Twitter
                $postfix = preg_replace('/<a[^>]*href="([^"]*)"[^>]*>.*?<\/a>/i', '$1', $postfix);
                return strip_tags($postfix);
            }
        }
        
        // If no SocialBuddy field found, return default postfix
        return 'Click here to learn more: ' . $entry->getUrl();
    }

    public function getHashtag(ElementInterface $entry) : string {
        foreach($entry->getFieldLayout()->getCustomFields() as $field) {
            if($this->isSocialBuddyField($field)) {
                $value = $entry->{$field->handle};
                if(version_compare(Craft::$app->getInfo()->version, '5.0', '<')) {
                    $value = Json::decode($value);
                }
                return strip_tags($value['hashtag'] ?? '');
            }
        }
        return '';
    }

    public function getMediumContent(Entry $entry): string
    {
        // Use the same 'text' field logic as other platforms
        return $this->getText($entry);
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

    /**
     * Get entry handles for settings lookup
     */
    private function getEntryHandles(ElementInterface $entry): array {
        return [
            'sectionHandle' => $entry !== null ? $entry->getSection()->handle : null,
            'entryHandle' => $entry !== null ? $entry->getType()->handle : null
        ];
    }

    /**
     * Get fallback text from configured field in settings
     */
    private function getFallbackText(ElementInterface $entry): string {
        $settings = SocialBuddyPlugin::getInstance()->getSettings();
        $handles = $this->getEntryHandles($entry);
        
        $textFieldHandle = $settings->getTextField($handles['sectionHandle'], $handles['entryHandle']);
        
        if (!empty($textFieldHandle) && $entry->{$textFieldHandle}) {
            return strip_tags($entry->{$textFieldHandle});
        }
        
        return '';
    }

    /**
     * Get fallback images from configured field in settings
     */
    private function getFallbackImages(Element $entry): array {
        $settings = SocialBuddyPlugin::getInstance()->getSettings();
        $handles = $this->getEntryHandles($entry);
        
        $imageFieldHandle = $settings->getImageField($handles['sectionHandle'], $handles['entryHandle']);
        
        $imageUrls = [];
        if (!empty($imageFieldHandle) && $entry->{$imageFieldHandle}) {
            $fallbackAssets = $entry->{$imageFieldHandle}->all();
            foreach($fallbackAssets as $asset) {
                if($asset instanceof Asset) {
                    $imageUrls[] = $asset->url;
                    Craft::info("SocialBuddy getImage: Added fallback image URL: " . $asset->url, __METHOD__);
                }
            }
        }
        
        return $imageUrls;
    }
}
