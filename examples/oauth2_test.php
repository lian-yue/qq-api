<?php
namespace LianYue\QQApi;
require __DIR__ . '/config.php';

$accessToken = empty($_COOKIE['qq_oauth2_access_token']) ? '' : $_COOKIE['qq_oauth2_access_token'];

$accessToken = json_decode($accessToken, true);

if (empty($accessToken)) {
    header('Location: ./oauth2.php');
    die;
}


$oauth2 = new OAuth2(CLIENT_ID, CLIENT_SELECT);
$oauth2->setRedirectUri(URI_BASE . 'callback.php');
$oauth2->setAccessToken($accessToken);

if (!empty($_REQUEST['path'])) {

    $path = $_REQUEST['path'];
    $params = array();
    if (!empty($_REQUEST['params'])) {
        parse_str($_REQUEST['params'], $params);
    } else {
        $params = array();
    }
    $response = $oauth2->api('GET', $path, $params)->response()->getJson();
} else {
    $response = array();
}


?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta charset="utf-8" />
</head>
<body>
    <pre><?=print_r($response)?></pre>

    <form>
        Path: <input type="text" name="path" value="/user/get_user_info" />
        Params: <input type="text" name="params" value="" style="width: 50em;">
        <input type="submit" value="提交">
    </form>
</body>
</html>
