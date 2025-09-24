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
            Craft::info("SocialBuddy: Entry with ID {$this->entryId} not found, skipping", __METHOD__);
            return;
        }

        // Check if 'all' platform is used but no SocialBuddy custom field exists
        if ($this->platform === 'all' && !$this->hasSocialBuddyField($entry)) {
            $errorMessage = "Cannot publish to all platforms: Entry '{$entry->title}' (ID: {$entry->id}) does not have a SocialBuddy custom field. Please add a SocialBuddy field to this entry type or publish to individual platforms.";
            Craft::error("SocialBuddy: " . $errorMessage, __METHOD__);
            throw new \Exception($errorMessage);
        }

        // Check if entry has already been published to social media
        if ($this->isAlreadyPublished($entry) && $this->platform != 'all') {
            Craft::info("SocialBuddy: Entry '{$entry->title}' (ID: {$entry->id}) has already been published to social media, skipping", __METHOD__);
            return;
        }

        $post = SocialBuddyPlugin::getInstance()->post;
        $text = $post->getText($entry);
        $images = $post->getImage($entry);
        $board = $post->getBoard($entry);
        $postfix = $post->getPostfix($entry);
        $hashtag = $post->getHashtag($entry);

        $entryUrl = $entry->getUrl();

        // "Click here to learn more:" . $entryUrl
        $message = $text . "\n\n" . $postfix;

        Craft::info("SocialBuddy Text: " . $message, __METHOD__);
        Craft::info("SocialBuddy Images: " . json_encode($images), __METHOD__);
        Craft::info("SocialBuddy Board: " . $board, __METHOD__);
        
        // Use first image for platforms that only support single images, or null if no images
        $primaryImage = !empty($images) ? $images[0] : null;

        $this->setProgress($queue, 1);

        $publishedSuccessfully = false;

        $plugin = SocialBuddyPlugin::getInstance();
        $settings = $plugin->getSettings();

        switch($this->platform) {
            case 'pinterest':
                if ($settings->pinterestConnected > 0) {
                    $title = $entry->title;
                    $board_name = !empty($board) ? $board : $settings->pinterestDefaultBoard;
                    $result = $plugin->pinterest->publishPost($text,$title,$board_name,$primaryImage);
                    Craft::info("SocialBuddy Pinterest Result: " . json_encode($result), __METHOD__);
                    $publishedSuccessfully = $this->isSuccessResult($result);
                } else {
                    Craft::warning("SocialBuddy: Pinterest not connected, skipping Pinterest post for entry ID: " . $entry->id, __METHOD__);
                }
                break;
            case 'facebook':
                if ($settings->facebookConnected > 0) {
                    $result = $plugin->facebook->publishPost($message, $images);
                    Craft::info("SocialBuddy Facebook Result: " . json_encode($result), __METHOD__);
                    $publishedSuccessfully = $this->isSuccessResult($result);
                } else {
                    Craft::warning("SocialBuddy: Facebook not connected, skipping Facebook post for entry ID: " . $entry->id, __METHOD__);
                }
                break;
            case 'instagram':
                if ($settings->instagramConnected > 0) {
                    // Construct Instagram-specific post body from custom field components
                    $instagramBody = $this->constructInstagramPostBody($entry);
                    $result = $plugin->instagram->publishPost($instagramBody, $images);
                    Craft::info("SocialBuddy Instagram Result: " . json_encode($result), __METHOD__);
                    $publishedSuccessfully = $this->isSuccessResult($result);
                } else {
                    Craft::warning("SocialBuddy: Instagram not connected, skipping Instagram post for entry ID: " . $entry->id, __METHOD__);
                }
                break;
            case 'telegram':
                if (!empty($settings->telegramChannelAccount) && !empty($settings->telegramBotToken)) {
                    // Telegram-specific processing: crop text (max 500 chars)
                    $telegramMessage = $this->prepareMessageText($text, $postfix, 500);
                    
                    $result = $plugin->telegram->publishPost($telegramMessage, $images);
                    Craft::info("SocialBuddy Telegram Result: " . json_encode($result), __METHOD__);
                    $publishedSuccessfully = $this->isSuccessResult($result);
                } else {
                    Craft::warning("SocialBuddy: Telegram not connected (missing channel or bot token), skipping Telegram post for entry ID: " . $entry->id, __METHOD__);
                }
                break;
            case 'linkedin':
                if ($settings->linkedinConnected > 0) {
                    $result = $plugin->linkedin->publishPost($message, $images);
                    Craft::info("SocialBuddy LinkedIn Result: " . json_encode($result), __METHOD__);
                    $publishedSuccessfully = $this->isSuccessResult($result);
                } else {
                    Craft::warning("SocialBuddy: LinkedIn not connected, skipping LinkedIn post for entry ID: " . $entry->id, __METHOD__);
                }
                break;
            case 'twitter':
                if ($settings->twitterConnected > 0) {
                    // Twitter-specific processing: crop text and limit images
                    $twitterMessage = $this->prepareMessageText($text, $postfix, 250);
                    $twitterImages = $this->prepareTwitterImages($images);
                    
                    $result = $plugin->twitter->publishPost($twitterMessage, $twitterImages);
                    Craft::info("SocialBuddy Twitter Result: " . json_encode($result), __METHOD__);
                    $publishedSuccessfully = $this->isSuccessResult($result);
                } else {
                    Craft::warning("SocialBuddy: Twitter not connected, skipping Twitter post for entry ID: " . $entry->id, __METHOD__);
                }
                break;
            case 'medium':
                if ($settings->isMediumEnabled()) {
                    // Medium-specific processing: use full content from designated field
                    $mediumContent = $this->constructMediumPostBody($entry);
                    $title = $entry->title;
                    $tags = []; // You can extract tags from the entry if needed
                    $status = 'public'; // Default status
                    $result = $plugin->medium->publishPost($title, $mediumContent, $tags, $status);
                    Craft::info("SocialBuddy Medium Result: " . json_encode($result), __METHOD__);
                    $publishedSuccessfully = $this->isSuccessResult($result);
                } else {
                    Craft::warning("SocialBuddy: Medium not connected, skipping Medium post for entry ID: " . $entry->id, __METHOD__);
                }
                break;
            case 'all':
                $successCount = 0;
                $attemptedCount = 0;
                
                // Pinterest
                if ($settings->pinterestConnected > 0) {
                    $title = $entry->title;
                    $board_name = !empty($board) ? $board : $settings->pinterestDefaultBoard;
                    $result = $plugin->pinterest->publishPost($text,$title,$board_name,$primaryImage);
                    Craft::info("SocialBuddy Pinterest Result: " . json_encode($result), __METHOD__);
                    if ($this->isSuccessResult($result)) $successCount++;
                    $attemptedCount++;
                } else {
                    Craft::warning("SocialBuddy: Pinterest not connected, skipping Pinterest post for entry ID: " . $entry->id, __METHOD__);
                }

                $this->setProgress($queue, 1/5);
                
                // Facebook
                if ($settings->facebookConnected > 0) {
                    $result = $plugin->facebook->publishPost($text, $images);
                    Craft::info("SocialBuddy Facebook Result: " . json_encode($result), __METHOD__);
                    if ($this->isSuccessResult($result)) $successCount++;
                    $attemptedCount++;
                } else {
                    Craft::warning("SocialBuddy: Facebook not connected, skipping Facebook post for entry ID: " . $entry->id, __METHOD__);
                }

                $this->setProgress($queue, 2/5);
                
                // Instagram
                if ($settings->instagramConnected > 0) {
                    // Construct Instagram-specific post body from custom field components
                    $instagramBody = $this->constructInstagramPostBody($entry);
                    $result = $plugin->instagram->publishPost($instagramBody, $images);
                    Craft::info("SocialBuddy Instagram Result: " . json_encode($result), __METHOD__);
                    if ($this->isSuccessResult($result)) $successCount++;
                    $attemptedCount++;
                } else {
                    Craft::warning("SocialBuddy: Instagram not connected, skipping Instagram post for entry ID: " . $entry->id, __METHOD__);
                }

                $this->setProgress($queue, 3/5);
                
                // LinkedIn
                if ($settings->linkedinConnected > 0) {
                    $result = $plugin->linkedin->publishPost($message, $images);
                    Craft::info("SocialBuddy LinkedIn Result: " . json_encode($result), __METHOD__);
                    if ($this->isSuccessResult($result)) $successCount++;
                    $attemptedCount++;
                } else {
                    Craft::warning("SocialBuddy: LinkedIn not connected, skipping LinkedIn post for entry ID: " . $entry->id, __METHOD__);
                }

                $this->setProgress($queue, 4/6);
                
                // Twitter
                if ($settings->twitterConnected > 0) {
                    // Twitter-specific processing: crop text and limit images
                    $twitterMessage = $this->prepareMessageText($text, $postfix, 250);
                    $twitterImages = $this->prepareTwitterImages($images);
                    
                    $result = $plugin->twitter->publishPost($twitterMessage, $twitterImages);
                    Craft::info("SocialBuddy Twitter Result: " . json_encode($result), __METHOD__);
                    if ($this->isSuccessResult($result)) $successCount++;
                    $attemptedCount++;
                } else {
                    Craft::warning("SocialBuddy: Twitter not connected, skipping Twitter post for entry ID: " . $entry->id, __METHOD__);
                }

                $this->setProgress($queue, 5/7);
                
                // Medium
                if ($settings->isMediumEnabled()) {
                    // Medium-specific processing: use full content from designated field
                    $mediumContent = $this->constructMediumPostBody($entry);
                    $title = $entry->title;
                    $tags = []; // You can extract tags from the entry if needed
                    $status = 'public'; // Default status
                    $result = $plugin->medium->publishPost($title, $mediumContent, $tags, $status);
                    Craft::info("SocialBuddy Medium Result: " . json_encode($result), __METHOD__);
                    if ($this->isSuccessResult($result)) $successCount++;
                    $attemptedCount++;
                } else {
                    Craft::warning("SocialBuddy: Medium not connected, skipping Medium post for entry ID: " . $entry->id, __METHOD__);
                }

                $this->setProgress($queue, 6/7);
                
                // Telegram
                if (!empty($settings->telegramChannelAccount) && !empty($settings->telegramBotToken)) {
                    // Telegram-specific processing: crop text (max 500 chars)
                    $telegramMessage = $this->prepareMessageText($text, $postfix, 500);
                    
                    $result = $plugin->telegram->publishPost($telegramMessage, $images);
                    Craft::info("SocialBuddy Telegram Result: " . json_encode($result), __METHOD__);
                    if ($this->isSuccessResult($result)) $successCount++;
                    $attemptedCount++;
                } else {
                    Craft::warning("SocialBuddy: Telegram not connected (missing channel or bot token), skipping Telegram post for entry ID: " . $entry->id, __METHOD__);
                }
                
                // Consider successful if at least one platform succeeded
                $publishedSuccessfully = $successCount > 0;
                
                Craft::info("SocialBuddy: Posted to {$successCount} out of {$attemptedCount} connected platforms for entry ID: " . $entry->id, __METHOD__);
                break;
        }
        
        // Update published status if posting was successful for all platforms
        if ($publishedSuccessfully && $this->platform == 'all') {
            $this->updatePublishedStatus($entry);
        }
        
        $this->setProgress($queue, 1);
    }

    /**
     * Prepare message text with length limitations, preserving mandatory postfix
     */
    private function prepareMessageText(string $text, string $postfix, int $maxLength): string {
        $separator = "\n\n";
        
        // Calculate the required space for postfix and separator
        $postfixLength = strlen($postfix);
        $separatorLength = strlen($separator);
        $requiredPostfixSpace = $postfixLength + $separatorLength;
        
        // If postfix alone exceeds maxLength, we have a problem
        if ($requiredPostfixSpace > $maxLength) {
            Craft::warning("SocialBuddy: Postfix text is too long for platform limit ({$postfixLength} chars, limit: {$maxLength})", __METHOD__);
            // Return just the postfix, but log the issue
            return $postfix;
        }
        
        // Calculate available space for the main text
        $availableTextSpace = $maxLength - $requiredPostfixSpace;
        
        // If text fits within available space, return the full message
        if (strlen($text) <= $availableTextSpace) {
            return $text . $separator . $postfix;
        }
        
        // Need to crop the text portion while preserving postfix
        $croppedText = $text;
        
        // Try to find the first paragraph break or sentence end within available space
        $paragraphBreak = strpos($text, "\n\n");
        $sentenceEnd = strpos($text, ". ");
        
        // If we have a paragraph break within available space, use that
        if ($paragraphBreak !== false && $paragraphBreak <= $availableTextSpace) {
            $croppedText = substr($text, 0, $paragraphBreak);
        }
        // If we have a sentence end within available space, use that
        else if ($sentenceEnd !== false && $sentenceEnd <= $availableTextSpace) {
            $croppedText = substr($text, 0, $sentenceEnd + 1); // Include the period
        }
        // Otherwise, crop at word boundary near the available space limit
        else {
            $croppedText = substr($text, 0, $availableTextSpace - 3); // Reserve space for ellipsis
            $lastSpace = strrpos($croppedText, ' ');
            
            if ($lastSpace !== false) {
                $croppedText = substr($croppedText, 0, $lastSpace);
            }
            
            // Add ellipsis to indicate text was cropped
            $croppedText .= '...';
        }
        
        return $croppedText . $separator . $postfix;
    }

    /**
     * Prepare images for Twitter (max 4 images)
     */
    private function prepareTwitterImages(array $images): array {
        // Twitter allows maximum 4 images
        if (count($images) > 4) {
            Craft::info("SocialBuddy: Limiting Twitter post to first 4 images (had " . count($images) . " images)", __METHOD__);
            return array_slice($images, 0, 4);
        }
        
        return $images;
    }

    private function constructInstagramPostBody($entry): string {
        try {
            $post = SocialBuddyPlugin::getInstance()->post;
            
            // Use the Post service methods to get components
            $text = $post->getText($entry);
            $postfix = $post->getPostfix($entry);
            $hashtag = $post->getHashtag($entry);
            
            // Construct Instagram post body
            $instagramBody = $text;
            
            if (!empty($postfix)) {
                $instagramBody .= "\n\n" . $postfix;
            }
            
            if (!empty($hashtag)) {
                // Ensure hashtags start with # if not already present
                $hashtags = explode(' ', $hashtag);
                $formattedHashtags = array_map(function($tag) {
                    $tag = trim($tag);
                    return !empty($tag) && $tag[0] !== '#' ? '#' . $tag : $tag;
                }, $hashtags);
                $instagramBody .= "\n\n" . implode(' ', $formattedHashtags);
            }
            
            Craft::info("SocialBuddy: Constructed Instagram post body for entry ID {$entry->id}: " . $instagramBody, __METHOD__);
            return $instagramBody;
        } catch (\Exception $e) {
            Craft::error("SocialBuddy: Error constructing Instagram post body for entry ID: {$entry->id} - {$e->getMessage()}", __METHOD__);
        }
        
        // Fallback to generic message if error occurred
        $post = SocialBuddyPlugin::getInstance()->post;
        $text = $post->getText($entry);
        $entryUrl = $entry->getUrl();
        return $text . "\n\nClick here to learn more: " . $entryUrl;
    }

    private function constructMediumPostBody($entry): string {
        try {
            $post = SocialBuddyPlugin::getInstance()->post;
            
            // Use the same text + postfix approach as other platforms
            $text = $post->getText($entry);
            $postfix = $post->getPostfix($entry);
            
            $fullContent = $text;
            if (!empty($postfix)) {
                $fullContent .= "\n\n" . $postfix;
            }
            
            Craft::info("SocialBuddy: Using standard text content for Medium post for entry ID {$entry->id}", __METHOD__);
            return $fullContent;
            
        } catch (\Exception $e) {
            Craft::error("SocialBuddy: Error constructing Medium post body for entry ID: {$entry->id} - {$e->getMessage()}", __METHOD__);
        }
        
        // Final fallback 
        $post = SocialBuddyPlugin::getInstance()->post;
        $text = $post->getText($entry);
        $entryUrl = $entry->getUrl();
        return $text . "\n\nRead the full article: " . $entryUrl;
    }

    private function isSuccessResult($result): bool {
        // You'll need to adjust this based on how your publishing methods return success/failure
        // This is a generic implementation - modify based on your actual result format
        if (is_array($result)) {
            return !isset($result['error']) && (isset($result['success']) ? $result['success'] : true);
        }
        return $result !== false && $result !== null;
    }

    private function isAlreadyPublished($entry): bool {
        try {
            foreach($entry->getFieldLayout()->getCustomFields() as $field) {
                if($field instanceof \convergine\socialbuddy\fields\SocialBuddyField) {
                    $fieldValue = $entry->{$field->handle};
                    
                    // Decode field value if it's a string (Craft < 5.0)
                    if(is_string($fieldValue)) {
                        $fieldValue = \craft\helpers\Json::decode($fieldValue);
                    }
                    
                    // Check if already published
                    $isPublished = isset($fieldValue['published']) && $fieldValue['published'] === true;
                    
                    if ($isPublished) {
                        Craft::info("SocialBuddy: Entry ID {$entry->id} already marked as published", __METHOD__);
                        return true;
                    }
                    
                    break; // Only check the first SocialBuddyField found
                }
            }
        } catch (\Exception $e) {
            Craft::error("SocialBuddy: Error checking published status for entry ID: {$entry->id} - {$e->getMessage()}", __METHOD__);
            // In case of error, assume not published to be safe
            return false;
        }
        
        return false;
    }

    private function updatePublishedStatus($entry): void {
        try {
            foreach($entry->getFieldLayout()->getCustomFields() as $field) {
                if($field instanceof \convergine\socialbuddy\fields\SocialBuddyField) {
                    $fieldValue = $entry->{$field->handle};
                    
                    // Decode field value if it's a string (Craft < 5.0)
                    if(is_string($fieldValue)) {
                        $fieldValue = \craft\helpers\Json::decode($fieldValue);
                    }
                    
                    // Set published flag to true
                    $fieldValue['published'] = true;
                    $entry->setFieldValue($field->handle, $fieldValue);
                    
                    // Save the entry with the updated field value
                    $saved = Craft::$app->getElements()->saveElement($entry, false);
                    
                    if ($saved) {
                        Craft::info("SocialBuddy: Updated published status for entry ID: " . $entry->id, __METHOD__);
                    } else {
                        Craft::error("SocialBuddy: Failed to update published status for entry ID: " . $entry->id, __METHOD__);
                    }
                    
                    break; // Only process the first SocialBuddyField found
                }
            }
        } catch (\Exception $e) {
            Craft::error("SocialBuddy: Error updating published status for entry ID: " . $entry->id . " - " . $e->getMessage(), __METHOD__);
        }
    }

    /**
     * Check if the entry has a SocialBuddy custom field
     */
    private function hasSocialBuddyField($entry): bool {
        foreach($entry->getFieldLayout()->getCustomFields() as $field) {
            if($field instanceof \convergine\socialbuddy\fields\SocialBuddyField) {
                return true;
            }
        }
        return false;
    }

    protected function defaultDescription() : string {
        return Craft::t('convergine-socialbuddy','Submitting post to '.$this->platform);
    }
}
