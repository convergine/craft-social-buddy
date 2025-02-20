<?php
namespace convergine\socialbuddy;

use convergine\socialbuddy\fields\SocialBuddyField;
use convergine\socialbuddy\models\SettingsModel;
use convergine\socialbuddy\queue\SubmitPost;
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
use craft\helpers\Queue;
use craft\helpers\UrlHelper;
use craft\services\Fields;
use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;
use yii\base\BootstrapInterface;


class StatusChangeEvent extends Event
{
    // Properties
    // =========================================================================

    /**
     * @var ElementInterface|null The element model associated with the event.
     */
    public $element;

    /**
     * @var string Previous status
     */
    public $statusBeforeSave = '';

    // Public Methods
    // =========================================================================

    /**
     * @param string $nameOfStatus
     *
     * @return bool
     */
    public function changedTo(string $nameOfStatus): bool
    {
        return ($this->element->getStatus() === $nameOfStatus);
    }

    /**
     * @return bool
     */
    public function changedToPublished(): bool
    {
       return in_array($this->element->getStatus(), [Entry::STATUS_LIVE, Element::STATUS_ENABLED]);
    }

    /**
     * @return bool
     */
    public function changedToUnpublished(): bool
    {
        return !$this->changedToPublished();
    }

    /**
     * @return ElementInterface|null
     */
    public function getElement()
    {
        return $this->element;
    }
}


class ScheduledElements extends Controller
{
    // Constants
    // =========================================================================

    const LAST_CHECK_CACHE_KEY = 'lastScheduledCheck';
    const LAST_CHECK_DEFAULT_INTERVAL = '-24 hours';
    const DATE_FORMAT = 'Y-m-d H:i';

    // Properties
    // =========================================================================

    /**
     * @var CacheInterface
     */
    protected $cache;

    // Public Methods
    // =========================================================================

    /**
     * Element Status Change
     *
     * @param string $id
     * @param Module $module
     * @param CacheInterface $cache
     * @param array $config
     */
    public function __construct(string $id, Module $module, CacheInterface $cache, array $config = [])
    {
        $this->cache = $cache;
        parent::__construct($id, $module, $config);
    }


    /**
     * Checks for scheduled Entries, call this command via cron
     *
     * @param string $forcedCheckInterval Time string of lower bound of the range, e.g. '-2 hours'
     *
     * @return int
     */
    public function actionScheduled($forcedCheckInterval = null)
    {
        $lastCheck = $this->cache->exists(self::LAST_CHECK_CACHE_KEY)
            ? $this->cache->get(self::LAST_CHECK_CACHE_KEY)
            : Db::prepareDateForDb((new \DateTime())->modify(self::LAST_CHECK_DEFAULT_INTERVAL));

        if ($forcedCheckInterval) {
            $lastCheck = Db::prepareDateForDb((new \DateTime())->modify($forcedCheckInterval));
        }

        $now       = Db::prepareDateForDb(new \DateTime());
        $published = $this->getPublishedEntries($lastCheck, $now);
        $expired   = $this->getExpiredEntries($lastCheck, $now);
        $entries   = array_merge($published, $expired);

        // Remember this check
        $this->cache->set(self::LAST_CHECK_CACHE_KEY, $now);

        // Print info
        ConsoleHelper::output(sprintf("> Expired Entries: %d", count($expired)));
        ConsoleHelper::output(sprintf("> Published Entries: %d", count($published)));
        ConsoleHelper::output(sprintf("> Range: %s to %s", $lastCheck, $now));

        if (!count($entries)) {
            return ExitCode::OK;
        }

        $this->fireEvent($published, Entry::STATUS_PENDING);
        $this->fireEvent($expired, Entry::STATUS_LIVE);

        $this->drawTable($entries);

        return ExitCode::OK;
    }

    /**
     * @param array $entries
     */
    protected function drawTable(array $entries)
    {
        $rows = [];

        foreach ($entries as $entry) {
            /** @var Entry $entry */
            $postDateString   = $entry->postDate ? $entry->postDate->format(self::DATE_FORMAT) : '-';
            $expiryDateString = $entry->expiryDate ? $entry->expiryDate->format(self::DATE_FORMAT) : '-';
            $rows[]           = [$entry->title, $postDateString, $expiryDateString];
        };

        echo Table::widget([
            'headers' => ['Title', 'PostDate', 'ExpiryDate'],
            'rows'    => $rows,
        ]);

    }

    /**
     * @param array  $elements
     * @param string $previousStatus
     */
    protected function fireEvent(array $elements, $previousStatus = '')
    {
        if (count($elements) === 0) {
            return;
        }
        foreach ($elements as $element) {
            Event::trigger(
                ElementStatusEvents::class,
                ElementStatusEvents::EVENT_STATUS_CHANGED,
                new StatusChangeEvent([
                    'element'          => $element,
                    'statusBeforeSave' => $previousStatus
                ])
            );
        }
    }


