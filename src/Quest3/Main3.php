<?php 
namespace Quest3;
class TMNWallet {
    // ** Login Credential **
    private $username;
    private $password;
    private $authtype;
    // ** API Host & API Endpoint **
    private $api_host = 'https://mobile-api-gateway.truemoney.com/mobile-api-gateway/api/v1';
    private $api_endpoint_signin = '/signin';
    private $api_endpoint_profile = '/profile/';
    private $api_endpoint_topup = '/topup/mobile/';
    private $api_endpoint_gettran = '/profile/transactions/history/';
    private $api_endpoint_getreport = '/profile/activities/';
    public function __construct($user, $pass, $type = 'email') {
        $this->username = $user;
        $this->password = $pass;
        $this->authtype = $type;
    }
    public function GetToken() {
        $url = $this->api_host.$this->api_endpoint_signin;
        $header = array(
            "Host: mobile-api-gateway.truemoney.com",
            "Content-Type: application/json"
        );
        $data = array(
            "username"=> $this->username,
            "password"=> sha1($this->username.$this->password),
            "type"=> $this->authtype,
        );
        return $this->WalletCurl($url, json_encode($data), $header);
    }
    public function GetProfile($token) {
        $url = $this->api_host.$this->api_endpoint_profile.$token;
        $header = array("Host: mobile-api-gateway.truemoney.com");
        return $this->WalletCurl($url, false, $header);
    }
    
    public function GetBalance($token) {
        $url = $this->api_host.$this->api_endpoint_profile.'balance/'.$token;
        $header = array("Host: mobile-api-gateway.truemoney.com");
        return json_decode($this->WalletCurl($url, false, $header), true)['data']['currentBalance'];
    }
    
    public function GetTransaction($token, $start, $end, $limit = 50) {
        $url = $this->api_host.$this->api_endpoint_gettran.$token.'/?startDate='.$start.'&endDate='.$end.'&limit='.$limit.'&page=1&type=&action=';
        $header = array("Host: mobile-api-gateway.truemoney.com");
        return $this->WalletCurl($url, false, $header);
    }
    public function GetReport($token, $id) {
        $url = $this->api_host.$this->api_endpoint_getreport.$id.'/detail/'.$token;
        $header = array("Host: mobile-api-gateway.truemoney.com");
        return $this->WalletCurl($url, false, $header);
    }
    public function Topup($cashcard, $token) {
        $url = $this->api_host.$this->api_endpoint_topup.time().'/'.$token.'/cashcard/'.$cashcard;
        $header = array("Host: mobile-api-gateway.truemoney.com");
        return $this->WalletCurl($url, true, $header);
    }
    private function WalletCurl($url, $data, $header) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);  
        if ($data) {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'okhttp/3.8.0');
        $result = curl_exec($ch);
        return $result;
    }
}
use pocketmine\Server;
use pocketmine\Player;
use pocketmine\event\Listener;
use pocketmine\command\Command;
use pocketmine\command\CommandExecutor;
use pocketmine\command\CommandSender;
use pocketmine\command\SimpleCommandMap;
use pocketmine\command\ConsoleCommandSender;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\level\particle\FlameParticle;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\Egg;
use pocketmine\entity\Entity;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\entity\EntityDeathEvent;
use pocketmine\event\entity\EntityRegainHealthEvent;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\level\Level;
use pocketmine\block\Block;
use pocketmine\inventory\BaseInventory;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\level\particle\RedstoneParticle;
use pocketmine\item\Snowball;
use pocketmine\entity\EnderPearl;
use pocketmine\block\TNT;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\entity\Effect;
use onebone\economyapi\EconomyAPI;
use pocketmine\scheduler\CallbackTask;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\item\ChainHelmet;
use pocketmine\item\Armor;
use pocketmine\item\ItemString;
use pocketmine\utils\TextFormat as T;
class Main3 extends PluginBase implements Listener
{

