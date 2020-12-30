---
layout: post
title: Linux下打包Flutter应用以及GitLab-Runner的简单介绍
tags: [Flutter, GitLab]
---

### Linux下打包Flutter应用

本该没啥好说的，安装完环境，通过命令`Flutter build apk`的命令就可以了。但是由于墙的原因，导致在服务器上安装Flutter环境变得困难。

1. 安装Flutter环境。按照[官网]([https://flutter.dev](https://flutter.dev/))(或[中文镜像网站](https://flutter.cn/docs/get-started/install/linux))的安装指南，一步步下来就可以了。

2. 设置Flutter环境变量。由于中国网络的原因，无法直接从Pub仓库下载。需要设置镜像：

   ```bash
   export PUB_HOSTED_URL=https://pub.flutter-io.cn
    export FLUTTER_STORAGE_BASE_URL=https://storage.flutter-io.cn
   ```

3. 安装Java环境。通过软件管理工具（apt、yum）等都可以。

4. 安装Android SDK。这就比较麻烦了，下载SDK需要翻墙，具体步骤：

   - 下载android tools。在[android中文网站](https://developer.android.google.cn/studio?hl=en)下载Command line tools。

   - 使用`./tools/bin/sdkmanager`下载SDK。由于公司服务器不能翻墙，下载SDK又没有可用的代理。我是通过在本地翻墙下载好Linux版SDK，再传到服务器的方法。我本地是windows电脑，`sdkmanager`下载LinuxSDK，需要先设置环境变量`REPO_OS_OVERRIDE=linux`，告诉它我们要下载哪个环境的SDK，接着通过命令：

     ```bash
     sdkmanager "platforms;android-29" --proxy=http --proxy_host=127.0.0.1 --proxy_port=9999
     ```

     这里还有个插曲，刚开始用命令`sdkmanager`时报错，后发现是本地的JAVA版本太高，可以在当前命令行中设置临时的`JAVA_HOME`环境变量，路径指向Android Studio中配置的JAVA 环境就可以了。
   
5. 环境设置好后，通过命令`flutter build apk`的时候，会卡在`gradlew assembleRelease`下。直接进到项目的`./android`目录下，直接运行`gradlew assembleRelease --debug`打包，发现它一直尝试从`dl.google.com`谷歌仓库拉库，这导致打包命令超时最后失败。Flutter项目中，使用了很多第三方库，而其中的很多库都有各自的`build.gradle`，不建议直接改第三方库中的代码以及库很多的时候改起来也很困难。可以在根目录下的build.gradle中添加如下代码：

   ```bash
     allprojects { project ->
          project.getBuildscript().repositories {
              google { url "https://maven.aliyun.com/repository/google"}
              jcenter { url "https://maven.aliyun.com/repository/jcenter"}
          }
          repositories {
              google { url "https://maven.aliyun.com/repository/google"}
              jcenter { url "https://maven.aliyun.com/repository/jcenter"}
          }
     }
     ```
   
     将所有的Project以及Project.ScriptHandler中的repositories都添加了这两个代理的仓库，这样的话就解决了网络不通的问题。
     
     还有一点，由于第三方库很多，每个库中的`compileSdkVersion`都不一样，可以通过在根`build.gradle`中加入脚本：
   
     ```groovy
     subprojects {
         afterEvaluate {project ->
             if (project.hasProperty("android")) {
                 android {
                     compileSdkVersion 28
                 }
             }
         }
     }
     ```
### GitLab-Runner的简单使用

参照[官网安装指南](https://docs.gitlab.com/runner/install/linux-manually.html), 一步步安装就好了。安装完成后，通过命令`gitlab-runner register`命令注册，将我们搭建的`Gitlab`实例中共享Runner指定的URL以及令牌注册进来。指定相应的项目支持该Runner后，项目的`CI/CD`中就能看到Runner了。
在项目的根目录下创建`.gitlab-ci.yml`文件后，每一个的push，都会触发一个流水线作业，具体执行的操作就是配置在`.gitlab-ci.yml`文件中的。看一个简单的脚本文件：

```yaml
before_script:
  - echo "before project"
    
main-run-script:
  script:
    - uname -a
    
after_script:
  - echo "after project"

```

这只是最简单的示例，其他更多的复杂的内容，还要继续学习。
