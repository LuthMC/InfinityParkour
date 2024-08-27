<?php

namespace Luthfi\InfinityParkour;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\player\Player;
use pocketmine\utils\Config;
use pocketmine\world\generator\GeneratorManager;
use pocketmine\scheduler\TaskScheduler;
use pocketmine\block\Block;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\console\ConsoleCommandSender;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageByBlockEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\math\Vector3;
use pocketmine\world\generator\Flat;
use pocketmine\utils\TextFormat as TF;
use pocketmine\world\generator\FlatGeneratorOptions;

class Main extends PluginBase implements Listener {

    private $parkourWorlds = [];
    private $distances = [];
    private $config;

    public function onEnable(): void {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->saveDefaultConfig();
        $this->config = $this->getConfig();
    }

    public function onDisable(): void {
        foreach ($this->parkourWorlds as $world) {
            $this->deleteWorld($world);
        }
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        if ($sender instanceof Player) {
            $this->startParkour($sender);
        } else {
            $sender->sendMessage(TF::RED . "This command can only be used in-game.");
        }
        return true;
    }

    private function startParkour(Player $player) {
        $worldName = "parkour_" . $player->getName();
        $this->createWorld($worldName);
        $this->parkourWorlds[$player->getName()] = $worldName;
        $player->teleport($this->getServer()->getLevelByName($worldName)->getSafeSpawn());
        $player->sendMessage(TF::GREEN . "Starting InfinityParkour!");
        $this->distances[$player->getName()] = 0;
        $this->updateScoreboard($player);
    }

    private function createWorld(string $worldName) {
        $levelManager = $this->getServer()->getLevelManager();
        $options = new GenerationOptions([
            "preset" => "2;7,2x3,2;1;",
        ]);
        $levelManager->generateLevel($worldName, $options, GeneratorManager::getGenerator(Flat::class));
        $level = $levelManager->loadLevel($worldName);
        $this->getScheduler()->scheduleRepeatingTask(new ParkourTask($level, $this), 20);
    }

    private function deleteWorld(string $worldName) {
        $levelManager = $this->getServer()->getLevelManager();
        if ($levelManager->isLevelGenerated($worldName)) {
            $level = $levelManager->getLevelByName($worldName);
            $levelManager->unloadLevel($level, true);
            $path = $this->getServer()->getDataPath() . "worlds/" . $worldName;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            }
        }
    }

    private function deleteDirectory(string $dir) {
        if (!file_exists($dir)) return;
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            is_dir("$dir/$file") ? $this->deleteDirectory("$dir/$file") : unlink("$dir/$file");
        }
        rmdir($dir);
    }

    public function onPlayerQuit(PlayerQuitEvent $event) {
        $player = $event->getPlayer();
        if (isset($this->parkourWorlds[$player->getName()])) {
            $this->deleteWorld($this->parkourWorlds[$player->getName()]);
            unset($this->parkourWorlds[$player->getName()]);
        }
    }

    public function onPlayerMove(PlayerMoveEvent $event) {
        $player = $event->getPlayer();
        if (isset($this->parkourWorlds[$player->getName()])) {
            $currentY = $event->getTo()->getY();
            if ($currentY < 0) {
                $player->teleport($player->getLevel()->getSafeSpawn());
                $this->distances[$player->getName()] = 0;
                $player->sendMessage(TF::RED . "You fell! Try again.");
            } else {
                $distance = (int)$event->getTo()->getX();
                if ($distance > $this->distances[$player->getName()]) {
                    $this->distances[$player->getName()] = $distance;
                    $this->updateScoreboard($player);
                }
            }
        }
    }

    private function updateScoreboard(Player $player) {
    $distance = $this->distances[$player->getName()];
    $scoreboardTitle = $this->config->getNested("scoreboard.title", "Parkour");

    $scoreboard = $player->getScoreboard();
    if ($scoreboard === null) {
        $scoreboard = new Scoreboard($player, $scoreboardTitle);
    }

    $scoreboard->clearLines();
    $scoreboard->addLine(1, "§eDistance: " . $distance . " blocks");
    $scoreboard->addLine(2, "§aPlayer: " . $player->getName());
    $scoreboard->addLine(3, "§cGoal: Reach the end!");

    $player->setScoreboard($scoreboard);
    }
}

class ParkourTask extends \pocketmine\scheduler\Task {

    private $level;

    public function __construct(Level $level, Main $plugin) {
        $this->level = $level;
    }

    public function onRun(int $currentTick) {
        $this->generateParkourSection();
    }

    private function generateParkourSection() {
        $x = 100;
        $y = 64;
        $z = 0;
        for ($i = 0; $i < 5; $i++) {
            $this->level->setBlock(new Vector3($x, $y, $z), Block::get(Block::GRASS));
            $x += rand(2, 4);
            $y += rand(-1, 1);
        }
    }
}
