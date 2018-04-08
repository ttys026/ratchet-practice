<?php
namespace MyApp;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Wamp\Exception;
//error_reporting(0);
class Chat implements MessageComponentInterface {
    private $users;
    private $id = 1;
    private static $pdo = null;
    public function __construct() {
        $this->users = [];
    }
    public function onOpen(ConnectionInterface $conn) {
        try {
            $query = $conn->httpRequest->getUri()->getQuery();
            //get query from URL like ws://127.0.0.1:8080/?id=123456
            $query_list = explode("&", $query);
            $usr = trim(substr($query_list[0], 3));
            if($usr != ''){
                $user_id = $usr;
            }
            else{
                $user_id = $this->id;
                $this->id = $this->id + 1;
            }
            // Use an auto increment id if you don't specify one in URL.
            $conn->__set('user_id', $user_id);
            //The setter located at vendor/cboden/ratchet/src/Ratchet/AbstractConnectionDecorator.php
            //Saving user id and map it to specific connection
            
            if(isset($this->users[$user_id]) && !is_null($user_id)){
                $this->users[$user_id]->send(json_encode(array(
                    'type'=>'logOutUser'
                )));
                //Sending some information tell the old connection have been logged out.

                $this->users[$user_id]->__set('delete_flag', false);
                // It is necessary to have the flag in order to abort closed connections correctly.

                $this->users[$user_id]->close();
                unset($this->users[$user_id]);
                echo 'kick out!!!!'.PHP_EOL;
            }
            $this->users[$user_id] = $conn;
            echo $user_id .' joined chat  '. (string)sizeof($this->users).'  user(s) online now!'.PHP_EOL;
            //...$res = $this->fetch_message($user_id);
            //...$conn->send(json_encode($res));
        }
        catch (\Exception $e){
            echo '-----------------'.$e->getMessage();
        }
    }
    public function onMessage(ConnectionInterface $from, $msg) {
        $sender = $from->__get('user_id');
        $json = json_decode($msg, true);
        $receiver = isset($json['receiver']) ? $json['receiver'] : '';
        $content = isset($json['content']) ? $json['content'] : '';
        $image = isset($json['image']) ? $json['image'] : '';
        // parse Json

        $receiverConn = isset($this->users[$receiver]) ? $this->users[$receiver] : null;
        // Get the receiver's connection

        if($receiver == '' || $content == ''){
            return;
        }
        //Abort on invalid Json information.

        if (is_null($receiverConn)) {
            // The reseiver is offline.
            // Sending message to database.
            echo 'send by db'.PHP_EOL;
        }
        else {
            try {
                $receiverConn->send('['.json_encode(array(
                        'id' => '-1',
                        'sender' => $sender,
                        'receiver' => $receiver,
                        'datetime' => time(),
                        'content' => $content,
                        'image' => $image,
                    )).']');
                echo 'send by ws'.PHP_EOL;

            } catch (Exception $e) {
                //If sending by websocket failed
                //Need to send message to database here.
                echo 'send by db2'.PHP_EOL;
            }
            $from->send('success');
        }
    }
    public function onClose(ConnectionInterface $conn) {
        try {
            if (!$conn->__isset('delete_flag')) {
                echo $conn->__get('user_id') .' left chat  '. (string)sizeof($this->users).'  user(s) online now!'.PHP_EOL;
                unset($this->users[$conn->__get('user_id')]);
            }
            // Use the delete flag to check if the user is disconnectted and is not kicked out by logging elsewhere.
            // remove the connection from array.
        }catch (Exception $e){
            echo $e->getMessage();
        }
    }
    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "An error has occurred: {$e->getMessage()}\n";
        $conn->close();
    }
    private function pdo() {
        // This ensures we only create the PDO object once
        /*
        if(self::$pdo !== null) {
            return self::$pdo;
        }
        try {
            self::$pdo = new \PDO('mysql:host=127.0.0.1;dbname=test;charset=test', "test", "test",array(
                \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            ));
            date_default_timezone_set('America/Detroit');
        }catch (\PDOException $e){
            echo "Database connection Failed";
        }
        return self::$pdo;*/
    }
}
