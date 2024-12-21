<?php

namespace chalk\cameraman;

use chalk\cameraman\movement\Movement;
use chalk\cameraman\movement\StraightMovement;
use chalk\cameraman\task\AutoSaveTask;
use chalk\utils\Messages;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\level\Level;
use pocketmine\level\Location;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\server\Server;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;

class Cameraman extends PluginBase implements Listener {
    /** @var Cameraman */
    private static $instance = null;

    /**
     * @return Cameraman
     */
    public static function getInstance(): Cameraman{
        return self::$instance;
    }

    /* ====================================================================================================================== *
     *                                                    GLOBAL VARIABLES                                                    *
     * ====================================================================================================================== */

    const TICKS_PER_SECOND = 10;
    const DELAY = 100;

    /** @var Location[][] */
    private $waypointMap = [];

    /** @var Camera[] */
    private $cameras = [];

    /* ====================================================================================================================== *
     *                                                    EVENT LISTENERS                                                     *
     * ====================================================================================================================== */

    public function onLoad(): void {
        self::$instance = $this;
    }

    public function onEnable(): void {
        $this->loadConfigs();
        $this->loadMessages();

        Server::getInstance()->getPluginManager()->registerEvents($this, $this);
        $this->getScheduler()->scheduleRepeatingTask(new AutoSaveTask(), 20 * 60 * 15); //15m
    }

    public function onDisable(): void {
        $this->saveConfigs();
    }

    public function onPlayerQuit(PlayerQuitEvent $event): void {
        if(($camera = $this->getCamera($event->getPlayer())) !== null && $camera->isRunning()){
            $camera->stop();
        }
    }

    public function onDataPacketReceive(DataPacketReceiveEvent $event): void {
        if($event->getPacket() instanceof MovePlayerPacket && ($camera = $this->getCamera($event->getPlayer())) !== null && $camera->isRunning()){
            $event->setCancelled(true);
        }
    }

    /* ====================================================================================================================== *
     *                                                    RESOURCE CONTROL                                                    *
     * ====================================================================================================================== */

    /** @var Messages */
    private $messages = null;
    const MESSAGE_VERSION = 1;

    public function loadMessages(): void {
        @mkdir($this->getDataFolder());
        $this->updateMessages("messages.yml");
        $this->messages = new Messages((new Config($this->getDataFolder() . "messages.yml", Config::YAML))->getAll());
    }

    /**
     * @param string $filename
     */
    public function updateMessages($filename = "messages.yml"): void {
        $this->saveResource($filename, false);

        $messages = (new Config($this->getDataFolder() . $filename, Config::YAML))->getAll();
        if(!isset($messages["version"]) || $messages["version"] < self::MESSAGE_VERSION){
            $this->saveResource($filename, true);
        }
    }

    /**
     * @return Messages
     */
    public function getMessages(): Messages {
        return $this->messages;
    }

    public function loadConfigs(): void {
        @mkdir($this->getDataFolder());
        $config = new Config($this->getDataFolder() . "waypoint-map.json", Config::JSON);

        foreach($config->getAll() as $key => $waypoints){
            $this->waypointMap[$key] = [];
            foreach($waypoints as $waypoint){
                $x = floatval($waypoint["x"]); $y = floatval($waypoint["y"]); $z = floatval($waypoint["y"]);
                $yaw = floatval($waypoint["yaw"]); $pitch = floatval($waypoint["pitch"]);
                $level = $this->getServer()->getLevelByName($waypoint["level"]);

                $this->waypointMap[$key][] = new Location($x, $y, $z, $yaw, $pitch, $level);
            }
        }
    }

    public function saveConfigs(): void {
        $waypointMap = [];

        foreach($this->getWaypointMap() as $key => $waypoints){
            if($key === null) continue;

            $waypointMap[$key] = [];
            foreach($waypoints as $waypoint){
                $waypointMap[$key][] = [
                    "x" => $waypoint->getX(), "y" => $waypoint->getY(), "z" => $waypoint->getZ(),
                    "yaw" => $waypoint->getYaw(), "pitch" => $waypoint->getPitch(),
                    "level" => $waypoint->isValid() ? $waypoint->getLevel()->getName() : null
                ];
            }
        }

        $config = new Config($this->getDataFolder() . "waypoint-map.json", Config::JSON);
        $config->setAll($waypointMap);
        $config->save();
    }

