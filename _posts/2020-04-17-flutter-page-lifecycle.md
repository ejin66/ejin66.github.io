---
layout: post
title: Flutter页面的生命周期
tags: ["Flutter"]
---

### 结论

先上结果，Flutter页面的生命周期：

**一个常规的StatefulWidget的生命周期**

*page create* -> *page state create* -> *page state init* -> *page state didChangeDependencies* -> *page state build* -> {history pages} build -> .....-> *page state dispose* -> {history pages} build

> 比较诡异的是，不管是push新页面，还是pop当前页，history pages(不在栈顶的页面)都会重新build一下。为什么会这样，后面会详细说明。

**启动app时，home page的生命周期**

*page create* -> *page state create* -> *page state init* -> *page state didChangeDependencies* -> *page state build* -> *page home create* -> *page state didUpdateWidget* -> *page state build*

> 启动页page被创建了两次，而相应的state却没有，很明显地体现出了widget与element的差距了。

**state.didUpdateWidget何时被调用**

在调用了`setState`或者其他的情况，导致页面刷新时，某个子节点`State`的直接父节点会调用`updateChild`方法，判断该`State`是否能被复用。如可以，会调用`State.update`方法，该方法会调用`state.didUpdateWidget`.

**state.didChangeDependencies何时被调用**

1. 一个新页面会push出来时，会调用到该方法

2. 当屏幕方向改变或者语言发生变化时，会调用该方法。(针对这种情况，下面有详细说明。)

   

### 实验

#### 页面push & pop

本次实验的页面结构：*homepage -> statefulpage 1 -> statefulpage2 -> statefulpage3*. 

1. 首先，启动app, 进入*homepage*

```bash
I/flutter (20505): Page Home create
I/flutter (20505): Page Home state create
I/flutter (20505): Page Home initState
I/flutter (20505): Page Home didChangeDependencies
I/flutter (20505): Page Home build
I/flutter (20505): Page Home create
I/flutter (20505): Page Home didUpdateWidget
I/flutter (20505): Page Home build
```

2. 从*homepage* push 到 *statefulpage 1*

```bash
I/flutter (20505): Page StatefulWidget 1 create
I/flutter (20505): Page StatefulWidget 1 state create
I/flutter (20505): Page StatefulWidget 1 initState
I/flutter (20505): Page StatefulWidget 1 didChangeDependencies
I/flutter (20505): Page StatefulWidget 1 build
I/flutter (20505): Page Home build
```

3. 从*statefulpage 1* push 到 *statefulpage 2*

```bash
I/flutter (20505): Page StatefulWidget 2 create
I/flutter (20505): Page StatefulWidget 2 state create
I/flutter (20505): Page StatefulWidget 2 initState
I/flutter (20505): Page StatefulWidget 2 didChangeDependencies
I/flutter (20505): Page StatefulWidget 2 build
I/flutter (20505): Page Home build
I/flutter (20505): Page StatefulWidget 1 build
```

4. 从*statefulpage 2* push 到 *statefulpage 3*

```bash
I/flutter (20505): Page StatefulWidget 3 create
I/flutter (20505): Page StatefulWidget 3 state create
I/flutter (20505): Page StatefulWidget 3 initState
I/flutter (20505): Page StatefulWidget 3 didChangeDependencies
I/flutter (20505): Page StatefulWidget 3 build
I/flutter (20505): Page Home build
I/flutter (20505): Page StatefulWidget 2 build
I/flutter (20505): Page StatefulWidget 1 build
```

5. 此时，hot reload一下

```bash
I/flutter (20505): Page Home create
I/flutter (20505): Page StatefulWidget 2 create
I/flutter (20505): Page StatefulWidget 2 didUpdateWidget
I/flutter (20505): Page StatefulWidget 2 build
I/flutter (20505): Page Home didUpdateWidget
I/flutter (20505): Page Home build
I/flutter (20505): Page StatefulWidget 3 create
I/flutter (20505): Page StatefulWidget 3 didUpdateWidget
I/flutter (20505): Page StatefulWidget 3 build
I/flutter (20505): Page StatefulWidget 1 create
I/flutter (20505): Page StatefulWidget 1 didUpdateWidget
I/flutter (20505): Page StatefulWidget 1 build
```

6. *statefulpage 3* pop

```bash
I/flutter (20505): Page Home build
I/flutter (20505): Page StatefulWidget 2 build
I/flutter (20505): Page StatefulWidget 1 build
I/flutter (20505): Page StatefulWidget 3 dispose
```

