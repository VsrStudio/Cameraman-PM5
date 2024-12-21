<?php

/**
 * @author ChalkPE <chalkpe@gmail.com>
 * @since 2015-10-25 19:25
 */

namespace chalk\cameraman\task;

use chalk\cameraman\Cameraman;
use pocketmine\scheduler\Task;

class AutoSaveTask extends Task {
    /** @var Cameraman */
    private $plugin;

    public function __construct(){
        $this->plugin = Cameraman::getInstance();
    }

    public function onRun(int $currentTick): void {
        $this->plugin->saveConfigs();
    }
}
