<?php
//HTTP代理(Socks C端)
header("Content-Type: application/xin-binary");
#error_reporting(E_ERROR);

//$_SERVER['REQUEST_SCHEME'] != "https" && die;
//$_SERVER['REQUEST_METHOD'] != "POST" && die;

$X_PWD = "c!&54s@d5f#@%*-";                                   //验证码

$m_type = $_SERVER['HTTP_XIN_TYPE'];        //见文件版解释
$m_pwd  = $_SERVER['HTTP_XIN_PWD'];
$m_id  =  $_SERVER['HTTP_XIN_ID'];

//验证
$m_array = array($m_type, $m_pwd, $m_id);
if (in_array("",$m_array)) {header("HTTP/1.1 404 Not Foud");die;}          //都不能为空
$m_pwd != $X_PWD && die("密码错误");                //密码认证

$tcpport = $m_id;       //tcp端口，包含了会话id
$c_data = file_get_contents("php://input"); //本机客户端来的数据

if ($m_type==="xin-tcp") {

    $log = '';
    $fp = stream_socket_client("tcp://127.0.0.1:". $tcpport, $errno, $errstr, 3);
    if (!$fp){
        die("TCP端口绑定失败: ". $errno . '-' . $errstr);
    }
    stream_set_timeout($fp, 0, 100000);     //超时即需要本地端交换数据了
    fwrite($fp, $c_data);
    $log .= "c_f:'" . $c_data . "'" . stream_socket_get_name($fp, TRUE) ."\n";
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
