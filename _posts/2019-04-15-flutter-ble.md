---
layout: post
title: Flutter中的BLE蓝牙
tags: [Flutter, Bluetooth]
---

### 蓝牙的简单知识

蓝牙，根据不同的版本，可以分成：

- 经典蓝牙，蓝牙版本2.1
- 高速蓝牙，蓝牙版本3.0
- 低功耗蓝牙，蓝牙版本4.0

最普及的，也是人们认知的蓝牙，一般是经典蓝牙。高速蓝牙，是在经典蓝牙的基础上，提升了传输速率。而低功耗蓝牙，区别于经典蓝牙，最大的特点是低功耗，副作用是传输速率慢。

今天的主题是低功耗蓝牙(Bluetooth Low Energy)，简称`BLE`。

### GATT协议

`BLE`基于`GATT`(Generic Attribute Profile)协议。`GATT`协议中有三个概念：

- 服务（Service）
- 特征（Characteristic）
- 描述（Descriptor）

一个设备中，有一个`Profile`配置文件，可以配置多个`Service`。一个`Service`中可以包含多个`Characteristic`。一个`Characteristic`中可以包含多个`Descriptor`。

关系如下图所示：

![关系图]({{site.baseurl}}/assets/img/pexels/gattStructure.png)

每个`Service`都有一个唯一标识UUID。`Service`的UUID有两个长度：

- 16Bit. 官方认证的UUID.
- 128Bit. 自定义的UUID.

每个`Characteristic`也有一个唯一的UUID标识。它是`BLE`通讯中的最小单元，通过它可以读取数据或者写入数据。

### GAP协议

`BLE`的整个通讯流程，包括：广播、扫描、连接，都是基于`Gap`(Generic Access Profile)协议。在`Gap`协议中，定义了两种角色：

- 外围设备（Peripheral）
- 中心设备（Central）

`Peripheral`一般是低功耗设备，用来提供数据的一方，如智能手表。

`Central`一般用来连接外围设备，处理数据的一方，如我们常用的手机。

外围设备会定期向外广播数据。广播数据又分成两种：

- `Advertising Data Payload`, 广播数据
- `Scan Response data Payload`， 扫描回复数据

每种广播数据的最大长度是31Byte。其中，广播数据(`Advertising Data Payload`)是必须的，只有不停的广播数据才能被中心设备发现。而扫描回复数据是可选的，它包含了设备的名字等信息。

一旦中心设备与外围设备连接之后，就会停止发送广播数据。断开之后，会恢复并继续发送广播数据。

广播的流程图：

![广播流程图]({{site.baseurl}}/assets/img/pexels/gap_advertising.png)

### BLE on Flutter

`Flutter`自身无法提供关于蓝牙的api，只能通过混编的方式去调`native`层。第三方库`flutter-blue`就是这样做的，项目地址：[flutter-blue](https://github.com/pauldemarco/flutter_blue).

#### 扫描设备

```dart
StreamSubscription ss = FlutterBlue.instance.scan().listen((scanResult) {
    //...
});

//停止扫描
ss.cancel();
```

#### 连接设备

```dart
StreamSubscription connection = FlutterBlue.instance.connect(device).listen((s) {
    if (s == BluetoothDeviceState.connected) {
        //...
    }
});

//断开连接
connection.cancel();
```

#### 扫描服务

```dart
device.discoverServices().then((list) {
    //...
});
```

> `discoverServices`方法需要在连接上之后调用

#### 读取数据

```dart
device.readCharacteristic(characteristic)
```

#### 写入数据

```dart
device.writeCharacteristic(characteristic, value)
```

