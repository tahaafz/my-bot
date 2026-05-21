<?php
require_once __DIR__ . '/../../env/config.php';

function xui_cookie_path(string $url = 'default'): string {
    $key = sha1(rtrim($url, '/'));
    return sys_get_temp_dir() . "/xui_cookie_{$key}.txt";
}

function xui_cookie_is_valid(string $url): bool {
    $path = xui_cookie_path($url);
    if (!file_exists($path) || filemtime($path) < (time() - 86400)) {
        return false;
    }

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        if ($line[0] !== '#' || strpos($line, '#HttpOnly_') === 0) {
            return true;
        }
    }

    return false;
}

function xui_cookie_cleanup(string $url = 'default'): void {
    $path = xui_cookie_path($url);
    if (file_exists($path)) {
        unlink($path);
    }
}

function login($url,$username,$password,$force = false){
    if (!$force && xui_cookie_is_valid($url)) {
        return array('success' => true, 'cached' => true);
    }

    if ($force) {
        xui_cookie_cleanup($url);
    }

    $cookiePath = xui_cookie_path($url);
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $url.'/login',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT_MS => 4000,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => "username=$username&password=$password",
        CURLOPT_COOKIEJAR => $cookiePath,
        CURLOPT_COOKIEFILE => $cookiePath,
    ));
    $response = curl_exec($curl);
    if (curl_error($curl)) {
        $token = [];
        $token['error'] = curl_error($curl);
        $token['errror'] = $token['error'];
        curl_close($curl);
        return $token;
    }
    curl_close($curl);
    return json_decode($response,true);
}

function xui_request(array $panel, string $method, string $path, $postFields = null, array $headers = array('Accept: application/json')): array {
    $url = rtrim($panel['url_panel'], '/');
    login($url, $panel['username_panel'], $panel['password_panel']);

    $request = function () use ($url, $method, $path, $postFields, $headers) {
        $curl = curl_init();
        $options = array(
            CURLOPT_URL => $url . $path,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_COOKIEFILE => xui_cookie_path($url),
            CURLOPT_COOKIEJAR => xui_cookie_path($url),
            CURLOPT_SSL_VERIFYPEER => false,
        );
        if ($postFields !== null) {
            $options[CURLOPT_POSTFIELDS] = $postFields;
        }
        curl_setopt_array($curl, $options);
        $raw = curl_exec($curl);
        $error = curl_error($curl);
        $httpCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        return array(
            'body' => $raw,
            'error' => $error,
            'http_code' => $httpCode,
        );
    };

    $response = $request();
    if (in_array($response['http_code'], array(401, 403, 404), true) || $response['body'] === false || $response['body'] === '') {
        login($url, $panel['username_panel'], $panel['password_panel'], true);
        $response = $request();
    }

    return $response;
}


function get_Client($username,$namepanel){
    global $connect;
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $namepanel,"select");
    $response = xui_request($marzban_list_get, 'GET', '/panel/api/inbounds/getClientTraffics/' . rawurlencode($username));
    $decoded = json_decode($response['body'], true);
    return isset($decoded['obj']) ? $decoded['obj'] : null;
}
function get_clinets($username,$namepanel){
    global $connect;
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $namepanel,"select");
    $output = [];
    $response = xui_request($marzban_list_get, 'GET', '/panel/api/inbounds/list');
    $decoded = json_decode($response['body'], true);
    $response = $decoded['obj'] ?? [];
    foreach ($response as $client) {
        $clients = json_decode($client['settings'] ?? '{}', true)['clients'] ?? [];
        foreach ($clients as $clinets) {
            if (($clinets['email'] ?? '') == $username) {
                $output = $clinets;
                break 2;
            }
        }
    }
    return $output;
}
function addClient($namepanel, $usernameac, $Expire,$Total, $Uuid,$Flow,$subid){
    global $connect;
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $namepanel,"select");
    $Allowedusername = get_Client($usernameac,$namepanel);
    if (isset($Allowedusername['email'])) {
        $random_number = rand(1000000, 9999999);
        $username_ac = $usernameac . $random_number;
    }
    $config = array(
        "id" => intval($marzban_list_get['inboundid']),
        'settings' => json_encode(array(
            'clients' => array(
                array(
                    "id" => $Uuid,
                    "flow" => $Flow,
                    "email" => $usernameac,
                    "totalGB" => $Total,
                    "expiryTime" => $Expire,
                    "enable" => true,
                    "tgId" => "",
                    "subId" => $subid,
                    "reset" => 0
                )),
            'decryption' => 'none',
            'fallbacks' => array(),
        ))
    );

    $configpanel = json_encode($config,true);
    $response = xui_request($marzban_list_get, 'POST', '/panel/api/inbounds/addClient', $configpanel, array(
        'Accept: application/json',
        'Content-Type: application/json',
    ));
    return json_decode($response['body'], true);
}
function updateClient($namepanel, $username,array $config){
    global $connect;
    $settings = isset($config['settings']) ? json_decode($config['settings'], true) : array();
    $clientId = $settings['clients'][0]['id'] ?? null;
    if(empty($clientId)){
        $UsernameData = get_clinets($username,$namepanel);
        if(!is_array($UsernameData) || empty($UsernameData['id'])){
            return array(
                'success' => false,
                'msg' => 'User not found',
            );
        }
        $clientId = $UsernameData['id'];
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $namepanel,"select");
    $configpanel = json_encode($config,true);
    $response = xui_request($marzban_list_get, 'POST', '/panel/api/inbounds/updateClient/' . rawurlencode($clientId), $configpanel, array(
        'Accept: application/json',
        'Content-Type: application/json',
    ));
    return json_decode($response['body'], true);
}
function ResetUserDataUsagex_uisin($usernamepanel, $namepanel){
    global $connect;
    $data_user = get_clinets($usernamepanel,$namepanel);
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $namepanel,"select");
    xui_request($marzban_list_get, 'POST', "/panel/api/inbounds/{$marzban_list_get['inboundid']}/resetClientTraffic/" . rawurlencode($data_user['email']));
}
function removeClient($location,$username){
    global $connect;
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $location,"select");
    $data_user = get_clinets($username,$location);
    $response = xui_request($marzban_list_get, 'POST', "/panel/api/inbounds/{$marzban_list_get['inboundid']}/delClient/" . rawurlencode($data_user['id']));
    return json_decode($response['body'],true);
}
function get_onlinecli($name_panel,$username){
    global $connect;
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $name_panel,"select");
    $response = xui_request($marzban_list_get, 'POST', '/panel/api/inbounds/onlines');
    $decoded = json_decode($response['body'], true);
    $response = $decoded['obj'] ?? null;
    if($response == null)return "offline";
    if(in_array($username,$response))return "online";
    return "offline";

}