    /* ====================================================================================================================== *
     *                                                   GETTERS AND SETTERS                                                  *
     * ====================================================================================================================== */

    /**
     * @return Location[][]
     */
    public function getWaypointMap(): array {
        return $this->waypointMap;
    }

    /**
     * @param Location[][] $waypointMap
     * @return Location[][]
     */
    public function setWaypointMap(array $waypointMap): array {
        $this->waypointMap = $waypointMap;
        return $waypointMap;
    }

    /**
     * @param Player $player
     * @return Location[]|null
     */
    public function getWaypoints(Player $player): ?array {
        return isset($this->waypointMap[$player->getName()]) ? $this->waypointMap[$player->getName()] : null;
    }

    /**
     * @param Player $player
     * @param Location[] $waypoints
     * @return Location[]
     */
    public function setWaypoints(Player $player, array $waypoints): array {
        $this->waypointMap[$player->getName()] = $waypoints;
        return $waypoints;
    }

    /**
     * @param Player $player
     * @param Location $waypoint
     * @param int $index
     * @return Location[]
     */
    public function setWaypoint(Player $player, Location $waypoint, int $index = -1): array {
        if($index >= 0){
            $this->waypointMap[$player->getName()][$index] = $waypoint;
        } else {
            $this->waypointMap[$player->getName()][] = $waypoint;
        }
        return $this->waypointMap[$player->getName()];
    }

    /**
     * @return Camera[]
     */
    public function getCameras(): array {
        return $this->cameras;
    }

    /**
     * @param Player $player
     * @return Camera|null
     */
    public function getCamera(Player $player): ?Camera {
        return isset($this->cameras[$player->getName()]) ? $this->cameras[$player->getName()] : null;
    }

    /**
     * @param Player $player
     * @param Camera $camera
     * @return Camera
     */
    public function setCamera(Player $player, Camera $camera): Camera {
        $this->cameras[$player->getName()] = $camera;
        return $camera;
    }

    /* ====================================================================================================================== *
     *                                                     HELPER METHODS                                                     *
     * ====================================================================================================================== */

    /**
     * @param Location[] $waypoints
     * @return Movement[]
     */
    public static function createStraightMovements(array $waypoints): array {
        $lastWaypoint = null;

        $movements = [];
        foreach($waypoints as $waypoint){
            if($lastWaypoint !== null && !$waypoint->equals($lastWaypoint)){
                $movements[] = new StraightMovement($lastWaypoint, $waypoint);
            }
            $lastWaypoint = $waypoint;
        }
        return $movements;
    }

    /**
     * @param Player $player
     * @return bool|int
     */
    public static function sendMovePlayerPacket(Player $player) {
        $packet = new MovePlayerPacket();
        $packet->entityRuntimeId = $player->getId();
        $packet->position = $player->getPosition();
        $packet->yaw = $player->getYaw();
        $packet->headYaw = $player->getYaw();
        $packet->pitch = $player->getPitch();
        $packet->onGround = true;

        $player->sendDataPacket($packet);
    }

    /* ====================================================================================================================== *
     *                                                     MESSAGE SENDERS                                                    *
     * ====================================================================================================================== */

    private static $colorError = TextFormat::RESET . TextFormat::RED;
    private static $colorLight = TextFormat::RESET . TextFormat::GREEN;
    private static $colorDark  = TextFormat::RESET . TextFormat::DARK_GREEN;
    private static $colorTITLE = TextFormat::RESET . TextFormat::RED;

    private static $commandMap = [
        "1" => ["p", "start", "stop"],
        "2" => ["info", "goto", "clear"],
        "3" => ["help", "about"]
    ];

    /**
     * @param CommandSender $sender
     * @param string $key
     * @param array $format
     * @return bool
     */
    public function onCommand(CommandSender $sender, Command $command, string $commandAlias, array $args): bool {
        if($sender === null){
            return false;
        }

        $prefix = Cameraman::$colorTitle . $this->getMessages()->getMessage("prefix") . Cameraman::$colorLight;

        if(count($args) < 1) {
            return $this->sendHelpMessages($sender);
        }

        $commandKey = strtolower($args[0]);
        if(in_array($commandKey, self::$commands)) {
            switch($commandKey) {
                case "help":
                    return $this->sendHelpMessages($sender);
                case "about":
                    return $this->sendAboutMessages($sender);
                case "p":
                    return $this->handlePCommand($sender, $args);
                case "start":
                    return $this->handleStartCommand($sender, $args);
                case "stop":
                    return $this->handleStopCommand($sender);
                case "info":
                    return $this->handleInfoCommand($sender);
                default:
                    return $this->sendUnknownCommandErrorMessage($sender);
            }
        }
        return false;
    }

