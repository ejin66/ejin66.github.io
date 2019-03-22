---
layout: post
title: Dart中Future、Zone、Timer的源码学习
tags: [Dart]
---

### `Future`

`Dart`是以事件驱动的单队列模型，借助`Future`能够向队列中添加事件以执行。这里讨论以下两种生成`Future`的方式：

- `factory Future(FutureOr<T> computation())`
- `factory Future.delayed(Duration duration, [FutureOr<T> computation()])`

首先看下两种方式的源码：

```dart
factory Future(FutureOr<T> computation()) {
    _Future<T> result = new _Future<T>();
    Timer.run(() {
      try {
        result._complete(computation());
      } catch (e, s) {
        _completeWithErrorCallback(result, e, s);
      }
    });
    return result;
}

factory Future.delayed(Duration duration, [FutureOr<T> computation()]) {
    _Future<T> result = new _Future<T>();
    new Timer(duration, () {
      if (computation == null) {
        result._complete(null);
      } else {
        try {
          result._complete(computation());
        } catch (e, s) {
          _completeWithErrorCallback(result, e, s);
        }
      }
    });
    return result;
}
```

第一种方式下，直接调用的是`Timer.run`, 它本质是去创建一个`Timer`：

```dart
static void run(void callback()) {
    new Timer(Duration.zero, callback);
}
```

可以得到一个简单的结论：这两种方式本质是一样的，前者只是后者的简化。如：

```dart
Future(() {
    print("test");
});
//等于
Future.delayed(Duration.zero, () {
    print("test");
});
```



### `Future.delayed`

聚焦于`Future.delayed`。

看源码，首先是实例出一个`_Future`，它的源码片段：

```dart
class _Future<T> implements Future<T> {
    
    final Zone _zone;
    //...
    _Future() : _zone = Zone.current;
    //...
    
}
```

构造函数里啥都没做，只是初始化了一下`_zone`。`Zone`类似于`Java`中的`ThreadLocal`,  它的作用除了可以存储外，最主要的作用是提供一个异步代码调用的环境。

接着，实例化`Timer`，源码片段：

```dart
factory Timer(Duration duration, void callback()) {
    if (Zone.current == Zone.root) {
      // No need to bind the callback. We know that the root's timer will
      // be invoked in the root zone.
      return Zone.current.createTimer(duration, callback);
    }
    return Zone.current
        .createTimer(duration, Zone.current.bindCallbackGuarded(callback));
}
```

这里又是`Zone`相关的代码。我们先跳过，只需大致了解这里会创建一个`Timer`，在`duration`时间之后，会调用`callback()`。

传入`Timer`的`callback()`逻辑中，最主要的逻辑是：

```dart
result._complete(computation());
```

跟进去：

```dart
void _complete(FutureOr<T> value) {
    assert(!_isComplete);
    if (value is Future<T>) {
      if (value is _Future<T>) {
        _chainCoreFuture(value, this);
      } else {
        _chainForeignFuture(value, this);
      }
    } else {
      _FutureListener listeners = _removeListeners();
      _setValue(value);
      _propagateToListeners(this, listeners);
    }
}
```

> `FutureOr<T> value` 表示 `value` 的类型可能是`Future<T>`或者`T`

如果是`T`，直接完成并通知；如果是`Future<T>`会等待这个`Future`结束，如此链式下去。

看个例子：

```dart
main() {
  Future(() {
    print("outside");
    return Future.delayed(Duration(seconds: 2), () {
      print("inside");
    }).whenComplete(() {
      print("inside completed");
    });
  }).whenComplete(() {
    print("outside completed");
  });
}
```

打印如下：

```bash
outside
inside
inside completed
outside completed
```

简单的结论：`Future`只有等它的所有嵌套子`Future`完成之后，它才算完成。

最后，`Future.delay`返回了最开始创建的`_Future`实例。

总结下`Future.delay`的流程：

