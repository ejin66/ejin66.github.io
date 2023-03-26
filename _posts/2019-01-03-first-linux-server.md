---
layout: post
title: 记录一次初始化服务器的过程
tags: [Linux]
---

### 用户与权限

#### 用户组

用户组管理有添加、删除、修改的操作。用户组相关命令本质上是对`/etc/group`文件的修改。

##### 创建用户组

```bash
groupadd [-g group_id] group_name
```

> 每个组都有一个组标识号，通过`-g`来指定。如没有指定，默认在最大组标识号的基础上+1。

##### 删除用户组

```bash
groupdel group_name
```

##### 修改用户组

```bash
groupmod [-g group_id] [-n new_group_name] group_name
```

> `-n`用来修改用户组的名称。

#### 用户

##### 创建用户

```bash
useradd [-d dir] [-g init_group] [-G other_group] [-u user_number] user_name
```

其中，`-d`用来指定用户的主目录，默认是`/home/${user_name}`。 `-g`指定用户初始的用户组，默认是创建一个用户名同名的用户组。`-G`是指定额外的用户组。`-u`指定用户的用户号。

##### 删除用户

```bash
userdel [-r] user_name
```

`-r`表示把用户的主目录也一并删除掉。

##### 修改用户

```bash
usermod [可选性与创建用户一致] user_name
```

##### 修改密码

```bash
passwd user_name
```

#### 分配权限

通过命令`chown`，可以修改文件的拥有者为指定的用户、用户组。一般只有`root`管理员能够使用。

```bash
chown [-R] user[:group] file_or_dir
```

其中，`-R`表示指定目录以及其子目录下的所有文件。`file_or_dir` 表示可以是文件或者目录，支持通配符，若有多个文件，用空格隔开。

命令`chgrp`修改文件或目录的所有群组。相当于`chown`的一部分功能。

```bash
chgrp [-R] file_or_dir
```

<br/>



### 限制root的SSH登陆

修改`SSH`配置文件`/etc/ssh/sshd_config`：

```bash
PermitRootLogin no
```

同时，还有一些配置，如：

```bash
//允许公钥登陆
PubkeyAuthentication yes
//公钥的存放路径
AuthorizedKeysFile .ssh/authorized_keys
//允许密码登陆
PasswordAuthentication yes
```

<br/>



### 启用公钥登陆

#### 创建公私钥

通过`ssh-keygen`来生成：

```bash
ssh-keygen [-f key_name]
```

如不指定文件名，在命令运行过程中，也会要求输入文件名的。

并且还会询问是否需要输入密码。若不设置，直接回车；若设置密码后，以后登录时，除了要指定私钥外，还需要指定密码。

#### 将公钥配置到指定位置

在上面`SSH`的配置中，有提到`AuthorizedKeysFile`，该配置就是用来指定具体的公钥文件。

```bash
ssh-copy-id -i pub_key_dir user@ip
```

该命令会把公钥内容复制到对应`user`的主目录下的`.ssh/authorized_keys`文件中。

还有一种方式，通过手动复制的方式。

将公钥复制到`user`的主目录下的`.ssh/`中，通过命令将公钥拷贝到文件`authorized_keys`中：

```bash
cat pub_key >> authorized_key
```

设置完成后，终端就可以通过秘钥登陆了。

<br/>



### 后台任务

#### 运行后台任务：

```bash
nohup command &
```

默认会输出日志到`nohup.out`文件中。

#### 查看后台任务：

```bash
jobs -l
```

查看当前shell后台运行的任务，`-l`显示任务进程号。

```bash
ps -aux
```

`-aux`显示所有包含其他使用者的进程。

#### 关闭后台任务

```bash
kill [-9] pid
```

`-9`表示彻底杀死进程，`pid`是任务的进程号。

<br/>



### 防火墙端口限制

如果有部署`HTTP`服务，还需要去防火墙中开发对应端口。