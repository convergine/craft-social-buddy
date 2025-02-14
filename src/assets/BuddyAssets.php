<?php
namespace convergine\socialbuddy\assets;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

class BuddyAssets extends AssetBundle {
	public function init() : void {
        $port = getenv('WEBPACK_DEV_SERVER_PORT');
        if(!empty($port)) {
            $this->sourcePath = null;
            $this->baseUrl = 'http://localhost:'.getenv('WEBPACK_DEV_SERVER_PORT');
        } else {
            $this->sourcePath = '@convergine/socialbuddy/assets/dist';
        }
		$this->depends = [CpAsset::class];
		$this->js = ['socialbuddy.js', 'tag-it.min.js'];
		$this->css = ['socialbuddy.css', 'jquery.tagit.css', 'tagit.ui-zendesk.css'];
		parent::init();
	}
}