    /**
     * @param $rangeStart
     * @param $rangeEnd
     *
     * @return array
     */
    protected function getPublishedEntries($rangeStart, $rangeEnd): array
    {
        // TODO: Support Product and other Elements with postDate

        // Entries published within time frame
        $entries = (Entry::find()
            ->where(['between', 'postDate', $rangeStart, $rangeEnd])
            ->withStructure(false)
            ->orderBy(null)
            ->status('live')
        )->all();

        // Exclude manually published entries (postDate ≅ dateUpdated)
        return array_filter($entries, function (Entry $item) {
            $diffInSeconds = abs($item->postDate->getTimestamp() - $item->dateUpdated->getTimestamp());
            return ($diffInSeconds > 60);
        });
    }

    /**
     * @param $rangeStart
     * @param $rangeEnd
     *
     * @return Entry[]
     */
    protected function getExpiredEntries($rangeStart, $rangeEnd): array
    {
        // TODO: Support Product and other Elements with expiryDate

        return (Entry::find()
            ->where(['between', 'expiryDate', $rangeStart, $rangeEnd])
            ->withStructure(false)
            ->orderBy(null)
            ->status('expired')
        )->all();
    }
}

class ElementStatusBehavior extends Behavior
{
    // Properties
    // =========================================================================

    /**
     * @var string
     */
    public $statusBeforeSave = '';

    // Public Methods
    // =========================================================================

    /**
     * Saves the status of an element before it is saved
     */
    public function rememberPreviousStatus()
    {
        /** @var Element $element */
        $element = $this->owner;

        $originalElement = Craft::$app->getElements()->getElementById(
            $element->id,
            get_class($element),
            $element->siteId
        );

        $this->statusBeforeSave = $originalElement === null ?: $originalElement->getStatus();
    }

    /**
     * Triggers an event if the status has changed
     */
    public function fireEventOnChange()
    {
        /** @var Element $element */
        $element = $this->owner;

        // Nothing changed?
        if ($this->statusBeforeSave === $element->getStatus()) {
            return;
        }

        if (Event::hasHandlers(ElementStatusEvents::class, ElementStatusEvents::EVENT_STATUS_CHANGED)) {
            Event::trigger(
                ElementStatusEvents::class,
                ElementStatusEvents::EVENT_STATUS_CHANGED,
                new StatusChangeEvent([
                    'element' => $element,
                    'statusBeforeSave' => $this->statusBeforeSave
                ])
            );
        }
    }
}


class ElementStatusEvents extends Component implements BootstrapInterface
{
    // Constants
    // =========================================================================

    const EVENT_STATUS_CHANGED = 'statusChanged';

    // Public Methods
    // =========================================================================

    /**
     * Register console command
     *
     * @param CraftConsoleApp $app
     * @param string $group
     */
    public static function registerScheduledCommand(CraftConsoleApp $app, $group = 'element-status-events')
    {
        $app->controllerMap[$group] = ScheduledElements::class;
    }

    /**
     * Bootstrap the extension
     *
     * @param YiiApp $app
     */
    public function bootstrap($app)
    {
        // Make sure it's Craft
        if (!($app instanceof CraftWebApp || $app instanceof CraftConsoleApp)) {
            return;
        }

        Event::on(Elements::class, Elements::EVENT_BEFORE_SAVE_ELEMENT, [$this, 'rememberPreviousStatus']);
        Event::on(Elements::class, Elements::EVENT_AFTER_SAVE_ELEMENT, [$this, 'fireEventOnChange']);

        if ($app instanceof CraftConsoleApp) {
            // Tell Craft about the concrete implementation of CacheInterface
            Craft::$container->set(CacheInterface::class, Craft::$app->getCache());
            self::registerScheduledCommand($app);
        }
    }

    /**
     * Register event listener
     *
     * @param ElementEvent $event
     */
    public function rememberPreviousStatus(ElementEvent $event)
    {
        /** @var Element|ElementStatusBehavior $element */
        $element = $event->element;

        // Attach behavior to access the status later
        $element->attachBehavior('elementStatusEvents', ElementStatusBehavior::class);

        // No need to remember anything
        if ($event->isNew) {
            return;
        }

        $element->rememberPreviousStatus();
    }

    /**
     * Register event listener
     *
     * @param ElementEvent $event
     */
    public function fireEventOnChange(ElementEvent $event)
    {
        /** @var Element|ElementStatusBehavior $element */
        $element = $event->element;

        // Fire ElementStatusEvents::EVENT_STATUS_CHANGED
        if ($element->getBehavior('elementStatusEvents') !== null) {
            $element->fireEventOnChange();
        }
    }
}


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
                $event->rules['convergine-socialbuddy/ig_conn'] = 'convergine-socialbuddy/facebook/ig-conn';
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
                    $event->html .= $this->post->getSubmitControl();
                }
            }
        );

        $settings = $this->getSettings();
        if($settings->mode == 'automatic') {
            Event::on(
				ElementStatusEvents::class, 
				ElementStatusEvents::EVENT_STATUS_CHANGED, 
                function(StatusChangeEvent $event) {
					$isLive      = $event->changedToPublished();
					if($isLive) {
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
