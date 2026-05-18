<?php
require_once 'functions.php';
require_once __DIR__ . '/../../env/config.php';
#-----------------------------#
function token_panel($url_panel, $username_panel, $password_panel){
    $url_get_token = $url_panel . '/api/admin/token';
    $data_token = array(
        'username' => $username_panel,
        'password' => $password_panel
    );
    $options = array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_TIMEOUT => 40, // Total time in seconds to wait for the request
        CURLOPT_CONNECTTIMEOUT => 20, // Time in seconds to wait while trying to connect
        CURLOPT_POSTFIELDS => http_build_query($data_token),
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/x-www-form-urlencoded',
            'accept: application/json'
        ),
        CURLOPT_CAINFO => "fullchain.cer"
    );

    $curl_token = curl_init($url_get_token);
    curl_setopt_array($curl_token, $options);
    $token = curl_exec($curl_token);
    if (curl_error($curl_token)) {
        $token = [];
        $token['error'] = curl_error($curl_token);
        return $token;
    }
    curl_close($curl_token);

    $body = json_decode($token, true);
    return $body;
}

#-----------------------------#

function getuser($usernameac,$location)
{
    global $panel_apikey;
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $location,"select");
  //  $Check_token = token_panel($marzban_list_get['url_panel'], $marzban_list_get['username_panel'], $marzban_list_get['password_panel']);
    $url =  $marzban_list_get['url_panel'].'/api/user/' . $usernameac;
    $header_value = 'Bearer ';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPGET, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Accept: application/json',
        'Authorization: ' . $header_value .  $panel_apikey
    ));
    curl_setopt($ch, CURLOPT_CAINFO, "fullchain.cer");
    
    
    $output = curl_exec($ch);

    curl_close($ch);
    $data_useer = json_decode($output, true);
    return $data_useer;
}
#-----------------------------#
function ResetUserDataUsage($usernameac,$location)
{
    global $panel_apikey;
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $location,"select");
   // $Check_token = token_panel($marzban_list_get['url_panel'], $marzban_list_get['username_panel'], $marzban_list_get['password_panel']);
    $url =  $marzban_list_get['url_panel'].'/api/user/' . $usernameac.'/reset';
    $header_value = 'Bearer ';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST , true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Accept: application/json',
        'Authorization: ' . $header_value .  $panel_apikey
    ));
    curl_setopt($ch, CURLOPT_CAINFO, "fullchain.cer");

    $output = curl_exec($ch);
    curl_close($ch);
    $data_useer = json_decode($output, true);
    return $data_useer;
}
#-----------------------------#
function adduser($username,$expire,$data_limit,$location)
{
    global $panel_apikey;
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $location,"select");
  //  $Check_token = token_panel($marzban_list_get['url_panel'], $marzban_list_get['username_panel'], $marzban_list_get['password_panel']);
    $url = $marzban_list_get['url_panel']."/api/user";
    $nameprotocol = array();
if(isset($marzban_list_get['vless']) && $marzban_list_get['vless'] == "onvless"){
    $nameprotocol['vless'] = array();
}
if(isset($marzban_list_get['vmess']) && $marzban_list_get['vmess'] == "onvmess"){
    $nameprotocol['vmess'] = array();
}
if(isset($marzban_list_get['trojan']) && $marzban_list_get['trojan'] == "ontrojan"){
    $nameprotocol['trojan'] = array();
}
if(isset($marzban_list_get['shadowsocks']) && $marzban_list_get['shadowsocks'] == "onshadowsocks"){
    $nameprotocol['shadowsocks'] = array();
}
if(isset($nameprotocol['vless']) && $marzban_list_get['flow'] == "flowon"){
    $nameprotocol['vless']['flow'] = 'xtls-rprx-vision';
}
    $header_value = 'Bearer ';
    $data = array(
        "proxies" => json_decode($marzban_list_get['proxies']),
        "inbounds"  => json_decode($marzban_list_get['inbounds']),
        "data_limit" => $data_limit,
        "username" => $username
    );
    if($expire == "0"){
        $data['expire'] = 0;
    }else {
        $data['expire'] = $expire;
    }
    $payload = json_encode($data);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Accept: application/json',
        'Authorization: ' . $header_value .  $panel_apikey,
        'Content-Type: application/json'
    ));
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_CAINFO, "fullchain.cer");

    $response = curl_exec($ch);
    curl_close($ch);

    return $response;
}
//----------------------------------
function Get_System_Stats($location){
    global $panel_apikey;
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $location,"select");
  //  $Check_token = token_panel($marzban_list_get['url_panel'], $marzban_list_get['username_panel'], $marzban_list_get['password_panel']);
    $url =  $marzban_list_get['url_panel'].'/api/system';
    $header_value = 'Bearer ';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPGET, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Accept: application/json',
        'Authorization: ' . $header_value .  $panel_apikey,
    ));
    curl_setopt($ch, CURLOPT_CAINFO, "fullchain.cer");

    $output = curl_exec($ch);
    curl_close($ch);
    $Get_System_Stats = json_decode($output, true);
    return $Get_System_Stats;
}
//----------------------------------
function removeuser($location,$username)
{
    global $panel_apikey;
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $location,"select");
 //   $Check_token = token_panel($marzban_list_get['url_panel'], $marzban_list_get['username_panel'], $marzban_list_get['password_panel']);
    $url =  $marzban_list_get['url_panel'].'/api/user/'.$username;
    $header_value = 'Bearer ';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    curl_setopt($ch, CURLOPT_HTTPGET, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Accept: application/json',
        'Authorization: ' . $header_value .  $panel_apikey
    ));
    curl_setopt($ch, CURLOPT_CAINFO, "fullchain.cer");

    $output = curl_exec($ch);
    curl_close($ch);
    $data_useer = json_decode($output, true);
    return $data_useer;
}
//----------------------------------
function Modifyuser($location,$username,array $data)
{
    global $panel_apikey;
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $location,"select");
//    $Check_token = token_panel($marzban_list_get['url_panel'], $marzban_list_get['username_panel'], $marzban_list_get['password_panel']);
    $url =  $marzban_list_get['url_panel'].'/api/user/'.$username;
    $payload = json_encode($data);
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
$headers = array();
$headers[] = 'Accept: application/json';
$headers[] = 'Authorization: Bearer '.$panel_apikey;
$headers[] = 'Content-Type: application/json';
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_CAINFO, "fullchain.cer");

$result = curl_exec($ch);
curl_close($ch);
     $data_useer = json_decode($result, true);
    return $data_useer;
}

#-----------------------------------------------#
function revoke_sub($username,$location)
{
    global $panel_apikey;
    global $connect;
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $location,"select");
  //  $Check_token = token_panel($marzban_list_get['url_panel'], $marzban_list_get['username_panel'], $marzban_list_get['password_panel']);
    $usernameac = $username;
    $url =  $marzban_list_get['url_panel'].'/api/user/' . $usernameac.'/revoke_sub';
    $header_value = 'Bearer ';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST , true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Accept: application/json',
        'Authorization: ' . $header_value .  $panel_apikey
    ));
    curl_setopt($ch, CURLOPT_CAINFO, "fullchain.cer");

    $output = curl_exec($ch);
    curl_close($ch);
    $data_useer = json_decode($output, true);
    return $data_useer;
}
