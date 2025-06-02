<?php

namespace convergine\socialbuddy\fields;

use convergine\socialbuddy\models\SettingsModel;
use convergine\socialbuddy\SocialBuddyPlugin;
use Craft;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\elements\Asset;
use craft\fields\PlainText;
use craft\fields\Dropdown;
use craft\helpers\Json;
use yii\helpers\Html;
use yii\db\Schema;

class SocialBuddyField extends Field {
    public ?int $charLimit = null;

    public static function displayName(): string {
        return Craft::t('app', 'Social Buddy');
    }

    public static function icon(): string {
        return Craft::$app->view->renderTemplate('convergine-socialbuddy/_components/fieldtypes/SocialBuddyField/icon.svg');
    }

    public function getContentColumnType(): string {
        return Schema::TYPE_TEXT;
    }

    public function getInputHtml($value, ElementInterface $element = null): string {
        $view = Craft::$app->getView();
        $id = $view->formatInputId($this->handle);
        $namespacedId = $view->namespaceInputId($id);

        /** @var SettingsModel $settings */
        $settings = SocialBuddyPlugin::getInstance()->getSettings();

        // Retrieve the entry's section handle and entry handle
        $sectionHandle = $element !== null ? $element->getSection()->handle : null;
        $entryHandle = $element !== null ? $element->getType()->handle : null; // Get the entry's handle

        // Get the field names from the settings arrays
        $imageFieldHandle = $settings->getImageField($sectionHandle, $entryHandle);
        $textFieldHandle = $settings->getTextField($sectionHandle, $entryHandle);

        if(is_string($value)) {
            $value = Json::decode($value);
        }

        if ($element !== null && $element->{$imageFieldHandle} && !is_string($element->{$imageFieldHandle}) && !isset($value['image'])) {
            $elements = $element->{$imageFieldHandle}->all();
        } else {
            $elements = [];
        
            if (isset($value['image']) && is_array($value['image'])) {
                foreach ($value['image'] as $assetId) {
                    $asset = Craft::$app->getAssets()->getAssetById($assetId);
                    if ($asset) {
                        $elements[] = $asset;
                    }
                }
            }
        }

        $imageUrl =  !empty($elements) ? $elements[0]->getUrl() : null;

        $imageFieldHtml = Craft::$app->view->renderTemplate('_components/fieldtypes/Assets/input', [
            'name' => $this->handle . '[image]',
            'id' => $namespacedId . '-image',
            'elementType' => Asset::class,
            'elements' => !empty($elements) ? $elements : null,
            'criteria' => ['kind' => 'image'],
            'useSingleFolder' => true,
            'viewMode' => 'large',
            'showSiteMenu' => false,
            'sources' => '*',
            'condition' => false,
            'sourceElementId' => null,
            'limit' => 10,
            'storageKey' => 'AssetsSelectModal',
            'fieldId' => 'image',
            'canUpload' => false,
            'fsType' => 'craft',
            'defaultFieldLayoutId' => '',
            'selectionLabel' => Craft::t('app', 'Select an image'),
            'jsClass' => 'Craft.AssetSelectInput',
        ]);

        // Get the entry's text field dynamically
        $entryText = $element !== null && $element->{$textFieldHandle} ? $element->{$textFieldHandle} : '';

        $textFieldHtml = Craft::$app->view->renderTemplate('_components/fieldtypes/PlainText/input', [
            'name' => $this->handle . '[text]',
            'value' => $value['text'] ?? $entryText,
            'id' => $namespacedId . '-text',
            'placeholder' => '',
            'field' => new PlainText([
                'multiline' => true,
                'initialRows' => 7
            ]),
            'orientation' => $this->getOrientation($element),
        ]);

        // Pinterest board dropdown
        $boardFieldHtml = null;
        if ($settings->isPinterestEnabled()) {
            $boards = SocialBuddyPlugin::getInstance()->pinterest->getBoards();
            $boardOptions = [];
            foreach ($boards as $board) {
                // Use id as value, name as label
                $boardOptions[$board['id']] = $board['name'] ?? $board['id'];
            }
            $selectedBoard = !empty($value['board']) ? $value['board'] : $settings->pinterestDefaultBoard;
            $boardFieldHtml = \yii\helpers\Html::dropDownList(
                $this->handle . '[board]',
                $selectedBoard,
                $boardOptions,
                [
                    'id' => $namespacedId . '-board',
                    'prompt' => 'Select a Pinterest board',
                ]
            );
        }

        $inputId = $namespacedId . '-hashtag';

        $hashtagFieldHtml = $settings->isFacebookEnabled() ? Craft::$app->view->renderTemplate('_components/fieldtypes/PlainText/input', [
            'name' => $this->handle . '[hashtag]',
            'value' => !empty($value['hashtag']) ? $value['hashtag'] : '',
            'id' => $namespacedId . '-hashtag',
            'placeholder' => '',
            'field' => new PlainText([
                'multiline' => true,
                'initialRows' => 2
            ]),
            'orientation' => $this->getOrientation($element)
        ]) : null;

        // TODO: get custom text before url from settings!!
        // $element !== null && $element instanceof Entry ? 
        $postfix = 'Click here to learn more: <a href="'. $element->getUrl() . '" target="_blank">' . $element->getUrl() . '</a>';

        $postfixFieldHtml = Craft::$app->view->renderTemplate('_components/fieldtypes/PlainText/input', [
            'name' => $this->handle . '[postfix]',
            'value' => !empty($value['postfix']) ? $value['postfix'] : $postfix,
            'id' => $namespacedId . '-postfix',
            'placeholder' => '',
            'field' => new PlainText([
                'multiline' => false
            ]),
            'orientation' => $this->getOrientation($element)
        ]);


        $mediumFieldHtml = Html::dropDownList(
            $this->handle . '[mediumField]',
            $value['mediumField'] ?? null,
            [
                'postExcept' => 'Except',
                'postContent' => 'Post Content'
            ],
            [
                'id' => $namespacedId . '-mediumField',
            ]
        );

        $fieldHtml = Craft::$app->view->renderTemplate('convergine-socialbuddy/_components/fieldtypes/SocialBuddyField/input', [
            'imageFieldHtml' => $imageFieldHtml,
            'textFieldHtml' => $textFieldHtml,
            'boardFieldHtml' => $boardFieldHtml,
            'hashtagFieldHtml' => $hashtagFieldHtml,
            'postfixFieldHtml' => $postfixFieldHtml,
            'mediumFieldHtml' => $mediumFieldHtml,
            'title' => $element->title ?? 'TITLE',
            'imageUrl' => isset($value['image'][0]) ? Craft::$app->getAssets()->getAssetById($value['image'][0])->getUrl() : $imageUrl,
            'text' => $value['text'] ?? $entryText, // isset($value['text']) ? nl2br($value['text']) : '',
            'board' => !empty($value['board']) ? $value['board'] : $settings->pinterestDefaultBoard,
            'hashtag' => !empty($value['hashtag']) ? $value['hashtag'] : '',
            'postfix' => !empty($value['postfix']) ? $value['postfix'] : $postfix,
            'isPinterestEnabled' => $settings->isPinterestEnabled(),
            'isFacebookEnabled' => $settings->isFacebookEnabled(),
            'isTelegramEnabled' => $settings->isTelegramEnabled(),
            'isMediumEnabled' => $settings->isMediumEnabled(),
            'isInstagramEnabled' => $settings->isInstagramEnabled()
        ]);

        //$fieldHtml = Json::encode($value);

        return $fieldHtml;
    }
}
