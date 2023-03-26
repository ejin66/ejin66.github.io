---
layout: post
title: Dockerfile的基本使用
tags: [Docker]
---

### Dockerfile的作用

`Dockerfile`是用来生成`Docker`镜像的，它描述生成一个镜像所要的所有步骤，包括该镜像依赖的其他镜像、编译时需要运行的命令等。

然后，命令`docker build`会根据当前目录下的`Dockerfile`编译生成一个本地镜像。
```bash
# 默认查找指定path下的Dockerfile
docker build -t tagname .
```

<br/>

###  Dockerfile的详细介绍

首先，看一下`Dockerfile`的简单例子：

```dockerfile
FROM centos
ARG version
USER root
EXPOSE 80/tcp
VOLUME /home/work
WORKDIR /home/work
ADD config.yaml ./config/
RUN cd ./config
RUN ls
CMD echo version
```

生成镜像命令：

```bash
docker build --build-arg version=1.1.0 -t image_name .
```

运行镜像命令：

```bash
docker run -d -p 80:80 -v /local-path:/container-path --restart=always --name container_name image_name
```

详细介绍下`Dockerfile`中关键字的作用。

#### `FROM`

要生成的镜像需要依赖的其他镜像。如`FROM centos`, 指我的镜像需要基于`centos`系统，在`docker run`时会自动去[`Docker`官方镜像仓库](https://hub.docker.com)去拉取所需要的镜像。

格式：

```dockerfile
FROM <image>[:<tag>] [AS <name>]
```

#### `ARG`

设置一个或多个入参供后续步骤使用，然后在生成镜像时`docker build --build-arg key=value`传入。

它可以设置或者不设置默认值。若设置了默认值，且在`build`时没有传递该值，那就会直接去用该默认值。

格式：

```dockerfile
ARG <name>[=<default value>]
```

#### `USER`

指定在镜像中需要执行的命令由哪个用户执行，默认是`root`用户。这里用户指容器系统中的用户，与实体服务器无关。

格式：

```dockerfile
USER <user>[:<group>] or
USER <UID>[:<GID>]
```

#### `EXPOSE`

指出容器对外暴露的端口、协议。然后，在`docker run`时通过`-p`指定外部端口与容器端口的映射关系，如将外部的8081端口映射到容器的80端口：

```bash
docker run -p 8081:80 image_name
```

若`docker run -P`这种写法的话，无需特殊指定映射关系，会自动将`Dockerfile`中`EXPOSE`的所有端口映射到主机的随机高阶端口，可通过`docker port image_name`查看。

格式：

```dockerfile
EXPOSE <port> [<port>/<protocol>...]
```

#### `VOLUME`

VOLUME指令创建具有指定名称的安装点，并将其标记为从本机主机或其他容器保存外部安装的卷。

在`docker run`时 通过`-v /local_path:/container_path`，将本地路径与容器路径做映射。

格式：

```dockerfile
VOLUME ["/data"]
```

#### `WORKDIR`

指定`Dockerfile`中相关命令执行的工作路径。所有的相对路径都是基于这个`WORKDIR`，且可以多册设置`WORKDIR`。

格式：

```dockerfile
WORKDIR /path/to/workdir
```

#### `ADD`

将外部的文件拷贝到镜像中，如本机中的文件、远端的文件等，并且会将自动将压缩文件解压。而`COPY`只能做本地的纯拷贝工作。

格式：

```dockerfile
ADD [--chown=<user>:<group>] <src>... <dest>
```

其中，`src`需要是相对路径，即运行`docker build`的本地路径。

#### `RUN`

指定在编译生成镜像时需要执行的一些命令。可以设置多个`RUN`命令。

格式：

```dockerfile
RUN <command>
RUN ["executable", "param1", "param2"]
```

#### `CMD`

`CMD`指定容器运行起来后要执行的命令。只能设置一个`CMD`，若设置了多个，只有最后一个`CMD`会生效。

格式：

```dockerfile
CMD ["executable","param1","param2"] (exec form, this is the preferred form)
CMD ["param1","param2"] (as default parameters to ENTRYPOINT)
CMD command param1 param2 (shell form)
```



