<?php
/**
 * Created by PhpStorm.
 * User: 明月有色
 * Date: 2018/11/15
 * Time: 11:18
 */

use Utopia\Socket\Connect\HttpConnect;
use Utopia\Socket\Http\HttpHandle;

require '../vendor/autoload.php';


$scheduler = new \Utopia\Socket\Scheduler();

$connect = new HttpConnect();
$connect->setHandle(new HttpHandle());
$scheduler->monitor('tcp://0.0.0.0:8080', $connect);

/**
 * 模拟socket监听
 */
while (1){
    $scheduler->run(0);
}
