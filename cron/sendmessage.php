<?php
ini_set('error_log', 'error_log');
date_default_timezone_set('Asia/Tehran');
require_once __DIR__ . '/../../../env/config.php';
require_once '../botapi.php';
require_once '../functions.php';
require_once '../keyboard.php';
if(!is_file('info'))return;
if(!is_file('users.json'))return;


$userid = json_decode(file_get_contents('users.json'));
$info = json_decode(file_get_contents('info'),true);
$count = 0;
if(count($userid) == 0){
    if(isset($info['id_admin'])){
    sendmessage($info['id_admin'], "📌 پیام برای تمامی کاربران ارسال گردید.", null, 'HTML');
    unlink('info');
    }
    return;
    
}
foreach ($userid as $iduser){
        if($count == 20)break;
            $sent = sendmessage($iduser->id, $info['text'], mainMenuKeyboard($iduser->id), 'HTML');
            if (!empty($info['pin']) && $sent && isset($sent->result->message_id)) {
                pinChatMessage($iduser->id, $sent->result->message_id);
            }
        unset($userid[0]);
        $userid = array_values($userid);
        $count +=1;
}
file_put_contents('users.json',json_encode($userid,true));