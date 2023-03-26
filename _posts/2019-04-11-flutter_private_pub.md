---
layout: post
title: 搭建Flutter的私有包管理仓库
tags: [Flutter]
---

### `pub_server`

官方有开源一个私有仓库项目: [pub_server](https://github.com/v7lin/simple_pub_server)。它可以帮助我们在本地起一个私有仓库服务。具体的命令可以查看该项目说明。

但为了能够一劳永逸，可以把它做成Docker镜像。`Github`上已经有人实现了这种方式：[simple_pub_server](https://github.com/v7lin/simple_pub_server)。

<br/>

### `simple_pub_server`

该项目将`pub_server`做成了镜像，提供了一份`docker-compose`文件。

完整的流程如下：

**第一步**，部署服务。将`docker-compose`拷贝到服务器某目录下：

```bash
docker-compose up
```

**第二步**，发布`flutter package/plugin`。发布命令：

```bash
flutter packages pub publish --server http://${your pub_server domain}
```
`pub publish`命令需要`google`认证。所以，尽管是发布到私人仓库，仍然需要走`google`账号认证。参照正常的发布流程：[如何发布一个Flutter插件](https://ejin66.github.io/2019/03/25/flutter_publish_plugin.html)。

**第三步**，从私有仓库引入包或插件。

`Flutter`的`pubspec.yaml`支持多种包引入方式。如最简单的`pub.dartlang.org`、本地包引入、`Git`项目引入、私人仓库引入，具体可以查看官方文档: [Pub Dependencies](https://www.dartlang.org/tools/pub/dependencies)。

这里，使用私人仓库引入的方式：

```yaml
test:
  hosted:
    name: test # name of your package/plugin
    url: http://${your pub_server domain}
  version: ^0.0.1
```

有个注意点：`url`地址最后不能以`/`结尾。像这种写法：`http://${your pub_server domain}/`, 会导致`flutter packages get`获取不到相应的包。

<br/>

### 结束

一切顺利的话，就能够正常的从私有仓库`publish`/`get`了。唯一的缺点是没有展示页面，无法直观的看到已有的项目以及版本。



