<?php
/**
 * Created by PhpStorm.
 * User: 明月有色
 * Date: 2018/11/10
 * Time: 14:33
 */

namespace Utopia\Socket\Connect;


use Utopia\Socket\Scheduler;

abstract class SocketConnect
{
    public $id;
    protected $socket;
    public $localSocketUri;
    public $remoteSocketUri;

    /** @var Scheduler */
    protected $schedule;

    /** @var ConnectHandle */
    protected $handle;

    /**
     * 设置连接
     * @param $socket
     */
    public function setConnectSocket($socket)
    {
        $this->socket = $socket;
        $this->id     = (int)$socket;
    }

    /**
     * 获取tcp回话
     * @return mixed
     */
    public function getSocket()
    {
        return $this->socket;
    }

    /**
     * 设置调度
     * @param Scheduler $schedule
     */
    public function setSchedule(Scheduler $schedule)
    {
        $this->schedule = $schedule;
    }

    /**
     * 处理所有数据包括粘包，之后调用处理
     * @param $handle
     */
    public function setHandle($handle)
    {
        $this->handle = $handle;
    }

    /**
     * 有信息，可能多次触发
     *
     * 如果有输出：
     * $schedule->addForWrite($this->id,$this);
     *
     * @return bool
     */
    abstract public function onEncodeInput(): bool;

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
    abstract public function onSocketWrite(): bool;

    /**
     * 发送到客户端
     *
     * @param string $str
     * @return mixed
     */
    abstract public function write(string $str);
}