- 创建`_Future`实例：`result`。

- 创建延时为`duration`的`Timer`。并在时间到达时，判断`computation`的返回类型：直接结束或者链式调用下去。

- 返回`_Future`实例。


### `Zone`

整体流程梳理完之后，跳回去看`Timer`的实现。不过在这之前，先来了解下`Zone`。

上面有提到`Zone`的作用是提供一个异步代码调用的环境。这比较难理解，看一个例子：

```dart
main() {
  runZoned(() {
    print("test");
  }, zoneSpecification: ZoneSpecification(
    print: (self, parent, zone, s) {
      parent.print(zone, "hook it: $s");
    }
  )); 
}
```

打印如下：

```bash
hook it: test
```

这个例子显示了如何利用`Zone`去hook `print`函数。

具体的做法就是通过`runZoned`创建了一个自定义的`Zone`，并让我们的代码运行在这个`Zone`的环境下。

`Zone`的部分源码：

```dart
abstract class Zone {
    //...
    
    static const Zone root = _rootZone;
    
    static Zone _current = _rootZone;
   
    Zone get parent;
    
    Zone fork({ZoneSpecification specification, Map zoneValues});
    
    Timer createTimer(Duration duration, void callback());
    
    void print(String line);
    
    ZoneCallback<R> registerCallback<R>(R callback());
    //...
}
```

三个变量：

- `static const Zone root`
- `static Zone  _current`
- `Zone parent`

其中，`root`是常量，表示最根部的`Zone`。`main()`函数的环境就是`Zone root`。默认下`_current`与`root`一致。

`parent`若是`null`, 说明该`zone`就是`root`；其他的`zone`的`parent`都不会是`null`。要想创建一个新的`Zone`，只能通过现有的`zone`去`fork`一份，新`fork`出来的`zone`的`parent`就是调用`fork`的`zone`。

可以得到一个结论：`root`是其他所有`zone`的祖先。

`fork`方法通过`specification`来`hook` parent 的相关功能方法，来达到不一样的效果；第二个参数`Map zoneValues`可以理解为新`fork`出来的`zone`的环境变量，通过`zone[key]`来存取。

`createTimer`方法是不是感觉很熟悉？`Future`最后就是调用到这里。

`print`方法跟我们平常调用的`print()`方法有什么联系呢？

看下平常调用的`print()`源码：

```dart
void print(Object object) {
  String line = "$object";
  if (printToZone == null) {
    printToConsole(line);
  } else {
    printToZone(line);
  }
}
```

而在`fork zone`时，设置了`printToZone`:

```dart
Zone _rootFork(Zone self, ZoneDelegate parent, Zone zone,
    ZoneSpecification specification, Map zoneValues) {
  //...
  printToZone = _printToZone;
  //...
}

void _printToZone(String line) {
  Zone.current.print(line);
}
```

常用的`print()`方法最后调用的是`Zone.current.print(line)`，这两个方法就关联了起来。看到这里，对上面`hook print`的例子是不是理解到了？

`registerCallback` 的主要作用简单理解就是去wrap 原来的`callback`。

`Zone`的继承结构：

- `Zone `
  - `_Zone `
    -  `_RootZone`
    -  `_CustomZone`

`_Zone`的源码很简单：

