<?php

namespace convergine\socialbuddy\fields;

use convergine\socialbuddy\models\SettingsModel;
use convergine\socialbuddy\SocialBuddyPlugin;
use Craft;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\elements\Asset;
use craft\fields\PlainText;
use craft\helpers\Json;
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

        if(is_string($value)) {
            $value = Json::decode($value);
        }

        $elements = array();
        if(isset($value['image']) && is_array($value['image'])) {
            foreach($value['image'] as $assetId) {
                $asset = Craft::$app->getAssets()->getAssetById($assetId);
                if($asset) {
                    $elements[] = $asset;
                }
            }
        }

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
            'limit' => 1,
            'storageKey' => 'AssetsSelectModal',
            'fieldId' => 'image',
            'canUpload' => false,
            'fsType' => 'craft',
            'defaultFieldLayoutId' => '',
            'selectionLabel' => Craft::t('app', 'Select an image'),
            'jsClass' => 'Craft.AssetSelectInput',
        ]);

        $textFieldHtml = Craft::$app->view->renderTemplate('_components/fieldtypes/PlainText/input', [
            'name' => $this->handle . '[text]',
            'value' => $value['text'] ?? '',
            'id' => $namespacedId . '-text',
            'placeholder' => '',
            'field' => new PlainText([
                'multiline' => true,
                'initialRows' => 7
            ]),
            'orientation' => $this->getOrientation($element),
        ]);

        $boardFieldHtml = $settings->isPinterestEnabled() ? Craft::$app->view->renderTemplate('_components/fieldtypes/PlainText/input', [
            'name' => $this->handle . '[board]',
            'value' => !empty($value['board']) ? $value['board'] : $settings->pinterestDefaultBoard,
            'id' => $namespacedId . '-board',
            'placeholder' => '',
            'field' => new PlainText([
                'multiline' => false
            ]),
            'orientation' => $this->getOrientation($element)
        ]) : null;

        $fieldHtml = Craft::$app->view->renderTemplate('convergine-socialbuddy/_components/fieldtypes/SocialBuddyField/input', [
            'imageFieldHtml' => $imageFieldHtml,
            'textFieldHtml' => $textFieldHtml,
            'boardFieldHtml' => $boardFieldHtml,
            'imageUrl' => isset($value['image'][0]) ? Craft::$app->getAssets()->getAssetById($value['image'][0])->getUrl() : '',
            'text' => isset($value['text']) ? nl2br($value['text']) : '',
            'board' => !empty($value['board']) ? $value['board'] : $settings->pinterestDefaultBoard,
            'isPinterestEnabled' => $settings->isPinterestEnabled(),
            'isFacebookEnabled' => $settings->isFacebookEnabled(),
            'isTelegramEnabled' => $settings->isTelegramEnabled()
        ]);

        //$fieldHtml = Json::encode($value);

        return $fieldHtml;
    }
}
