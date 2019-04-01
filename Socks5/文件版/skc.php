<?php
//HTTP代理(Socks C端)
header("Content-Type: application/xin-binary");

//$_SERVER['REQUEST_SCHEME'] != "https" && die;
//$_SERVER['REQUEST_METHOD'] != "POST" && die;

$X_PWD = "c!&54s@d5f#@%*-";                                   //验证码

$m_type = $_SERVER['HTTP_XIN_TYPE'];      //类型标志，可扩展
$m_pwd  = $_SERVER['HTTP_XIN_PWD'];       //验证码
$m_id  =  $_SERVER['HTTP_XIN_ID'];        //会话ID

//验证
$m_array = array($m_type, $m_pwd, $m_id);
if (in_array("",$m_array)) {header("HTTP/1.1 404 Not Foud");die;}          //都不能为空
$m_pwd != $X_PWD && die("mima");                //密码认证

//
$c_data = file_get_contents("php://input"); //本机客户端来的数据

if ($m_type==="xin-tcp") {
    $log = '';
    require("File_stream.php");
    $arr = array( 'sid' => $m_id );
    $s = new File_stream($arr);     //
    $call = $s->write($c_data);     //向S端进程发本地端来的数据
    if ($call != 0 ) {
        $s->close();
        die("无法连接S端: ". $s->err);
    }
    $data = $s->read();             //等待S端发来的数据
    if ($data < 0 ) {
        $s->close();
        die("读超时: ". $s->err);
    }
    print $data;                    //完毕，发往本地端

} elseif ($m_type==="xin-udp") {
    # code...
}

//file_put_contents("clog.txt", $log);
