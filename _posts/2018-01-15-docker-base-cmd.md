---
layout: post
title: Docker基础命令
tags: [Docker]
---





*相关命令：*

```cmd
docker image ls //列出已经下载到本地的镜像
docker run [-d] [-p 4000:80] image-name //运行镜像, -d表示后台运行，-p 4000:80 将服务器4000端口映射到容器的80端口
docker container ls //列出正在运行的容器
docker container ls -all //列出所有的容器
docker container ls -a -q //列出所有的容器， in quiet mode
docker build -t friendlyhello .  //build项目，生成image。  -t tag , 'friendlyhello'表示 tag name, '.'表示当前目录
docker stop containerId //停止运行容器
docker rm containerId //删除容器
docker rmi imageId //删除本地镜像

docker search registry //查找镜像
docker pull registry //下载镜像
docker push registry //上传镜像
```

