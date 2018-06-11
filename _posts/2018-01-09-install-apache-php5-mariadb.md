---
layout: post
title: CentOS7中安装apache,php5,mariaDB
tags: [Linux]
---

### Apache

```cmd
yum install httpd #安装apache

Chkconfig httpd on #设为开机启动
```



### MariaDB

```cmd
yum install mysql mariaDB-server(mariadb代替mysqldb) (centos7之前的版本，用mysql-server,默认源中没有mariaDB-server)

mysqladmin -u root -p password xxx(设置密码，初始没有密码就直接回车)

//若access deny,修改mysql user表
mysql -u root -p(初始没有密码，直接回车)
use mysql;
update user set password=PASSWORD('密码') where user = 'root';
exit;
service mysqld restart

Chkconfig mysqld on#开机启动
```



### PHP5

```cmd
yum install PHP

yum install php-mysql php-gd libjpeg* php-imap php-ldap php-odbcphp-pear   php-xml php-xmlrpc php-mbstring php-mcrypt php-bcmath  php-mhashlibmcrypt  #这里选择以上安装包进行安装，根据提示输入Y回车

重启mysql apache
```

