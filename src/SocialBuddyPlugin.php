<?php
namespace convergine\socialbuddy;

use convergine\socialbuddy\fields\SocialBuddyField;
use convergine\socialbuddy\models\SettingsModel;
use convergine\socialbuddy\queue\SubmitPost;
use convergine\socialbuddy\services\Facebook;
use convergine\socialbuddy\services\Pinterest;
use convergine\socialbuddy\services\Post;
use convergine\socialbuddy\services\Telegram;
use Craft;
use craft\base\Element;
use craft\base\Plugin;
use craft\events\DefineHtmlEvent;
use craft\events\ModelEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\helpers\Queue;
use craft\helpers\UrlHelper;
use craft\services\Fields;
use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;
use yii\base\Event;

/**
 * @property Pinterest $pinterest
 * @property Facebook $facebook
 * @property Telegram $telegram
 * @property Post $post
 */
class SocialBuddyPlugin extends Plugin {
    public const PLATFORM_PINTEREST = 'pinterest';
    public const PLATFORM_FACEBOOK = 'facebook';
    public const PLATFORM_TELEGRAM = 'telegram';


	public static $plugin;
	public ?string $name = 'Social Buddy';

	public function init() {
		$this->hasCpSection = true;
		$this->hasCpSettings = true;
		parent::init();

		$this->_setComponents();
		$this->_setRoutes();
		$this->_setEvents();
	}

	protected function _setComponents() : void {
		$this->setComponents([
			'pinterest' => Pinterest::class,
            'facebook' => Facebook::class,
            'telegram' => Telegram::class,
            'post' => Post::class
		]);
	}

	protected function _setRoutes() : void {
		// Register CP routes
		Event::on(
			UrlManager::class,
			UrlManager::EVENT_REGISTER_CP_URL_RULES,
			function (RegisterUrlRulesEvent $event) {
                $event->rules['convergine-socialbuddy/settings/general'] = 'convergine-socialbuddy/settings/general';
                $event->rules['convergine-socialbuddy/settings/pinterest'] = 'convergine-socialbuddy/settings/pinterest';
                $event->rules['convergine-socialbuddy/settings/facebook'] = 'convergine-socialbuddy/settings/facebook';
                $event->rules['convergine-socialbuddy/settings/telegram'] = 'convergine-socialbuddy/settings/telegram';
                $event->rules['convergine-socialbuddy/settings/fields'] = 'convergine-socialbuddy/settings/fields';
			}
		);
	}

	protected function _setEvents() : void {
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function (Event $event) {
                $variable = $event->sender;
                $variable->set('socialbuddy', BuddyVariable::class);
            }
        );

        Event::on(
            Fields::class,
            Fields::EVENT_REGISTER_FIELD_TYPES,
            function(RegisterComponentTypesEvent $event) {
                $event->types[] = SocialBuddyField::class;
            }
        );

		Event::on(
			SocialBuddyPlugin::class,
			SocialBuddyPlugin::EVENT_AFTER_SAVE_SETTINGS,
			function(Event $event) {
				//Craft::dump($_POST);
			}
		);

        Event::on(
            Element::class,
            Element::EVENT_DEFINE_ADDITIONAL_BUTTONS,
            function (DefineHtmlEvent $event) {
                if (($event->sender->enabled && $event->sender->getEnabledForSite())) {
                    $event->html .= $this->post->getSubmitControl();
                }
            }
        );

        $settings = $this->getSettings();
        if($settings->mode == 'automatic') {
            Event::on(
                Element::class,
                Element::EVENT_AFTER_SAVE,
                function(ModelEvent $event) {
                    if($event->isNew) {
                        /** Entry @entry */
                        $entry = $event->sender;
                        Queue::push(
                            new SubmitPost([
                                'entryId' => $entry->id,
                                'platform' => 'all',
                            ]),10,0
                        );
                    }
                }
            );
        }
	}

	protected function createSettingsModel(): SettingsModel {
		return new SettingsModel();
	}

	/**
	 * @return string|null
	 * @throws \Twig\Error\LoaderError
	 * @throws \Twig\Error\RuntimeError
	 * @throws \Twig\Error\SyntaxError
	 * @throws \yii\base\Exception
	 */
	protected function settingsHtml(): ?string {
		return \Craft::$app->getView()->renderTemplate(
			'convergine-socialbuddy/settings',
			[ 'settings' => $this->getSettings() ]
		);
	}

	/**
	 * @return string
	 */
	public function getPluginName(): string {
		return $this->name;
	}

	/**
	 * @return array|null
	 */
	public function getCpNavItem(): ?array {
		$nav = parent::getCpNavItem();

		$nav['label'] = \Craft::t('convergine-socialbuddy', $this->getPluginName());
		$nav['url'] = 'convergine-socialbuddy';

		if(Craft::$app->getUser()->getIsAdmin()) {
			$nav['subnav']['settings'] = [
				'label' => Craft::t('convergine-socialbuddy', 'Settings'),
				'url' => 'convergine-socialbuddy/settings/general',
			];
		}

		return $nav;
	}

	/**
	 * @return mixed
	 */
	public function getSettingsResponse(): mixed {
		return Craft::$app->controller->redirect(UrlHelper::cpUrl('convergine-socialbuddy/settings/general'));
	}
}
