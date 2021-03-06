<?php
/**
 * @name ZombieGame
 * @author Neo-Developer
 * @main Neo\ZombieGame
 * @version 0.1.0
 * @api 4.0.6
 */

namespace Neo;

use Closure;
use jojoe77777\FormAPI\SimpleForm;
use pocketmine\entity\Skin;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDeathEvent;
use pocketmine\event\entity\ProjectileHitEntityEvent;
use pocketmine\event\entity\ProjectileLaunchEvent;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerExhaustEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\event\server\ServerEvent;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\item\ItemIds;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\network\mcpe\protocol\PlaySoundPacket;
use pocketmine\network\mcpe\protocol\types\BossBarColor;
use pocketmine\network\mcpe\protocol\types\LevelSoundEvent;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\scheduler\TaskScheduler;
use pocketmine\Server;

class ZombieGame extends PluginBase {

    private ?DataLib $data = null;

    public function onEnable() : void {
        $this->data = new DataLib();

        $this->getServer()->getPluginManager()->registerEvents(
            new class($this->data, $this->getScheduler()) implements Listener {
                public function __construct(public DataLib $data, public TaskScheduler $task, public array $bool = []){}

                private function getKey(String $name) : ?int {
                    if( !isset( ($data = $this->data->getData())['???'] ) ) {
                        return null;
                    } else {
                        return array_search($name, array_column($data['???'], '??????'));
                    }
                }

                private function sendRoomsForm(Player $player) : void {
                    $data = $this->data->getData();

                    $body = new SimpleForm(
                        function(Player $soruce, $data) : void {
                            $this->JoinRoom($soruce);
                        } 
                    );
                    $body->setTitle('??l??8??? ????????r');

                    if( isset($data['???']) ) { 
                        $body->addButton('??8- ??l'.$data['???']['??????'].'??r??8?????? ??? -??r');
                    } else {
                        $body->setContent("\n ??b??? ??f?????? ??? ?????? ????????????.??r\n\n");
                    }

                    $body->sendToPlayer($player);
                }

                private function JoinRoom(Player $player) : void {
                    if( !isset(($this->data->getData())['???']) ) {
                        $player->sendMessage(' ??b??? ??f?????? ?????????????????????.??r');
                        return;
                    }

                    if( $this->isRoomPlayer($player) ) {
                        $player->sendMessage(' ??b??? ??f?????? ?????? ?????? ?????? ?????? ???????????????.??r');
                        return;
                    }

                    $data = $this->data->getData();

                    $data['???']['??????'][] = $player->getName();

                    if( count($data['???']['??????']) === count(Server::getInstance()->getOnlinePlayers()) )
                        $data['???']['????????????'] = 20;

                    $this->data->setData($data);

                    $this->keyClening();

                    $this->executeRoomPlayers(
                        function(Player $players) use($player, $data): void {
                            $players->sendMessage(" ??b??? ??l??f".$player->getName()."??r??f????????? ?????? ?????? ???????????????.??r\n ??f- ?????? ?????? ??????: ??b".count($data['???']['??????'])."?????r");
                        }
                    );

                    $vector = new Vector3(28, 5, -83);
                    $player->teleport($vector);
                    $player->getNetworkSession()->sendDataPacket(
                        LevelSoundEventPacket::nonActorSound(LevelSoundEvent::RECORD_MELLOHI, $vector, false)
                    );
                    
                    ExtendsLib::setItem($player, 8, ItemIds::MAGMA_CREAM, "??r??c??? ???????????r");
                    
                    
                }


                private function keyClening() : void {
                    if( !isset(($this->data->getData())['???']) )
                        return;

                    $data = ($this->data->getData())['???']['??????'];
                    $arr = [];

                    foreach($data as $value) {
                        $arr[] = $value;
                    }
                    $result = $this->data->getData();
                    $result['???']['??????'] = $arr;

                    $this->data->setData($result);
                }

                private function joinClening(string $index) : void {
                    if( !isset(($this->data->getData())['??????']) )
                        return;

                    $data = ($this->data->getData())['??????'][$index];
                    $arr = [];

                    foreach($data as $value) {
                        $arr[] = $value;
                    }
                    $result = $this->data->getData();
                    $result['??????'][$index] = $arr;

                    $this->data->setData($result);
                }

                private function onCleaning() : void {
                    $this->joinClening('??????');
                    $this->joinClening('??????');
                    $this->joinClening('??????');
                }

                private function selectRamdomZombie() : void {
                    $data = $this->data->getData();
                    $human = $data['??????']['??????'];
                    $key = array_rand($human, ceil((count($data['??????']['??????']) / 5)) );

                    unset($data['??????']['??????'][$key]);
                    $data['??????']['??????'][] = $human[$key];

                    $this->data->setData($data);
                    $this->onCleaning();
                }

                private function isZombiePlayer(Player $player) : bool {
                    $result = false;
                    $data = $this->data->getData();

                    if( isset($data['??????']) )
                    {
                        foreach($data['??????']['??????'] as $value) {
                            if( $value == $player->getName() )
                            {
                                $result = true;
                                break;
                            }
    
                        }
                    }

                    return $result;
                }

                private function infectionZombie(Player $player) : void {
                    $data = $this->data->getData();

                    if( $this->isGamePlayer($player, '??????') ) {
                        $key = $this->playerGameKey($player, '??????');
                        $data['??????']['??????'][] = $data['??????']['??????'][$key];
                        unset($data['??????']['??????'][$key]);
                        unset($data['??????']['??????'][$player->getName()]);
                        $this->data->setData($data);
                        $this->onCleaning();

                        $this->executeGamePlayers(
                            function(Player $players) use($player) : void {
                                $players->sendMessage(' ??c??? '.$player->getName().'??f?????? ??????????????? ?????? ???????????????.??r');
                            }
                        );

                        for($i = 0; $i < 36; $i++){
                            ExtendsLib::setItem($player, $i, ItemIds::AIR);
                        }
                        Gun::$bool[$player->getName()]['??????'] = false;

                        $this->setSkin($player, Server::getInstance()->getDataPath().'zombieSkin/Zombie.png');
                    }
                }

                private function executeZombiePlayers(Closure $funcion) : void {
                    ExtendsLib::executePlayers(
                        function(Player $players) use($funcion): void {
                            if( $this->isZombiePlayer($players) )
                            {
                                $funcion($players);
                            }
                        });
                }

                # ?????? https://gist.github.com/robske110/5f93a00b2dee86b83497c437edfe4451
                private function setSkin(Player $player, string $path) : void {
                    $img = @imagecreatefrompng($path);
                    $bytes = '';
                    $l = (int) @getimagesize($path)[1];

                    for ($y = 0; $y < $l; $y++) {
                        for ($x = 0; $x < 64; $x++) {
                            $rgba = @imagecolorat($img, $x, $y);
                            $a = ((~((int)($rgba >> 24))) << 1) & 0xff;
                            $r = ($rgba >> 16) & 0xff;
                            $g = ($rgba >> 8) & 0xff;
                            $b = $rgba & 0xff;
                            $bytes .= chr($r) . chr($g) . chr($b) . chr($a);
                        }
                    }
                    @imagedestroy($img);

                    $player->setSkin(new Skin("Zomnbie", $bytes));
                    $player->sendSkin();
                }

                private function startGame() : void {
                    $data = $this->data->getData();
                    $task = null;

                    $data['??????'] = [ 
                        '??????' => $data['???']['??????'],
                        '??????' => $data['???']['??????'],
                        '??????' => [],
                        '??????' => (60 * 5) + 20,
                        '??????' => []
                    ];
                    unset($data['???']);
                    unset($data['?????????']);

                    $this->data->setData($data);

                    $this->selectRamdomZombie();

                    $task = $this->task->scheduleRepeatingTask(new ClosureTask(
                        function() use(&$task) : void {
                            $data = $this->data->getData();
                            $time = $data['??????']['??????'];

                            if( count($data['??????']['??????']) <= 0 or $time <= 0 or count($data['??????']['??????'])  <= 0 or count($data['??????']['??????']) <= 0) {
                                $this->finishGame();
                                $task->cancel();
                                return;
                            }

                            $this->executeGamePlayers(
                                function(Player $players) use($data, $time) : void {
                                    if( $time > (60 * 5)) {
                                        $color = BossBarColor::RED;

                                        if( $time > (60 * 5) + 5)
                                        $color = BossBarColor::YELLOW;

                                        if( $time > (60 * 5) + 10)
                                            $color = BossBarColor::GREEN;


                                        ExtendsLib::hideBossBarPacket($players);
                                        ExtendsLib::sendBossBarPacket($players, '??l??e! ??f???????????? ??e'.($time - (60 * 5)).'??? ??f?????? ?????????????????r', $color);
                                    } else {
                                        if( $time == (60 * 5) ) {
                                            $vector = $players->getLocation();
                                            $players->getNetworkSession()->sendDataPacket(
                                                PlaySoundPacket::create("mob.enderdragon.growl", $vector->x, $vector->y, $vector->z, 0.1, 1)
                                            );
                                            $players->sendTitle('??l??c!??r', '??f???????????? ?????? ??????????????r');
                                            ExtendsLib::setItem($players, 0, ItemIds::BOW, "??r??f?????r", 1);
                                            ExtendsLib::setItem($players, 1, ItemIds::ARROW, "??r??f?????r", 1);
                                            
                                            $this->executeZombiePlayers(
                                                function(Player $zombie) : void {
                                                    $this->setSkin($zombie, Server::getInstance()->getDataPath().'zombieSkin/Zombie.png');

                                                    for($i = 0; $i < 36; $i++){
                                                        ExtendsLib::setItem($zombie, $i, ItemIds::AIR);
                                                    }
                                                }
                                            );
                                        }
                                        ExtendsLib::sendBossBarPacket($players, '??l??b! ??r??f?????? ???????????? ??b'.$time.'??? ??f??????????????? ??l??b!??r');
                                    }
                                }
                            );

                            $data = $this->data->getData();

                            $data['??????']['??????'] -= 1;
                            $this->data->setData($data);

                        }
                    ), 20);

                }

                private function finishGame() : void {
                    $data = $this->data->getData();
                
                    $this->executeGamePlayers(
                        function(Player $players) use($data) : void {

                            $players->teleport(new Vector3(10, 5, 20));
                            ExtendsLib::setItem($players, 8, ItemIds::BOOK, '??r??f?????? ????????r');
                            ExtendsLib::hideBossBarPacket($players);

                            if( $data['??????']['??????'] <= 0 ) {
                                $players->sendMessage(' ??b??? ????????f??? ???????????? ?????????????????????.??r');
                                return;
                            }

                            if( count($data['??????']['??????']) > 0 ) {
                                $players->sendMessage(' ??b??? ??a????????f??? ???????????? ?????????????????????.??r');
                                return;
                            }
                            
                        }
                    );

                    unset($data['??????']);
                    $this->data->setData($data);

                    Server::getInstance()->broadcastMessage(' ??f- ????????? ????????????, ??? ????????? ???????????????.??r');
                }

                private function isGamePlayer(Player $player, string $index) : bool {
                    $result = false;
                    $data = $this->data->getData();
                    if( isset($data['??????']) )
                    {
                        foreach($data['??????'][$index] as $value) {
                            if( $value == $player->getName() )
                            {
                                $result = true;
                                break;
                            }
    
                        }
                    }

                    return $result;
                }

                private function isRoomPlayer(Player $player) : bool {
                    $result = false;
                    $data = $this->data->getData();

                    if( isset($data['???']) )
                    {
                        foreach($data['???']['??????'] as $value) {
                            if( $value == $player->getName() )
                            {
                                $result = true;
                                break;
                            }
    
                        }
                    }

                    return $result;
                }

                private function executeGamePlayers(Closure $funcion) : void {
                    ExtendsLib::executePlayers(
                        function(Player $players) use($funcion): void {
                            if( $this->isGamePlayer($players, '??????') )
                            {
                                $funcion($players);
                            }
                        });
                }

                private function executeRoomPlayers(Closure $funcion) : void {
                    ExtendsLib::executePlayers(
                        function(Player $players) use($funcion): void {
                            if( $this->isRoomPlayer($players) )
                            {
                                $funcion($players);
                            }
                        });
                }

                private function waitTime() : void {
                    $task = null;
                    $task = $this->task->scheduleRepeatingTask(new ClosureTask(
                        function() use(&$task): void{
                            $data = $this->data->getData();

                            if( !isset($data['?????????']) )
                            {
                                $task->cancel();
                                return;
                            }
                                                        
                            if( count($data['???']['??????']) <= 0 or (Server::getInstance()->getPlayerByPrefix($data['???']['??????'])) === null) {
                                unset($data['???']);
                                unset($data['?????????']);
                                $this->data->setData($data);
                                Server::getInstance()->broadcastMessage(' ??f- ????????? ????????????, ??? ????????? ???????????????.??r');
                                $task->cancel();
                                return;
                            }
                                

                            if( $data['???']['????????????'] <= 0 )
                            {
                                $this->executeRoomPlayers(
                                    function(Player $players) : void {
                                        $players->getNetworkSession()->sendDataPacket(
                                            LevelSoundEventPacket::nonActorSound(LevelSoundEvent::STOP_RECORD, new Vector3(10, 5, 20), false)
                                        );

                                        $this->setSkin($players, Server::getInstance()->getDataPath().'zombieSkin/Human.png');

                                        ExtendsLib::setItem($players, 8, ItemIds::AIR);
                                        $players->setNameTagVisible(false);
                                        $players->setNameTagAlwaysVisible(false);
                                        $players->teleport(new Vector3(10, 5, 20));
                                    }
                                );
                                $this->startGame();
                                $task->cancel();
                                return;
                            }

                            if( count($data['???']['??????']) > 1){
                                $this->executeRoomPlayers(
                                    function(Player $players) use($data) : void {                                    
                                        $players->sendTip('??l??b! ??r??f?????? ???????????? ??b'.$data['???']['????????????'].'??? ??f??????????????? ??l??b!??r');
                                    }
                                );

                                $data['???']['????????????'] -= 1;

                                $this->data->setData($data);
                            } else {
                                $this->executeRoomPlayers(
                                    function(Player $players) use($data) : void {                                    
                                        $players->sendTip('??l??b! ??r??f?????? ??????????????? ???????????? ??? ??l??b!??r');
                                        $data['???']['????????????'] = 60;

                                        $this->data->setData($data);
                                    }
                                );
                            }
                        }

                    ), 20);

                }

                private function CreateRoom(Player $player) : void {
                    $data = $this->data->getData();

                    if( isset($data['??????']) ) {
                        $player->sendMessage('?????? ?????? ????????? ?????? ???????????????. ???????????? ??????????????????.');
                        return;
                    }
                    
                    if( isset($data['???']) ) {
                        $player->sendMessage('?????? '.$data['???']['??????'].'?????? ?????? ?????? ????????????.');
                        return;
                    }
                    

                    $data['???'] = [
                        '??????' => [
                            $player->getName()
                        ],
                        '??????' => $player->getName(),
                        '????????????' => 60
                    ];

                    $data['?????????'] = true;
                    
                    $this->data->setData($data);
                    $this->waitTime();

                    ExtendsLib::setItem($player, 8, ItemIds::FIRE_CHARGE, '??r??c??? ????????r');
                    $vector = new Vector3(28, 5, -83);
                    $player->teleport($vector);
                    $player->getNetworkSession()->sendDataPacket(
                        LevelSoundEventPacket::nonActorSound(LevelSoundEvent::RECORD_MELLOHI, $vector, false)
                    );
                    Server::getInstance()->broadcastMessage(' ??b??? ??l??f'.$player->getName().'??r??f????????? ?????? ?????? ?????? ??????????????????.??r');
                    $player->sendMessage(' ??f- ?????? ?????? ??????: ??b'.count($data['???']['??????']).'?????r');
                }

                private function removeRoom(Player $player, ?int $key) : void {
                    $data = $this->data->getData();

                    if( $key !== null )
                    {
                        if( isset($data['???']) )
                        {
                            unset($data['???']);
                            unset($data['?????????']);
                        }

                        $player->sendMessage(' ??b??? ??f?????? ?????? ?????????????????????.??r');
                        $this->executeRoomPlayers(
                            function(Player $players) use($player) : void {
                                
                                $vector = new Vector3(10, 5, 20);#(Server::getInstance()->getWorldManager()->getWorldByName('world'))->getSpawnLocation();
                                $players->teleport($vector);
                                $players->getNetworkSession()->sendDataPacket(
                                    LevelSoundEventPacket::nonActorSound(LevelSoundEvent::STOP_RECORD, $vector, false)
                                );
                                ExtendsLib::setItem($players, 8, ItemIds::BOOK, '??r??f?????? ????????r');

                                if( $players->getName() == $player->getName() )
                                    return;
                                
                                $players->sendMessage('????????? ?????? ?????? ?????? ??? ?????? ?????? ????????????.');
                            }
                        );

                        $this->data->setData($data);

                    } else {
                        $player->sendMessage('????????? ?????? ?????? ?????? ???????????????.');
                    }

                }

                private function reverseBool(Player $player) : void {
                    $this->task->scheduleDelayedTask(new ClosureTask(
                        function() use($player) : void {
                            $this->bool[$player->getName()] = false;
                        }
                    ), 10);
                }

                private function playerGameKey(Player $player, string $index) : ?int {
                    $result = null;
                    $data = $this->data->getData();

                    foreach($data['??????'][$index] as $key => $value){
                        if( $value == $player->getName() )
                        {
                            $result = $key;
                            break;
                        }
                    }

                    return $result;
                }

                private function playerRoomKey(Player $player) : ?int {
                    $result = null;
                    $data = $this->data->getData();

                    foreach($data['???']['??????'] as $key => $value){
                        if( $value == $player->getName() )
                        {
                            $result = $key;
                            break;
                        }
                    }

                    return $result;
                }

                private function sendQuit(Player $player, string $id, ?int $key, string $index) : void {
                    $data = $this->data->getData();

                    if( $key !== null) {
                        unset($data[$id][$index][$key]);
                        $this->data->setData($data);
                        $this->onCleaning();

                    }
                }

                private function Quit(Player $player) : void {
                    if( $this->isGamePlayer($player, '??????') ) {
                        $this->sendQuit($player, '??????', $this->playerGameKey($player, '??????'), '??????');
                    }

                    if( $this->isGamePlayer($player, '??????') ) {
                        $this->sendQuit($player, '??????', $this->playerGameKey($player, '??????'), '??????');
                    }

                    if( $this->isGamePlayer($player, '??????') ) {
                        $this->sendQuit($player, '??????', $this->playerGameKey($player, '??????'), '??????');
                    }

                    if( $this->isRoomPlayer($player) ) {
                        $this->sendQuit($player, '???', $this->playerRoomKey($player), '??????');
                    }

                    $player->sendMessage(' ??b??? ??f????????? ?????????????????????.??r');
                    $player->getNetworkSession()->sendDataPacket(
                        LevelSoundEventPacket::nonActorSound(LevelSoundEvent::STOP_RECORD, new Vector3(10, 5, 20), false)
                    );

                    ExtendsLib::setItem($player, 8, ItemIds::BOOK, "??r??f?????? ????????r");

                    $this->executeRoomPlayers(
                        function(Player $players) use($player) : void {
                            $players->sendMessage(' ??b??? ??f'.$player->getName().'?????? ???????????? ?????????????????????.??r');
                        }
                    );
                    

                    $this->executeGamePlayers(
                        function(Player $players) use($player) : void {
                            $players->sendMessage(' ??b??? ??f'.$player->getName().'?????? ???????????? ?????????????????????.??r');
                        }
                    );
                }

                public function onHit(ProjectileHitEntityEvent $event) : void {
                }

                public function FallDamge(EntityDamageEvent $event) : void {
                    if( $event->getCause() === EntityDamageEvent::CAUSE_FALL )
                        $event->cancel();
                        
                }
                public function onDamage(EntityDamageByEntityEvent $event) : void {
                    $data = $this->data->getData();
                    if( !isset($data['??????']))
                        return;

                    if( !($data['??????']['??????'] < (60 * 5)) )
                        return;

                    if( ($entity = $event->getEntity()) instanceof Player && ($damager = $event->getDamager()) instanceof Player ) {
                        if( ($this->isZombiePlayer($damager) && $this->isZombiePlayer($entity)) || ($this->isGamePlayer($damager, '??????') && $this->isGamePlayer($entity, '??????')) ){  
                            $event->cancel();
                            return;
                        }

                        if( $this->isZombiePlayer($damager)  && $this->isGamePlayer($entity, '??????') ) {
                            $data = $this->data->getData();
                            if( !isset($data['??????']['??????'][$entity->getName()]) ) {
                                $data['??????']['??????'][$entity->getName()] = 0;
                                $this->data->setData($data);
                            }
                            $data = $this->data->getData();

                            if( $data['??????']['??????'][$entity->getName()] <= 2 ) {
                                $data['??????']['??????'][$entity->getName()] += 1;
                                $this->data->setData($data);
                                $inf = ($this->data->getData())['??????']['??????'][$entity->getName()];
                                $damager->sendTip($entity->getName().'?????? ????????? : '.floor(($inf * 33) + 1).'%');
                                $entity->sendTip('????????? ????????? : '.floor(($inf * 33) + 1).'%');
                                
                                if( $inf >= 3) 
                                    $this->infectionZombie($entity);
                            } 


                        } 

                        if( $this->isGamePlayer($damager, '??????') ) {
                            $event->setAttackCooldown(5);
                            $event->setKnockBack(0.25);
                        }

                    }

                }

                public function onThrow(ProjectileLaunchEvent $event)  : void {
                    $player = $event->getEntity()->getOwningEntity();

                    if( $player instanceof Player ) {
                        if( isset(($this->data->getData())['??????']) )
                        if( ($this->data->getData())['??????']['??????'] > (60 * 5) )
                        if( $this->isGamePlayer($player, '??????')) {
                            $event->cancel();
                        }


                        if( $this->isZombiePlayer($event->getEntity()->getOwningEntity()) )
                            $event->cancel();
                    }
                } 
                
                public function onExhaust(PlayerExhaustEvent $event) : void {
                    $event->cancel();
                }

                public function onInvHandle(InventoryTransactionEvent $event) : void {
                    $saction = $event->getTransaction();

                    foreach($saction->getActions() as $action) {
                        if( $action instanceof SlotChangeAction ){
                            if( (match($action->getSourceItem()->getName()){
                                '??r??f?????? ????????r', '??r??c??? ????????r', '??r??c??? ???????????r' => true,
                                default => false
                            }) ) {
                                $event->cancel();

                            }

                        }

                    }

                }

                public function onTouch(PlayerInteractEvent $event) : void { 
                    $player = $event->getPlayer();

                    if( $this->bool[$player->getName()] )
                        return;

                    if( $event->getItem()->getName() === '??r??f?????? ????????r' ) {
                        $this->bool[$player->getName()] = true;
                        $this->reverseBool($player);

                        $body = new SimpleForm(
                            function(Player $source, $data) : void {

                                if( !is_int($data) )
                                    return;
    
                                if( !boolval($data) )
                                {
    
                                    $this->sendRoomsForm($source);
                                    
                                } else {
                
                                    $this->CreateRoom($source);
    
                                }
    
                            }
    
                        );
    
                        $body->setTitle('??l??8?????? ????????r');
                        $body->setContent("\n ??b??? ??f??????????????? ????????? ?????????.??r\n\n");
                        $body->addButton("??l??8??? ????????r\n??8- ?????????????????? ??? ????????? ??????????????? -??r");
                        $body->addButton("??l??8??? ???????????r\n??8- ????????? ?????? ?????? ??????????????? -??r");
    
                        $event->cancel();
    
                        $body->sendToPlayer($player);

                    } elseif( $event->getItem()->getName() === '??r??c??? ????????r' ) {
                        $this->removeRoom(
                            $player, 
                            $this->getKey($player->getName(), $this->getKey($player->getName()))
                        );


                        $event->cancel();

                    } elseif( $event->getItem()->getName() === '??r??c??? ???????????r') {
                        $this->Quit($player);
                    }
                    
                }

                public function onBreak(BlockBreakEvent $event) : void {
                    if( !(Server::getInstance()->isOp($event->getPlayer()->getName())) )
                        $event->cancel();
                }

                public function onPlace(BlockPlaceEvent $event) : void {
                    if( !(Server::getInstance()->isOp($event->getPlayer()->getName())) )
                        $event->cancel();

                    if( $event->getItem()->getName() === '??r??c??? ????????r' )
                        $event->cancel();
                }

                public function onDeath(EntityDeathEvent $event) : void {
                    $drops = $event->getDrops();

                    foreach($event->getDrops() as $key => $item)
                    {
                        if( $item->getName() === '??r??f?????? ????????r' || $item->getName() == '??r??c??? ????????r' || $item == '??r??c??? ???????????r')
                            unset($drops[$key]);
                    }

                    $event->setDrops($drops);
                }

                public function onRespawn(PlayerRespawnEvent $event) : void {
                    $player = $event->getPlayer();
                    if( 
                        !(
                            $this->isGamePlayer($player, '??????') ||
                            $this->isGamePlayer($player, '??????') || 
                            $this->isGamePlayer($player, '??????') ||
                            $this->isRoomPlayer($player)
                        )
                     ) {
                        ExtendsLib::setItem($player, 8, ItemIds::BOOK, '??r??f?????? ????????r');

                    }
                }

                public function onJoin(PlayerJoinEvent $event) : void {
                    $player = $event->getPlayer();

                    $this->bool[$player->getName()] = false;

                    if( $player->getInventory()->getItem(8)->getCustomName() === '??r??f?????? ????????r' )
                        return;
                    ExtendsLib::setItem($player, 8, ItemIds::BOOK, '??r??f?????? ????????r');
                }

                public function onQuit(PlayerQuitEvent $event) : void {
                    $player = $event->getPlayer();

                    if( $this->isRoomPlayer($player) || $this->isGamePlayer($player, '??????'))
                        $this->Quit($player);

                }

            }
            , $this);
        
    }
}
 ?>
