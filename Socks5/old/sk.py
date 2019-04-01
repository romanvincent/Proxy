#!/usr/bin/env python3
#-*- coding:utf-8 -*-
# socks5原理：https://hatboy.github.io/2018/04/28/Python编写socks5服务器/
# 这是一个本地测试性质的脚本

import socket
from socketserver import StreamRequestHandler, ThreadingTCPServer
import select
import struct
from threading import Thread, Lock
from time import strftime

LADD = "0.0.0.0"
PORT  = 1080
SOCKS_VERSION = 5
DEBUG = False
ID = 1000
socket.setdefaulttimeout(5)

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
            DstHost = self.connection.recv(AddrLen)
            DstAddr = socket.gethostbyname(DstHost)
        DstPort = struct.unpack('!H', self.connection.recv(2))[0]

        # 响应CONNECT请求(正常的HTTP请求)
        try:
            if CMD == 1:  # CONNECT
                remote = socket.socket(socket.AF_INET, socket.SOCK_STREAM)          # 与远程服务器建立连接
                print(remote.connect((DstAddr, DstPort)))
                Logs(self.id, 'I', 'Connected to %s:%s' % (DstAddr, DstPort))
            else:
                Logs(self.id, 'D', 'No support request type: %d' % CMD)
                self.server.close_request(self.request)

            reply = struct.pack("!BBBBIH", SOCKS_VERSION, 0, 0, 1, 0, 0)   # 响应 成功 信息
            #reply = b'\x05\x00\x00\x01\x00\x00\x00\x00\x00\x00'
        except Exception as err:
            Logs(self.id, 'E', err)
            reply = struct.pack("!BBBBIH", SOCKS_VERSION, 5, 0, ATYP, 0, 0) # 响应 连接被拒绝 错误
        self.connection.sendall(reply)
        
        if reply[1] == 0 and CMD == 1:
            self.Exchange_loop(self.connection, remote)
        self.server.close_request(self.request)

        
    def Exchange_loop(self, client, remote):
        '''开始交换数据'''

        Logs(self.id, 'D', 'Exchange...')
        while True:
            r, _, _ = select.select([client, remote], [], [])
            if client in r:
                data = client.recv(4096)
                if remote.send(data) <= 0:
                    break
            if remote in r:
                data = remote.recv(4096)
                if client.send(data) <= 0:
                    break

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
    laddr  = (LADD, PORT)
    try:
        server = ThreadingTCPServer(laddr, Socks5)
        Logs(ID, 'I', "Listening " + str(laddr))
        server.serve_forever()
    except Exception as err:
        Logs(ID, 'E', "Listen close! " + str(err))

