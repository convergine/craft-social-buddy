<?php

namespace convergine\socialbuddy\migrations;

use craft\db\Migration;

/**
 * Install migration.
 */
class Install extends Migration {
	/**
	 * @inheritdoc
	 */
	public function safeUp(): bool {
		return true;
	}

	/**
	 * @inheritdoc
	 */
	public function safeDown(): bool {
		return true;
	}
}
