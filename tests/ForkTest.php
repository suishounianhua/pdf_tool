<?php


use Phalcon\Http\Request;
use Phalcon\Mvc\Dispatcher;
use \Phalcon\Mvc\Controller;
use Phalcon\Mvc\Model\Resultset;
use Firebase\JWT\JWT;
use Phalcon\Di;

use PHPUnit\Framework\TestCase;

class ForkTest extends \PHPUnit\Framework\TestCase
{

    /*public function testfork()
    {
        $arr = array();
        for($x=0;$x<1000;$x++) {
            $arr[$x] = 5;
        }

        $pids = array();

        for($i =0; $i<10;) {
            $pids[$i] = $pid = pcntl_fork();
            echo "====in out loop $i====";
//父进程和子进程都会执行下面代码
            if ($pids[$i] == -1) {
                //错误处理：创建子进程失败时返回-1.
                exit;
            } else if ($pid == 0 ) {

                //子进程得到的$pid为0, 所以这里是子进程执行的逻辑。
                $i++;
                echo "start the $i fork and the pid is $pid";
                $start = ($i-1)*100; $end = $i*100;
                for($j =$start; $j < $end; $j++){
                    echo "in $i fork and set $j";
                    $arr[$j] = 0; sleep(1);
                }
                echo "set $start to $end success.";
                exit;
            }
            else{
                //父进程会得到子进程号，所以这里是父进程执行的逻辑
                continue;
            }

        }
        foreach ($pids as $i => $pid) {
            if($pid) {
                pcntl_waitpid($pid, $status);
                //等待子进程中断，防止子进程成为僵尸进程。
            }
        }


    }*/

}