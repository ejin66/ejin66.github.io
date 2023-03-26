---
layout: post
title: android反编译以及二次打包
tags: [Android]
---

### 工具准备

- `apktool`. 直接去[官网](https://ibotpeaches.github.io/Apktool/)下载。
  - apktool.jar
  - apktool.bat
- dex2jar.jar. 将dex文件转成jar文件。
- jd-gui.exe. 通过图形化的界面展示jar文件。

<br/>

### 反编译apk

**第一步**， 解压apk包。

**第二步**，将classes.dex转成jar。

```bash
d2j-dex2har dex_path
```

**第三步**，在`jd-gui`中打开第二步生成的jar文件。

<br/>

### 二次打包

**第一步**，通过`apktool`直接解析apk文件。

```bash
apktool d apk_path -o output_path
```

**第二步**，修改`smali`文件。在第一步中，会将dex转成`smali`格式的代码。对于`smali`语言不太熟悉的话，可以先反编译，找到对应的类以及方法后，再去`smali`文件夹中直接定位并修改。

**第三步**，重新打包。

```bash
apktool b old_output_path -o new_apk_path
```

**第四部**，签名。因为第三步中生成的apk包是没有签名的，无法安装到手机中，需要手动对它进行签名。`JRE`自带签名工具，地址`%jre_path%/bin`下。

```bash
jarsigner -keystore keystore_path unsign.apk keystore_alias
```



