<?php

namespace convergine\socialbuddy\console;

use convergine\socialbuddy\fields\SocialBuddyField;
use convergine\socialbuddy\queue\SubmitPost;
use convergine\socialbuddy\SocialBuddyPlugin;
use Craft;
use craft\console\Controller;
use craft\elements\Entry;
use craft\helpers\Json;
use craft\helpers\Queue;
use yii\console\ExitCode;

class SocialBuddyController extends Controller
{
    /**
     * Check for entries that need to be posted to social media
     * Run this command via cron every hour: php craft social-buddy/scheduled
     */
    public function actionScheduled(): int
    {
        $this->stdout("Social Buddy: Starting scheduled posting check...\n");
        
        // Save cache flag to indicate cron job is running
        Craft::$app->getCache()->set('socialbuddy_cron_last_run', time(), 86400); // 24 hours
        
        // Get entries from the last 24 hours that are live/enabled
        $entries = $this->getEligibleEntries();
        
        $this->stdout("Social Buddy: Found " . count($entries) . " eligible entries\n");
        
        $processedCount = 0;
        
        foreach ($entries as $entry) {
            if ($this->shouldPostEntry($entry)) {
                $this->queueSocialPost($entry);
                $processedCount++;
                $this->stdout("Social Buddy: Queued post for entry '{$entry->title}' (ID: {$entry->id})\n");
            }
        }
        
        $this->stdout("Social Buddy: Processed {$processedCount} entries for social media posting\n");
        
        return ExitCode::OK;
    }
    
    /**
     * Get entries that are eligible for social media posting
     */
    private function getEligibleEntries(): array
    {
        // Calculate the datetime for 24 hours ago
        $twentyFourHoursAgo = (new \DateTime())->modify('-24 hours');
        
        $query = Entry::find()
            ->status(Entry::STATUS_ENABLED) // Only fully published entries
            ->andWhere([
                'or',
                // Condition 1: postDate exists and is within last 24h
                ['>=', 'postDate', $twentyFourHoursAgo->format('Y-m-d H:i:s')],
                // Condition 2: postDate is null/empty BUT dateCreated is within last 24h
                [
                    'and',
                    ['postDate' => null],
                    ['>=', 'elements.dateCreated', $twentyFourHoursAgo->format('Y-m-d H:i:s')]
                ]
            ])
            ->orderBy('postDate DESC, elements.dateCreated DESC'); // Order by both for consistency
            
        return $query->all();
    }
    
    /**
     * Check if an entry should be posted to social media
     */
    private function shouldPostEntry(Entry $entry): bool
    {
        // Check if entry type is enabled for social posts in plugin settings
        $settings = SocialBuddyPlugin::getInstance()->getSettings();
        $sectionHandle = $entry->getSection()->handle;
        $entryTypeHandle = $entry->getType()->handle;
        $isEnabled = $settings->getEnabled($sectionHandle, $entryTypeHandle);

        if (!$isEnabled) {
            $this->stdout("Social Buddy: Entry type '{$entryTypeHandle}' in section '{$sectionHandle}' is disabled for social publishing. Skipping entry '{$entry->title}' (ID: {$entry->id})\n");
            return false;
        }

        // Check if entry has SocialBuddyField and hasn't been published yet
        foreach ($entry->getFieldLayout()->getCustomFields() as $field) {
            if ($field instanceof SocialBuddyField) {
                $fieldValue = $entry->{$field->handle};
                // Decode field value if it's a string (Craft < 5.0)
                if (is_string($fieldValue)) {
                    $fieldValue = Json::decode($fieldValue);
                }
                // Check if already published
                $isPublished = isset($fieldValue['published']) && $fieldValue['published'] === true;
                if (!$isPublished) {
                    $this->stdout("Social Buddy: Entry '{$entry->title}' (ID: {$entry->id}) not yet published to social media\n");
                    return true;
                }
                break; // Only check the first SocialBuddyField
            }
        }
        return false;
    }
    
    /**
     * Queue a social media post for the entry
     */
    private function queueSocialPost(Entry $entry): void
    {
        try {
            Queue::push(
                new SubmitPost([
                    'entryId' => $entry->id,
                    'platform' => 'all',
                ]), 10, 0
            );
            
            Craft::info("Social Buddy Scheduled: Queued post for entry ID: {$entry->id}", __METHOD__);
            
        } catch (\Exception $e) {
            $this->stderr("Social Buddy: Error queuing post for entry '{$entry->title}' (ID: {$entry->id}): {$e->getMessage()}\n");
            Craft::error("Social Buddy Scheduled: Error queuing post for entry ID: {$entry->id} - {$e->getMessage()}", __METHOD__);
        }
    }
    
    /**
     * Manual command to check a specific entry
     */
    public function actionCheckEntry(int $entryId = null): int
    {
        if (!$entryId) {
            $this->stderr("Please provide an entry ID: php craft social-buddy/check-entry --entryId=123\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }
        
        $entry = Craft::$app->entries->getEntryById($entryId);
        
        if (!$entry) {
            $this->stderr("Entry with ID {$entryId} not found\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }
        
        $this->stdout("Checking entry '{$entry->title}' (ID: {$entry->id})\n");
        $this->stdout("Status: {$entry->getStatus()}\n");
        $this->stdout("Post Date: {$entry->postDate->format('Y-m-d H:i:s')}\n");
        
        if ($this->shouldPostEntry($entry)) {
            $this->stdout("Entry is eligible for social media posting\n");
            
            if ($this->confirm("Queue this entry for social media posting?")) {
                $this->queueSocialPost($entry);
                $this->stdout("Entry queued for social media posting\n");
            }
        } else {
            $this->stdout("Entry is NOT eligible for social media posting (already published or no SocialBuddyField)\n");
        }
        
        return ExitCode::OK;
    }
}
