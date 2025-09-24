<?php
namespace convergine\socialbuddy;

use convergine\socialbuddy\fields\SocialBuddyField;
use convergine\socialbuddy\models\SettingsModel;
use convergine\socialbuddy\queue\SubmitPost;
use convergine\socialbuddy\console\SocialBuddyController;
use convergine\socialbuddy\services\Facebook;
use convergine\socialbuddy\services\Instagram;
use convergine\socialbuddy\services\Twitter;
use convergine\socialbuddy\services\Pinterest;
use convergine\socialbuddy\services\Platform;
use convergine\socialbuddy\services\Post;
use convergine\socialbuddy\services\Telegram;
use convergine\socialbuddy\services\Medium;
use convergine\socialbuddy\services\Linkedin;
use Craft;
use craft\base\Element;
use craft\base\Plugin;
use yii\base\Component;
use yii\base\Behavior;
use yii\base\Event;
use yii\caching\CacheInterface;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\console\widgets\Table;
use yii\helpers\BaseConsole as ConsoleHelper;
use craft\elements\Entry;
use craft\base\ElementInterface;
use craft\events\DefineHtmlEvent;
use craft\events\ModelEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\ElementEvent;
use craft\helpers\Queue;
use craft\helpers\UrlHelper;
use craft\services\Fields;
use craft\web\twig\variables\CraftVariable;
use convergine\socialbuddy\variables\BuddyVariable;
use craft\web\UrlManager;
use craft\console\Application as ConsoleApplication;
use yii\base\BootstrapInterface;


/**
 * @property Pinterest $pinterest
 * @property Facebook $facebook
 * @property Telegram $telegram
 * @property Post $post
 */
class SocialBuddyPlugin extends Plugin {
    public const PLATFORM_PINTEREST = 'pinterest';
    public const PLATFORM_FACEBOOK = 'facebook';
	public const PLATFORM_INSTAGRAM = 'instagram';
	public const PLATFORM_TWITTER = 'twitter';
    public const PLATFORM_TELEGRAM = 'telegram';
	public const PLATFORM_MEDIUM = 'medium';
	public const PLATFORM_LINKEDIN = 'linkedin';

	public const EDITION_LITE = 'lite';
	public const EDITION_STANDARD = 'standard';
    public const EDITION_PRO = 'pro';

    public static function editions(): array
    {
        return [
            self::EDITION_LITE,
			self::EDITION_STANDARD,
            self::EDITION_PRO,
        ];
    }


	public static $plugin;
	public ?string $name = 'Social Buddy';

	public function init() {
		$this->hasCpSection = true;
		$this->hasCpSettings = true;
		parent::init();

		$this->_setComponents();
		$this->_setRoutes();
		$this->_setEvents();
		$this->_setConsoleCommands();
	}

	protected function _setComponents() : void {
		$this->setComponents([
			'platform' => Platform::class,
			'pinterest' => Pinterest::class,
            'facebook' => Facebook::class,
			'instagram' => Instagram::class,
			'twitter' => Twitter::class,
            'telegram' => Telegram::class,
			'medium' => Medium::class,
			'linkedin' => Linkedin::class,
            'post' => Post::class
		]);
	}

