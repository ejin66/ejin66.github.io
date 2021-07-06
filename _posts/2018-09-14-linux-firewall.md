---
layout: post
title: Linux中防火墙 [iptables, firewall, ufw] 的基本用法
tags: [Linux, CentOS, Firewall]
---

### CentOS

在 `CentOS` 中有两类防火墙： `iptabls` , `firewall`。 `CentOS 7` 以前的版本默认是 `iptabls`， 之后的版本默认都是 `firewall`。下面会分别介绍下两种防火墙的基本用法。

<br/>

#### `iptables` 的基本用法

安装 `iptables`:

 ```bash
yum install iptabls-services
 ```

开启/关闭/重启 `iptabls`:

 ```bash
systemctl start iptables
systemctl stop iptables
systemctl restart iptables
 ```

开机启动/禁止开机启动：

 ```bash
systemctl enable iptables
systemctl disable iptables
 ```

查看状态：

 ```bash
systemctl status iptables
 ```

`iptables` 配置端口的文件路径是： `/etc/sysconfig/iptables`。示例如下：

```yaml
#开放tcp 80端口
-A INPUT -m state -state NEW -m tcp -p tcp --dport 80 -j ACCEPT
#开放tcp 3306端口
-A INPUT -m state -state NEW -m tcp -p tcp --dport 3306 -j ACCEPT
#禁止指定ip访问8000端口
-I INPUT -s 121.222.222.222 -p tcp --dprot 8000 -j DROP
#允许指定ip访问8001端口
-I INPUT -s 121.222.222.222 -p tcp --dprot 8001 -j ACCEPT

#-A表示增加一条规则， -I表示插入一条规则，可以指定插入位置
#-m表示要状态的模块（tcp）
#-p表示指定通讯协议
#--dport表示目标端口，即需要访问本服务器的端口
#--sport表示源端口，即指定访问源的端口
#-s表示源地址，即外部client的ip地址
```

更改完配置文件，需要重启 `iptables`。

<br/>

#### `firewall` 的基本用法

安装 `firewall`:

 ```bash
yum install firewalld
 ```
开启/关闭/重启 `firewall`:

 ```bash
systemctl start firewalld
systemctl stop firewalld
systemctl restart firewalld
 ```

开机启动/禁止开机启动：

 ```bash
systemctl enable firewalld
systemctl disable firewalld
 ```

查看状态：

 ```bash
systemctl status firewalld
 ```

`firewall` 提供多种信任级别Zone，不同的网络连接可以归类到不同的信任级别中去。各信任级别如下：

- drop: 丢弃所有进入的包，而不给出任何响应
- block: 拒绝所有外部发起的连接，允许内部发起的连接
- public: 允许指定的进入连接
- external: 同上，对伪装的进入连接，一般用于路由转发
- dmz: 允许受限制的进入连接
- work: 允许受信任的计算机被限制的进入连接，类似 workgroup
- home: 同上，类似 homegroup
- internal: 同上，范围针对所有互联网用户
- trusted: 信任所有连接

> `firewall` 具体的规则管理， 可以使用 `firewall-cmd` 命令。

譬如，若想开放80端口，只需要将80端口的网络连接添加到信任级别为 `public` 的zone中:

``` bash
firewall-cmd --zone=public --add-port=80/tcp --permanent

#zone=public 表示指定public的信任级别
#--add-port=80/tcp 指添加端口为80的tcp连接
#--permanent 指该设置永久生效。没有此参数，重启之后失效
```

更改 `firewall` 配置之后，需要重新加载才能生效：

 ```bash
firewall-cmd --reload
 ```

关于 `firewall-cmd`，除了上面的命令，还有一些基本命令：

1. 查看版本：

   ```bash
   firewall-cmd --version
   ```

2. 查看帮助：

   ```bash
   firewall-cmd --help
   ```

3. 查看状态：

   ```bash
   firewall-cmd --state
   ```

4. 查看区域信息：

   ```bash
   firewall-cmd --get-active-zones
   ```

5. 完全重载，需要断开连接：

   ```bash
   firewall-cmd --complete-reload
   ```

6. 查看一个zone上的所有打开端口：

   ```bash
   firewall-cmd --zone=public --list-ports
   ```

7. 删除一个zone上已经打开的端口：

   ```bash
   firewall-cmd --zone=public --remove-port=80/tcp --permanent
   ```
<br>

### Ubuntu
Ubuntu中常用的防火墙工具是ufw.
##### ufw的基本用法

1. 安装
```bash
yum install ufw
```

2. 开启防火墙, 并随系统自启动
```bash
ufw enable
```

3. 关闭防火墙
```bash
ufw disable
```

4. 查看防火墙状态
```bash
ufw status
```

5. 设置端口
```bash
ufw allow 80 # 允许外部访问80端口
ufw deny 80 # 禁止外部访问80 端口
ufw delete deny 80 # 删除上面的规则 
ufw allow from 192.168.1.1 # 允许此IP访问所有的本机端口
ufw deny smtp # 禁止外部访问smtp服务
ufw delete allow smtp # 删除上面建立的某条规则
# 要拒绝所有的TCP流量从10.0.0.0/8 到192.168.0.1地址的22端口
ufw deny proto tcp from 10.0.0.0/8 to 192.168.0.1 port 22 

# 可以允许网络段访问这个主机
ufw allow from 10.0.0.0/8
ufw allow from 172.16.0.0/12
ufw allow from 192.168.0.0/16

# 禁止所有的外部访问
ufw default deny
```