    /**
     * Handle "p" command for setting waypoints
     * 
     * @param CommandSender $sender
     * @param array $args
     * @return bool
     */
    public function handlePCommand(CommandSender $sender, array $args): bool {
        if(!$sender instanceof Player){
            $this->sendMessage($sender, "#error-only-in-game");
            return true;
        }

        $waypoints = $this->getWaypoints($sender);
        if ($waypoints === null) {
            $waypoints = $this->setWaypoints($sender, []);
        }

        if(count($args) > 1 && is_numeric($args[1])) {
            $index = intval($args[1]);
            if ($this->checkIndex($index, $waypoints, $sender)) {
                return true;
            }

            $this->setWaypoint($sender, $sender->getLocation(), $index - 1);
            $this->sendMessage($sender, "message-reset-waypoint", ["index" => $index, "total" => count($waypoints)]);
        } else {
            $this->setWaypoint($sender, $sender->getLocation());
            $this->sendMessage($sender, "message-added-waypoint", ["index" => count($waypoints)]);
        }
        return true;
    }

    /**
     * Handle "start" command for starting camera movement
     * 
     * @param CommandSender $sender
     * @param array $args
     * @return bool
     */
    public function handleStartCommand(CommandSender $sender, array $args): bool {
        if(count($args) < 2 || !is_numeric($args[1])) {
            return $this->sendHelpMessages($sender, $args[0]);
        }

        $waypoints = $this->getWaypoints($sender);
        if($this->checkIndex(intval($args[1]), $waypoints, $sender)) {
            return true;
        }

        $camera = new Camera($sender);
        $camera->start($waypoints, intval($args[1]) - 1);
        $this->setCamera($sender, $camera);
        $this->sendMessage($sender, "message-start-camera", ["index" => intval($args[1])]);
        return true;
    }

    /**
     * Handle "stop" command for stopping camera movement
     * 
     * @param CommandSender $sender
     * @return bool
     */
    public function handleStopCommand(CommandSender $sender): bool {
        $camera = $this->getCamera($sender);
        if($camera !== null) {
            $camera->stop();
            $this->sendMessage($sender, "message-stop-camera");
        } else {
            $this->sendMessage($sender, "#error-no-active-camera");
        }
        return true;
    }

    /**
     * Handle "info" command for camera info
     * 
     * @param CommandSender $sender
     * @return bool
     */
    public function handleInfoCommand(CommandSender $sender): bool {
        $camera = $this->getCamera($sender);
        if($camera !== null) {
            $this->sendMessage($sender, "message-camera-info", ["status" => $camera->isRunning() ? "running" : "stopped"]);
        } else {
            $this->sendMessage($sender, "#error-no-active-camera");
        }
        return true;
    }

    /**
     * Send message to a sender
     * 
     * @param CommandSender $sender
     * @param string $key
     * @param array $format
     * @return void
     */
    public function sendMessage(CommandSender $sender, string $key, array $format = []): void {
        $message = $this->getMessages()->getMessage($key, $format);
        $sender->sendMessage($message);
    }

    /**
     * Check if index is out of bounds
     * 
     * @param int $index
     * @param array $array
     * @param CommandSender|null $sender
     * @return bool
     */
    public function checkIndex(int $index, array $array, CommandSender $sender = null): bool {
        if($index < 1 || $index > count($array)) {
            $this->sendMessage($sender, "#error-index-out-of-bounds", ["total" => count($array)]);
            return true;
        }
        return false;
    }
    
