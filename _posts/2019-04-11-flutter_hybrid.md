---
layout: post
title: Flutter中的Hybrid Coding详解
tags: [Flutter]
---

### 混合编程的例子

在`Flutter`中，通过`MethodChannel`与原生通讯的。当创建一个插件项目时，生成的项目会自带`MethodChannel`的例子。

可以用`Android Stuido`创建一个插件项目，或者使用命令行创建：

```bash
flutter create --template=plugin -i swift -a kotlin hybrid_plugin
```

当项目创建完，可以看到生成的关键代码：

*lib/hybrid_plugin.dart*

```dart
import 'dart:async';

import 'package:flutter/services.dart';

class HybridPlugin {
  static const MethodChannel _channel =
      const MethodChannel('hybrid_plugin');

  static Future<String> get platformVersion async {
    final String version = await _channel.invokeMethod('getPlatformVersion');
    return version;
  }
}
```

*android/src/../hybrid_plugin/HybridPlugin.kt*

```kotlin
package com.example.hybrid_plugin

import io.flutter.plugin.common.MethodCall
import io.flutter.plugin.common.MethodChannel
import io.flutter.plugin.common.MethodChannel.MethodCallHandler
import io.flutter.plugin.common.MethodChannel.Result
import io.flutter.plugin.common.PluginRegistry.Registrar

class HybridPlugin: MethodCallHandler {
  companion object {
    @JvmStatic
    fun registerWith(registrar: Registrar) {
      val channel = MethodChannel(registrar.messenger(), "hybrid_plugin")
      channel.setMethodCallHandler(HybridPlugin())
    }
  }

  override fun onMethodCall(call: MethodCall, result: Result) {
    if (call.method == "getPlatformVersion") {
      result.success("Android ${android.os.Build.VERSION.RELEASE}")
    } else {
      result.notImplemented()
    }
  }
}
```

*ios/Classes/SwiftHybridPlugin.swift*

```swift
import Flutter
import UIKit

public class SwiftHybridPlugin: NSObject, FlutterPlugin {
  public static func register(with registrar: FlutterPluginRegistrar) {
    let channel = FlutterMethodChannel(name: "hybrid_plugin", binaryMessenger: registrar.messenger())
    let instance = SwiftHybridPlugin()
    registrar.addMethodCallDelegate(instance, channel: channel)
  }

  public func handle(_ call: FlutterMethodCall, result: @escaping FlutterResult) {
    result("iOS " + UIDevice.current.systemVersion)
  }
}

```

以上就是一个完整的`Flutter`与`android native` 、`iOS native`通讯的例子。下面来详细分析一下。

### Flutter中创建`MethodChannel`

在`Flutter`项目中，创建`MethodChannel`实例。并且要约定一个Channel名字，因为这个名字在`native`端需要用到。

创建一个`MethodChannel`实例：

```dart
static const MethodChannel _channel = const MethodChannel('hybrid_plugin');
```

然后，通过`MethodChannel.invokeMethod<T>`来调起原生中的方法。

`invokeMethod`方法接收两个入参：

- `String method`. 方法名（并非原生中的方法名，只相当于是一个Key）。原生端会根据不同的名字去调用不同的逻辑。
- `dynamic arguments`. 可选，入参。

返回一个`Future<T>`, 是原生端返回的内容`T`.

```dart
final String version = await _channel.invokeMethod('getPlatformVersion');
```

到此，`Flutter`端的工作就结束了。下面是原生端的代码实现。

### 原生端对接`MethodChannel`

以`Android`端为例，需要实现`MethodCallHandler` 接口。在上面的例子中，是创建了`HybridPlugin`类。

`MethodCallHandler`接口只有一个方法：

```java
public interface MethodCallHandler {
      void onMethodCall(MethodCall var1, MethodChannel.Result var2);
}
```

`onMethodCall`的第一个参数`MethodCall`, 有两个属性：

- `String method`
- `Object arguments`

这跟在`Flutter`中调用`inokeMethod`方法的入参完全一致。在`Flutter`端调用`inokeMethod`之后，原生端就会回调`onMethodCall`方法，并通过`MethodCall`将入参传递过来。

第二个参数`MethodChannel.Result`,  明显就是返回结果用的。它有三个方法：

它的定义如下：

```dart
public interface Result {
        void success(@Nullable Object var1);

        void error(String var1, @Nullable String var2, @Nullable Object var3);

        void notImplemented();
    }
```

`suceess`方法在成功时调用。接收一个参数，并会将值返回到`Flutter`中，即`inovkeMethod`的返回值。

`error`方法在失败时调用。接收三个参数，第一个参数是错误码；第二个参数是错误信息；第三个参数是错误详情。

`notImplemented`方法表示没有匹配到方法名。

从源码中能够看到一些本质, 下面是一部分源码：

