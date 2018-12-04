<?php
/**
 * Created by PhpStorm.
 * User: 明月有色
 * Date: 2018/11/12
 * Time: 20:53
 */

namespace Utopia\Socket\Http;


use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use RingCentral\Psr7\Request;

/**
 * 这个类使用了 React\Http\Io\ServerRequest 的代码
 * @package Utopia\Socket\Http
 */
class ServerRequest extends Request implements ServerRequestInterface
{
    private $attributes = array();

    private $serverParams;
    private $fileParams = array();
    private $cookies = array();
    private $queryParams = array();
    private $parsedBody;

    /**
     * @param null|string $method HTTP method for the request.
     * @param null|string|UriInterface $uri URI for the request.
     * @param array $headers Headers for the message.
     * @param string|resource|StreamInterface $body Message body.
     * @param string $protocolVersion HTTP protocol version.
     * @param array $serverParams server-side parameters
     *
     * @throws \InvalidArgumentException for an invalid URI
     */
    public function __construct(
        $method,
        $uri,
        array $headers = array(),
        $body = null,
        $protocolVersion = '1.1',
        $serverParams = array()
    ) {
        $this->serverParams = $serverParams;
        parent::__construct($method, $uri, $headers, $body, $protocolVersion);
    }

    public function getServerParams()
    {
        return $this->serverParams;
    }

    public function getCookieParams()
    {
        return $this->cookies;
    }

    public function withCookieParams(array $cookies)
    {
        $new = clone $this;
        $new->cookies = $cookies;
        return $new;
    }

    public function getQueryParams()
    {
        return $this->queryParams;
    }

    public function withQueryParams(array $query)
    {
        $new = clone $this;
        $new->queryParams = $query;
        return $new;
    }

    public function getUploadedFiles()
    {
        return $this->fileParams;
    }

    public function withUploadedFiles(array $uploadedFiles)
    {
        $new = clone $this;
        $new->fileParams = $uploadedFiles;
        return $new;
    }

    public function getParsedBody()
    {
        return $this->parsedBody;
    }

    public function withParsedBody($data)
    {
        $new = clone $this;
        $new->parsedBody = $data;
        return $new;
    }

    public function getAttributes()
    {
        return $this->attributes;
    }

    public function getAttribute($name, $default = null)
    {
        if (!array_key_exists($name, $this->attributes)) {
            return $default;
        }
        return $this->attributes[$name];
    }

    public function withAttribute($name, $value)
    {
        $new = clone $this;
        $new->attributes[$name] = $value;
        return $new;
    }

    public function withoutAttribute($name)
    {
        $new = clone $this;
        unset($new->attributes[$name]);
        return $new;
    }

    /**
     * @internal
     * @param string $cookie
     * @return boolean|mixed[]
     */
    public static function parseCookie($cookie)
    {
        // PSR-7 `getHeaderLine('Cookies')` will return multiple
        // cookie header comma-seperated. Multiple cookie headers
        // are not allowed according to https://tools.ietf.org/html/rfc6265#section-5.4
        if (strpos($cookie, ',') !== false) {
            return false;
        }

        $cookieArray = explode(';', $cookie);
        $result = array();

        foreach ($cookieArray as $pair) {
            $pair = trim($pair);
            $nameValuePair = explode('=', $pair, 2);

            if (count($nameValuePair) === 2) {
                $key = urldecode($nameValuePair[0]);
                $value = urldecode($nameValuePair[1]);
                $result[$key] = $value;
            }
        }

        return $result;
    }
}