7. *statefulpage 2* pop

```bash
I/flutter (20505): Page Home build
I/flutter (20505): Page StatefulWidget 1 build
I/flutter (20505): Page StatefulWidget 2 dispose
```

8. *statefulpage 1* pop

```bash
I/flutter (20505): Page Home build
I/flutter (20505): Page StatefulWidget 1 dispose
```

9. *homepage* pop

```bash
D/FlutterView(22514): Detaching from a FlutterEngine: io.flutter.embedding.engine.FlutterEngine@586b66e
D/FlutterActivityAndFragmentDelegate(22514): Detaching FlutterEngine from the Activity that owns this Fragment.
D/FlutterEngine(22514): Destroying.
D/FlutterEnginePluginRegistry(22514): Destroying.
W/libEGL  (22514): eglTerminate() called w/ 1 objects remaining
```

> 好像 home page的dispose方法没有被调用？



#### 屏幕翻转
一般情况下，屏幕翻转不会触发任何的方法调用。

但是当你的build中，有判断屏幕方向时：

```dart
MaterialButton(
	color: MediaQuery.of(context).orientation ==  Orientation.portrait ? Colors.green : Colors.red,
	textColor: Colors.white,
	onPressed: () {
		Navigator.of(context).pushNamed("page32");
	},
	child: Text("跳转"),
)
```

这个时候，`didChangeDependencies`会被触发：

```bash
I/flutter (20505): Page StatefulWidget 2 didChangeDependencies
I/flutter (20505): Page StatefulWidget 2 build
```

一般情况下，widget树的父节点有`InheritedWidget`子类`WidgetType1`。当子widget有通过`context.dependOnInheritedWidgetOfExactType(WidgetType1)`获取到父节点`WidgetType1`, 并根据`WidgetType1`中的值进行显示等。此时，child便已经注册监听了`WidgetType1`。当`WidgetType1`改变且`WidgetType2.updateShouldNotify == true`时，会通知这个child, 触发`childState.didChangeDependencies`方法，最后触发`childState.build`。

#### TabBar 控件

1. 默认在tab 1页面

```bash
I/flutter (20505): tab 1 create
I/flutter (20505): tab 2 create
I/flutter (20505): tab 1 state create
I/flutter (20505): tab 1 initState
I/flutter (20505): tab 1 didChangeDependencies
I/flutter (20505): tab 1 build
```

2. 点击 tab 2

```bash
I/flutter (20505): tab 2 state create
I/flutter (20505): tab 2 initState
I/flutter (20505): tab 2 didChangeDependencies
I/flutter (20505): tab 2 build
I/flutter (20505): tab 1 dispose
```

3. 点击 tab 1

```bash
I/flutter (20505): tab 1 state create
I/flutter (20505): tab 1 initState
I/flutter (20505): tab 1 didChangeDependencies
I/flutter (20505): tab 1 build
I/flutter (20505): tab 2 dispose
```

4. hot reload

```bash
I/flutter (20505): tab 1 create
I/flutter (20505): tab 2 create
I/flutter (20505): tab 1 didUpdateWidget
I/flutter (20505): tab 1 build
```

#### 切换相同控件

如下面的代码：

```dart
random == 0 ? TabViewWidget("widget 1", Colors.green) : TabViewWidget("widget 2", Colors.red)
```

1. 具体日志

```bash
I/flutter (20505): widget 1 create
I/flutter (20505): widget 1 state create
I/flutter (20505): widget 1 initState
I/flutter (20505): widget 1 didChangeDependencies
I/flutter (20505): widget 1 build

I/flutter (20505): widget 2 create
I/flutter (20505): widget 2 didUpdateWidget
I/flutter (20505): widget 2 build
```

###  Navigator

上面有个遗留问题，为什么在push新页面时，之前的页面都要重新build一次？从`Navigator`的源码中，尝试寻找答案。

在这之前，先了解下`WidgetsApp`路由的优先级：*home > routes > onGenerateRoute*

> home的路由，对应的routeName是"/"

而`WidgetsApp` 的 路由配置：home/routes/onGenenrateRoute, 最后都会转换成 Navigator的onGenenrateRoute。（源码比较清晰，这里不展开）

#### NavigatorState.push

`Navigator.pushName`, 最后会调用`NavigatorState.push`方法，它的 源码：