```dart
private final class IncomingMethodCallHandler implements BinaryMessageHandler {
       //...
        public void onMessage(ByteBuffer message, final BinaryReply reply) {
			//...
            try {
                this.handler.onMethodCall(call, new MethodChannel.Result() {
                    public void success(Object result) {
                     	reply.reply(MethodChannel.this.codec.encodeSuccessEnvelope(result));
                    }

                    public void error(String errorCode, String errorMessage, Object errorDetails) {
                        	reply.reply(MethodChannel.this.codec.encodeErrorEnvelope(errorCode, errorMessage, errorDetails));
                    }

                    public void notImplemented() {
                        reply.reply((ByteBuffer)null);
                    }
                });
            } catch (RuntimeException var5) {
				//...
            }

        }
    }
```

其中，`BinaryReply.reply`方法只接收一个`ByteBuffer`, `BinaryReply`定义如下：

```java
public interface BinaryReply {
    void reply(ByteBuffer var1);
}
```

也就是说，不管是`success`、`error`、`notImplemented`, 都是返回的`ByteBuffer`。那`ByteBuffer`的内容是如何区分成功、失败数据的呢？

`StandardMethodCodec`是默认的`codec`，看下它的实现：

```dart
public final class StandardMethodCodec implements MethodCodec {
   //...
    public ByteBuffer encodeSuccessEnvelope(Object result) {
        ExposedByteArrayOutputStream stream = new ExposedByteArrayOutputStream();
        stream.write(0);
        this.messageCodec.writeValue(stream, result);
        ByteBuffer buffer = ByteBuffer.allocateDirect(stream.size());
        buffer.put(stream.buffer(), 0, stream.size());
        return buffer;
    }

    public ByteBuffer encodeErrorEnvelope(String errorCode, String errorMessage, Object errorDetails) {
        ExposedByteArrayOutputStream stream = new ExposedByteArrayOutputStream();
        stream.write(1);
        this.messageCodec.writeValue(stream, errorCode);
        this.messageCodec.writeValue(stream, errorMessage);
        this.messageCodec.writeValue(stream, errorDetails);
        ByteBuffer buffer = ByteBuffer.allocateDirect(stream.size());
        buffer.put(stream.buffer(), 0, stream.size());
        return buffer;
    }
    //...
}
```

如果是成功，先写入`0`，再写入返回的内容；如果是失败，先写入`1`，再分别写入错误信息。

源码分析部分到此结束，接着回到上面的分析中。

细心的同学可能注意到，在`android/HybridPlugin`中，还有一个静态方法：

```kotlin
companion object {
    @JvmStatic
    fun registerWith(registrar: Registrar) {
      val channel = MethodChannel(registrar.messenger(), "hybrid_plugin")
      channel.setMethodCallHandler(HybridPlugin())
    }
  }
```

它有两个作用：

- 将原生端与`Flutter`端连接起来。

  在上面`Flutter`端创建Channel时，有约定一个Channel名`hybrid_plugin`。这里就是通过这个Channel名来匹配对应的Channel。

- 框架会自动查找项目中的`registerWith(Registrar)`方法，并通过代码生成的方式生成`GeneratedPluginRegistrant`类。在该类中，会自动调用`HybridPlugin.register`方法注册。

对于第二个作用，可以看下`demo`中代码。我们在创建这个插件时，在根目录下会自动生成一个`example`项目，并且已经引入了这个插件。

*example/pubspec.yaml*:

```yaml
name: hybrid_plugin_example
description: Demonstrates how to use the hybrid_plugin plugin.

#...

dev_dependencies:
  flutter_test:
    sdk: flutter

  hybrid_plugin:
    path: ../
#...
```

在`example`项目中的`GeneratedPluginRegistrant`类：

```java
public final class GeneratedPluginRegistrant {
  public static void registerWith(PluginRegistry registry) {
    if (alreadyRegisteredWith(registry)) {
      return;
    }
    HybridPlugin.registerWith(registry.registrarFor("com.example.hybrid_plugin.HybridPlugin"));
  }

  //...
}
```

看到了吗？它已经调用`HybridPlugin.registerWith`去注册了。

而在`example`项目的`MainActivity`中，会调用`GeneratedPluginRegistrant.registerWith`:

```kotlin
class MainActivity: FlutterActivity() {
  override fun onCreate(savedInstanceState: Bundle?) {
    super.onCreate(savedInstanceState)
    GeneratedPluginRegistrant.registerWith(this)
  }
}
```

这样，我们写的插件就会自动注册完成。

<br/>

### 总结

开发一个混编插件，可以总结成下面几步：

- 约定协议。包括Channel名，方法名，方法入参与出参。
- 在`Flutter`端创建`MethodChanel`。根据业务逻辑，通过`MethodChannel.invokeMethod`实现调原生。
- 在原生端（以`Android`为例），实现`MethodCallHandler`接口。根据不同的方法名，调用不同的逻辑。
- 创建`registerWith`静态方法，通过Channel名将`MethodCallHandler`实现类绑定到`MethodChannel`中。

当然，如果只是项目中需要混编，而不想实现一个插件的话，可以直接在`MainActivty`中直接进行`MethodChannel`绑定，无需创建静态方法`registerWith`。

