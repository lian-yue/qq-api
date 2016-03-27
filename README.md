

# Composer 安装

    composer require lianyue/qq-api





# QQ OAuth2 应用

### 如何申请应用

应用列表
    http://connect.qq.com/manage/index

创建应用
    **在应用列表右上角**

回调地址
    请在 你应用列表 点击 **查看详情** 然后 **信息编辑** 最后 设置回调地址  
    **注意回调地址可能会审核所以设置了不一定立刻生效**

Client Id
    就是你的  **APP ID**

Client Secret
    就是你的  **APP KEY**



### OAuth2 api 列表
http://wiki.open.qq.com/wiki/website/API%E5%88%97%E8%A1%A8


### Oauth2使用方法

    namespace LianYue\QQApi;

    $oauth2 = new OAuth2(CLIENT_ID, CLIENT_SELECT);
    $oauth2->setRedirectUri(CALLBACK_URI);
    try {
        // 设置 state
        if (!empty($_COOKIE['qq_api_state'])) {
            $oauth2->setState($_COOKIE['qq_api_state']);
        }

        // 取得令牌
        $accessToken = $oauth2->getAccessToken();

        // 访问令牌
        print_r($accessToken);

        // Openid
        print_r($oauth2->getOpenid());


        // 用户信息
        print_r($oauth2->getUserInfo()->getJson(true));

        // 其他api调用
        print_r($this->api('GET', '/user/get_user_info')->response()->getJson(false));
    } catch (QQApiException $e) {

        // 获取重定向链接
        $uri = $oauth2->getAuthorizeUri();

        // 储存 state
        setcookie('qq_api_state', $oauth2->getState(), time() + 86400, '/');

        // 重定向
        header('Location: ' . $uri);
    }


### 注意
QQ 的OAuth2 和规范的要多一个步骤 要先获取到 access_token 然后 还要用 access_token 获取到 openid 才能调用 api
