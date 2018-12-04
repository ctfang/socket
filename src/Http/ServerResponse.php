<?php
/**
 * Created by PhpStorm.
 * User: 明月有色
 * Date: 2018/11/12
 * Time: 21:07
 */

namespace Utopia\Socket\Http;

use RingCentral\Psr7\Response;
use React\Http\Io\HttpBodyStream;
use React\Stream\ReadableStreamInterface;

/**
 * Class ServerResponse
 * @package Utopia\Socket\Http
 */
class ServerResponse extends Response
{
    public function __construct(
        $status = 200,
        array $headers = array(),
        $body = null,
        $version = '1.1',
        $reason = null
    ) {
        if ($body instanceof ReadableStreamInterface) {
            $body = new HttpBodyStream($body, null);
        }

        parent::__construct(
            $status,
            $headers,
            $body,
            $version,
            $reason
        );
    }
}