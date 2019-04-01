<?php
//HTTP代理(Socks S端)
header("Content-Type: application/xin-binary");
//error_reporting(E_ERROR);

//$_SERVER['REQUEST_SCHEME'] != "https" && die;
//$_SERVER['REQUEST_METHOD'] != "POST" && die;

$X_PWD = "c!&54s@d5f#@%*-";                                   //验证码

$m_type = $_SERVER['HTTP_XIN_TYPE'];            //见文件版解释
$m_host = $_SERVER['HTTP_XIN_HOST'];
$m_port = $_SERVER['HTTP_XIN_PORT'];
$m_pwd  = $_SERVER['HTTP_XIN_PWD'];
$m_id  =  $_SERVER['HTTP_XIN_ID'];

//验证
$m_array = array($m_type, $m_host, $m_port, $m_pwd, $m_id);
if (in_array("",$m_array)) {header("HTTP/1.1 404 Not Foud");die;}               //都不能为空

$m_pwd != $X_PWD && die;        //密码验证
if ($m_type !="xin-con") {      //类型验证
    die();
}

//重新创建sock文件
$sockfile = 'x_'. $m_id .'.sock';
if (file_exists($sockfile)){
    unlink($sockfile);
}

//Unix套接字服务创建
$server = stream_socket_server("unix://". $sockfile, $errno, $errstr);
if (!$server){
    die("Unix 文件创建失败: ". $errno ."-". $errstr);
}
$fp = fsockopen($m_host, $m_port, $errno, $errstr, 5 ); //与远端建立连接
if (!$fp) {
    fclose($server);
    unlink($sockfile);
    die();
}

$read = array(
    0 => $server,
    1 => $fp
);
$write = NULL;
$event = NULL;
$conn = NULL;

$log = '';

while(true) {
    $readd = $read;
    $mod_fd = stream_select($readd, $write, $event, 5);
    $log .= "\nmod_fd:" . $mod_fd . "\n";
    $log .= "count:" . count($read) . "\n";
    $log .= "count:" . count($readd) . "\n";
    
    //file_put_contents("tlog.txt", $log);
    if ($mod_fd == FALSE) {
        header("HTTP/1.1 601 S Time out");
        break;
    }
    
    foreach ($readd as $r) {
        if ($r == $server) {
            $conn = stream_socket_accept($server);
            stream_set_timeout($conn, 3);
            $log .= "有C端连接了\n";
            $read[2]=$conn;
        } else {
            $sock_data = fread($r, 4096); //从远端获取数据
            if (strlen($sock_data) == 0) {  //对方主动关闭了
                $key_to_del = array_search($r, $read, TRUE);
                fclose($r);
                unset($read[$key_to_del]);
                $log .= "关闭 - " . $key_to_del  . "\n";
            } else if ($sock_data == FALSE) {
                //err
                $log .= "异常 - " . tream_socket_get_name($r)  . "\n";
            } else {
                if ($r == $conn) {
                    fwrite($fp, $sock_data); //to 远端
                } else if ($r == $fp) {
                    fwrite($conn, $sock_data); //to 远端
                } else {
                    $log .= "!!!!\n";
                }
                $log .= "to ". $sock_data ."\n";
            }
            
        }
    }
}
fclose($conn);
fclose($server);
fclose($fp);
unlink($sockfile);

//file_put_contents("slog.txt", $log);

