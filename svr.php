<?php
ini_set("log_errors","1");
ini_set("error_log",'../socket.log');

define('HOST_NAME',"0.0.0.0"); 
define('PORT',"1111");

$null = NULL;

require_once("zSocket.php");
$zsocket = new zSocket();

$socketResource = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
socket_set_option($socketResource, SOL_SOCKET, SO_REUSEADDR, 1);
socket_bind($socketResource, 0, PORT);
socket_listen($socketResource);

$clientSocketArray = array($socketResource);
while (true) {
	$newSocketArray = $clientSocketArray;
	socket_select($newSocketArray, $null, $null, 0, 10);
	
	if (in_array($socketResource, $newSocketArray)) {
        echo 'connected';
		$newSocket = socket_accept($socketResource);
		$clientSocketArray[] = $newSocket;
		
		$header = socket_read($newSocket, 1024);
		$zsocket->doHandshake($header, $newSocket, HOST_NAME, PORT);
		
		socket_getpeername($newSocket, $client_ip_address);
		$connectionACK = $zsocket->newConnectionACK($client_ip_address);
		
		$zsocket->send($connectionACK);
		
		$newSocketIndex = array_search($socketResource, $newSocketArray);
		unset($newSocketArray[$newSocketIndex]);
	}
	
	foreach ($newSocketArray as $newSocketArrayResource) {	
		while(socket_recv($newSocketArrayResource, $socketData, 1024, 0) >= 1){
            $socketMessage = $zsocket->unseal($socketData);
            $decodeData = base64_encode($socketData);
            
            echo "socketData\n";
            echo "=>".$socketData;
            echo "end socketData\n\n";
            echo "socketMessage\n";
            echo $socketMessage;
            echo "end socketMessage\n";
            $msg = [
                "type"=>"send",
            ];
			//$messageObj = json_decode($socketMessage);
			
            //$chat_box_message = $chatHandler->createChatBoxMessage($messageObj->chat_user, $messageObj->chat_message);
            $chat_box_message = $zsocket->createChatBoxMessage("zMessing", $socketMessage);
			$zsocket->send($chat_box_message);
			break 2;
		}
		
		$socketData = @socket_read($newSocketArrayResource, 1024, PHP_NORMAL_READ);
		if ($socketData === false) { 
			socket_getpeername($newSocketArrayResource, $client_ip_address);
			$connectionACK = $zsocket->connectionDisconnectACK($client_ip_address);
			$zsocket->send($connectionACK);
			$newSocketIndex = array_search($newSocketArrayResource, $clientSocketArray);
			unset($clientSocketArray[$newSocketIndex]);			
		}
	}
}
socket_close($socketResource);
?>