	protected function _setRoutes() : void {
		// Register CP routes
		Event::on(
			UrlManager::class,
			UrlManager::EVENT_REGISTER_CP_URL_RULES,
			function (RegisterUrlRulesEvent $event) {
                $event->rules['convergine-socialbuddy/fb_auth'] = 'convergine-socialbuddy/facebook/fb-auth';
                $event->rules['convergine-socialbuddy/fb_conn'] = 'convergine-socialbuddy/facebook/fb-conn';
                $event->rules['convergine-socialbuddy/pin_auth'] = 'convergine-socialbuddy/pinterest/pin-auth';
                $event->rules['convergine-socialbuddy/pin_conn'] = 'convergine-socialbuddy/pinterest/pin-conn';
                $event->rules['convergine-socialbuddy/pin_disconnect'] = 'convergine-socialbuddy/pinterest/pin-disconnect';
                $event->rules['convergine-socialbuddy/ig_conn'] = 'convergine-socialbuddy/facebook/ig-conn';
                $event->rules['convergine-socialbuddy/linkedin-auth'] = 'convergine-socialbuddy/linkedin/linkedin-auth';
                $event->rules['convergine-socialbuddy/linkedin-conn'] = 'convergine-socialbuddy/linkedin/linkedin-conn';
                $event->rules['convergine-socialbuddy/twitter_auth'] = 'convergine-socialbuddy/twitter/twitter-auth';
                $event->rules['convergine-socialbuddy/twitter_callback'] = 'convergine-socialbuddy/twitter/twitter-callback';
                $event->rules['convergine-socialbuddy/twitter_conn'] = 'convergine-socialbuddy/twitter/twitter-conn';
                $event->rules['convergine-socialbuddy/twitter_disconnect'] = 'convergine-socialbuddy/twitter/twitter-disconnect';
                $event->rules['convergine-socialbuddy/medium/connect'] = 'convergine-socialbuddy/medium/connect';
                $event->rules['convergine-socialbuddy/medium/disconnect'] = 'convergine-socialbuddy/medium/disconnect';
                $event->rules['convergine-socialbuddy/medium/status'] = 'convergine-socialbuddy/medium/status';
                $event->rules['convergine-socialbuddy/disconnect'] = 'convergine-socialbuddy/settings/disconnect';
				$event->rules['convergine-socialbuddy/dashboard'] = 'convergine-socialbuddy/dashboard';
                $event->rules['convergine-socialbuddy/settings/general'] = 'convergine-socialbuddy/settings/general';
                $event->rules['convergine-socialbuddy/settings/pinterest'] = 'convergine-socialbuddy/settings/pinterest';
                $event->rules['convergine-socialbuddy/settings/facebook'] = 'convergine-socialbuddy/settings/facebook';
                $event->rules['convergine-socialbuddy/settings/telegram'] = 'convergine-socialbuddy/settings/telegram';
                $event->rules['convergine-socialbuddy/settings/fields'] = 'convergine-socialbuddy/settings/fields';
				$event->rules['convergine-socialbuddy/settings/instagram'] = 'convergine-socialbuddy/settings/instagram';
				$event->rules['convergine-socialbuddy/settings/twitter'] = 'convergine-socialbuddy/settings/twitter';
				$event->rules['convergine-socialbuddy/settings/linkedin'] = 'convergine-socialbuddy/settings/linkedin';
				$event->rules['convergine-socialbuddy/settings/medium'] = 'convergine-socialbuddy/settings/medium';
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
                    // Check if this entry type is enabled for social posting
                    if ($event->sender instanceof Entry) {
                        $settings = $this->getSettings();
                        $sectionHandle = $event->sender->getSection()->handle;
                        $entryTypeHandle = $event->sender->getType()->handle;
                        $isEnabled = $settings->getEnabled($sectionHandle, $entryTypeHandle);
                        
                        if ($isEnabled) {
                            $event->html .= $this->post->getSubmitControl();
                        }
                    } else {
                        // For non-Entry elements, do not show any additional controls
                    }
                }
            }
        );

	}

	protected function _setConsoleCommands(): void {
		if (Craft::$app instanceof ConsoleApplication) {
			Craft::$app->controllerMap['social-buddy'] = SocialBuddyController::class;
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
			$nav['subnav']['dashboard'] = [
				'label' => Craft::t('convergine-socialbuddy', 'Dashboard'),
				'url' => 'convergine-socialbuddy/dashboard',
			];

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

	public function getDashboardResponse(): mixed {
		return Craft::$app->controller->redirect(UrlHelper::cpUrl('convergine-socialbuddy/dashboard'));
	}

}
