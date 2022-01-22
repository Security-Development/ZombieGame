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
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\event\server\ServerEvent;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\item\ItemIds;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\network\mcpe\protocol\PlaySoundPacket;
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
                    if( !isset( ($data = $this->data->getData())['방'] ) ) {
                        return null;
                    } else {
                        return array_search($name, array_column($data['방'], '방장'));
                    }
                }

                private function sendRoomsForm(Player $player) : void {
                    $data = $this->data->getData();

                    $body = new SimpleForm(
                        function(Player $soruce, $data) : void {
                            $this->JoinRoom($soruce);
                        } 
                    );
                    $body->setTitle('방 목록');

                    if( isset($data['방']) ) { 
                        $body->addButton($data['방']['방장'].'님의 방');
                    } else {
                        $body->setContent('생성된 방이 없습니다.');
                    }

                    $body->sendToPlayer($player);
                }

                private function JoinRoom(Player $player) : void {
                    if( !isset(($this->data->getData())['방']) ) {
                        $player->sendMessage('해당 방이 사라졌습니다.');
                        return;
                    }

                    if( $this->isRoomPlayer($player) ) {
                        $player->sendMessage('당신은 이미 대기방에 있습니다.');
                        return;
                    }

                    $data = $this->data->getData();

                    if( count($data['방']['인원']) === count(Server::getInstance()->getOnlinePlayers()) )
                        $data['방']['대기시간'] = 20;

                    $data['방']['인원'][] = $player->getName();

                    $this->data->setData($data);

                    $this->keyClening();

                    $this->executeRoomPlayers(
                        function(Player $players) use($player, $data): void {
                            $players->sendMessage($player->getName().'님께서 대기 방에 입장 하셨습니다. 현재인원 '.count($data['방']['인원']).'명');
                        }
                    );

                    $vector = new Vector3(28, 5, -83);
                    $player->teleport($vector);
                    $player->getNetworkSession()->sendDataPacket(
                        LevelSoundEventPacket::nonActorSound(LevelSoundEvent::RECORD_MELLOHI, $vector, false)
                    );
                    
                    ExtendsLib::setItem($player, 8, ItemIds::MAGMA_CREAM, "방 나가기");
                    
                    
                }


                private function keyClening() : void {
                    if( !isset(($this->data->getData())['방']) )
                        return;

                    $data = ($this->data->getData())['방']['인원'];
                    $arr = [];

                    foreach($data as $value) {
                        $arr[] = $value;
                    }
                    $result = $this->data->getData();
                    $result['방']['인원'] = $arr;

                    $this->data->setData($result);
                }

                private function joinClening(string $index) : void {
                    if( !isset(($this->data->getData())['시작']) )
                        return;

                    $data = ($this->data->getData())['시작'][$index];
                    $arr = [];

                    foreach($data as $value) {
                        $arr[] = $value;
                    }
                    $result = $this->data->getData();
                    $result['시작'][$index] = $arr;

                    $this->data->setData($result);
                }

                private function onCleaning() : void {
                    $this->joinClening('인원');
                    $this->joinClening('좀비');
                    $this->joinClening('인간');
                }

                private function selectRamdomZombie() : void {
                    $data = $this->data->getData();
                    $human = $data['시작']['인간'];
                    $key = array_rand($human, ceil((count($data['시작']['인원']) / 5)) );

                    unset($data['시작']['인간'][$key]);
                    $data['시작']['좀비'][] = $human[$key];

                    $this->data->setData($data);
                    $this->onCleaning();
                }

                private function isZombiePlayer(Player $player) : bool {
                    $result = false;
                    $data = $this->data->getData();

                    if( isset($data['시작']) )
                    {
                        foreach($data['시작']['좀비'] as $value) {
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

                    if( $this->isGamePlayer($player, '인간') ) {
                        $key = $this->playerGameKey($player, '인간');
                        $data['시작']['좀비'][] = $data['시작']['인간'][$key];
                        unset($data['시작']['인간'][$key]);
                        $this->data->setData($data);
                        $this->onCleaning();

                        $this->executeGamePlayers(
                            function(Player $players) use($player) : void {
                                $players->sendMessage($player->getName().'님이 좀비 바이러스에 감염 되었습니다.');
                            }
                        );

                        for($i = 0; $i < 36; $i++){
                            ExtendsLib::setItem($player, $i, ItemIds::AIR);
                        }

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

                # 참고 https://gist.github.com/robske110/5f93a00b2dee86b83497c437edfe4451
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

                    $data['시작'] = [ 
                        '인원' => $data['방']['인원'],
                        '인간' => $data['방']['인원'],
                        '좀비' => [],
                        '시간' => (60 * 5) + 20
                    ];
                    unset($data['방']);
                    unset($data['활성화']);

                    $this->data->setData($data);

                    $this->selectRamdomZombie();

                    $task = $this->task->scheduleRepeatingTask(new ClosureTask(
                        function() use(&$task) : void {
                            $data = $this->data->getData();
                            $time = $data['시작']['시간'];

                            if( count($data['시작']['인원']) <= 0 or $time <= 0 or count($data['시작']['인간'])  <= 0 or count($data['시작']['좀비']) <= 0) {
                                $this->finishGame();
                                $task->cancel();
                                return;
                            }

                            $this->executeGamePlayers(
                                function(Player $players) use($data, $time) : void {
                                    if( $time > (60 * 5)) {
                                        $players->sendTitle(' ', '좀비 감여자가 '.($time - (60 * 5)).'초 후에 선정됩니다.');
                                    } else {
                                        if( $time == (60 * 5) ) {
                                            $vector = $players->getLocation();
                                            $players->getNetworkSession()->sendDataPacket(
                                                PlaySoundPacket::create("mob.enderdragon.growl", $vector->x, $vector->y, $vector->z, 0.1, 1)
                                            );
                                            $players->sendTitle(' ', '좀비 감염자가 발생 했습니다.');
                                            
                                            $this->executeZombiePlayers(
                                                function(Player $zombie) : void {
                                                    $this->setSkin($zombie, Server::getInstance()->getDataPath().'zombieSkin/Zombie.png');

                                                    for($i = 0; $i < 36; $i++){
                                                        ExtendsLib::setItem($zombie, $i, ItemIds::AIR);
                                                    }
                                                }
                                            );
                                        }

                                        $players->sendTip('게임 종료까지 '.$time.'초 남았습니다.');
                                    }
                                }
                            );

                            $data = $this->data->getData();

                            $data['시작']['시간'] -= 1;
                            $this->data->setData($data);

                        }
                    ), 20);

                }

                private function finishGame() : void {
                    $data = $this->data->getData();
                
                    $this->executeGamePlayers(
                        function(Player $players) use($data) : void {

                            if( $data['시작']['시간'] <= 0 ) {
                                $players->sendMessage('인간이 좀비 게임에서 승리했습니다.');
                            }

                            if( count($data['시작']['좀비']) > 0 ) {
                                $players->sendMessage('좀비가 좀비 게임에서 승리했습니다.');
                            }
                            

                            $players->teleport(new Vector3(10, 5, 20));
                            ExtendsLib::setItem($players, 8, ItemIds::BOOK, '좀비 게임');
                        }
                    );

                    unset($data['시작']);
                    $this->data->setData($data);

                    Server::getInstance()->broadcastMessage('좀비 게임이 종료 되었습니다. 이제 좀비 게임 방 생성이 가능합니다.');
                }

                private function isGamePlayer(Player $player, string $index) : bool {
                    $result = false;
                    $data = $this->data->getData();
                    if( isset($data['시작']) )
                    {
                        foreach($data['시작'][$index] as $value) {
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

                    if( isset($data['방']) )
                    {
                        foreach($data['방']['인원'] as $value) {
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
                            if( $this->isGamePlayer($players, '인원') )
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

                            if( !isset($data['활성화']) )
                            {
                                $task->cancel();
                                return;
                            }
                                                        
                            if( count($data['방']['인원']) <= 0 or (Server::getInstance()->getPlayerByPrefix($data['방']['방장'])) === null) {
                                unset($data['방']);
                                unset($data['활성화']);
                                $this->data->setData($data);
                                Server::getInstance()->broadcastMessage('좀비 게임이 종료 되었습니다. 이제 좀비 게임 방 생성이 가능합니다.');
                                $task->cancel();
                                return;
                            }
                                

                            if( $data['방']['대기시간'] <= 0 )
                            {
                                $this->executeRoomPlayers(
                                    function(Player $players) : void {
                                        $players->getNetworkSession()->sendDataPacket(
                                            LevelSoundEventPacket::nonActorSound(LevelSoundEvent::STOP_RECORD, new Vector3(10, 5, 20), false)
                                        );

                                        $this->setSkin($players, Server::getInstance()->getDataPath().'zombieSkin/Human.png');
                                        for($i = 0; $i < 36; $i++){
                                            ExtendsLib::setItem($players, $i, ItemIds::SNOWBALL, "넉백용 눈덩이", 16);
                                        }

                                        $players->teleport(new Vector3(10, 5, 20));
                                    }
                                );
                                $this->startGame();
                                $task->cancel();
                                return;
                            }

                            $this->executeRoomPlayers(
                                function(Player $players) use($data) : void {                                    
                                    $players->sendTip('게임 시작까지 '.$data['방']['대기시간'].'초 남았습니다.');
                                }
                            );

                            $data['방']['대기시간'] -= 1;

                            $this->data->setData($data);

                        }
                    ), 20);

                }

                private function CreateRoom(Player $player) : void {
                    $data = $this->data->getData();

                    if( isset($data['시작']) ) {
                        $player->sendMessage('이미 좀비 게임이 시작 되었습니다. 다음판을 기다려주세요.');
                        return;
                    }
                    
                    if( isset($data['방']) ) {
                        $player->sendMessage('이미 '.$data['방']['방장'].'님이 방을 생성 했습니다.');
                        return;
                    }
                    

                    $data['방'] = [
                        '인원' => [
                            $player->getName()
                        ],
                        '방장' => $player->getName(),
                        '대기시간' => 60
                    ];

                    $data['활성화'] = true;
                    
                    $this->data->setData($data);
                    $this->waitTime();

                    ExtendsLib::setItem($player, 8, ItemIds::REDSTONE, '방 삭제');
                    $vector = new Vector3(28, 5, -83);
                    $player->teleport($vector);
                    $player->getNetworkSession()->sendDataPacket(
                        LevelSoundEventPacket::nonActorSound(LevelSoundEvent::RECORD_MELLOHI, $vector, false)
                    );
                    Server::getInstance()->broadcastMessage($player->getName().'님께서 좀비 게임방을 만드셨습니다. 참가 해서 게임을 즐겨보세요!.');
                    $player->sendMessage('현재 당신의 방의 인원 수 : 1명');
                }

                private function removeRoom(Player $player, ?int $key) : void {
                    $data = $this->data->getData();

                    if( $key !== null )
                    {
                        if( isset($data['방']) )
                        {
                            unset($data['방']);
                            unset($data['활성화']);
                        }

                        $player->sendMessage('당신은 좀비 게임 방을 삭제하셨습니다.');
                        $this->executeRoomPlayers(
                            function(Player $players) use($player) : void {
                                
                                $vector = new Vector3(10, 5, 20);#(Server::getInstance()->getWorldManager()->getWorldByName('world'))->getSpawnLocation();
                                $players->teleport($vector);
                                $players->getNetworkSession()->sendDataPacket(
                                    LevelSoundEventPacket::nonActorSound(LevelSoundEvent::STOP_RECORD, $vector, false)
                                );
                                ExtendsLib::setItem($players, 8, ItemIds::BOOK, '좀비 게임');

                                if( $players->getName() == $player->getName() )
                                    return;
                                
                                $players->sendMessage('방장이 좀비 게임 시작 전 방을 삭제 했습니다.');
                            }
                        );

                        $this->data->setData($data);

                    } else {
                        $player->sendMessage('당신은 방을 생성 하지 않았습니다.');
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

                    foreach($data['시작'][$index] as $key => $value){
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

                    foreach($data['방']['인원'] as $key => $value){
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
                    if( $this->isGamePlayer($player, '인원') ) {
                        $this->sendQuit($player, '시작', $this->playerGameKey($player, '인원'), '인원');
                    }

                    if( $this->isGamePlayer($player, '좀비') ) {
                        $this->sendQuit($player, '시작', $this->playerGameKey($player, '좀비'), '좀비');
                    }

                    if( $this->isGamePlayer($player, '인간') ) {
                        $this->sendQuit($player, '시작', $this->playerGameKey($player, '인간'), '인간');
                    }

                    if( $this->isRoomPlayer($player) ) {
                        $this->sendQuit($player, '방', $this->playerRoomKey($player), '인원');
                    }

                    $player->sendMessage('당신은 게임에서 나가셨습니다.');
                    $player->getNetworkSession()->sendDataPacket(
                        LevelSoundEventPacket::nonActorSound(LevelSoundEvent::STOP_RECORD, new Vector3(10, 5, 20), false)
                    );

                    ExtendsLib::setItem($player, 8, ItemIds::BOOK, "좀비 게임");

                    $this->executeRoomPlayers(
                        function(Player $players) use($player) : void {
                            $players->sendMessage($player->getName().'님이 게임에서 나가셨습니다.');
                        }
                    );
                    

                    $this->executeGamePlayers(
                        function(Player $players) use($player) : void {
                            $players->sendMessage($player->getName().'님이 게임에서 나가셨습니다.');
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
                    if( !isset($data['시작']))
                        return;

                    if( !($data['시작']['시간'] < (60 * 5)) )
                        return;

                    if( ($entity = $event->getEntity()) instanceof Player && ($damager = $event->getDamager()) instanceof Player ) {
                        if( $this->isZombiePlayer($damager)  && $this->isGamePlayer($entity, '인간') ) {
                            $this->infectionZombie($entity);
                        } 

                        if( $this->isGamePlayer($damager, '인간') && $this->isGamePlayer($entity, '인간') )
                        if( $this->isZombiePlayer($damager) && $this->isZombiePlayer($entity) ){  
                            $event->cancel();
                        }
                    }

                }

                public function onThrow(ProjectileLaunchEvent $event)  : void {
                    $player = $event->getEntity()->getOwningEntity();

                    if( $player instanceof Player ) {
                        if( isset(($this->data->getData())['시작']) )
                        if( ($this->data->getData())['시작']['시간'] > (60 * 5) )
                        if( $this->isGamePlayer($player, '인간')) {
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
                                '좀비 게임', '방 삭제', '방 나가기' => true,
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

                    if( $event->getItem()->getName() === '좀비 게임' ) {
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
    
                        $body->setTitle('좀비 게임');
                        $body->addButton('방 목록');
                        $body->addButton('방 만들기');
    
                        $event->cancel();
    
                        $body->sendToPlayer($player);

                    } elseif( $event->getItem()->getName() === '방 삭제' ) {
                        $this->removeRoom(
                            $player, 
                            $this->getKey($player->getName(), $this->getKey($player->getName()))
                        );


                        $event->cancel();

                    } elseif( $event->getItem()->getName() === '방 나가기') {
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

                    if( $event->getItem()->getName() === '방 삭제' )
                        $event->cancel();
                }

                public function onDeath(EntityDeathEvent $event) : void {
                    $drops = $event->getDrops();

                    foreach($event->getDrops() as $key => $item)
                    {
                        if( $item->getName() === '좀비 게임' || $item->getName() == '방 삭제' || $item == '방 나가기')
                            unset($drops[$key]);
                    }

                    $event->setDrops($drops);
                }

                public function onRespawn(PlayerRespawnEvent $event) : void {
                    $player = $event->getPlayer();
                    if( 
                        !(
                            $this->isGamePlayer($player, '인원') ||
                            $this->isGamePlayer($player, '좀비') || 
                            $this->isGamePlayer($player, '인간') ||
                            $this->isRoomPlayer($player)
                        )
                     ) {
                        ExtendsLib::setItem($player, 8, ItemIds::BOOK, '좀비 게임');

                    }
                }

                public function onJoin(PlayerJoinEvent $event) : void {
                    $player = $event->getPlayer();

                    $this->bool[$player->getName()] = false;

                    if( $player->getInventory()->getItem(8)->getCustomName() === '좀비 게임' )
                        return;
                    ExtendsLib::setItem($player, 8, ItemIds::BOOK, '좀비 게임');
                }

                public function onQuit(PlayerQuitEvent $event) : void {
                    $player = $event->getPlayer();

                    $this->Quit($player);

                }

            }
            , $this);
        
    }
}
 ?>