    public function onEnable()
    {
        $this->getServer()
            ->getPluginManager()
            ->registerEvents($this, $this);
        $this->getLogger()
            ->info("§b>> §aระบบเติมเงินทำงาน");
        if(!is_dir($this->getDataFolder())){
            @mkdir($this->getDataFolder());
		}	
        $this->saveResource("config.yml"); 
        $this->config = new Config($this->getDataFolder()."config.yml", Config::YAML);
$host = $this->config->get("host");
$username = $this->config->get("user");
$password = $this->config->get("pass");
$db = $this->config->get("database");

$cont = mysqli_connect($host, $username, $password);

// Check connection
if (!$cont) {
    $this->getLogger()
            ->info("§c ไม่สามารถเชื่อมต่อฐานข้อมูลได้");
}else {
$this->getLogger()
            ->info("§a เชื่อมต่อฐานข้อมูลสำเร็จ");
        }

if(!file_exists($this->getDataFolder() . "pocketrefill.db")){
            $this->database = new \SQLite3($this->getDataFolder() . "pocketrefill.db", SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
            $resource = $this->getResource("sqlite3.sql");
            $this->database->exec(stream_get_contents($resource));
            @fclose($resource);
        }else{
            $this->database = new \SQLite3($this->getDataFolder() . "pocketrefill.db", SQLITE3_OPEN_READWRITE);
        }

    }
    public function onCommand(CommandSender $sender, Command $command, $label, array $args)
    {
        $player = $sender->getPlayer($args[0]);
        $name = $player->getName();
        $cmd = strtolower($command->getName());
        switch ($cmd)
        {
            case "wallet":
$pf = "§9[§aPocket§eRefill§9] ";
$ref = $args[0];
if (isset($ref)){
$sender->sendMessage($pf."§eกรุณารอสักครู่...");
//start
$email = $this->config->get("email");//อีเมลทรูวอเล็ท
$password = $this->config->get("password");//รหัสผ่านทรูวอเล็ท
//end
$wallet = new TMNWallet($email,$password);
$login = 0;
date_default_timezone_set('Asia/Bangkok');
$start_date = date('Y-m-d', strtotime('-7 days'));//เริ่มดึงข้อมูลวันที่
$end_date = date('Y-m-d', strtotime('1 days'));//จบการดึงข้อมูลวันที่
$token = json_decode($wallet->GetToken(), true)['data']['accessToken'];
if (!$token){
	$login = 1;
}
$activities = json_decode($wallet->GetTransaction($token, $start_date, $end_date), true)['data']['activities'];
$status = 0;
$ruse = 0;
$dtype = $this->config->get("data-type");
if ($dtype == "SQLITE"){
$prepare1 = $this->database->prepare("SELECT * FROM pocketrefill WHERE ref = :rf");
$prepare1->bindValue(":rf", $ref, SQLITE3_TEXT);
$gr = $prepare1->execute();
$dsl = $gr->fetchArray(SQLITE3_ASSOC);
if ($dsl){
      $ruse = 1;
}
$prepare2 = $this->database->prepare("INSERT INTO pocketrefill (ref) VALUES (:rf)");
$prepare2->bindValue(":rf", $ref, SQLITE3_TEXT);
$prepare2->execute();
}else if ($dtype == "MYSQL"){
$host = $this->config->get("host");
$username = $this->config->get("user");
$password = $this->config->get("pass");
$db = $this->config->get("database");
$conn = mysqli_connect($host, $username, $password, $db);
$sql2 = "SELECT id, ref FROM pocketrefill where ref = '".mysqli_real_escape_string($conn, $ref)."'";
$gruy = mysqli_query($conn, $sql2);
$rsuy = mysqli_fetch_array($gruy,MYSQLI_ASSOC);
$sql = "INSERT INTO pocketrefill (id, ref) VALUE ('',".mysqli_real_escape_string($conn, $ref).")";
mysqli_query($conn, $sql);
 if ($rsuy){
      $ruse = 1;
}
}

if ($ruse != 1){
foreach($activities as $reports) {
    if($reports['text3En'] == 'creditor') {
        $report = json_decode($wallet->GetReport($token,$reports['reportID']),true);//ดึงข้อมูลเเบบละเอียด
        $ref_t = $report['data']['section4']['column2']['cell1']['value'];
        $amount = $report['data']['section3']['column1']['cell1']['value'];
        $moneys = str_replace(',', '', $amount);
        if ($ref_t === $ref) {//ตรวจสอบเลขอ้างอิงว่าถูกต้องหรือไม่
        $status = 1;//เมื่อเลขอ้างอิงถูกต้อง
        break;
      }else {
        $status = 0;//เมื่อเลขอ้างอิงผิด
      }
    }
}
}

if ($login == "1"){
	$status = 3;
}
if ($ruse == "1"){
	$status = 2;
}
switch ($status) {

	case '1':
		$sender->sendMessage($pf."§aเเจ้งโอนสำเร็จ");
		//cmd1
		$command1 = $this->config->get("command1");
		$c1 = str_replace("{player}", $name, $command1);
        $cmd1 = str_replace("{amount}", $moneys, $c1);
		if ($cmd1 == false){
        }else {
        	$this->getServer()->dispatchCommand(new ConsoleCommandSender(), $cmd1);
        }
        //cmd2
        $command2 = $this->config->get("command2");
		$c2 = str_replace("{player}", $name, $command2);
        $cmd2 = str_replace("{amount}", $moneys, $c2);
		if ($cmd2 == false){
        }else {
        	$this->getServer()->dispatchCommand(new ConsoleCommandSender(), $cmd2);
        }
        //cmd3
        $command3 = $this->config->get("command3");
		$c3 = str_replace("{player}", $name, $command3);
        $cmd3 = str_replace("{amount}", $moneys, $c3);
		if ($cmd3 == false){
        }else {
        	$this->getServer()->dispatchCommand(new ConsoleCommandSender(), $cmd3);
        }
        //cmd4
        $command4 = $this->config->get("command4");
		$c4 = str_replace("{player}", $name, $command4);
        $cmd4 = str_replace("{amount}", $moneys, $c4);
		if ($cmd4 == false){
        }else {
        	$this->getServer()->dispatchCommand(new ConsoleCommandSender(), $cmd4);
        }
        //cmd5
        $command5 = $this->config->get("command5");
		$c5 = str_replace("{player}", $name, $command5);
        $cmd5 = str_replace("{amount}", $moneys, $c5);
		if ($cmd5 == false){
        }else {
        	$this->getServer()->dispatchCommand(new ConsoleCommandSender(), $cmd5);
        }
		break;
	case '2':
		$sender->sendMessage($pf."§aเลขอ้างอิงนี้ถูกเเจ้งไปเเล้ว");
		break;
	case '3':
		$sender->sendMessage($pf."§cอีเมลทรูวอเล็ทหรือรหัสผ่านทรูวอเล็ท ผิด");
		break;
	case '0':
		$sender->sendMessage($pf."§cเลขอ้างอิงผิด");
		break;
}
}else {
	$sender->sendMessage($pf."§6กรุณากรอกเลขอ้างอิง พิม /wallet [เลขอ้างอิง]");
}
break;

case 'truemoney':
$pf = "§9[§aPocket§eRefill§9] ";
$ref = $args[0];
if (isset($ref)){
$sender->sendMessage($pf."§eกรุณารอสักครู่...");
//start
$email = $this->config->get("email");//อีเมลทรูวอเล็ท
$password = $this->config->get("password");//รหัสผ่านทรูวอเล็ท
//end
$login = 0;
$wallet = new TMNWallet($email,$password);
$token = json_decode($wallet->GetToken(), true)['data']['accessToken'];
if (!$token){
    $login = 1;
}
$status = 0;
$ruse = 0;
$dtype = $this->config->get("data-type");
if ($dtype == "SQLITE"){
$prepare1 = $this->database->prepare("SELECT * FROM pocketrefills WHERE ref = :rf");
$prepare1->bindValue(":rf", $ref, SQLITE3_TEXT);
$gr = $prepare1->execute();
$dsl = $gr->fetchArray(SQLITE3_ASSOC);
if ($dsl){
      $ruse = 1;
}
$prepare2 = $this->database->prepare("INSERT INTO pocketrefills (ref) VALUES (:rf)");
$prepare2->bindValue(":rf", $ref, SQLITE3_TEXT);
$prepare2->execute();
}else if ($dtype == "MYSQL"){
$host = $this->config->get("host");
$username = $this->config->get("user");
$password = $this->config->get("pass");
$db = $this->config->get("database");
$conn = mysqli_connect($host, $username, $password, $db);
$sql2 = "SELECT id, ref FROM pocketrefills where ref = '".mysqli_real_escape_string($conn, $ref)."'";
$gruy = mysqli_query($conn, $sql2);
$rsuy = mysqli_fetch_array($gruy,MYSQLI_ASSOC);
$sql = "INSERT INTO pocketrefills (id, ref) VALUE ('',".mysqli_real_escape_string($conn, $ref).")";
mysqli_query($conn, $sql);
 if ($rsuy){
      $ruse = 1;
}
}

if ($ruse != 1){


$tm = json_decode($wallet->Topup($ref,$token));
if (isset($tm->amount)){
    $status = 1;
    $moneys = $tm->amount;
}else if(isset($tm->code)){
    if ($tm->code < 0){
        $status = 0;
    }
}

}

if ($login == "1"){
    $status = 3;
}
if ($ruse == "1"){
    $status = 2;
}
switch ($status) {

    case '1':
        $sender->sendMessage($pf."§aเติมเงินสำเร็จ");
        //cmd1
        $command1 = $this->config->get("command1");
        $c1 = str_replace("{player}", $name, $command1);
        $cmd1 = str_replace("{amount}", $moneys, $c1);
        if ($cmd1 == false){
        }else {
            $this->getServer()->dispatchCommand(new ConsoleCommandSender(), $cmd1);
        }
        //cmd2
        $command2 = $this->config->get("command2");
        $c2 = str_replace("{player}", $name, $command2);
        $cmd2 = str_replace("{amount}", $moneys, $c2);
        if ($cmd2 == false){
        }else {
            $this->getServer()->dispatchCommand(new ConsoleCommandSender(), $cmd2);
        }
        //cmd3
        $command3 = $this->config->get("command3");
        $c3 = str_replace("{player}", $name, $command3);
        $cmd3 = str_replace("{amount}", $moneys, $c3);
        if ($cmd3 == false){
        }else {
            $this->getServer()->dispatchCommand(new ConsoleCommandSender(), $cmd3);
        }
        //cmd4
        $command4 = $this->config->get("command4");
        $c4 = str_replace("{player}", $name, $command4);
        $cmd4 = str_replace("{amount}", $moneys, $c4);
        if ($cmd4 == false){
        }else {
            $this->getServer()->dispatchCommand(new ConsoleCommandSender(), $cmd4);
        }
        //cmd5
        $command5 = $this->config->get("command5");
        $c5 = str_replace("{player}", $name, $command5);
        $cmd5 = str_replace("{amount}", $moneys, $c5);
        if ($cmd5 == false){
        }else {
            $this->getServer()->dispatchCommand(new ConsoleCommandSender(), $cmd5);
        }
        break;
    case '2':
        $sender->sendMessage($pf."§aเลขบัตรทรูมันนี่นี้ถูกใช้ไปเเล้ว");
        break;
    case '3':
        $sender->sendMessage($pf."§cอีเมลทรูวอเล็ทหรือรหัสผ่านทรูวอเล็ท ผิด");
        break;
    case '0':
        $sender->sendMessage($pf."§cเลขบัตรทรูมันนี่ผิด");
        break;
}
}else {
    $sender->sendMessage($pf."§6กรุณากรอกเลขบัตรทรูมันนี่ พิม /truemoney [เลขบัตรทรูมันนี่]");
}

    break;
                    }
                }
        }
