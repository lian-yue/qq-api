<?php
namespace LianYue\QQApi;

class Request
{

    protected $uri;

    protected $method = 'GET';

    protected $headers = [];

    protected $body;

    protected $options = array();

    protected $responseFilter;

    public function __construct($method, $uri, array $headers = array(), $body = null, array $options = array())
    {
        if (is_scalar($uri)) {
            $uri = (string) $uri;
        } elseif (is_object($uri) && method_exists($uri, '__toString')) {
            $uri = $uri->__toString();
        } else {
            throw new InvalidArgumentException('Uri must be a string');
        }
        $this->method = strtoupper($method?: 'GET');
        $this->uri = $uri;
        $this->headers = $headers;
        $this->body = $body;
        $this->options = $options;
    }

    public function getOptions()
    {
        return $this->options;
    }

    public function setOptions(array $options = array())
    {
        $this->options = $options;
        return $this;
    }


    public function getOption($name, $default = null)
    {
        return isset($this->options[$name]) ? $this->options[$name] : $default;
    }

    public function setOption($name, $default)
    {
        $this->options[$name] = $default;
        return $this;
    }

    public function getResponseCallback()
    {
        return $this->responseCallback;
    }

    public function setResponseCallback(callable $callback)
    {
        $this->responseCallback = $callback;
        return $this;
    }


    public function response() {
        $options = [
            CURLOPT_CAINFO => __DIR__ . DIRECTORY_SEPARATOR . 'cacert.pem',
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_HEADER => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_URL => $this->uri,
            CURLOPT_ENCODING => 'gzip,deflate',

            // 限制协议
			CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
			CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        ];

        foreach ($this->options as $name => $value) {
            if (!$name) {
                continue;
            }
            if (!is_int($name)) {
                if (strtr($name, 0, 5) !== 'CURLOPT_') {
                    $name = 'CURLOPT_' . $name;
                }
                $name = strtoupper($name);
                if (!defined($name)) {
                    throw  new InvalidArgumentException(sprintf('%s constant does not exist', $name));
                }
                $name = constant($name);
            }
            $options[$name] = $value;
        }


        $headers = [];
        foreach ($this->headers as $name => $header) {
            if (is_array($header)) {
                foreach ($header as  $value) {
                    if ($value !== null) {
                        $headers[] = $name .': ' . rtrim($value, "; \r\n\t");
                    }
                }
            } elseif ($header !== null) {
                $headers[] = $name .': ' . rtrim($header, "; \r\n\t");
            }
        }
        if ($headers) {
            if (empty($options[CURLOPT_HTTPHEADER])) {
                $options[CURLOPT_HTTPHEADER] = $headers;
            } else {
                $options[CURLOPT_HTTPHEADER] = array_merge($options[CURLOPT_HTTPHEADER], $headers);
            }
        }


        $options = array_filter($options, function($value) {
            return $value !== null;
        });

        $body = $this->body;


        switch ($this->method) {
            case 'HEAD':
                if (!isset($options[CURLOPT_NOBODY])) {
                    $options[CURLOPT_NOBODY] = true;
                }
            case 'GET':
                break;
            case 'POST':
                $options[CURLOPT_POST] = true;
                if (!isset($options[CURLOPT_POSTFIELDS])) {
                    if (is_scalar($body)) {
                        $options[CURLOPT_POSTFIELDS] = (string) $body;
                    } else {
                        $contentType = '';
                        if (!empty($options[CURLOPT_HTTPHEADER])) {
                            foreach($options[CURLOPT_HTTPHEADER] as $header) {
                                if (strncasecmp('Content-Type', $header, 12) === 0) {
                                    $contentType = substr($header, 13);
                                    break;
                                }
                            }
                        }
                        if (stripos($contentType, 'multipart/form-data') !== false) {
                            $data = [];
                            foreach ($body as $key => $value) {
                                $data[$key] = $value;
                            }
                            $options[CURLOPT_POSTFIELDS] = $data;
                        } elseif (stripos($contentType, '/json') !== false) {
                            $options[CURLOPT_POSTFIELDS] = json_encode($body);
                        } else {
                            if (is_object($body) && method_exists($body, '__toString')) {
                                $body = $body->__toString();
                            } elseif ($body) {
                                $data = [];
                                foreach ($body as $key => $value) {
                                    $data[$key] = $value;
                                }
                                $options[CURLOPT_POSTFIELDS] = http_build_query($data, null, '&');
                            }
                        }
                    }
                }
                break;
            case 'PUT':
                if (!isset($options[CURLOPT_CUSTOMREQUEST])) {
                    $options[CURLOPT_CUSTOMREQUEST] = 'PUT';
                }
                if (!isset($options[CURLOPT_INFILE]) && is_resource($this->body)) {
                    $options[CURLOPT_PUT] = true;
                    $options[CURLOPT_INFILE] = $this->body;
                }
                break;
            case 'DELETE':
                if (!isset($options[CURLOPT_CUSTOMREQUEST])) {
                    $options[CURLOPT_CUSTOMREQUEST] = 'DELETE';
                }
        }


        $options[CURLOPT_HEADER] = true;

        $ch = curl_init();
        curl_setopt_array($ch, $options);
        $response = curl_exec($ch);
        if (curl_errno($ch) > 0) {
            throw new RequestException(curl_error($ch), curl_errno($ch));
        }
        $parts = explode("\r\n\r\n", $response);

        $body = array_pop($parts);
        $header = array_pop($parts);

        $headers = [];
        foreach (explode("\r\n", $header) as $headerLine) {
            if (strpos($headerLine, ':') !== false) {
                list($key, $value) = explode(':', $headerLine);
                $key = strtolower(trim($key));
                $value = trim($value);
                if (empty($headers[$key])) {
                    $headers[$key] = [$value];
                } else {
                    $headers[$key][] = $value;
                }
            }
        }


        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $response = new Response($statusCode, $headers, $body);

        $callback = $this->getResponseCallback();
        if ($callback) {
            $response = call_user_func($callback, $response);
        }
        return $response;
    }
}
