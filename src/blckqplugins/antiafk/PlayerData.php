<?php

namespace blckqplugins\antiafk;

use pocketmine\player\Player;

class PlayerData {
    private int $lastActivity;
    private bool $afk = false;
    private bool $warned = false;

    public function __construct(Player $player) {
        $this->lastActivity = time();
    }

    public function updateActivity(): void {
        $this->lastActivity = time();
        $this->warned = false;
    }

    public function getLastActivity(): int {
        return $this->lastActivity;
    }

    public function isAfk(): bool {
        return $this->afk;
    }

    public function setAfk(bool $afk): void {
        $this->afk = $afk;
    }

    public function isWarned(): bool {
        return $this->warned;
    }

    public function setWarned(bool $warned): void {
        $this->warned = $warned;
    }
}