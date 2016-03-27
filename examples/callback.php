<?php
namespace LianYue\QQApi;
require __DIR__ . '/config.php';

$state = empty($_COOKIE['qq_api_state']) ? '' : $_COOKIE['qq_api_state'];

$oauth2 = new OAuth2(CLIENT_ID, CLIENT_SELECT);
$oauth2->setRedirectUri(URI_BASE . 'callback.php');
$oauth2->setState($state);
$accessToken = $oauth2->getAccessToken();
$openid = $oauth2->getOpenid();
setcookie('qq_oauth2_access_token', json_encode($accessToken), time() + 86400, '/');
setcookie('qq_oauth2_openid', json_encode($openid), time() + 86400, '/');
?>

<pre>
访问令牌
<?=print_r($accessToken)?>

// Openid
<?=print_r($openid)?>

个人信息
<?=print_r($oauth2->getUserInfo()->getJson())?>
</pre>

<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta charset="utf-8" />
</head>
<body>
<a href="./oauth2_test.php">测试 Api</a>
</body>
</html>
