<?php
//HTTP代理(Socks S端)
header("Content-Type: application/xin-binary");
//error_reporting(E_ERROR);

//$_SERVER['REQUEST_SCHEME'] != "https" && die;
//$_SERVER['REQUEST_METHOD'] != "POST" && die;

$X_PWD = "c!&54s@d5f#@%*-";                                   //验证码

$m_type = $_SERVER['HTTP_XIN_TYPE'];    //类型标志，可扩展
$m_host = $_SERVER['HTTP_XIN_HOST'];    //远端主机
$m_port = $_SERVER['HTTP_XIN_PORT'];    //远端端口
$m_pwd  = $_SERVER['HTTP_XIN_PWD'];     //验证码
$m_id  =  $_SERVER['HTTP_XIN_ID'];      //会话ID

//验证
$m_array = array( $m_type, $m_host, $m_port, $m_pwd, $m_id );
if ( in_array("",$m_array) ) { header("HTTP/1.1 404 Not Foud");die; }               //都不能为空
$m_pwd != $X_PWD && die;        //密码验证
if ( $m_type !="xin-con" ) {      //类型验证
    die("chuwuliexing");
}

$log = '';  //调试用

//服务端监听
require("File_stream.php");
$arr = array('sid' => $m_id);
$s = new File_stream($arr);         //初始化
if ( $s->listen()!=0 ) {            //监听
    $s->close();
    die("服务端失败:". $s->err);
}

//与远端建立连接
$fp = fsockopen( $m_host, $m_port, $errno, $errstr, 5 ); 
if (!$fp) {
    $s->close();
    die();
}
stream_set_timeout($fp, 0, 500000);     //最多500ms的阻塞时间

while (1) {
    $data = $s->read();                 //开始等待数据
    if ($data < 0) {
        $s->close();
        fclose($fp);
        $log .= "du chaoshi:" . $s->err .  "\n";;
        //file_put_contents("slog.txt", $log);
        die("读取超时:". $s->err);
    }
    $log .= "读到的数据：\n" . tohex($data) . $s->err .  "\n";
    fwrite($fp, $data);                 //发送到远端

    $sock_data = '';                    //
    $fpline = 1;                        //远端会话主动关闭标志
    while ($fpline){
        $temp_data = fread($fp, 4096);  //从远端获取数据
        $info = stream_get_meta_data($fp);
        if ($info['timed_out']) {       //因超时而进入下一个循环，等待本地的数据传递过来
            break;
        }
        if (strlen($temp_data) == 0) {  //判断远端是否主动关闭连接
            $log .= "关闭 - 远端 \n";
            $fpline = 0;
            break;
        } else if ($temp_data == FALSE) {   //接受异常
            //err
            $log .= "异常 - 远端 \n";
            break;
        } else {
            $sock_data .= $temp_data;       //接受到临时变量里去
        }

    }
    $s->write($sock_data);
    $log .= "需要向本地端发送： \n". tohex($sock_data) ."\n";
    
}

//完毕
$s->close();
fclose($fp);
//file_put_contents("slog.txt", $log);


function tohex($content) {
    $hex='';
    for ($i=0;$i<=strlen($content);$i++) {
        $asc = ord(substr($content,$i,1));
        $hex .= dechex($asc);
    }
    return $hex;
}
