---
layout: post
title: Flutter 下常用命令
tags: [Flutter]
---



### 1. 直接安装运行

> flutter run [--debug/release]
>
> 默认是debug

<br/>



### 2. 打包生成APK

> flutter build apk [--debug/release] [--split-per-abi] [--target-platform=android-arm64]
>
> 默认是release。
> 
> split-per-abi表示生成对应abi的几种包。不加的话是一个fat apk（实际中这种apk会报错，报找不到flutter.so）。

<br/>



### 3. 安装APK

> flutter install
>
> 该命令是安装release包



### 4. 获取第三方包

>flutter packages get

一开始使用的时候，会经常出现 `Got socket error trying to find package at http://pub.dartlang.org` 错误，设置各种代理都不行。最后发现，在官网上使用教程的开头，就已经提醒过了：

> *If you’re in China, please read [this wiki article](https://github.com/flutter/flutter/wiki/Using-Flutter-in-China) first*. 

通过设置PUB镜像来解决墙的问题。
