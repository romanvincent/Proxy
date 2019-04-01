<?php
/**
 * PHP基于文件的在一对进程之间的数据传输类
 * 作者：XinRoom https://github.com/xinroom/
 */
class File_stream {
 
    private $sid;                       //会话ID
    private $tempdir = 'temp/';         //会话交换缓存文件路径
    private $linterval = 200000;        //监听间隔  单位： sm(微秒)
    private $ltimeout  = 5;             //监听最长等待时间 单位： m(秒)
    private $rinterval = 10000;         //读操作间隔
    private $rtimeout  = 2;             //读操最长等待时间
    private $winterval = 10000;         //写操作。。。
    private $wtimeout  = 1;             //。。。
    
    private $ltime = 0;                 //监听已等待时长 ，微秒
    private $rtime = 0;                 //读操作。。。
    private $wtime = 0;                 //写操作。。。
    
    private $type = 'c';                //默认为客户端模式(先进行写操作，再进行读)
    
    public $err = '';                   //异常信息存储
  
    //初始化对象，sid为必须项，其它可以默认
    function __construct($arr) {
        if (!isset($arr['sid']) || $arr['sid'] == '') {
            return -1;      //必须有sid项
        }
        
        isset($arr['sid']) && $this->set('sid',$arr['sid']);
        isset($arr['tempdir']) && $this->set('tempdir', $arr['tempdir']);
        isset($arr['linterval']) && $this->set('linterval',$arr['linterval']);
        isset($arr['linterval']) && $this->set('linterval',$arr['ltimeout']);
        isset($arr['rinterval']) && $this->set('rinterval',$arr['rinterval']);
        isset($arr['rtimeout']) && $this->set('rtimeout',$arr['rtimeout']);
        isset($arr['ltime']) && $this->set('ltime',$arr['ltime']);
        isset($arr['rtime']) && $this->set('rtime',$arr['rtime']);
        
        //缓存目录若不存在则创建
        if ( $this->tempdir != '' && !file_exists($this->tempdir) ){
            mkdir($this->tempdir,0777,true);
        }
        return 0;
    }
    
    //变量设置
    function set($k, $v) {
        if (isset($v) && $v != '') {
            $this->$k = $v;
        }
    }
    
    //构造客户/服务/监听缓存文件路径
    function tempfile($t) {
        return $this->tempdir . $t . $this->sid;
    }
    
    //监听等待延迟
    function lusleep() {
        if ($this->ltime >= $this->ltimeout * 1000000) {
            $this->ltime = 0;
            return 1;   //到了最大等待时间
        } else {
            usleep($this->linterval);   //执行延迟
            $this->ltime += $this->linterval;
            return 0;   //没到最大等待时间
        }
    }
    
    //读延迟
    function rusleep() {
        if ($this->rtime >= $this->rtimeout * 1000000) {
            $this->rtime = 0;
            return 1;
        } else {
            usleep($this->rinterval);
            $this->rtime += $this->rinterval;
            return 0;
        }
    }
    
    //写延迟
    function wusleep() {
        if ($this->wtime >= $this->wtimeout * 1000000) {
            $this->wtime = 0;
            return 1;
        } else {
            usleep($this->winterval);
            $this->wtime += $this->winterval;
            return 0;
        }
    }
    
    //监听文件创建，初始化，表示可以接受请求
    function listen() {
        try {
            $this->type = 's';
            if (file_exists($this->tempfile('s')) ) {unlink($this->tempfile('s'));}
            if (file_exists($this->tempfile('c')) ) {unlink($this->tempfile('c'));}
            file_put_contents($this->tempfile('l'), '0', LOCK_EX);
        } catch (Exception $e) {
            $this->err = $e->getMessage();
            return -1;
        }
        return 0;   //监听初始化正常
    }
    
    //读操作
    function read() {
        if ($this->err != '') return -1;    //有关键异常，不再继续执行
        $rtemp='';      //读临时变量
        if ($this->type == 's') {           //用于文件名判断
            $ttype = 'c';
        } else {
            $ttype = 's';
        }
        $tempfile = $this->tempfile($ttype);    //服务端读取客户端文件；客户端读取服务端文件
        while ( file_exists($this->tempfile('l')) ) {   //判断会话是否结束
            $fr = NULL;
            if (file_exists($tempfile) ) {      //判断对方文件是否存在(当对方需要发送消息时会创建)
                $fr = fopen($tempfile,'rb');    //
                while ($fr) {                   //一直读完 //这里可以改进！
                    $data = fread($fr,2048);
                    if ($data=='') break;
                    $rtemp .= $data;
                    if ($this->rusleep()) {     //读超时
                        $this->err = "r too long";
                        return -1;
                    }
                }
            }
            if ($fr) break;
            if ($this->lusleep()) {            //一直没有对方文件，即监听超时
                $this->err = "l too long";
                return -2;
            }
        }
        unlink($tempfile);                     //删除文件，表示我已经读完了
        return $rtemp;
    }
    

    //写处理
    function write($data) {
        if ($this->err != '') return -1;
        
        if ($this->type == 's') {
            $ttype = 's';
        } else {
            $ttype = 'c';
        }
        $tempfile = $this->tempfile($ttype);
        
        if ($this->type == 'c' && !file_exists($this->tempfile('l'))) {
            $this->err = "No server!";
            return -2;
        }
        
        while (file_exists($tempfile) && !$this->wusleep() ) {  //自己的文件还未被对方删除，表示对方还未读完，需要等待
            //
        }
        
        //创建自己的文件并写入需要传输的数据
        $fw=fopen($tempfile,'wb+');
        fwrite($fw,$data);
        fclose($fw);
        return 0;   //正常返回
    }

    //关闭，即删除会话所有缓存
    function close() {
        $tempfile = $this->tempfile($this->type);
        file_exists($tempfile) && unlink($tempfile);
        if ($this->type == 's') {
            $tempfile = $this->tempfile($this->type);
            unlink($this->tempdir . 'l' . $this->sid);
        }
    }

    //function status($w = 5) {
    //    $tempfile = $this->tempfile('l');
    //    if ( $w === 5 ) {
    //        return file_get_contents($tempfile);
    //    } else {
    //        file_put_contents($tempfile, $w, LOCK_EX);
    //        return $w;
    //    }
    //}
    
    //正常退出时清理L文件
    function __destruct() {
        if ($this->type == 's') {
            $tempfile = $this->tempfile($this->type);
            unlink($this->tempdir . 'l' . $this->sid);
        }
    }

}
 
