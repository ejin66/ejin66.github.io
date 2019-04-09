---
layout: post
title: fish-redux库的学习使用
tags: [Flutter, Redux]
---

### fish-redux库的介绍

`fish-redux`是一个基于 `Redux` 数据管理的组装式 `flutter` 应用框架。现已由阿里闲鱼团队开源，项目地址：[Fish Redux](https://github.com/alibaba/fish-redux)。

关于该库，官方也已经有了详细的介绍：[刚刚，阿里宣布开源Flutter应用框架Fish Redux！](https://www.yuque.com/xytech/flutter/ycc9ni)。

但是，如果对`Redux`不太了解，或者刚接触`Fish Redux`不久, 肯定会有很多困惑、茫然，不知如何使用。因为相较于`flutter-redux`, `fish-redux`在它的基础上又抽象出了很多的概念，如`Effect`、`Adapter`、`Dependent`、`Page`等。想短时间内完全理解，确实很困难。这也是本文的目的，帮助我们更快的理解使用`fish-redex`。

<br/>

### fish-redux 的关系图

针对这么多的概念，我是通过画图的方式来加深理解的。

![Fish-Redux关系图]({{site.baseurl}}/assets/img/pexels/fish-redux.png)

从图中可以看出，`fish-redux`大大小小的概念快20个了。下面详细介绍每个概念的作用以及彼此的关系。

<br/>

### fish-redux的详细介绍

#### `Page<T, P>`

> T指状态的数据类型，P指页面的入参类型。

`Page`就是一个完整的页面，它继承自`Component<T>`。同时扩展出了两个参数：`InitState<T, P>`、`Middleware<T>`。

`middleware`的概念与`Redux`中是一致的。

`initState`的作用是将打开页面时传入的参数类型为`P`的值对应到状态`T`中，控制页面初始的显示内容。

如何打开一个页面? 

```dart
//创建一个Page
class MyPage extends Page<PageState, List<String>> {
    ....
}

void main() => runApp(createApp());

Widget createApp() {
  //将page添加到routes
  final AbstractRoutes routes = HybridRoutes(routes: [
    PageRoutes(
      pages: <String, Page<Object, dynamic>> {
        "home_page": MyPage()
      }
    )
  ]);

  return MaterialApp(
    title: "Test fish redux",
    debugShowCheckedModeBanner: false,
    theme: ThemeData(
      primarySwatch: Colors.blue,
    ),
    //通过routes.buildPage的方式生成一个Widget
    home: routes.buildPage("home_page", ["android", "iOS", "flutter", "java", "kotlin", "dart"]),
    onGenerateRoute: (settings) {
      return MaterialPageRoute<Object>(builder: (context) {
        return routes.buildPage(settings.name, settings.arguments);
      });
    },
  );
}
```

从上面的例子可以看出，通过`AbstractRoutes.buildPage（String key, dynamic arguments）`来生成页面对应的`Widget`。而页面传参就是通过第二个参数来传递的。

#### `Component<T>`

`Page`的大部分功能都是`Component`提供的。`Component`字面意思就是组件，很明显它是为了控件的复用而存在的。它的特点有：复用、低耦合、即插即用等。

组件的参数比较多，下面逐个介绍。

##### `ViewBuilder<T> view`

首先看下`ViewBuilder`的定义：

```dart
typedef ViewBuilder<T> = Widget Function(
  T state,
  Dispatch dispatch,
  ViewService viewService,
);
```

它的作用是提供组件的UI展示。`state`存储着当前的组件状态，`dispatch`用来发射事件，`viewService`用来绑定子组件或者`adapter`。

> 关于`viewService`，后面有例子帮助理解。

##### `Reducer<T> reducer`

`reducer`的定义：

```dart
typedef Reducer<T> = T Function(T state, Action action);
```

`Action`是框架提供的固定类型，定义如下：

```dart
class Action {
  const Action(this.type, {this.payload});
  final Object type;
  final dynamic payload;
}
```

`reducer`的作用就不在详述了。

##### `ReducerFilter<T> filter`

`filter`的定义：

```dart
typedef ReducerFilter<T> = bool Function(T state, Action action);
```

它的作用是为组件过滤不必要的事件。只有返回值是`true`的`action`会进入到`reducer`处理。

##### `Effect<T> effect`

`effect`的定义：

```dart
typedef Effect<T> = dynamic Function(Action action, Context<T> ctx);
```

`ctx`提供组件的上下文，包括了`state`、`dispatch`、`BuildContext context`，以及`appBroadcast`、`pageBroadcast`  action。

它与`reducer`有点像，都是处理特定的`action`事件。区别有以下几点：

- `effect`处理非修改数据的行为事件，`reducer`处理修改数据的行为事件。
- `effect`返回`bool`或`Future`, `reducer`无返回值

如果`effect`的返回值是一个非空值(not null , not false)，则代表自己优先处理，不再做下一步的动作；否则广播给其他组件的 Effect 部分，同时发送给 Reducer。

> 约定`effect`接收的`action.type`以`on`开头，`reducer`的以非`on`开头。

看源码，是将`effect`转成`middleware`, 如果返回值满足条件，就不在`next`了。

```dart
Dispatch createDispatch(
      OnAction onAction, Context<T> ctx, Dispatch parentDispatch) {
    Dispatch dispatch = (Action action) {
      throw Exception(
          'Dispatching while appending your effect & onError to dispatch is not allowed.');
    };

    /// attach to store.dispatch
    dispatch = _applyOnAction<T>(onAction, ctx)(
      dispatch: (Action action) => dispatch(action),
      getState: () => ctx.state,
    )(parentDispatch);
    return dispatch;
  }

static Middleware<T> _applyOnAction<T>(OnAction onAction, Context<T> ctx) {
    return ({Dispatch dispatch, Get<T> getState}) {
      return (Dispatch next) {
        return (Action action) {
          final Object result = onAction?.call(action);
          if (result != null && result != false) {
            return;
          }

          //skip-lifecycle-actions
          if (action.type is Lifecycle) {
            return;
          }

          if (!shouldBeInterruptedBeforeReducer(action)) {
            ctx.pageBroadcast(action);
          }

          next(action);
        };
      };
    };
  }
```

##### `HigherEffect<T> higherEffect`

`higherEffect`是`effect`的升级版，它允许拥有自己的临时状态。

如：

```dart
class PageEffectPart extends EffectPart<PageState> {

  //higher effect的状态变量
  bool addFlag = true;

  @override
  Map<Object, OnAction> createMap() {
    return {
      PageType.OnAuto: _pageEffect
    };
  }

  void _pageEffect(Action action) {
    print("at higher effect: ${action.type}");

    if (addFlag) {
      dispatch(ActionCreator.addData());
    } else {
      dispatch(ActionCreator.deleteData());
    }
    addFlag = !addFlag;
  }

}


class MyPage extends Page<PageState, List<String>> {
  MyPage()
      : super(
            ...
            higherEffect: higherEffect(() => PageEffectPart()),
        	...
}
```

> `higherEffect`、`effect`同时只能设置其中一个。

##### `OnError<T> onError`

`onError`的定义：

```dart
typedef OnError<T> = bool Function(Exception exception, Context<T> ctx);
```

它用来处理`effect`中的异常。返回`true`,代表已处理，返回`false`代表不处理，继续抛出该异常。

##### `Dependencies<T> dependencies`

它的作用是给当前组件提供其他组件或者`Adapter`。

`dependencies`接收两个入参：`Map<String, Dependent<T>> slots` 、`AbstractAdapter<T> adapter`。

`slots`的作用是给`ViewBuilder`提供子组件，在`ViewBuilder`中通过`ViewService.buildComponent(key)`来使用，key`值就是`Map的`key`。

`Dependent` = `Connnector` + `Component`。因为子组件的状态与当前组件的状态，类型不一致，需要使用`connector`来转换沟通。

如：

```dart
class MyPage extends Page<PageState, List<String>> {
  MyPage()
      : super(
          	//...
            dependencies: Dependencies(
                slots: {
                  "count": CountConnector() + CountComponent()
                }
            ),
            //...
}
```

`adapter`的作用是给组件中`ListView`提供`ListAdapter`的。在`ViewBuilder`中通过`ViewService.buildAdapter()`来获取`ListAdapter`。

##### `ShouldUpdate<T> shouldUpdate`

`shouldUpdate`的定义：

```dart
typedef ShouldUpdate<T> = bool Function(T old, T now);
```

它的作用是决定组件是否需要重新更新。

通过比较`old`、`now`两种状态，默认逻辑是：若两个变量不相等就刷新。

```dart
static ShouldUpdate<K> updateByDefault<K>() =>
      (K _, K __) => !identical(_, __);
```

##### `WidgetWrapper wrapper`

`wrapper`的定义：

```dart
typedef WidgetWrapper = Widget Function(Widget child);
```

它的作用是包裹`ViewBuilder`的内容。

##### `List<Middleware<T>> middleware`

`middleware`的定义：

```dart
typedef Middleware<T> = Composable<Dispatch> Function({
  Dispatch dispatch,
  Get<T> getState,
});
```

`Composable`的定义：

```dart
typedef Composable<T> = T Function(T next);
```

如何定义一个`middleware`:

```dart
Composable<Dispatch> pageMiddleware({Dispatch dispatch, Get<PageState> getState}) {
  return (Dispatch next) {
    return (Action action) {
      //do something
      next(action);
    };
  };
}
```

> 发射事件的处理流程依次是：`effect` -> `middleware` -> `reducer`。

<br/>

### fish-redux的项目结构

推荐的项目结构：

- main.dart

- ***page-1-package***
  - page.dart
  - action.dart
  - reducer.dart
  - effect.dart
  - middleware.dart
  - view.dart
  - state.dart
  - ***adapter-package***
    - ...
  - ***component-package***
    - ...
- ***page-2-package***