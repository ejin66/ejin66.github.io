---
layout: post
title: CentOS7相关集合
tags: [Linux]
---

### 安装`Apache`,`Php5`,`MariaDB`

`Apache`安装：

```bash
#安装apache
yum install httpd 
#设为开机启动
Chkconfig httpd on 
```

`MariaDB`安装：

```bash
# mariadb代替mysqldb;centos7之前的版本，用mysql-server,默认源中没有mariaDB-server
yum install mysql mariaDB-server
# 设置密码，初始没有密码就直接回车
mysqladmin -u root -p password xxx

# 若access deny,修改mysql user表
# 初始没有密码，直接回车
mysql -u root -p
use mysql;
update user set password=PASSWORD('密码') where user = 'root';
exit;
service mysqld restart
#开机启动
Chkconfig mysqld on
```

`PHP5`安装：

```cmd
yum install PHP
#这里选择以上安装包进行安装，根据提示输入Y回车
yum install php-mysql php-gd libjpeg* php-imap php-ldap php-odbcphp-pear   php-xml php-xmlrpc php-mbstring php-mcrypt php-bcmath  php-mhashlibmcrypt  

# 重启 mysql apache
```

<br/>

### `Apache`支持HTTPS设置

首先安装`SSL`:

```bash
yum install mod_ssl openssl
```

生成签名证书（可以申请免费的腾讯云证书，1年有效期）：

```bash
openssl genrsa -out ca.key 2048
openssl req -new -key ca.key -out ca.csr
openssl x509 -req -days 365 -in ca.csr -signkey ca.key -out ca.crt
cp ca.crt /etc/pki/tls/certs/
cp ca.key /etc/pki/tls/private/
cp ca.csr /etc/pki/tls/private/
```

修改Apache设置：

```bash
vim /etc/httpd/conf.d/ssl.conf
SSLCertificateFile /etc/pki/tls/certs/ca.crt
SSLCertificateKeyFile /etc/pki/tls/private/ca.key
Systemctl restart iptables.service
```

修改防火墙，增加443端口：

```bash
vi /etc/sysconfig/iptables
# add
INPUT -m state --state NEW -m tcp -p tcp --dport 443 -j ACCEPT
systemctl restart iptables.service 
```

<br/>

### `VMWare`安装CentOS7之后无法连接网络

安装完CentOS7之后，默认是没有开启网卡的。

```bash
cd /etc/sysconfig/network-scripts/
ls
```

查看下`ifcfg-eno`后面的数字。如 `eno32`，则编辑文件 `vi ifcfg-eno32`

```bash
vim ifcfg-eno32
#edit
#开启自动启用网络连接
ONBOOT="yes"
#保存退出
```

重启网络：

```bash
service network restart 
```


### linux系统相关

#### uname
显示系统信息
```bash
-a或--all 　显示全部的信息。
-m或--machine 　显示电脑类型。
-n或--nodename 　显示在网络上的主机名称。
-r或--release 　显示操作系统的发行编号。
-s或--sysname 　显示操作系统名称。
-v 　显示操作系统的版本。
--help 　显示帮助。
--version 　显示版本信息。
```

#### 查看cpu信息
```bash
cat /proc/cpuinfo
# or
lscpu
```

#### 查看内存信息
```bash
cat /proc/meminfo
```

#### 查看CPU与内存的使用情况
1. top
通过 top 内部命令可以控制此处的显示方式:
　　PID：进程的ID
　　USER：进程所有者
　　PR：进程的优先级别，越小越优先被执行
　　NInice：值
　　VIRT：进程占用的虚拟内存
　　RES：进程占用的物理内存
　　SHR：进程使用的共享内存
　　S：进程的状态。S表示休眠，R表示正在运行，Z表示僵死状态，N表示该进程优先值为负数
　　%CPU：进程占用CPU的使用率
　　%MEM：进程使用的物理内存和总内存的百分比
　　TIME+：该进程启动后占用的总的CPU时间，即占用CPU使用时间的累加值。
　　COMMAND：进程启动命令名称
  
内部命令如下表：
　　s- 改变画面更新频率
　　l - 关闭或开启第一部分第一行 top 信息的表示
　　t - 关闭或开启第一部分第二行 Tasks 和第三行 Cpus 信息的表示
　　m - 关闭或开启第一部分第四行 Mem 和 第五行 Swap 信息的表示
　　N - 以 PID 的大小的顺序排列表示进程列表
　　P - 以 CPU 占用率大小的顺序排列进程列表
　　M - 以内存占用率大小的顺序排列进程列表
　　h - 显示帮助
　　n - 设置在进程列表所显示进程的数量
　　q - 退出 top
　　s -改变画面更新周期
  
2. ps
各列的含义:
   F 代表这个程序的旗标 (flag)， 4 代表使用者为 super user；
　　S 代表这个程序的状态 (STAT)；
　　PID 程序的 ID ；
　　C CPU 使用的资源百分比
　　PRI 这个是 Priority (优先执行序) 的缩写；
　　NI 这个是 Nice 值。
　　ADDR 这个是 kernel function，指出该程序在内存的那个部分。如果是个 running # 的程序，一般就是『 - 』
　　SZ 使用掉的内存大小；
　　WCHAN 目前这个程序是否正在运作当中，若为 - 表示正在运作；
　　TTY 登入者的终端机位置；
　　TIME 使用掉的 CPU 时间。
　　CMD 所下达的指令
  
3. free
free命令可以显示当前系统未使用的和已使用的内存数目，还可以显示被内核使用的内存缓冲区。
total:总计物理内存的大小。
　　used:已使用多大。
　　free:可用有多少。
　　Shared:多个进程共享的内存总额。
　　Buffers/cached:磁盘缓存的大小。
