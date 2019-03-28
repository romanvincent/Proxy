<?php
//HTTP代理(网页版)
//error_reporting(E_ALL);
ini_set("zlib.output_compression", "On");
ini_set("zlib.output_compression_level", "5");

$X_WWWHOST = "***";  //网站域名
$X_DHOST = "***";   //默认代理域名
$X_DTYPE = "text/html"; //默认页面mate


//身份验证
if ($_SERVER['REDIRECT_URL'] == '/mima'){
    setcookie("anthCookie", "#hjhdjs45%^fyt%", time()+6048000, "/", $X_WWWHOST, NULL, 1);
    header('Location: https://'. $X_WWWHOST .'/');
    die;
}
if ($_COOKIE["anthCookie"] != "#hjhdjs45%^fyt%"){
    if(strpos($_SERVER['HTTP_USER_AGENT'],'mima') === FALSE){
        header('Location: https://www.baidu.com/'); die;
    }
}
//END


//基本路由
$PATH = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
switch ($PATH){
    case "/set":
        setcookie("name", $_GET['n'], time()+3600, "/", $X_WWWHOST, NULL, 1);
        header('Location: https://'. $X_WWWHOST .'/');
        die;
    case "/c":
        echo gethostbyname($_GET['n']) .'<br>'. gethostbyname6($_GET['n']);  //查无污染ip
        die;
}
//END


//基本处理
isset($_COOKIE["name"]) && setcookie("name", $_COOKIE["name"], time()+3600, "/", $X_WWWHOST, NULL, 1);//NameCookie续租
header('Referrer-Policy: same-origin'); //禁止referrer no-referrer
//END


//禁用部分Request
$blok=array("/gen_204", "/og/_", "/async", "/status?");
$arrlength=count($blok);

for($x=0;$x<$arrlength;$x++) {
  if (strpos($_SERVER['REQUEST_URI'], $blok[$x]) !== FALSE){
       header('HTTP/1.1 404 Not Found');
       die; break;
  }
}
//END


//Proxy链接构造
$mirror = isset($_COOKIE["name"]) ? $_COOKIE["name"] : $X_DHOST;//默认域名
$url_path = $_SERVER['REQUEST_URI'];
if ($_SERVER['REQUEST_URI'][1]=='!'){   //识别是否需要代理其它网站
    $url = "https://". substr($_SERVER['REQUEST_URI'], 2);
    $url = str_replace( '.comm', '.com', $url);
    $url = parse_url($url);
    $mirror = $url["host"];
    if ($url["path"]=='') $url["path"]='/';
    if ($url["query"]) $url["query"]='?' . $url["query"];
    $url_path = $url["path"] . $url["query"];
}

/* Google必须https */
if($mirror == "$X_DHOST" && $_SERVER['REQUEST_SCHEME'] == "http" ) {
    header('Location: https://$X_WWWHOST/'); die; 
}

/* 获取http请求头,并构造 */
$headers = array();
foreach ($_SERVER as $k => $v) {
    if (substr($k, 0, 5) == "HTTP_") {
        $k = str_replace('_', ' ', substr($k, 5));
        $k = str_replace(' ', '-', ucwords(strtolower($k)));
        if ($k == "Host") $v = $mirror;
        if ($k == "Set-Cookie") $v = str_replace("DV=","NO=",$v);
        if ($k == "X-Forwarded-For" || $k == "X-Real-Ip" || $k == "Keep-Alive" || $k == "Referer") 
            continue;
        if ($k == "Accept-Encoding")
            $v = "identity;q=1.0, *;q=0";       # Alter "Accept-Encoding" header to accept unencoded content only  压缩了就无法替换字符了
        if ($k == "Connection" && $v == "keep-alive")
            $v = "close";                       # Alter value of "Connection" header from "keep-alive" to "close"
        $headers[] = $k . ": " . $v;
    }
}
$headers[] = 'Referer: ' . $mirror;
$url =  $_SERVER['REQUEST_SCHEME'] . "://" . $mirror . $url_path; //curl 要请求的url
$response = bcurl($url, $headers);

/* 处理response */
//识别头部的位置
$is_html = 0;
$nlnl = strpos($response, "\r\n\r\n");
$add = 4;
if (!$nlnl) {
    $nlnl = strpos($response, "\n\n");
    $add = 2;
}
if (!$nlnl) {
    die;
}