```dart
abstract class _Zone implements Zone {
  const _Zone();

  // TODO(floitsch): the types of the `_ZoneFunction`s should have a type for
  // all fields.
  _ZoneFunction<Function> get _run;
  _ZoneFunction<Function> get _runUnary;
  _ZoneFunction<Function> get _runBinary;
  _ZoneFunction<Function> get _registerCallback;
  _ZoneFunction<Function> get _registerUnaryCallback;
  _ZoneFunction<Function> get _registerBinaryCallback;
  _ZoneFunction<ErrorCallbackHandler> get _errorCallback;
  _ZoneFunction<ScheduleMicrotaskHandler> get _scheduleMicrotask;
  _ZoneFunction<CreateTimerHandler> get _createTimer;
  _ZoneFunction<CreatePeriodicTimerHandler> get _createPeriodicTimer;
  _ZoneFunction<PrintHandler> get _print;
  _ZoneFunction<ForkHandler> get _fork;
  _ZoneFunction<HandleUncaughtErrorHandler> get _handleUncaughtError;
  _Zone get parent;
  ZoneDelegate get _delegate;
  Map get _map;

  bool inSameErrorZone(Zone otherZone) {
    return identical(this, otherZone) ||
        identical(errorZone, otherZone.errorZone);
  }
}
```

`_ZoneFunction<T>`的定义如下:

```dart
class _ZoneFunction<T extends Function> {
  final _Zone zone;
  final T function;
  const _ZoneFunction(this.zone, this.function);
}
```

`_Zone`不仅没帮`Zone`实现任何功能，相反还搞出一堆`_ZoneFunction<T>`，有点猪队友的赶脚。那真相是啥？真相是真香，`_Zone`定义的这些变量是实现`hook`的基础啊。

来看下`_CustomZone`的源码片段：

```dart
class _CustomZone extends _Zone { 
    
    //...
    
	_CustomZone(this.parent, ZoneSpecification specification, this._map) {
        //...
        _print = (specification.print != null)
            ? new _ZoneFunction<PrintHandler>(this, specification.print)
            : parent._print;
        //...
    }

	//...
    
    void print(String line) {
        var implementation = this._print;
        assert(implementation != null);
        ZoneDelegate parentDelegate = _parentDelegate(implementation.zone);
        PrintHandler handler = implementation.function;
        return handler(implementation.zone, parentDelegate, this, line);
    }

	//...
}

```

以`print` 为例，创建`_CustomZone`时传入了`ZoneSpecification`， 若`ZoneSpecification` 有`print`的定义，就把该执行逻辑保存到`_print`里面。当调用`Zone.print`时在从`_print`中拿出来。

`Zone`的内容还有很多，这里就先打住，以后有时间在继续探索。



### `Timer`

看完`Zone`, 在回头看看`Timer`。在贴一遍源码：

```dart
factory Timer(Duration duration, void callback()) {
    if (Zone.current == Zone.root) {
      // No need to bind the callback. We know that the root's timer will
      // be invoked in the root zone.
      return Zone.current.createTimer(duration, callback);
    }
    return Zone.current
        .createTimer(duration, Zone.current.bindCallbackGuarded(callback));
  }
```

来理解一下：

- 若`current`与`root`一致，直接调用`createTimer`
- 否则，先`bindCallbackGuarded`, 然后在`createTimer`

差别很明显，在于要不要`bindCallbackGuarded`? 

原因也很简单，如果是`root`, 它的`registerCallback`默认是没有加逻辑的，具体可以看`_RootZone`源码，你传入什么就传出什么，因此不需要去wrap `callback`； 而如果`current zone` 不是`root zone`的环境，那它的`registerCallback`是有可能有新的实现，顾需要先调用`bindCallbackGuarded`去wrap一下。

最后有点小疑惑，`Zone.createTimer`最后回到了`Timer._createTimer`中，相关源码如下：

```dart
abstract class Timer {
    
    //...
    external static Timer _createTimer(Duration duration, void callback());
    //...
    
}
```

跟到最后也没有`_createTimer`的具体实现。而关键字`external` 也不是很懂，感觉像是要让外部提供该实现。



### Demo代码

关于`Zone`、`Timer`、`Event-Loop`的关系，该demo可以帮助理解：

[https://github.com/dart-archive/www.dartlang.org/blob/master/src/tests/site/articles/zones/task_interceptor.dart](https://github.com/dart-archive/www.dartlang.org/blob/master/src/tests/site/articles/zones/task_interceptor.dart)