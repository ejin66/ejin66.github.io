---
layout: post
title: Mysql主从设置--库主从以及表主从
tags: [Mysql]
---

Mysql本身支持多个数据库之间的`Master-slave`架构，具体步骤是：

#### 1. 配置`master`的`my.cnf`

在`[mysqld]`下增加：

```yaml
# 在一个主从架构中，server_id要唯一
server_id=100
# 设置需要同步的数据库。多个数据库用“，”隔开。
binlog-do-db=dbname1, dbname2
# 设置不需要同步的数据库
# 若两个都不设置，默认同步所有
# binlog-ignore-db=mysql

# 开启二进制日志记录，并指定日志名称。
# 数据库任何的变化都会记录到该日志文件中。
# 主从就是通过bin log实现的。
log-bin=master-mysql-bin
# bin log日志格式，有三种：mixed, statement, row
binlog_format=mixed
# 跳过主从复制中遇到的错误类型
# 如1062： 主键重复， 1032： 主从数据库不一致
slave_skip_errors=1062
```

#### 2. 配置`slave`的`my.cnf`

在`[mysqld]`下增加：

```yaml
server_id=101
log-bin=slave-mysql-bin
# 设置需要被同步的数据库
replicate-do-db=dbname1
# 设置不需要同步的数据库
replicate-ignore-db=dbname2
# 设置需要被同步的表。如：dbname1数据库中的userinfo表。
replicate-do-table=dbname1.userinfo
# 设置不需要被同步的表
# replicate-ignore-table=dbname1.userinfo

# 与上面两个的功能一样，支持通配符功能。
#replicate-wild-do-table
#replicate-wild-ignore-table
binlog_format=mixed
slave_skip_errors=1062
# 中继日志。需要开启，否则start slave会失败。
# 在主从复制中，slave会读master bin log到relay log，
# 然后SQL线程会读取slave log日志内容到从数据库。
relay_log=slave-mysql-relay-bin
# slave将复制事件写入自己的二进制日志
log_slave_updates=1
# 防止改变数据。该设置对于超级用户不生效。
read_only=1
```

#### 3. 查看`master`状态

连接到主数据库，查看`master`状态：

```bash
mysql -uroot -p

show master status;
```

正常情况下，会打印当前的bin log名以及位置position信息。

#### 4. 查看`slave`状态

连接到从数据库，查看`slave`状态：

```bash
mysql -uroot -p

show slave status;
```

一开始没有配置`slave`信息的话，会显示：

```bash
Empty set(0.00 sec)
```

这时，需要设置从数据库的`slave`信息：

```bash
change master to
	master_host='master-host',
	master_port='3306',
	master_user='root',
	master_password='123456',
	master_log_file='${上一步中master的bin log name}',
	master_log_pos='${上一步中master的position}';
```

重启`slave`服务：

```bash
stop slave;
start slave;
```

若`start slave`报错，可以先进行`reset`:

```bash
reset slave;
```

最后，在一次检查`slave`状态：

```bash
# \G让输出内容格式化显示
show slave status\G;
```

#### 5. 测试检查

在主数据库中更新数据，检查从数据库，若同步更新，则配置成功。

#### 6. 注意

***千万不能去修改***从数据库中需要被同步的信息，否则主从同步会失败。

若信息不同步，可通过删除从数据库中同步信息来重新同步。

因为`read_only`配置对超级用户不生效，要如何避免修改从数据库：`使用普通用户，并分配各个表的增删改查权限。超级用户只能本地登录，且避免直接操作数据库。`

#### 7. `docker-compose file`

使用`docker-compose`起主从数据库的例子：

```yaml
version: "3"
services:
	mysql-master:
		image: mysql:latest
		container_name: test-mysql-master
		environment:
			- MYSQL_ROOT_ASSWORD=123456
			- MYSQL_DATABASE=testdb
		networks:
			- test_network
		ports:
			- 18306:3306
		restart: always
		volumes:
			- ./master-mysqld.cnf:/etc/mysql/mysql.conf.d/mysqld.cnf
			- ./master-data:/var/lib/mysql
	mysql-slave:
		image: mysql:latest
		container_name: test-mysql-slave
		environment:
			- MYSQL_ROOT_ASSWORD=123456
			- MYSQL_DATABASE=testdb
		networks:
			- test_network
		ports:
			- 18406:3306
		restart: always
		volumes:
			- ./slave-mysqld.cnf:/etc/mysql/mysql.conf.d/mysqld.cnf
			- ./slave-data:/var/lib/mysql
networks:
	test_network:
```





