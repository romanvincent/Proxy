<?php
//HTTP代理(Socks)
//这个php用来远端转发

header("Content-Type: application/xin-binary");

error_reporting(E_ERROR);
#error_reporting(E_ALL);

//$_SERVER['REQUEST_SCHEME'] != "https" && die;
$_SERVER['REQUEST_METHOD'] != "POST" && die;

$X_TYPES = array('xin-tcp', 'xin-udp');                       //Socket 类型
$X_PWD = "c!&54s@d5f#@%*-";                                   //验证码

$m_type = $_SERVER['HTTP_XIN_TYPE'];
$m_host = $_SERVER['HTTP_XIN_HOST'];
$m_port = $_SERVER['HTTP_XIN_PORT'];
$m_pwd  = $_SERVER['HTTP_XIN_PWD'];
$m_id  = $_SERVER['HTTP_XIN_ID'];

$m_array = array($m_type, $m_host, $m_port, $m_pwd, $m_id);
if (in_array("",$m_array)) {header("HTTP/1.1 404 Not Foud");die;}         //都不能为空
$m_pwd != $X_PWD && die;

$c_data = file_get_contents("php://input");

if ($m_type==="xin-tcp") {

    $fp = fsockopen($m_host, $m_port, $errno, $errstr, 5 );
    stream_set_timeout($fp, 3);
    if (!$fp) {
        header("HTTP/1.1 601 NO-1");
        fclose($fp);
        die;
    }
    fwrite($fp, $c_data);
    while (!feof($fp)) {
        $r = fread($fp, 4096);
        print $r;
        if (strlen($r)<=0) break;
    }
    fclose($fp);
}