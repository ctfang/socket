<?php
/**
 * Created by PhpStorm.
 * User: 明月有色
 * Date: 2018/11/10
 * Time: 14:34
 */

namespace Utopia\Socket\Connect;

use RingCentral\Psr7 as g7;
use Utopia\Socket\Http\ServerRequest;

class HttpConnect extends SocketConnect
{
    protected $response;

    protected $maxLength = 65535;

    /** @var int 已经接受的长度 */
    public $bytesRead = 0;
    /** @var string 已经接受的数据 */
    public $stringBuffer = '';
    /** @var int 报文定义的长度 */
    public $contentLength = 0;

    /**
     * 接受完整数据触发
     *
     * @param $buffer
     */
    public function onSocketMessage($buffer)
    {
        list($headers,) = explode("\r\n\r\n", $buffer, 2);

        // additional, stricter safe-guard for request line
        // because request parser doesn't properly cope with invalid ones
        if (!preg_match('#^[^ ]+ [^ ]+ HTTP/\d\.\d#m', $headers)) {
            throw new \InvalidArgumentException('Unable to parse invalid request-line');
        }

        // parser does not support asterisk-form and authority-form
        // remember original target and temporarily replace and re-apply below
        $originalTarget = null;
        if (strncmp($headers, 'OPTIONS * ', 10) === 0) {
            $originalTarget = '*';
            $headers = 'OPTIONS / ' . substr($headers, 10);
        } elseif (strncmp($headers, 'CONNECT ', 8) === 0) {
            $parts = explode(' ', $headers, 3);
            $uri = parse_url('tcp://' . $parts[1]);

            // check this is a valid authority-form request-target (host:port)
            if (isset($uri['scheme'], $uri['host'], $uri['port']) && count($uri) === 3) {
                $originalTarget = $parts[1];
                $parts[1] = 'http://' . $parts[1] . '/';
                $headers = implode(' ', $parts);
            } else {
                throw new \InvalidArgumentException('CONNECT method MUST use authority-form request target');
            }
        }

        // parse request headers into obj implementing RequestInterface
        $request = g7\parse_request($headers);

        // create new obj implementing ServerRequestInterface by preserving all
        // previous properties and restoring original request-target
        $serverParams = array(
            'REQUEST_TIME' => time(),
            'REQUEST_TIME_FLOAT' => microtime(true)
        );

        $this->remoteSocketUri = @stream_socket_get_name($this->socket, true);
        if ($this->remoteSocketUri !== null) {
            $remoteAddress = parse_url($this->remoteSocketUri);
            $serverParams['REMOTE_ADDR'] = $remoteAddress['host'];
            $serverParams['REMOTE_PORT'] = $remoteAddress['port'];
        }

        if ($this->localSocketUri !== null) {
            $localAddress = parse_url($this->localSocketUri);
            if (isset($localAddress['host'], $localAddress['port'])) {
                $serverParams['SERVER_ADDR'] = $localAddress['host'];
                $serverParams['SERVER_PORT'] = $localAddress['port'];
            }
            if (isset($localAddress['scheme']) && $localAddress['scheme'] === 'https') {
                $serverParams['HTTPS'] = 'on';
            }
        }

        $target = $request->getRequestTarget();
        $request = new ServerRequest(
            $request->getMethod(),
            $request->getUri(),
            $request->getHeaders(),
            $request->getBody(),
            $request->getProtocolVersion(),
            $serverParams
        );
        $request = $request->withRequestTarget($target);

        // Add query params
        $queryString = $request->getUri()->getQuery();
        if ($queryString !== '') {
            $queryParams = array();
            parse_str($queryString, $queryParams);
            $request = $request->withQueryParams($queryParams);
        }

        $cookies = ServerRequest::parseCookie($request->getHeaderLine('Cookie'));
        if ($cookies !== false) {
            $request = $request->withCookieParams($cookies);
        }

        // re-apply actual request target from above
        if ($originalTarget !== null) {
            $request = $request->withUri(
                $request->getUri()->withPath(''),
                true
            )->withRequestTarget($originalTarget);
        }

        // only support HTTP/1.1 and HTTP/1.0 requests
        $protocolVersion = $request->getProtocolVersion();
        if ($protocolVersion !== '1.1' && $protocolVersion !== '1.0') {
            throw new \InvalidArgumentException('Received request with invalid protocol version', 505);
        }

        // ensure absolute-form request-target contains a valid URI
        $requestTarget = $request->getRequestTarget();
        if (strpos($requestTarget, '://') !== false && substr($requestTarget, 0, 1) !== '/') {
            $parts = parse_url($requestTarget);

            // make sure value contains valid host component (IP or hostname), but no fragment
            if (!isset($parts['scheme'], $parts['host']) || $parts['scheme'] !== 'http' || isset($parts['fragment'])) {
                throw new \InvalidArgumentException('Invalid absolute-form request-target');
            }
        }

        // Optional Host header value MUST be valid (host and optional port)
        if ($request->hasHeader('Host')) {
            $parts = parse_url('http://' . $request->getHeaderLine('Host'));

            // make sure value contains valid host component (IP or hostname)
            if (!$parts || !isset($parts['scheme'], $parts['host'])) {
                $parts = false;
            }

            // make sure value does not contain any other URI component
            unset($parts['scheme'], $parts['host'], $parts['port']);
            if ($parts === false || $parts) {
                throw new \InvalidArgumentException('Invalid Host header value');
            }
        }

        // set URI components from socket address if not already filled via Host header
        if ($request->getUri()->getHost() === '') {
            $parts = parse_url($this->localSocketUri);
            if (!isset($parts['host'], $parts['port'])) {
                $parts = array('host' => '127.0.0.1', 'port' => 80);
            }

            $request = $request->withUri(
                $request->getUri()->withScheme('http')->withHost($parts['host'])->withPort($parts['port']),
                true
            );
        }

        // Do not assume this is HTTPS when this happens to be port 443
        // detecting HTTPS is left up to the socket layer (TLS detection)
        if ($request->getUri()->getScheme() === 'https') {
            $request = $request->withUri(
                $request->getUri()->withScheme('http')->withPort(443),
                true
            );
        }

        // Update request URI to "https" scheme if the connection is encrypted
        $parts = parse_url($this->localSocketUri);
        if (isset($parts['scheme']) && $parts['scheme'] === 'https') {
            // The request URI may omit default ports here, so try to parse port
            // from Host header field (if possible)
            $port = $request->getUri()->getPort();
            if ($port === null) {
                $port = parse_url('tcp://' . $request->getHeaderLine('Host'), PHP_URL_PORT); // @codeCoverageIgnore
            }

            $request = $request->withUri(
                $request->getUri()->withScheme('https')->withPort($port),
                true
            );
        }

        // always sanitize Host header because it contains critical routing information
        $request = $request->withUri($request->getUri()->withUserInfo('u')->withUserInfo(''));

        $this->handle->handle($this,$request);
    }


