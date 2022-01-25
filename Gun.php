<?php

/**
 * @name Gun
 * @author Neo-Developer
 * @main Neo\Gun
 * @version 0.1.0
 * @api 4.0.6
 */

 namespace Neo;

use pocketmine\entity\projectile\Arrow;
use pocketmine\entity\projectile\Snowball;
use pocketmine\event\entity\ProjectileHitEntityEvent;
use pocketmine\event\entity\ProjectileHitEvent;
use pocketmine\event\entity\ProjectileLaunchEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\item\ItemIds;
use pocketmine\network\mcpe\protocol\MoveActorAbsolutePacket;
use pocketmine\network\mcpe\protocol\MoveActorDeltaPacket;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;
use pocketmine\network\mcpe\protocol\PlaySoundPacket;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\scheduler\TaskScheduler;

class Gun extends PluginBase {
    public static array $bool = [];

    public function onEnable() : void {
        $this->getServer()->getPluginManager()->registerEvents(
            new class($this->getScheduler()) implements Listener {


                public function __construct(private TaskScheduler $task)
                {
                    
                }

                public function onJoin(PlayerJoinEvent $event) {
                    Gun::$bool[$event->getPlayer()->getName()] = [
                        "탄창" => 20,
                        "확인" => false
                    ];
                }

                public function onHit(ProjectileHitEvent $event) : void {
                    if( ($player = $event->getEntity()->getOwningEntity()) instanceof Player && ($entity = $event->getEntity()) instanceof Snowball ) {
                        if( $event instanceof ProjectileHitEntityEvent) {
                            if( ($target = $event->getEntityHit()) instanceof Player ) {
                            }
                        }
                    }
                }

                public function pullArrow(PlayerItemUseEvent $event) : void {
                    $player = $event->getPlayer();

                    if( $event->getItem()->getId() === ItemIds::BOW ) {
                        if( Gun::$bool[$player->getName()]['확인'] )
                            return;
                        

                        Gun::$bool[$player->getName()]['확인'] = true;
                        $task = null;
                        $bool = false;

                        $this->task->scheduleDelayedTask(new ClosureTask(
                            function() use(&$bool, $player) : void {
                                if( $player->getItemUseDuration() > 5)
                                    $bool = true;
                                
                                if($bool)
                                    $task = $this->task->scheduleRepeatingTask(new ClosureTask(
                                        function() use(&$task, $player): void {
                                            if( !$player->isOnGround() ) {
                                                $player->sendTip('§c공중에서는 총을 쏠수 없습니다.');
                                                $task->cancel();
                                                return;
                                            }

                                            if( Gun::$bool[$player->getName()]['확인'])
                                            {
                                                if( Gun::$bool[$player->getName()]["탄창"] <= 0 )
                                                {
                                                    $player->sendTip('§c재장전중...');
                                                    $this->task->scheduleDelayedTask(new ClosureTask(
                                                        function() use($player) : void {
                                                            if( Gun::$bool[$player->getName()]["탄창"] > 0)
                                                                return;
                                                            $player->sendTip('§a장전완료');
                                                            Gun::$bool[$player->getName()]["탄창"] = 30;
                                                        }
                                                    ), 20 * 4);
                                                    $task->cancel();

                                                    return;
                                                }
                                                $location = $player->getLocation();
                                                //$location->y += 1.621;

                                                ExtendsLib::spawnArrow($player);
                                                Gun::$bool[$player->getName()]["탄창"] -= 1;
                                                $player->sendTip('남은 총알 : '.Gun::$bool[$player->getName()]["탄창"].' / 30');
                                                $player->teleport($location, $location->yaw, $location->pitch - 0.75);

                                                $player->getNetworkSession()->sendDataPacket(
                                                    PlaySoundPacket::create("ambient.weather.lightning.impact", $location->x, $location->y, $location->z, 0.1, 1)
                                                );

                                            } else {
                                                $task->cancel();
                                            }
                                        }
                                    ), 2);
                            }

                        ), 8);

                    }

                }

                public function shotArrow(ProjectileLaunchEvent $event) : void {
                    if( $event->getEntity() instanceof Arrow ) {
                        Gun::$bool[$event->getEntity()->getOwningEntity()->getNameTag()]['확인'] = false;
                        $event->cancel();
                    }
                }
            }
        , $this);
    }
}

?>
