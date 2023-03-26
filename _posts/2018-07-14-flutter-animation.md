---
layout: post
title: Flutter Animation 总结
tags: [Flutter]
---



### Flutter动画中的几个关键类如下：

- `Animation<T>`
- `CurvedAnimation`
- `AnimationController`
- `Tween`
- `AnimatedWidget`
- `AnimatedBuilder`
- `ImplicitlyAnimatedWidget`

<br/>



### Animation<T>

Flutter 动画系统是基于这个类来完成的，它是一个抽象类。实际使用时需要扩展它，如 `AnimationController ` ,  `CurvedAnimation `  都是它的实现类。

 `.value`  可以获取它的当前值。 

`.addListener` 添加对值的监听，值改变时回调。

`.addStatusListener` 添加动画状态监听。动画有四种状态：

```dart
enum AnimationStatus {
  /// 动画停在开始处
  dismissed,
  /// 动画从开始处运行
  forward,
  /// 动画从结束处向开始处反向运行
  reverse,
  /// 动画停在结束处
  completed,
}
```

<br/>



### `CurvedAnimation`

`CurvedAnimation` 定义了一个非线性的动画过程。通过设置不同的曲线（类似插值器），实现不同的效果。

```dart
//parent 就是上面说的Animation
//curve 指不同的曲线效果
final CurvedAnimation curve = 
    CurvedAnimation(parent: controller, curve: Curves.easeIn);
```

`Curves` 内置了很多效果，可以直接使用。也可以通过自定义的方式：

```dart
class ShakeCurve extends Curve {
  @override
  double transform(double t) {
    return math.sin(t * math.PI * 2);
  }
}
```

<br/>



### `AnimationController`

`AnimationController` 是一个特殊的 `Animation` 实现类， 它会在硬件准备好绘制下一帧时，生成一个新的value值。默认情况下，它会在你设定的时间区间内，匀速的产生从0.0到1.0区间的值。

```dart
//在2s内，匀速产生范围[0.0,1.0]的值
final AnimationController controller = 
    AnimationController(duration: const Duration(milliseconds: 2000), vsync: this);
```

上面的代码中，除了传入了时间，还有另外一个参数 `vsync` , 它的作用是避免当页面不在前台的动画绘制，减少资源消耗。

`vsync` 类型是 `TickerProvider` 。我们看到上面的例子，`vsync` 传入的是 `this` , 其实是通过 `Mixin`  实现的：

```dart
class _LogoAppState extends State<LogoApp> with SingleTickerProviderStateMixin {
  Animation<double> animation;
  AnimationController controller;

  initState() {
    super.initState();
    controller = AnimationController(
        duration: const Duration(milliseconds: 2000), vsync: this);
    animation = Tween(begin: 0.0, end: 300.0).animate(controller)
      ..addListener(() {
        setState(() {
          // the state that has changed here is the animation object’s value
        });
      });
    controller.forward();
  }
}
```

> `AnimationController` 需要通过调用 `.forward` 来启动动画。

<br/>



### `Tween`

默认情况下， `AnimationController` 值的区间是[0.0, 1.0]。通过 `Tween` ，可以更改区间范围，以及更改值的类型。

```dart
final AnimationController controller = AnimationController(
    duration: const Duration(milliseconds: 500), vsync: this);

//将范围设置为[-200.0， 0.0]
final Tween doubleTween = Tween<double>(begin: -200.0, end: 0.0);
Animation<double> anim = doubleTween.animate(controller);

//将值的类型改成Color
final Tween colorTween =
    ColorTween(begin: Colors.transparent, end: Colors.black54);
Animation<int> alpha = IntTween(begin: 0, end: 255).animate(controller);
```

`Tween.animate()` 入参为 `Animation` ，所以不仅可以传入 `AnimationController` ，也可以设置 `CurvedAnimation` :

```dart
//通过AnimationController设置动画时间
final AnimationController controller = AnimationController(
    duration: const Duration(milliseconds: 500), vsync: this);
//通过CurvedAnimation设置动画曲线
final Animation curve =
    CurvedAnimation(parent: controller, curve: Curves.easeOut);
//通过Tween更改动画区间
Animation<int> alpha = IntTween(begin: 0, end: 255).animate(curve);
```

<br/>



### `AnimatedWidget`

上面的动画类，要使页面动起来，需要调用 `setState` 方法，使页面rebuild。

而 `AnimatedWidget` 则无需调用 `setState` 方法，直接使页面依赖 `Animation.value` ，一旦value值改变，页面便自动更新：

```dart
import 'package:flutter/animation.dart';
import 'package:flutter/material.dart';

void main() {
  runApp(LogoApp());
}

class LogoApp extends StatefulWidget {
  _LogoAppState createState() => _LogoAppState();
}

class _LogoAppState extends State<LogoApp> with SingleTickerProviderStateMixin {
  AnimationController controller;
  Animation<double> animation;

  initState() {
    super.initState();
    controller = AnimationController(
        duration: const Duration(milliseconds: 2000), vsync: this);
    animation = Tween(begin: 0.0, end: 300.0).animate(controller);
    controller.forward();
  }

  Widget build(BuildContext context) {
    return AnimatedLogo(animation: animation);
  }

  dispose() {
    controller.dispose();
    super.dispose();
  }
}

class AnimatedLogo extends AnimatedWidget {
  AnimatedLogo({Key key, Animation<double> animation})
      : super(key: key, listenable: animation);

  Widget build(BuildContext context) {
    final Animation<double> animation = listenable;
    return Center(
      child: Container(
        margin: EdgeInsets.symmetric(vertical: 10.0),
        height: animation.value,
        width: animation.value,
        child: FlutterLogo(),
      ),
    );
  }
}
```

