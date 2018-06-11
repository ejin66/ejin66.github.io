---
layout: post
title: React Native上手整理
tags: [ReactNative]
---



#### 1. React Native开发环境搭建，参考： [搭建React Native开发环境（中文版）](http://reactnative.cn/docs/0.51/getting-started.html) 

#### 2. 常用命令

```
react-native init AwesomeProject //初始化一个项目，生成项目模板
react-native run-android //cd 到项目目录，运行android项目，默认会启动服务器
react-native start --port 8888 //启动node服务器，若没有设置port, 默认8081
react-native log-android //查看log日志
adb reverse tcp:8081 tcp:8888 //真机调试时，将手机8081端口转到电脑8888端口上
```

#### 3. 问题：

1. 1. unable to load script from assets ‘index.android bundle’  ,make sure  your bundle is packaged correctly or youu’re runing a packager server

   2. 1. 在Android/app/src/main目录下创建一个空的assets文件夹

      2. 在项目根目录下运行：

      3. 1. ```
            react-native bundle --platform android --dev false --entry-file index.js 
            --bundle-output android/app/src/main/assets/index.android.bundle 
            --assets-dest android/app/src/main/res
            ```

      4. 重新运行项目

   3. could not connect to development sever

   4. 1. node服务器默认的端口8081有可能被占用，通过浏览器访问localhost:port来判断服务器是否正常
      2. 通过命令来更改port
      3. 在手机的Dev Settings/Debug server host & port device 中，设置ip:port；若是模拟器，通过点击menu进入，ip为virtual box的ip

2. #### 4. reload js

3. 1. 模拟器可双击R
   2. 手机通过摇一摇，点击Reload
