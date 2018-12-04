<?php
/**
 * Created by PhpStorm.
 * User: 明月有色
 * Date: 2018/11/10
 * Time: 14:24
 */

namespace Utopia\Socket;


use Utopia\Socket\Connect\SocketConnect;

class Scheduler
{
    /** @var array 原始连接 */
    protected $socketMap = [];
    /** @var array 原始连接 绑定 处理类 */
    protected $socketBindConnectMap = [];
    /** @var array id=>ConnectInterface 已经连接的客户端，待读 */
    protected $socketWaitingForRead = [];
    /** @var array id=>ConnectInterface 已经连接的客户端，待写入 */
    protected $socketWaitingForWrite = [];

    /**
     * 监听地址
     * @param string $local_socket
     * @param SocketConnect $connect
     */
    public function monitor($local_socket = 'tcp://0.0.0.0:8080', SocketConnect $connect)
    {
        $socket = stream_socket_server($local_socket, $errNo, $errStr);
        stream_set_blocking($socket, 0);
        $socketId                = (int)$socket;
        $connect->localSocketUri = $local_socket;

        /** resource */
        $this->socketMap[$socketId]            = $socket;
        $this->socketBindConnectMap[$socketId] = $connect;
    }


    /**
     * 待读列表
     * @param int $id
     * @param SocketConnect $socket
     */
    public function addForRead(int $id, SocketConnect $socket)
    {
        $this->socketWaitingForRead[$id] = $socket;
    }

    /**
     * 待写列表
     * @param int $id
     * @param SocketConnect $socket
     */
    public function addForWrite(int $id, SocketConnect $socket)
    {
        $this->socketWaitingForWrite[$id] = $socket;
    }

    public function run($timeout = null)
    {
        $rSocks = [];
        foreach ($this->socketMap as $socket) {
            $rSocks[] = $socket;
        }

        /** @var SocketConnect $socketConnect */
        foreach ($this->socketWaitingForRead as $socketConnect) {
            $rSocks[] = $socketConnect->getSocket();
        }

        $wSocks = [];
        foreach ($this->socketWaitingForWrite as $socketConnect) {
            $wSocks[$socketConnect->id] = $socketConnect->getSocket();
        }

        $eSocks = [];
        if (!stream_select($rSocks, $wSocks, $eSocks, $timeout, 100000000)) {
            return;
        }

        foreach ($rSocks as $socket) {
            $socketId = (int)$socket;
            if (isset($this->socketMap[$socketId])) {
                // 新连接
                $stream    = stream_socket_accept($socket);
                $connectId = (int)$stream;
                /** @var SocketConnect $socketConnect */
                $socketConnect = clone $this->socketBindConnectMap[$socketId];
                $socketConnect->setConnectSocket($stream);
                $socketConnect->setSchedule($this);
                $this->socketWaitingForRead[$connectId] = $socketConnect;
            } else {
                /** 已经连接的套接字有事件 */
                /** @var SocketConnect $socketConnect */
                $socketConnect = $this->socketWaitingForRead[$socketId];
                $socketConnect->onEncodeInput();
                unset($this->socketWaitingForRead[$socketId]);
            }
        }

        foreach ($wSocks as $socket) {
            $socketId = (int)$socket;
            /** @var SocketConnect $socketConnect */
            $socketConnect = $this->socketWaitingForWrite[$socketId];
            $socketConnect->onSocketWrite();
            unset($this->socketWaitingForWrite[$socketId]);
        }
    }
}