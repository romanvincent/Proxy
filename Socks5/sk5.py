#!/usr/bin/env python3
#-*- coding:utf-8 -*-
# socks5原理：https://hatboy.github.io/2018/04/28/Python编写socks5服务器/
# https://github.com/xinroom

import socket
from socketserver import StreamRequestHandler, ThreadingTCPServer
import select
import struct
from threading import Thread, Lock
from time import strftime
import requests
import binascii

LADDR = "0.0.0.0"                   #本地监听IP地址
LPORT  = 1080                       #本地端口
SOCKS_VERSION = 5                   #SOCKS版本号
DEBUG = False                       #需要显示debug日志码
ID = 1000                           #会话ID基础值


RSCHEME = "https://"                #与中间服务器传输的协议
RHOST = "xxx.xxx.xxx"               #中间服务器域名
RKPATH = "/ss/skc.php"              #中间服务器C端文件路径
RSPATH = "/ss/sks.php"              #中间服务器S端文件路径
RPOST = 443                         #中间服务器端口
RURL = RSCHEME + RHOST + RKPATH

RPWD = "c!&54s@d5f#@%*-"

lock=Lock()
loglock=Lock()

def Logs(id, typ, msg):
    '''日志记录'''
    if not DEBUG and typ == 'D':
        return
    msg = "%s - [%d - %s] - %s" % (strftime("%Y-%m-%d %H:%M:%S"), id, typ, msg)
    loglock.acquire()
    print(msg)
    loglock.release()


class Socks5(StreamRequestHandler):
    '''本地Socks5处理'''

    def Hello(self):
        '''协商'''

        Logs(self.id, 'D', 'Hello...')
        rec = self.connection.recv(2)
        VER, NMETHODS = struct.unpack("!BB", rec)               # 解包获得版本号和验证方法数
        assert VER == SOCKS_VERSION                             # 设置socks5协议，METHODS字段的数目大于0
        assert NMETHODS > 0
        self.connection.recv(2)
        self.connection.sendall(struct.pack("!BB", SOCKS_VERSION, 0))   # 发送协商响应数据包(无需验证)
        self.Reqhandle()

    def Reqhandle(self):
        '''响应请求'''
        
        Logs(self.id, 'D', 'Reqhandle...')
        # 请求解包
        VER, CMD, _, ATYP = struct.unpack("!BBBB", self.connection.recv(4))
        assert VER == SOCKS_VERSION
        if ATYP == 1:  # IPv4
            DstAddr = socket.inet_ntoa(self.connection.recv(4))
        elif ATYP == 3:  # 域名
            AddrLen = ord(self.connection.recv(1))
            DstAddr = self.connection.recv(AddrLen).decode('utf-8')
        DstPort = struct.unpack('!H', self.connection.recv(2))[0]

        # 响应CONNECT请求(正常的HTTP请求)
        try:
            if CMD == 1:  # CONNECT
                Logs(self.id, 'I', 'Connected to %s:%s' % (DstAddr, DstPort))
            else:
                Logs(self.id, 'W', 'No support request type: %d' % CMD)
                self.server.close_request(self.request)

            reply = struct.pack("!BBBBIH", SOCKS_VERSION, 0, 0, 1, 0, 0)   # 响应 成功 信息
            #reply = b'\x05\x00\x00\x01\x00\x00\x00\x00\x00\x00'
        except Exception as err:
            Logs(self.id, 'E', err)
            reply = struct.pack("!BBBBIH", SOCKS_VERSION, 5, 0, ATYP, 0, 0) # 响应 连接被拒绝 错误
        self.connection.sendall(reply)
        
        # 建立连接成功，开始交换数据
        if reply[1] == 0 and CMD == 1:
            remote=(DstAddr, str(DstPort))
            self.SConnect(remote)           #建立会话，远程S端开启
            self.Exchange_loop(self.connection, remote)
        self.server.close_request(self.request)

        
    def Exchange_loop(self, client, remote):
        '''开始交换数据'''
        
        Logs(self.id, 'D', 'Exchange...')
        headers={
            'User-Agent':'XinApp/1.0',
            'Xin-Type':'xin-tcp',
            'Xin-Host':remote[0],
            'Xin-Port':remote[1],
            'Xin-Pwd':RPWD,
            'Xin-Id': str(self.id),
            'Content-Type':'application/xin-binary'
        }
        s = requests.Session()
        s.headers.update(headers)

        while True:
            # 等待数据
            r, _, _ = select.select([client], [], [])
            if client in r:
                data = client.recv(4096)
                rr = s.post(url=RURL, data=data, stream=True)
                #print(binascii.b2a_hex(data).upper())
                #print(data)
                if len(data) == 0 or len(rr.content) == 0:
                    break

                for chunk in rr.iter_content(chunk_size=4096):
                    data=chunk
                    #print(binascii.b2a_hex(data).upper())
                    #print(data)
                    client.send(data)

        #temp = self.Ssession.recv(20480)
        #print(temp)


    def SConnect(self, Rinfo):
        header = 'POST %s HTTP/1.1\r\n' % RSPATH + \
                'Host: %s\r\n' % RHOST + \
                'User-Agent: XinApp/1.0\r\n' + \
                'Accept: */*\r\n' + \
                'Xin-Type: xin-con\r\n' + \
                'Xin-Host: %s\r\n' % Rinfo[0] + \
                'Xin-Port: %s\r\n' % Rinfo[1] + \
                'Xin-Pwd: %s\r\n' % RPWD + \
                'Xin-Id: %d\r\n' % self.id + \
                'Connection: keep-alive\r\n' + \
                'Content-Type: application/xin-binary\r\n' + \
                'Content-Length: 0\r\n' + \
                '\r\n'
        data = header.encode()
        re = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
        re.connect((socket.gethostbyname(RHOST), RPOST))
        re.sendall(data)
        self.Ssession = re


    def handle(self):
        '''TCP处理'''

        lock.acquire()
        global ID
        ID += 1
        self.id = ID
        lock.release()
        Logs(self.id, 'I', 'Accepting from %s:%s' % self.client_address)
        try:
            self.Hello()
        except Exception as err:
            Logs(self.id, 'W', str(err))
        Logs(self.id, 'D', 'End.')
        


if __name__ == "__main__":
    laddr  = (LADDR, LPORT)
    try:
        server = ThreadingTCPServer(laddr, Socks5)
        Logs(ID, 'I', "Listening " + str(laddr))
        server.serve_forever()
    except Exception as err:
        Logs(ID, 'E', "Listen close! " + str(err))