上面代码中，通过控制 `Container` 的宽高，来实现的动画效果。

> Flutter中继承自 `AnimatedWidget` 的有：AnimatedBuilder, AnimatedModalBarrier, DecoratedBoxTransition, FadeTransition, PositionedTransition, RelativePositionedTransition, RotationTransition, ScaleTransition, SizeTransition, SlideTransition

<br/>



### `AnimatedBuilder`

`AnimatedBuilder` 继承自 `AnimatedWidget` ，它不同于 `AnimatedWidget` 的地方在于，将动画与Widget解耦开来。但动画实现原理都是一样的。

```dart
//专注动画的类
class GrowTransition extends StatelessWidget {
  GrowTransition({this.child, this.animation});

  final Widget child;
  final Animation<double> animation;

  Widget build(BuildContext context) {
    return Center(
      child: AnimatedBuilder(
          animation: animation,
          builder: (BuildContext context, Widget child) {
            return Container(
                height: animation.value, width: animation.value, child: child);
          },
          child: child),
    );
  }
}
```

在布局中使用：

```dart
class LogoApp extends StatefulWidget {
  _LogoAppState createState() => _LogoAppState();
}

class _LogoAppState extends State<LogoApp> with TickerProviderStateMixin {
  Animation animation;
  AnimationController controller;

  initState() {
    super.initState();
    controller = AnimationController(
        duration: const Duration(milliseconds: 2000), vsync: this);
    final CurvedAnimation curve =
        CurvedAnimation(parent: controller, curve: Curves.easeIn);
    animation = Tween(begin: 0.0, end: 300.0).animate(curve);
    controller.forward();
  }

  //使用上面封装动画的类
  Widget build(BuildContext context) {
    return GrowTransition(child: LogoWidget(), animation: animation);
  }

  dispose() {
    controller.dispose();
    super.dispose();
  }
}

void main() {
  runApp(LogoApp());
}
```

<br/>

### `ImplicitlyAnimatedWidget`

`ImplicitlyAnimatedWidget`替我们封装好了大部分功能。我们只需要一点额外代码，就能实现动画。

构造方法源码：

```dart
abstract class ImplicitlyAnimatedWidget extends StatefulWidget {

  const ImplicitlyAnimatedWidget({
    Key key,
    this.curve = Curves.linear,
    @required this.duration,
    this.onEnd,
  }) : assert(curve != null),
       assert(duration != null),
       super(key: key);
}
```

`Curves`是设置`CurvedAnimation`用的，`duration`是设置`AnimationController`用的。而这些，都不需要我们自己创建，`ImplicitlyAnimatedWidget`会帮我们做。

然后，在`ImplicitlyAnimatedWidgetState`中，有个方法需要介绍下，挺绕的：

```dart
typedef TweenVisitor<T> = Tween<T> Function(Tween<T> tween, T targetValue, TweenConstructor<T> constructor);

abstract class ImplicitlyAnimatedWidgetState<T extends ImplicitlyAnimatedWidget> extends State<T> with SingleTickerProviderStateMixin<T> {
    
    void forEachTween(TweenVisitor<dynamic> visitor);
    
}
```

`forEachTween`方法，主要功能，就能它的名字一样，遍历所有的`Tween`。但是`ImplicitlyAnimatedWidgetState`本身并不提供`Tween`，需要我们在继承时设置实际的`Tween`，并且支持多个`Tween`。

遍历`Tween`做什么逻辑呢？就是入参`vistor`, 它是一个方法体，每个`Tween`都经过它的逻辑洗礼一遍才行。

在`ImplicitlyAnimatedWidgetState`中，主要通过`forEachTween`完成两件事：

- 初始化`Tween`
- 更新`Tween`

部分源码：

```dart
@override
void didUpdateWidget(T oldWidget) {
    ///...
    if (_constructTweens()) {
        ///这里传入的是更新的逻辑
        forEachTween((Tween<dynamic> tween, dynamic targetValue, TweenConstructor<dynamic> constructor) {
            _updateTween(tween, targetValue);
            return tween;
        });
        ///...
    }
}

bool _constructTweens() {
    bool shouldStartAnimation = false;
    ///这里传入的是初始化逻辑
    forEachTween((Tween<dynamic> tween, dynamic targetValue, TweenConstructor<dynamic> constructor) {
        if (targetValue != null) {
            tween ??= constructor(targetValue);
            if (_shouldAnimateTween(tween, targetValue))
                shouldStartAnimation = true;
        } else {
            tween = null;
        }
        return tween;
    });
    return shouldStartAnimation;
}
```

#### 例子

```dart
class CircleProgressChart extends ImplicitlyAnimatedWidget {
    
  double progress;

  CircleProgressChart(this.size, Duration duration) : super(duration: duration);

  @override
  _CircleProgressState createState() => _CircleProgressState();
}

class _CircleProgressState
    extends AnimatedWidgetBaseState<CircleProgressChart> {
    
  Tween<double> _progressTween;

  @override
  Widget build(BuildContext context) {
    ///根据当前的值：_progressTween.evaluate(animation)
  }

  @override
  void forEachTween(visitor) {
    _progressTween = visitor(
        _progressTween, widget.progress, (v) => Tween<double>(begin: v));
  }
}
```

这代码量，确实是轻松不少！



### 总结

 实现Flutter动画的几个步骤：

1. 创建动画
   - `AnimationController ` 设置动画时间
   - `CurvedAnimation ` 设置插值器
   - `Tween ` 设置范围或者值类型
2. 更新界面
   - 通过 `Animation.addListener` 注册监听，调用 `setState` 更新界面
   - 通过 `AnimatedWidget` 或者 `AnimatedBuilder` 