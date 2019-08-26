---
layout: post
title: Docker基础命令
tags: [Docker]
---



### 整理Docker基本命令

列出本地的镜像

```bash
docker images
#or
docker image ls
```

基于本地镜像创建一个新的镜像

```bash
docker tag source_image target_image
```

运行镜像

```bash
#--name 表示设置运行的容器别名; -p 端口映射，前面的服务器端口，后面是容器端口; -d 表示后台运行
docker run [--name name] [-p 8080:800] [-d] image-name
```

查看正在运行的容器

```bash
# -a 表示查看所有容器
docker ps [-a]
#or
docker container ls [-a]
```

停止运行容器

```bash
docker stop containerId
```

删除容器

```bash
docker rm containerId
```

强制停止并移除

```bash
docker rm -f containerId
```

删除本地镜像

```bash
docker rmi imageId
```

build项目，生成镜像

```bash
# -t 表示设置镜像名, '.'表示当前目录
# 根据当前目录下的Dockerfile, 生成镜像
docker build -t image_name . 
```

查看日志

```bash
# -f 表示一直刷新
docker logs [-f] containerId
```

进入容器系统

```bash
docker exec -it containerId bash
```

不进入系统，运行命令直接返回

```bash
docker exec containerId commnad
```

导出镜像

```bash
docker save [-o/> local_file] images

#example
docker save -o helloworld.tar helloworld:latest
```

加载镜像文件

```bash
docker load [-i/< local_file]

#example
docker load -i helloworld.tar
```

导出容器

```bash
docker export [-o local_fle] container

#example
docker export -o container_name.tar container_name
```

导入容器

```bash
docker import container_name.tar container_name
```

docker compose

```bash
# 根据本目录下的compose file运行。可同时起多个镜像，并设置彼此的依赖关系等。
docker-compose up -d
docker-compose down
```

查找镜像

```bash
docker search registry
```

拉取镜像

```bash
docker pull registry
```

上传镜像

```bash
docker push registry
```

`Docker file`

> 在`Dockerfile`中，`CMD`、`ENTRYPOINT`都只有一个，且`CMD`会被最后一个替换，这两个都是在容器运行时运行。RUN是在生成镜像时运行。

本地仓库

```bash
#1 拉取仓库镜像
docker pull registry
#2 启动仓库镜像
docker run -d -p 5000:5000 -v /backup_path:/var/lib/registry registry
#3 将需要保存到本地的镜像，改名称以本地镜像地址起头
docker tag origin_image 127.0.0.1:5000/local_registry_image
#4 保存到本地
docker push 127.0.0.1:5000/local_registry_image
```

第4步中push可能会失败，因为本地启动的registry服务不是安全可信赖的。需要去修改docker的配置文件：`/etc/default/docker`,添加`--insecure-registry`:

```yaml
DOCKER_OPTS="--registry-mirror=http://hub-mirror.c.163.com --insecure-registry 127.0.0.1:5000"
```

宿主文件导入到容器

```bash
docker cp local_path containerId:[container_path]

# example 将sql文件导入到容器，并允许
docker cp ./test.sql mysql:/tmp/test.sql
# 进入到容器，进入mysql后,执行sql文件
source /tmp/test.sql
```

容器文件导出到宿主

```bash
docker cp containerId:[container_path] local_path
```