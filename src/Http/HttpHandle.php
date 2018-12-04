<?php
/**
 * Created by PhpStorm.
 * User: 明月有色
 * Date: 2018/11/16
 * Time: 17:37
 */

namespace Utopia\Socket\Http;

use Utopia\Socket\Connect\ConnectHandle;
use Utopia\Socket\Connect\SocketConnect;

class HttpHandle extends ConnectHandle
{
    /**
     * @var SocketConnect
     */
    protected $connect;

    /**
     * @param SocketConnect $connect
     * @param ServerRequest $serverRequest
     */
    public function handle(SocketConnect $connect, $serverRequest)
    {
        $this->connect  = $connect;
        $cot            = $serverRequest->getRequestTarget();
        $serverResponse = new ServerResponse(
            200,
            array(
                'Content-Type' => 'text/plain',
            ),
            "Hello World!\n".$cot
        );

        $this->writeResponse($serverRequest, $serverResponse);
    }

    /**
     * @param ServerRequest $request
     * @param ServerResponse $response
     * @return mixed
     */
    public function writeResponse(ServerRequest $request, ServerResponse $response)
    {
        $body     = $response->getBody();
        $response = $response->withProtocolVersion($request->getProtocolVersion());

        // assign default "X-Powered-By" header as first for history reasons
        if (!$response->hasHeader('X-Powered-By')) {
            $response = $response->withHeader('X-Powered-By', 'React/alpha');
        }

        if ($response->hasHeader('X-Powered-By') && $response->getHeaderLine('X-Powered-By') === '') {
            $response = $response->withoutHeader('X-Powered-By');
        }

        $response = $response->withoutHeader('Transfer-Encoding');

        // assign date header if no 'date' is given, use the current time where this code is running
        if (!$response->hasHeader('Date')) {
            // IMF-fixdate  = day-name "," SP date1 SP time-of-day SP GMT
            $response = $response->withHeader('Date', gmdate('D, d M Y H:i:s').' GMT');
        }

        if ($response->hasHeader('Date') && $response->getHeaderLine('Date') === '') {
            $response = $response->withoutHeader('Date');
        }

        $response = $response->withHeader('Content-Length', (string)$body->getSize());

        // HTTP/1.1 assumes persistent connection support by default
        // we do not support persistent connections, so let the client know
        if ($request->getProtocolVersion() === '1.1') {
            $response = $response->withHeader('Connection', 'close');
        }
        // 2xx response to CONNECT and 1xx and 204 MUST NOT include Content-Length or Transfer-Encoding header
        $code = $response->getStatusCode();
        if (($request->getMethod(
                ) === 'CONNECT' && $code >= 200 && $code < 300) || ($code >= 100 && $code < 200) || $code === 204) {
            $response = $response->withoutHeader('Content-Length')->withoutHeader('Transfer-Encoding');
        }

        // 101 (Switching Protocols) response uses Connection: upgrade header
        // persistent connections are currently not supported, so do not use
        // this for any other replies in order to preserve "Connection: close"
        if ($code === 101) {
            $response = $response->withHeader('Connection', 'upgrade');
        }


        // build HTTP response header by appending status line and header fields
        $headers = "HTTP/".$response->getProtocolVersion()." ".$response->getStatusCode(
            )." ".$response->getReasonPhrase()."\r\n";
        foreach ($response->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                $headers .= $name.": ".$value."\r\n";
            }
        }

        // response to HEAD and 1xx, 204 and 304 responses MUST NOT include a body
        // exclude status 101 (Switching Protocols) here for Upgrade request handling above
        if ($request->getMethod(
            ) === 'HEAD' || $code === 100 || ($code > 101 && $code < 200) || $code === 204 || $code === 304) {
            $body = '';
        }

        $this->connect->write($headers."\r\n".$body);
    }
}