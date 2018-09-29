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

