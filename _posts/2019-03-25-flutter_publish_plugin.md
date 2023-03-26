---
layout: post
title: 如何发布一个Flutter插件
tags: [Flutter]
---

`Flutter`插件发布的步骤：

- 创建插件项目。

  ```bash
  flutter create --template=plugin [-i swift] [-a kotlin] plugin_name
  ```

  `--template=plugin` 表示要创建一个插件，若`=package`则表示要创建一个项目。

  `-i swift`表示插件中的`iOS`使用`swift`开发。

  `-a kotlin`表示插件中的`android`使用`koltin`开发。

- 插件功能实现。

- 检查`pubspec.yaml`、`README.md`、`CHANGELOG.md`。

  `pubspec.yaml`中需要配置`author`、`homepage`，否则不能发布。

- 预发布。

  ```bash
  flutter packages pub publish --dry-run
  ```

  使用`--dry-run`在发布前检查分析插件代码，使我们能够及时优化。

- 发布插件。

  ```bash
  flutter packages pub publish --server=https://pub.dartlang.org
  ```

  将插件发布到`pub.dartlang.org`，需要提前准备一个`google`账号，一个梯子。

  具体步骤如下：

  - `windows`下设置`Dos`代理：

    ```bash
    set http_proxy=http://127.0.0.1:1080
    set https_proxy=http://127.0.0.1:1080
    ```

  - 运行命令。

    该命令运行后，会返回一个`account.google.com`域名的地址并等待验证，复制到浏览器并打开，输入`google`账号，正常情况下会跳转到`pub.dartlang.org`，表示验证通过。项目就能够正常发布了。
    
  - 打开`pub.dartlang.org`，输入插件名字搜索，就能看到刚发布的插件了。
