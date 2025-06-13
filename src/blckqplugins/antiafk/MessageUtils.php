<?php

namespace blckqplugins\antiafk;

use pocketmine\player\Player;
use pocketmine\utils\Config;

class MessageUtils {
    private Config $config;

    public function __construct(Config $config) {
        $this->config = $config;
    }

    public function getKickReason(): string {
        return $this->config->get("kick-reason", "You were kicked for being AFK.");
    }

    public function formatMessage(string $type, Player $player, array $placeholders = []): string {
        $message = $this->config->get("messages")[$type] ?? "";

        $replacements = array_merge([
            "player" => $player->getName(),
            "prefix" => AntiAFK::$prefix,
        ], $placeholders);

        foreach ($replacements as $key => $value) {
            $message = str_replace("{" . $key . "}", (string)$value, $message);
        }

        return str_replace("&", "§", $message);
    }
}