---
layout: post
title: 利用ADB+SCRCPY实现手机画面无线投屏
tags: [Android]
---

利用`ADB WIFI Connect` + [`Scrcpy`](https://github.com/Genymobile/scrcpy)来实现手机画面的无线投屏。`Scrcpy`是`genymobile`开源的利用`ADB`工具实现画面投屏的项目。

手机画面无线投屏的具体步骤如下：

1. 手机打开调试模式，有线连接到电脑

2. 将手机与电脑连接到同一个网络中（可以打开手机热点，电脑连接上）

3. 通过`ADB`设置手机打开`tcpip`监听端口

   ```bash
   adb tcpip 5555
   ```

4. 通过`ADB`查看局域网中手机端ip

   ```bash
   adb shell ifconfig
   ```

5. 通过`ADB`无线连接到手机

   ```bash
   adb connect device-ip:5555
   ```

   提示成功之后，就可以拔掉数据线了。其实，在知道手机ip之后，就可以拔掉数据线了。

6. 双击`scrcpy.exe`或者命令行敲`scrcpy`来开启无线投屏。

   > 需要注意的是，`ADB.exe`需要使用开源项目中自带的。如果在本地的环境变量中有设置`ADB` 路径, `scrcpy.exe`会默认用该配置的`ADB`，这可能会导致投屏失败。此时，移除`ADB`环境变量配置即可。