---
layout: post
title: Linux下mysql相关集合
tags: [Linux, mysql]
---



### 配置mysql，允许远程访问

```cmd
mysql -u root -p
use mysql;
grant all privileges on *.*  to  'root'@'%'  identified by 'youpassword'  with grant option;
flush privileges;

修改/etc/my.cnf
找到bind-address = 127.0.0.1这一行
改为bind-address = 0.0.0.0即可
```



### Mysql 存储引擎的特点

![mysql 存储引擎的特点]({{site.baseurl}}/assets/img/pexels/mysql-db-type.png)