    /**
     * @param CommandSender $sender
     * @param Command $command
     * @param string $commandAlias
     * @param array $args
     * @return bool
     */
    public function onCommand(CommandSender $sender, Command $command, string $commandAlias, array $args) : bool{
        if(!$sender instanceof Player){
            $this->sendMessage($sender, "#error-only-in-game");
            return true;
        }

        if(count($args) < 1){
            return $this->sendHelpMessages($sender);
        }

        switch(strToLower($args[0])){
            default:
                $this->sendUnknownCommandErrorMessage($sender);
                break;

            case "help":
                if(count($args) > 1){
                    return $this->sendHelpMessages($sender, $args[1]);
                }else{
                    return $this->sendHelpMessages($sender);
                }

            case "about":
                return $this->sendAboutMessages($sender);

            case "p":
                if(($waypoints = $this->getWaypoints($sender)) === null){
                    $waypoints = $this->setWaypoints($sender, []);
                }

                if(count($args) > 1 and is_numeric($args[1])){
                    if($this->checkIndex($index = intval($args[1]), $waypoints, $sender)){
                        return true;
                    }

                    $waypoints = $this->setWaypoint($sender, $sender->getLocation(), $index - 1);
                    $this->sendMessage($sender, "message-reset-waypoint", ["index" => $index, "total" => count($waypoints)]);
                }else{
                    $waypoints = $this->setWaypoint($sender, $sender->getLocation());
                    $this->sendMessage($sender, "message-added-waypoint", ["index" => count($waypoints)]);
                }
                break;

            case "start":
                if(count($args) < 2 or !is_numeric($args[1])){
                    return $this->sendHelpMessages($sender, $args[0]);
                }

                if(($waypoints = $this->getWaypoints($sender)) === null or count($waypoints) < 2){
                    $this->sendMessage($sender, "#error-too-few-waypoints");
                    return $this->sendHelpMessages($sender, "p");
                }

                if(($slowness = doubleval($args[1])) < 0.0000001){
                    return $this->sendMessage($sender, "#error-negative-slowness", ["slowness" => $slowness]);
                }

                if(($camera = $this->getCamera($sender)) !== null and $camera->isRunning()){
                    $this->sendMessage($sender, ".message-interrupting-current-travel");
                    $camera->stop();
                }

                $this->setCamera($sender, new Camera($sender, Cameraman::createStraightMovements($waypoints), $slowness))->start();
                break;

            case "stop":
                if(($camera = $this->getCamera($sender)) === null or !$camera->isRunning()){
                    return $this->sendMessage($sender, "#error-travels-already-interrupted");
                }

                $camera->stop(); unset($camera);
                $this->sendMessage($sender, "message-travelling-interrupted");
                break;

            case "info":
                if(($waypoints = $this->getWaypoints($sender)) === null or count($waypoints) === 0){
                    return $this->sendMessage($sender, "#error-no-waypoints-to-show");
                }

                if(count($args) > 1 and is_numeric($args[1])){
                    if($this->checkIndex($index = intval($args[1]), $waypoints, $sender)){
                        return true;
                    }

                    $this->sendWaypointMessage($sender, $waypoints[$index - 1], $index);
                }else{
                    foreach($waypoints as $index => $waypoint){
                        $this->sendWaypointMessage($sender, $waypoint, $index + 1);
                    }
                }
                break;

            case "goto":
                if(count($args) < 2 or !is_numeric($args[1])){
                    return $this->sendHelpMessages($sender, $args[0]);
                }

                if(($waypoints = $this->getWaypoints($sender)) === null or count($waypoints) === 0){
                    return $this->sendMessage($sender, "#error-no-waypoints-to-teleport");
                }

                if($this->checkIndex($index = intval($args[1]), $waypoints, $sender)){
                    return true;
                }

                $sender->teleport($waypoints[$index - 1]);
                $this->sendMessage($sender, "message-teleported", ["index" => $index]);
                break;

            case "clear":
                if(($waypoints = $this->getWaypoints($sender)) === null or count($waypoints) === 0){
                    return $this->sendMessage($sender, "#error-no-waypoints-to-remove");
                }

                if(count($args) > 1 and is_numeric($args[1])){
                    if($this->checkIndex($index = intval($args[1]), $waypoints, $sender)){
                        return true;
                    }

                    array_splice($waypoints, $index - 1, 1);
                    $this->sendMessage($sender, "message-removed-waypoint", ["index" => $index, "total" => count($waypoints)]);
                }else{
                    $waypoints = [];
                    $this->sendMessage($sender, "message-all-waypoint-removed");
                }
                $this->setWaypoints($sender, $waypoints);
                break;
        }
        return true;
    }
}