function build_client_link($namepanel, $uuid, $email){
    global $connect;
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $namepanel, "select");
    $response = xui_request($marzban_list_get, 'GET', '/panel/api/inbounds/list');
    $decoded = json_decode($response['body'], true);
    $inbounds = $decoded['obj'] ?? [];
    $inboundId = intval($marzban_list_get['inboundid']);
    $inbound = null;
    foreach ($inbounds as $item) {
        if ((int)$item['id'] === $inboundId) {
            $inbound = $item;
            break;
        }
    }
    if (!$inbound) return null;

    $protocol = $inbound['protocol'] ?? 'vless';
    $port = $inbound['port'] ?? 443;

    $linksubx = $marzban_list_get['linksubx'] ?? '';
    $parsed = parse_url(strpos($linksubx, '://') === false ? 'https://' . $linksubx : $linksubx);
    $host = $parsed['host'] ?? '';

    $streamSettings = json_decode($inbound['streamSettings'] ?? '{}', true);
    $network = $streamSettings['network'] ?? 'tcp';
    $security = $streamSettings['security'] ?? 'none';

    $params = ['type' => $network, 'encryption' => 'none', 'security' => $security];

    if ($security === 'tls') {
        $tls = $streamSettings['tlsSettings'] ?? [];
        if (!empty($tls['serverName'])) $params['sni'] = $tls['serverName'];
        if (!empty($tls['fingerprint'])) $params['fp'] = $tls['fingerprint'];
        if (!empty($tls['alpn'])) $params['alpn'] = implode(',', (array)$tls['alpn']);
    } elseif ($security === 'reality') {
        $reality = $streamSettings['realitySettings'] ?? [];
        if (!empty($reality['serverName'])) $params['sni'] = $reality['serverName'];
        if (!empty($reality['fingerprint'])) $params['fp'] = $reality['fingerprint'];
        if (!empty($reality['publicKey'])) $params['pbk'] = $reality['publicKey'];
        if (!empty($reality['shortIds'][0])) $params['sid'] = $reality['shortIds'][0];
        if (!empty($reality['spiderX'])) $params['spx'] = $reality['spiderX'];
        $params['flow'] = 'xtls-rprx-vision';
    }

    if ($network === 'ws') {
        $ws = $streamSettings['wsSettings'] ?? [];
        if (!empty($ws['path'])) $params['path'] = $ws['path'];
        if (!empty($ws['headers']['Host'])) $params['host'] = $ws['headers']['Host'];
    } elseif ($network === 'grpc') {
        $grpc = $streamSettings['grpcSettings'] ?? [];
        if (!empty($grpc['serviceName'])) $params['serviceName'] = $grpc['serviceName'];
        $params['mode'] = 'gun';
    } elseif ($network === 'h2' || $network === 'http') {
        $http = $streamSettings['httpSettings'] ?? [];
        if (!empty($http['path'])) $params['path'] = $http['path'];
        if (!empty($http['host'][0])) $params['host'] = $http['host'][0];
    } elseif ($network === 'tcp') {
        $tcp = $streamSettings['tcpSettings'] ?? [];
        $headerType = $tcp['header']['type'] ?? 'none';
        if ($headerType !== 'none') $params['headerType'] = $headerType;
    }

    $query = http_build_query($params);
    return "{$protocol}://{$uuid}@{$host}:{$port}?{$query}#" . rawurlencode($email);
}