    /**
     * @param $response
     * @return bool true 全部发送成功
     */
    public function write(string $response):bool
    {
        $len = fwrite($this->socket, $response);
        if ($len === strlen($response)) {
            $this->destroy();
            return true;
        }

        if ($len > 0) {
            $this->response = substr($response, $len);
        } else {
            if (!is_resource($this->socket) || feof($this->socket)) {
                $this->destroy();
                return false;
            }
            $this->response = substr($response, $len);
        }
        $this->schedule->addForWrite($this->id,$this);
        return false;
    }

    public function destroy()
    {
        fclose($this->socket);
    }

    /**
     * 有信息，可能多次触发
     *
     * 如果有输出：
     * $schedule->addForWrite($this->id,$this);
     *
     * @return bool {false:全部数据接完，true:还有数据没有接完}
     */
    public function onEncodeInput(): bool
    {
        $buffer = fread($this->socket, 1024);

        if ($buffer === '' || $buffer === false) {
            if (feof($this->socket) || !is_resource($this->socket) || $buffer === false) {
                $this->destroy();
                return false;
            }
        }

        $this->bytesRead    += strlen($buffer);
        $this->stringBuffer .= $buffer;

        if ($this->contentLength == 0) {
            $this->contentLength = $this->getContentLength($this->stringBuffer);
        }

        if ($this->contentLength == $this->bytesRead) {
            $this->onSocketMessage($this->stringBuffer);
            unset($this->stringBuffer);
            return false;
        }elseif ($this->maxLength<=$this->bytesRead){
            $this->onSocketMessage($this->stringBuffer);
            unset($this->stringBuffer);
            return false;
        }

        $this->schedule->addForRead($this->id,$this);
    }


    /**
     * 获取报文声明的大小
     *
     * @param $buffer
     * @return int
     */
    private function getContentLength($buffer): int
    {
        if (!strpos($buffer, "\r\n\r\n")) {
            return 0;
        }

        list($header,) = explode("\r\n\r\n", $buffer, 2);
        $method = substr($header, 0, strpos($header, ' '));

        if ($method === 'GET' || $method === 'OPTIONS' || $method === 'HEAD') {
            return strlen($header) + 4;
        }
        $match = array();
        if (preg_match("/\r\nContent-Length: ?(\d+)/i", $header, $match)) {
            $content_length = isset($match[1]) ? $match[1] : 0;

            return $content_length + strlen($header) + 4;
        }

        return $method === 'DELETE' ? strlen($header) + 4 : 0;
    }

    /**
     * 可写触发，可能多次触发
     *
     * 在onEncodeInput()需要：
     * $schedule->addForWrite($this->id,$this);
     *
     * 输出完后，如果需要关闭连接：
     * fwrite($this->socket, $this->response);
     * fclose($this->socket);
     *
     * @return  bool
     */
    public function onSocketWrite(): bool
    {
        $this->write($this->response);
        return false;
    }
}