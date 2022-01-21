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
use pocketmine\item\ItemFactory;
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

}
