<?php

/**
 * @name ExtendsLib
 * @author Neo-Developer
 * @main Neo\ExtendsLib
 * @version 0.1.0
 * @api 4.0.6
 */

 namespace Neo;

use Closure;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\item\ItemFactory;
use pocketmine\network\mcpe\protocol\BossEventPacket;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\Server;

class ExtendsLib extends PluginBase {

    public static function executePlayers(Closure $function) : void {
        foreach( Server::getInstance()->getOnlinePlayers() as $players ) {

            $function($players);
            
        }
    }

    public static function setItem(Player $player, int $slot, $id, string $name = "", int $count = 1) : void{
        $player->getInventory()->setItem($slot, ($item = ItemFactory::getInstance()->get($id)->setCount($count))->setCustomName(isset($name) ? $name : $item->getName()) );
    }

    public static function sendPackets(Player $player, array $packets) : void {
        foreach($packets as $packet) {
            $player->getNetworkSession()->sendDataPacket($packet);
        }
    }

    public static function sendBossBarPacket(Player $player,string $title = '', float $percent = 1.0) : void{
        ExtendsLib::sendPackets($player, [
            BossEventPacket::show(
                $player->getId(),
                '',
                0.0
            ),
            BossEventPacket::title(
                $player->getId(),
                $title
            ),
            BossEventPacket::healthPercent(
                $player->getId(),
                $percent
            )
        ]);
    }

    public static function hideBossBarPacket(Player $player) : void {
        $player->getNetworkSession()->sendDataPacket(
            BossEventPacket::hide($player->getId())
        );
    }

    public static function initCommand(string $commandName, string $descript, Closure $function) {
        Server::getInstance()->getCommandMap()->register($commandName, new class($commandName, $descript, $function) extends Command {

            public function __construct(private string $commandName, private string $descript, private Closure $function) {
                parent::__construct(
                    $this->commandName,
                    $this->descript
                );
            }


            public function execute(CommandSender $sender, string $commandLabel, array $args) : bool {
                ($this->function)($sender, $args);
             return false;
            }

        });
    }

}

class DataLib {
    private static array $data = [];

    public static function setData(array $value) : void {
        self::$data = $value;
    }

    public static function getData() : array {
        return self::$data;
    }


}
