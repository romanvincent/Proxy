<?php
//HTTP代理(Socks C端)
header("Content-Type: application/xin-binary");
#error_reporting(E_ERROR);

//$_SERVER['REQUEST_SCHEME'] != "https" && die;
//$_SERVER['REQUEST_METHOD'] != "POST" && die;

$X_PWD = "c!&54s@d5f#@%*-";                                   //验证码

$m_type = $_SERVER['HTTP_XIN_TYPE'];                            
$m_pwd  = $_SERVER['HTTP_XIN_PWD'];                           //验证码
$m_id  =  $_SERVER['HTTP_XIN_ID'];                            //会话ID

//验证
$m_array = array($m_type, $m_pwd, $m_id);
if (in_array("",$m_array)) {header("HTTP/1.1 404 Not Foud");die;}          //都不能为空
$m_pwd != $X_PWD && die("mima");                //密码认证
$sockfile = 'x_'. $m_id .'.sock';       //sock文件名，包含了会话id

if (!file_exists($sockfile)) {          //确定会话已建立
    header("HTTP/1.1 602 Sockfile Not Foud");die;
}

//本机客户端来的数据
$c_data = file_get_contents("php://input"); 

if ($m_type==="xin-tcp") {
    $log = '';
    $fp = stream_socket_client("unix://". $sockfile, $errno, $errstr, 3);
    if (!$fp){
        die("connect to server fail: $errno - $errstr");
    }
    stream_set_timeout($fp, 0, 100000);         //看来需要从本地端获取数据了
    fwrite($fp, $c_data);
    $i = 0;
    while(!feof($fp)) {
        $i++;
        $r = fread($fp, 4096);
        print $r;
        $log .= $i . " - f_c:" . $r . "\n";
        if (strlen($r)==0) break;
    }

    fclose($fp);

} elseif ($m_type==="xin-udp") {
    # code...
}

//file_put_contents("clog.txt", $log);
