<?php

namespace blckqplugins\antiafk;

use pocketmine\event\Listener;
use pocketmine\event\player\{
    PlayerJoinEvent,
    PlayerQuitEvent,
    PlayerMoveEvent,
    PlayerInteractEvent,
    PlayerChatEvent
};
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\scheduler\Task;
use pocketmine\Server;

class AntiAFK extends PluginBase implements Listener {
    public static string $prefix = "§8[§cAntiAFK§8]§r ";

    /** @var array<string, PlayerData> */
    private array $playerDataMap = [];
    private Config $config;
    private MessageUtils $messageUtils;

    private int $afkTimeMinutes;
    private int $kickTimeMinutes;
    private int $warningTimeMinutes;
    private bool $broadcastAfk;
    private array $exemptPlayers;

    public function onEnable(): void {
        @mkdir($this->getDataFolder());
        $this->saveDefaultConfig();
        $this->config = $this->getConfig();

        $this->messageUtils = new MessageUtils($this->config);
        $this->loadConfig();

        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->getScheduler()->scheduleRepeatingTask(new class($this) extends Task {
            private AntiAFK $plugin;
            public function __construct(AntiAFK $plugin) {
                $this->plugin = $plugin;
            }
            public function onRun(): void {
                $this->plugin->checkPlayersAfkStatus();
            }
        }, 20 * 60); // Every minute

        $this->getLogger()->info("§aAntiAFK §ev" . $this->getDescription()->getVersion() . " §aenabled!");
        $this->getLogger()->info("§b-----------------------------------");
        $this->getLogger()->info("§eFollow us:");
        $this->getLogger()->info("§9• GitHub: §fhttps://github.com/BlckqPlugins");
        $this->getLogger()->info("§b-----------------------------------");
    }

    private function loadConfig(): void {
        $this->afkTimeMinutes = $this->config->get("afk-time", 5);
        $this->kickTimeMinutes = $this->config->get("kick-time", 10);
        $this->warningTimeMinutes = $this->config->get("warning-time", 2);
        $this->broadcastAfk = $this->config->get("broadcast-afk", true);
        $this->exemptPlayers = array_map("strtolower", $this->config->get("exempt-players", []));
    }

    public function checkPlayersAfkStatus(): void {
        $currentTime = time();

        foreach (Server::getInstance()->getOnlinePlayers() as $player) {
            if ($this->isPlayerExempt($player)) continue;

            $name = strtolower($player->getName());
            if (!isset($this->playerDataMap[$name])) {
                $this->playerDataMap[$name] = new PlayerData($player);
                continue;
            }

            $data = $this->playerDataMap[$name];
            $inactiveMinutes = (int)(($currentTime - $data->getLastActivity()) / 60);

            if ($inactiveMinutes >= $this->kickTimeMinutes) {
                $player->kick($this->messageUtils->getKickReason());
                unset($this->playerDataMap[$name]);
                continue;
            }

            if ($inactiveMinutes >= ($this->kickTimeMinutes - $this->warningTimeMinutes) && !$data->isWarned()) {
                $data->setWarned(true);
                $player->sendMessage($this->messageUtils->formatMessage("warning", $player, ["time" => $this->warningTimeMinutes]));
                continue;
            }

            if ($inactiveMinutes >= $this->afkTimeMinutes && !$data->isAfk()) {
                $data->setAfk(true);
                if ($this->broadcastAfk) {
                    Server::getInstance()->broadcastMessage($this->messageUtils->formatMessage("afk", $player));
                }
            }
        }
    }

    private function isPlayerExempt(Player $player): bool {
        return $player->hasPermission("antiafk.bypass") || in_array(strtolower($player->getName()), $this->exemptPlayers, true);
    }

    public function onPlayerJoin(PlayerJoinEvent $event): void {
        $player = $event->getPlayer();
        $this->playerDataMap[strtolower($player->getName())] = new PlayerData($player);
    }

    public function onPlayerQuit(PlayerQuitEvent $event): void {
        unset($this->playerDataMap[strtolower($event->getPlayer()->getName())]);
    }

    public function onPlayerMove(PlayerMoveEvent $event): void {
        $this->updatePlayerActivity($event->getPlayer());
    }

    public function onPlayerInteract(PlayerInteractEvent $event): void {
        $this->updatePlayerActivity($event->getPlayer());
    }

    public function onPlayerChat(PlayerChatEvent $event): void {
        $this->updatePlayerActivity($event->getPlayer());
    }

    private function updatePlayerActivity(Player $player): void {
        $name = strtolower($player->getName());

        if (!isset($this->playerDataMap[$name])) {
            $this->playerDataMap[$name] = new PlayerData($player);
            return;
        }

        $data = $this->playerDataMap[$name];

        if ($data->isAfk()) {
            $data->setAfk(false);
            if ($this->broadcastAfk) {
                Server::getInstance()->broadcastMessage($this->messageUtils->formatMessage("no-longer-afk", $player));
            }
        }

        $data->updateActivity();
    }
}