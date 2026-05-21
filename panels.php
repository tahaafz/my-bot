<?php
ini_set('error_log', 'error_log');
require_once __DIR__ . '/../../env/config.php';
require_once 'apipanel.php';
require_once 'x-ui_single.php';
class ManagePanel{
    public $name_panel;
    public $connect;
    function createUser($name_panel,$usernameC, array $Data_Config){
        $Output = [];
        global $connect;
        // input time expire timestep use $Data_Config
        // input data_limit byte use $Data_Config
        // input username use $Data_Config
        // input from_id use $Data_Config
        // input type config use $Data_Config

        $Get_Data_Panel = select("marzban_panel", "*", "name_panel", $name_panel,"select");
        $expire = $Data_Config['expire'];
        $data_limit = $Data_Config['data_limit'];
        if($Get_Data_Panel['type'] == "marzban"){
            //create user
            $ConnectToPanel= adduser($usernameC,$expire,$data_limit,$Get_Data_Panel['name_panel']);
            $data_Output = json_decode($ConnectToPanel, true);
            if(isset($data_Output['detail']) && $data_Output['detail']){
                $Output['status'] = 'Unsuccessful';
                $Output['msg'] = $data_Output['detail'];
            }else{
                if (!preg_match('/^(https?:\/\/)?([a-zA-Z0-9-]+\.)+[a-zA-Z]{2,}(:\d+)?((\/[^\s\/]+)+)?$/', $data_Output['subscription_url'])) {
                    $data_Output['subscription_url'] = $Get_Data_Panel['url_panel'] . "/" . ltrim($data_Output['subscription_url'], "/");
                }
                $Output['status'] = 'successful';
                $Output['username'] = $data_Output['username'];
                $Output['subscription_url'] = $data_Output['subscription_url'];
                $Output['configs'] = $data_Output['links'];
            }
        }
        elseif($Get_Data_Panel['type'] == "x-ui_single"){
            $subId = bin2hex(random_bytes(8));
            $Expireac = $expire*1000;
            $uuid = generateUUID();
            $data_Output = addClient($Get_Data_Panel['name_panel'],$usernameC,$Expireac,$data_limit,$uuid,"",$subId);
            if(!is_array($data_Output) || empty($data_Output['success'])){
                $Output['status'] = 'Unsuccessful';
                $Output['msg'] = is_array($data_Output) ? ($data_Output['msg'] ?? 'empty or unexpected response from panel') : 'empty or unexpected response from panel';
            }else{
                $Output['status'] = 'successful';
                $Output['username'] = $usernameC;
                $domain = explode(":", $Get_Data_Panel['linksubx']);
                $Output['subscription_url'] = $domain[0].":".$domain[1].":2096/sub/{$subId}?name=$subId";
                $directLink = build_client_link($Get_Data_Panel['name_panel'], $uuid, $usernameC);
                if ($directLink) {
                    $Output['configs'] = [$directLink];
                } else {
                    $raw = outputlunk($Output['subscription_url']);
                    $decoded = base64_decode($raw, true);
                    $content = ($decoded !== false && !empty(trim($decoded))) ? trim($decoded) : trim($raw);
                    $Output['configs'] = array_values(array_filter(explode("\n", $content)));
                }
            }
        }
        else{
            $Output['status'] = 'Unsuccessful';
            $Output['msg'] = 'Panel Not Found';
        }
        return $Output;
    }
    function DataUser($name_panel,$username){
        $Output = array();
        global $connect;
        $Get_Data_Panel = select("marzban_panel", "*", "name_panel", $name_panel,"select");
        if($Get_Data_Panel['type'] == "marzban"){
            $UsernameData = getuser($username,$Get_Data_Panel['name_panel']);
            if(isset($UsernameData['detail']) && $UsernameData['detail']){
                $Output = array(
                    'status' => 'Unsuccessful',
                    'msg' => $UsernameData['detail']
                );
            }elseif(!isset($UsernameData['username'])){
                $Output = array(
                    'status' => 'Unsuccessful',
                    'msg' => @$UsernameData['detail']
                );
            }else{
                if (!preg_match('/^(https?:\/\/)?([a-zA-Z0-9-]+\.)+[a-zA-Z]{2,}(:\d+)?((\/[^\s\/]+)+)?$/', $UsernameData['subscription_url'])) {
                    $UsernameData['subscription_url'] = $Get_Data_Panel['url_panel'] . "/" . ltrim($UsernameData['subscription_url'], "/");
                }

                $Output = array(
                    'status' => $UsernameData['status'],
                    'username' => $UsernameData['username'],
                    'data_limit' => $UsernameData['data_limit'],
                    'expire' => $UsernameData['expire'],
                    'online_at' => $UsernameData['online_at'],
                    'used_traffic' => $UsernameData['used_traffic'],
                    'links' => $UsernameData['links'],
                    'subscription_url' => $UsernameData['subscription_url'],
                );
            }
        }
        elseif($Get_Data_Panel['type'] == "x-ui_single"){
            $UsernameData = get_Client($username,$Get_Data_Panel['name_panel']);
            if(!is_array($UsernameData) || empty($UsernameData['id'])){
                $Output = array(
                    'status' => 'Unsuccessful',
                    'msg' => is_array($UsernameData) ? ($UsernameData['msg'] ?? 'User not found') : 'empty or unexpected response from panel'
                );
            }else{
                if($UsernameData['enable']){
                    $UsernameData['enable'] = "active";
                }else{
                    $UsernameData['enable'] = "disabled";
                }
                $domain = explode(":", $Get_Data_Panel['linksubx']);
                $subId = $UsernameData['subId'] ?? '';
                $subscription_url = $domain[0].":".$domain[1].":2096/sub/{$subId}?name={$subId}";
                $raw_sub = outputlunk($subscription_url);
                $decoded_sub = base64_decode($raw_sub, true);
                $sub_content = ($decoded_sub !== false && !empty(trim($decoded_sub))) ? trim($decoded_sub) : trim($raw_sub);
                $links_user = array_values(array_filter(explode("\n", $sub_content)));
                $Output = array(
                    'status' => $UsernameData['enable'],
                    'username' => $UsernameData['email'],
                    'data_limit' => $UsernameData['total'],
                    'expire' => $UsernameData['expiryTime']/1000,
                    'online_at' => null,
                    'used_traffic' => $UsernameData['up']+$UsernameData['down'],
                    'links' => $links_user,
                    'subscription_url' => $subscription_url,
                );
            }
        }
        else{
            $Output = array(
                'status' => 'Unsuccessful',
                'msg' => 'Panel Not Found'
            );
        }
        return $Output;
    }
    function Revoke_sub($name_panel,$username){
        $Output = array();
        global $connect;
        $Get_Data_Panel = select("marzban_panel", "*", "name_panel", $name_panel,"select");
        if($Get_Data_Panel['type'] == "marzban"){
            $revoke_sub = revoke_sub($username,$name_panel);
            if(isset($revoke_sub['detail']) && $revoke_sub['detail']){
                $Output = array(
                    'status' => 'Unsuccessful',
                    'msg' => $revoke_sub['detail']
                );
            }else{
                $config = new ManagePanel();
                $Data_User  = $config->DataUser($name_panel,$username);
                if (!preg_match('/^(https?:\/\/)?([a-zA-Z0-9-]+\.)+[a-zA-Z]{2,}(:\d+)?((\/[^\s\/]+)+)?$/', $Data_User['subscription_url'])) {
                    $Data_User['subscription_url'] = $Get_Data_Panel['url_panel'] . "/" . ltrim($Data_User['subscription_url'], "/");
                }
                $Output = array(
                    'status' => 'successful',
                    'configs' => $Data_User['links'],
                    'subscription_url' => $Data_User['subscription_url']
                );
            }
        }
        elseif($Get_Data_Panel['type'] == "x-ui_single"){
            $clients = get_clinets($username,$name_panel);
            $subId = bin2hex(random_bytes(8));
            $config = array(
                'id' => intval($Get_Data_Panel['inboundid']),
                'settings' => json_encode(array(
                        'clients' => array(
                            array(
                                "id" => generateUUID(),
                                "flow" => $clients['flow'],
                                "email" => $clients['email'],
                                "totalGB" => $clients['totalGB'],
                                "expiryTime" => $clients['expiryTime'],
                                "enable" => true,
                                "subId" => $subId,
                            )),
                    )
                )
            );
            $updateinbound = updateClient($Get_Data_Panel['name_panel'],$username,$config);
            if(!$clients){
                $Output = array(
                    'status' => 'Unsuccessful',
                    'msg' => 'Unsuccessful'
                );
            }else{
                $domain = explode(":", $Get_Data_Panel['linksubx']);
                $Output = array(
                    'status' => 'successful',
                    'configs' => outputlunk($domain[0].":".$domain[1].":2096/sub/{$subId}?name=$subId"),
                    'subscription_url' => $domain[0].":".$domain[1].":2096/sub/{$subId}?name=$subId",
                );
            }
        }

        else{
            $Output = array(
                'status' => 'Unsuccessful',
                'msg' => 'Panel Not Found'
            );
        }
        return $Output;
    }
    function RemoveUser($name_panel,$username){
        $Output = array();
        global $connect;
        $Get_Data_Panel = select("marzban_panel", "*", "name_panel", $name_panel,"select");
        if($Get_Data_Panel['type'] == "marzban"){
            $UsernameData = removeuser($Get_Data_Panel['name_panel'],$username);
            if(isset($UsernameData['detail']) && $UsernameData['detail']){
                $Output = array(
                    'status' => 'Unsuccessful',
                    'msg' => $UsernameData['detail']
                );
            }else{
                $Output = array(
                    'status' => 'successful',
                    'username' => $username,
                );
            }
        }
        elseif($Get_Data_Panel['type'] == "x-ui_single"){
            $UsernameData = removeClient($Get_Data_Panel['name_panel'],$username);
            if(!$UsernameData['success']){
                $Output = array(
                    'status' => 'Unsuccessful',
                    'msg' => $UsernameData['msg']
                );
            }else{
                $Output = array(
                    'status' => 'successful',
                    'username' => $username,
                );
            }
        }
        else{
            $Output = array(
                'status' => 'Unsuccessful',
                'msg' => 'Panel Not Found'
            );
        }
        return $Output;
    }
    function ResetUserDataUsage($name_panel,$username){
        $Output = array();
        global $connect;
        $Get_Data_Panel = select("marzban_panel", "*", "name_panel", $name_panel,"select");
        if($Get_Data_Panel['type'] == "marzban"){
            return ResetUserDataUsage($username, $name_panel);
        }
        elseif($Get_Data_Panel['type'] == "x-ui_single"){
            return ResetUserDataUsagex_uisin($username, $name_panel);
        }
        return $Output;
    }
    function Modifyuser($username,$name_panel,$config = array()){
        $Output = array();
        global $connect;
        $Get_Data_Panel = select("marzban_panel", "*", "name_panel", $name_panel,"select");
        if($Get_Data_Panel['type'] == "marzban"){
            $response = Modifyuser($name_panel, $username, $config);
            if(isset($response['detail']) && $response['detail']){
                return array(
                    'status' => 'Unsuccessful',
                    'msg' => is_array($response['detail']) ? json_encode($response['detail']) : $response['detail'],
                );
            }
            return array(
                'status' => 'successful',
                'response' => $response,
            );
        }elseif($Get_Data_Panel['type'] == "x-ui_single"){
            $clients = get_clinets($username, $name_panel);
            if(!is_array($clients) || empty($clients['id'])){
                return array(
                    'status' => 'Unsuccessful',
                    'msg' => 'User not found',
                );
            }

            $incomingSettings = array();
            if(isset($config['settings'])){
                $incomingSettings = is_array($config['settings'])
                    ? $config['settings']
                    : (json_decode($config['settings'], true) ?: array());
            }
            if(isset($config['data_limit'])){
                $incomingSettings['clients'][0]['totalGB'] = (int) round((float) $config['data_limit']);
            }
            if(isset($config['expire'])){
                $expire = (int) $config['expire'];
                $incomingSettings['clients'][0]['expiryTime'] = $expire > 9999999999 ? $expire : $expire * 1000;
            }

            $baseSettings = array(
                'clients' => array(
                    array_replace_recursive($clients, array(
                        "id" => $clients['id'],
                        "flow" => $clients['flow'] ?? '',
                        "email" => $clients['email'],
                        "totalGB" => (int) ($clients['totalGB'] ?? $clients['total'] ?? 0),
                        "expiryTime" => (int) ($clients['expiryTime'] ?? 0),
                        "enable" => true,
                        "subId" => $clients['subId'] ?? '',
                    ))
                ),
                'decryption' => 'none',
                'fallbacks' => array(),
            );

            $configs = array(
                'id' => intval($Get_Data_Panel['inboundid']),
                'settings' => json_encode(array_replace_recursive($baseSettings, $incomingSettings)),
            );
            $updateinbound = updateClient($Get_Data_Panel['name_panel'], $username,$configs);
            if(!is_array($updateinbound) || empty($updateinbound['success'])){
                $targetClient = json_decode($configs['settings'], true)['clients'][0] ?? array();
                $liveClient = get_clinets($username, $name_panel);
                $trafficMatches = !isset($targetClient['totalGB']) || (
                    isset($liveClient['totalGB']) && (int) $liveClient['totalGB'] === (int) $targetClient['totalGB']
                );
                $expiryMatches = !isset($targetClient['expiryTime']) || (
                    isset($liveClient['expiryTime']) && abs((int) $liveClient['expiryTime'] - (int) $targetClient['expiryTime']) < 1000
                );
                if(is_array($liveClient) && !empty($liveClient['id']) && $trafficMatches && $expiryMatches){
                    return array(
                        'status' => 'successful',
                        'response' => $updateinbound,
                        'verified_after_update' => true,
                    );
                }
                return array(
                    'status' => 'Unsuccessful',
                    'msg' => is_array($updateinbound) ? ($updateinbound['msg'] ?? 'empty or unexpected response from panel') : 'empty or unexpected response from panel',
                    'response' => $updateinbound,
                );
            }
            return array(
                'status' => 'successful',
                'response' => $updateinbound,
            );
        }
        return array(
            'status' => 'Unsuccessful',
            'msg' => 'Panel Not Found',
        );

    }



}
