<?php

namespace chalk\cameraman;

use chalk\cameraman\movement\Movement;
use chalk\cameraman\task\CameraTask;
use pocketmine\player\Player;
use pocketmine\level\Location;
use pocketmine\world\Position;

class Camera {
    /** @var Player */
    private $target;

    /** @var Movement[] */
    private $movements = [];

    /** @var float */
    private $slowness;

    /** @var int */
    private $taskId = -1;

    /** @var int */
    private $gamemode;

    /** @var Location */
    private $location;

    /**
     * @param Player $target
     * @param Movement[] $movements
     * @param float $slowness
     */
    function __construct(Player $target, array $movements, float $slowness){
        $this->target = $target;
        $this->movements = $movements;
        $this->slowness = $slowness;
    }

    /**
     * @return Player
     */
    public function getTarget(): Player {
        return $this->target;
    }

    /**
     * @return Movement[]
     */
    public function getMovements(): array {
        return $this->movements;
    }

    /**
     * @param int $index
     * @return Movement
     */
    public function getMovement(int $index): Movement {
        return $this->movements[$index];
    }

    /**
     * @return float
     */
    public function getSlowness(): float {
        return $this->slowness;
    }

    /**
     * @return bool
     */
    public function isRunning(): bool {
        return $this->taskId !== -1;
    }

    public function start(): void {
        if(!$this->isRunning()){
            Cameraman::getInstance()->sendMessage($this->getTarget(), "message-travelling-will-start");

            $this->location = $this->getTarget()->getLocation();
            $this->gamemode = $this->getTarget()->getGamemode();

            $this->getTarget()->setGamemode(Player::SPECTATOR);

            $this->taskId = Cameraman::getInstance()->getScheduler()->scheduleDelayedRepeatingTask(
                new CameraTask($this), 
                Cameraman::DELAY, 
                20 / Cameraman::TICKS_PER_SECOND
            )->getTaskId();
        }
    }

    public function stop(): void {
        if($this->isRunning()){
            Cameraman::getInstance()->getScheduler()->cancelTask($this->taskId); 
            $this->taskId = -1;

            $this->getTarget()->teleport($this->location);
            $this->getTarget()->setGamemode($this->gamemode);

            Cameraman::getInstance()->sendMessage($this->getTarget(), "message-travelling-finished");
        }
    }
}