$no_headers = array("Alt-Svc","Transfer-Encoding","Strict-Transport-Security");
$headers = substr($response, 0, $nlnl); //'/^(.*?)(\r?\n|$)/ims'
if (preg_match_all('/^(\S*?):\s(.*?)(\r?\n|$)/ims', $headers, $matches)) {
    for ($i = 0; $i < count($matches[0]); $i++) {

        if (in_array($matches[1][$i],$no_headers)) continue;
        if ($matches[1][$i]=="Content-Type") $X_DTYPE=$matches[2][$i];
        if ($matches[1][$i]=="Set-Cookie"){
            $ct = str_replace(".google.co.uk", $X_WWWHOST, $matches[0][$i]);
            header($ct,false);
        }else{
            header($matches[0][$i]);
        }
    }
}

$r = substr($response, $nlnl + $add);
if ( in_array($X_DTYPE, array("text/html", "application/x-javascript", "text/css")) || (strpos($X_DTYPE, "charset") !== FALSE) ){
    /* body 只有字符类型的需要处理*/
    $r = str_replace( array( "id.google.co.uk",$X_DHOST,"clients1.google.co.uk","id.google.com.hk","www.google.com"),$X_WWWHOST,$r);
    $r = str_replace( 'content="origin"', 'content="same-origin"', $r);

    if ($mirror != $X_DHOST) {
        $newmirror = str_replace( '.com', '.comm', $mirror);
        $r = str_replace( $mirror, $X_WWWHOST.'/!'. $newmirror, $r);
        $r = str_replace( 'href="//', 'href="biaoji', $r);
        $r = str_replace( 'href="/', 'href="/!'. $newmirror .'/', $r);
        $r = str_replace( 'href="biaoji', 'href="//', $r);
    }else{
        $r = str_replace( array('consent.google.com','apis.google.com','adservice.google.co.uk'),  '0.0.0.0', $r);    //剔除的链接
        $r = str_replace( 'i.ytimg.com', $X_WWWHOST.'/!i.ytimg.comm', $r);
        $r = str_replace( 's.ytimg.com', $X_WWWHOST.'/!s.ytimg.comm', $r);
        $r = str_replace( 'encrypted-tbn0.gstatic.com',  $X_WWWHOST.'/!encrypted-tbn0.gstatic.comm', $r);
        $r = str_replace( 'encrypted-tbn1.gstatic.com',  $X_WWWHOST.'/!encrypted-tbn1.gstatic.comm', $r);
        $r = str_replace( 'navigator.serviceWorker',  'navigator.serviceWorke', $r);
        $r = str_replace( array('scholar.google.cn','scholar.google.com'),  'sci-hub.org.cn', $r);//学术搜索
    }

}

print $r;   //返回的文档内容
//END















//工具函数

function bcurl($url, $headers) {
    /* curl 开始 */
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HEADER, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    //curl_setopt($ch, CURLINFO_HEADER_OUT, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $_POST);
    }
    $response = curl_exec($ch);
    //echo curl_getinfo($ch,CURLINFO_HEADER_OUT);
    curl_close($ch);
    return $response;
}

function gethostbyname6($host, $try_a = false) {
    // get AAAA record for $host
    // if $try_a is true, if AAAA fails, it tries for A
    // the first match found is returned
    // otherwise returns false

    $dns = gethostbynamel6($host, $try_a);
    if ($dns == false) { return false; }
    else { return $dns[0]; }
}

function gethostbynamel6($host, $try_a = false) {
    // get AAAA records for $host,
    // if $try_a is true, if AAAA fails, it tries for A
    // results are returned in an array of ips found matching type
    // otherwise returns false

    $dns6 = dns_get_record($host, DNS_AAAA);
    if ($try_a == true) {
        $dns4 = dns_get_record($host, DNS_A);
        $dns = array_merge($dns4, $dns6);
    }
    else { $dns = $dns6; }
    $ip6 = array();
    $ip4 = array();
    foreach ($dns as $record) {
        if ($record["type"] == "A") {
            $ip4[] = $record["ip"];
        }
        if ($record["type"] == "AAAA") {
            $ip6[] = $record["ipv6"];
        }
    }
    if (count($ip6) < 1) {
        if ($try_a == true) {
            if (count($ip4) < 1) {
                return false;
            }
            else {
                return $ip4;
            }
        }
        else {
            return false;
        }
    }
    else {
        return $ip6;
    }
}
//END