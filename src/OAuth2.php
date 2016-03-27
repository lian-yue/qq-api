<?php
namespace LianYue\QQApi;


//  http://connect.qq.com/manage/index

class OAuth2
{

    protected $baseUri = 'https://graph.qq.com';

    protected $clientId;

    protected $clientSecret;

    protected $state;

    protected $accessToken;

    protected $openid;

    protected $redirectUri;

    protected $requestOptions = array();

    public function __construct($clientId, $clientSecret, array $accessToken = null, array $requestOptions = array())
    {
        $this->setClientId($clientId);
        $this->setClientSecret($clientSecret);
        $this->setAccessToken($accessToken);
        $this->setRequestOptions($requestOptions);
    }

    public function getClientId()
    {
        return $this->clientId;
    }

    public function setClientId($clientId)
    {
        $this->clientId = $clientId;
        return $this;
    }

    public function getClientSecret()
    {
        return $this->clientSecret;
    }

    public function setClientSecret($clientSecret)
    {
        $this->clientSecret = $clientSecret;
        return $this;
    }

    public function getRedirectUri()
    {
        return $this->redirectUri;
    }

    public function setRedirectUri($redirectUri)
    {
        $this->redirectUri = (string) $redirectUri;
        return $this;
    }

    public function getState()
    {
        if (!$this->state) {
            $this->state = md5(uniqid(mt_rand(), true));
        }
        return $this->state;
    }

    public function setState($state)
    {
        $this->state = (string) $state;
        return $this;
    }

    public function setRequestOptions(array $requestOptions = array())
    {
        $this->requestOptions =  $requestOptions;
        return $this;
    }


    public function getAccessToken(array $params = null)
    {
        if ($this->accessToken === null) {
            //  自动获取 access_token
            if ($params === null) {
                $params = $_GET;
            }
            if (empty($params['code'])) {
                throw new InvalidArgumentException('Code parameter is empty');
            }
            if (empty($params['state']) || $params['state'] !== $this->getState()) {
                throw new InvalidArgumentException('State parameter error (CSRF)');
            }
            $request = $this->request('GET', 'oauth2.0/token', array(
                'grant_type' => empty($params['grant_type']) ? 'authorization_code' : $params['grant_type'],
                'client_id' => $this->getClientId(),
                'client_secret' => $this->getClientSecret(),
                'code' => $params['code'],
                'redirect_uri' => empty($params['redirect_uri']) ? $this->getRedirectUri() : $params['redirect_uri'],
                'format' => 'json',
            ));

            $this->accessToken = $request->response()->getJson(true);
        }
        return $this->accessToken;
    }

    public function setAccessToken(array $accessToken = null)
    {
        $this->accessToken = $accessToken;
        return $this;
    }

    public function getOpenid($accessToken = null)
    {
        if ($this->openid === null) {
            if ($accessToken === null) {
                $accessToken = $this->getAccessToken();
            } elseif (!is_array($accessToken)) {
                $accessToken = ['accessToken' => $accessToken];
            }
            if (empty($accessToken['access_token'])) {
                throw new InvalidArgumentException('Not configuration access_token');
            }
            $this->openid = $this->request('GET', 'oauth2.0/me', ['access_token' => $accessToken['access_token']])->response()->getJson(true);
        }
        return $this->openid;
    }

    public function setOpenid(array $openid = null)
    {
        $this->openid = $openid;
        return $this;
    }

    public function getAuthorizeUri(array $params = array())
    {

        if (!empty($params['state'])) {
            $this->setState($params['state']);
        }

        if (!empty($params['redirect_uri'])) {
            $this->setRedirectUri($params['redirect_uri']);
        } else {
            $params['redirect_uri'] = $this->getRedirectUri();
            if (!$params['redirect_uri']) {
                throw new InvalidArgumentException('Not configuration redirect_uri');
            }
        }

        $params = array(
			'client_id' => $this->getClientId(),
			'state' => $this->getState(),
		) + $params + array(
            'response_type'	=> 'code',
        );

        if (!empty($params['scope']) && is_array($params['scope'])) {
            $params['scope'] = implode(',', $params['scope']);
        }
        return $this->getUri('oauth2.0/authorize', $params);
    }


    public function getUserInfo()
    {
        return $this->api('GET', 'user/get_user_info')->response();
    }


    public function getUri($path, array $params = array())
    {
        if (substr($path, 0, 7) === 'http://' || substr($path, 0, 8) === 'https://') {
            $uri = $path;
        } else {
            $uri = $this->baseUri .'/' . ltrim($path, '/');
        }
        if ($params) {
            $uri .= '?' . http_build_query($params, null, '&');
        }
        return $uri;
    }


    public function request($method, $path, array $params = array(), array $headers = array(), $body = null, array $options = array())
    {
        $request = new Request($method, $this->getUri($path, $params), $headers, $body, $options + $this->requestOptions + array(CURLOPT_USERAGENT => 'OAuth/2.0 (LianYue; http://lianyue.org, https://github.com/lian-yue/qq-api)'));
        return  $request->setResponseCallback(function(Response $response) use($path) {
            $body = $response->getBody();
            $body = trim($body, " \t\r\n;");
            if (strpos($body, 'callback(') === 0) {
                $body = trim(substr($body, 9, -1));
            }

            $pos = strpos($path, 'oauth2.0');
            if ($pos !== false && $pos <= 1 && $body && $body{0} !== '{' && $body{0} !== '[') {
                parse_str($body, $json);
                $body = json_encode($json);
            }

            $response->setBody($body);
            $json = $response->getJson();
            if (!empty($json->error_description)) {
                $error = $json->error_description;
            } elseif (!empty($json->error)) {
                if (is_int($json->error)) {
                    $error = sprintf('Error code %d', $json->error);
                } else {
                    $error = $json->error;
                }
            } elseif (!empty($json->msg) && !empty($json->ret)) {
                $error = $json->msg;
            } elseif ($response->getStatusCode() >= 400) {
                $error = sprintf('HTTP status code %d', $response->getStatusCode());
            }
            if (!empty($error)) {
                throw new ResponseException($error, empty($json->code) ? (empty($json->error) || !is_int($json->error) ? 0 : $json->error) : $json->code);
            }
            return $response;
        });
    }


    public function api($method, $path, array $params = array(), array $headers = array(), $body = null) {
        if (empty($params['access_token'])) {
            $accessToken = $this->getAccessToken();
            if (empty($accessToken['access_token'])) {
                throw new InvalidArgumentException('Not configuration access_token');
            }
            $params['access_token'] = $accessToken['access_token'];
        }

        if (empty($params['openid'])) {
            $openid = $this->getOpenid();
            if (empty($openid['openid'])) {
                throw new InvalidArgumentException('Not configuration openid');
            }
            $params['openid'] = $openid['openid'];
        }
        if (empty($params['oauth_consumer_key'])) {
            $params['oauth_consumer_key'] = $this->getClientId();
        }
        if (empty($params['format'])) {
            $params['format'] = 'json';
        }
        return $this->request($method, $path, $params, $headers, $body);
    }
}
