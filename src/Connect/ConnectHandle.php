<?php
/**
 * Created by PhpStorm.
 * User: 明月有色
 * Date: 2018/11/16
 * Time: 17:38
 */

namespace Utopia\Socket\Connect;


abstract class ConnectHandle
{
    /**
     * @param SocketConnect $connect
     * @param $data
     * @return mixed
     */
    abstract public function handle(SocketConnect $connect,$data) ;
}