```dart
Future<T> push<T extends Object>(Route<T> route) {
    assert(!_debugLocked);
    assert(() {
      _debugLocked = true;
      return true;
    }());
    assert(route != null);
    assert(route._navigator == null);
    final Route<dynamic> oldRoute = _history.isNotEmpty ? _history.last : null;
    route._navigator = this;
    route.install(_currentOverlayEntry);
    _history.add(route);
    route.didPush();
    route.didChangeNext(null);
    if (oldRoute != null) {
      oldRoute.didChangeNext(route);
      route.didChangePrevious(oldRoute);
    }
    for (NavigatorObserver observer in widget.observers)
      observer.didPush(route, oldRoute);
    RouteNotificationMessages.maybeNotifyRouteChange(_routePushedMethod, route, oldRoute);
    assert(() {
      _debugLocked = false;
      return true;
    }());
    _afterNavigation(route);
    return route.popped;
  }
```

在看源码之前，先了解下入参`Route`类：

*MaterialPageRoute <- PageRoute <- ModalRoute <- TransitionRoute<- OverlayRoute <- Route*

常用的是 `MaterialPageRoute`、`CupertinoPageRoute`， 应该都很熟悉。

关于`ModalRoute`, 也有两点需要先介绍下：

- *ModalRoute.maintainState*

  是否保存不可见的页面，即被push之后处于当前页面之下的页面。若true, 不可见页面的state会被保存。若false，不可见页面的数据都不会被保存。

  >  默认情况下，MaterialPageRoute、CupertinoPageRoute的`maintainState`均为true。

- *ModalRoute*一般会有两中*OverlayEntry*：`ModalBarrier`、`_ModalScope`。其中，`ModalBarrier`负责背景的绘制，如dialog的半透明背景。`_ModalScope`负责绘制page。

接着，来分析这段中的部分源码:

```dart
route.install(_currentOverlayEntry);

//其他关联代码
OverlayEntry get _currentOverlayEntry {
    for (Route<dynamic> route in _history.reversed) {
        if (route.overlayEntries.isNotEmpty)
            return route.overlayEntries.last;
    }
    return null;
}
```

`_currentOverlayEntry`这个就是在*_history*中的最后一个*overlayEntries*.

`route.install`方法在`OverlayRoute`以及`TransitionRoute`都有新增相关调用：

在`OverlayRoute`中，

*route.install*时将`route._overlayEntries` 插入到`NavigatorState`的`_entries`中，并且`NavigatorState.installAll`会触发重新build. 这里的`route._overlayEntries`就是上面提到的`ModalRoute`中的两个`OverlayEntry`.

在`TransitionRoute`中，

*route.install*时创建`AnimationController`、`Animation`.

```dart
_history.add(route);
```

这句比较好理解，将新的route添加到`_history`队列。

```dart
route.didPush();
route.didChangeNext(null);
```

在`TransitionRoute`中，

*route.didPush*时启动动画*Animation.forward*, 过场动画就是从这里开始的。*TransitionRoute.didPush*会调用到*_didPushOrReplace*, 该方法会监控`Animation`动画的状态。

*route.didChangeNext*, 用来设置`SecondaryAnimation`.

```dart
route.didChangePrevious(oldRoute);
```

在`ModalRoute` 中，

*route.didChangePrevious*, 调用*changedInternalState*.

*route.changedInternalState*, 一是调用`_ModalScope.setState`， 二是`ModalBarrier.markNeedsBuild`。其实就是让两个`OverlayEntry`重新build. 而`_ModalScope`的build方法会调用*route.buildTransitions*方法以及*route.buildPage*方法。（这两个方法应该不陌生，在自定义过场动画时会用到）



#### 栈下的page会触发build的原因

在`TransitionRoute`中，*disPush*-> *_didPushOrReplace* ->监听Animation状态，在*AnimationStatus.reverse*时， opaque = false, 表示栈顶的前一页被允许绘制出来，此时正好开始了过场动画。在*AnimationStatus.completed*时，opaque = true, 表示栈顶下的所有页面都不会被绘制，此时正好是过场动画结束。在设置opaque时，`overlayEntries.first.opaque = opaque`， 回调用`_overlay._didChangeEntryOpacity`，这个`_overlay`就是`Navigator.overlay`。该方法如下：

```dart
void _didChangeEntryOpacity() {
    setState(() {
      // We use the opacity of the entry in our build function, which means we
      // our state has changed.
    });
  }
```

然后，*OverlayState.build*方法触发，所有的`_entries`都会被涉及到，导致之前的*page.build*。

