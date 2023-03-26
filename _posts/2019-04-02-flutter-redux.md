---
layout: post
title: Flutter redux浅析
tags: [Flutter, Redux]
---

### Redux中的几个概念

#### Store

顾名思义，`Store`是用来存储、管理全局的页面状态的。将页面UI与存储在`Store`中的状态绑定，通过修改`Store`的状态达到UI自动更新的效果。

如何创建一个`Store`:

```dart
//自定义的页面状态
class CountState {
  int count;

  CountState(this.count);

  factory CountState.init() {
    return CountState(0);
  }
}

final store = Store<CountState>(countReducer,
    initialState: CountState.init(),
    middleware: [middleware]);
```

`Store<T>`中`T`就是指存储的状态类型，如上面例子中的`CountState`。

参数`reducer`、`middleware`,下面会提到。

参数`initialState`,去设置初始的状态。控制页面加载出来时的默认显示内容。



#### Reducer

`reducer`本质上是一个函数。它会根据不同的动作指令，去修改调整状态值。

`reducer`具体定义：

```dart
typedef State Reducer<State>(State state, dynamic action);
```

其中，`state`是`Store`中存储的状态，`action`是特定的动作指令，最后返回一个更新后的状态。

如何创建一个`reducer`:

```dart
enum Action {
  INCREMENT,
  DECREMENT
}

CountState countReducer(CountState state, dynamic action) {
  if (action == Action.INCREMENT) {
    state.count += 1;
  }

  if (action == Action.DECREMENT) {
    state.count -= 1;
  }
  return state;
}
```



#### Middleware

中间件，作用域位于`reducer`更新状态之前。本质上也是一个函数。

`middleware`具体定义：

```dart
typedef void Middleware<State>(Store<State> store,dynamic action,NextDispatcher next);
```

其中，前两个参数与`reducer`参数一致。`next`是调用下一个中间件的函数。

如何创建一个`middlerware`:

```dart
middleware(Store<CountState> store, dynamic action, NextDispatcher next) {

  if (action == Action.INCREMENT || action == Action.DECREMENT) {
	...
  }

  //调用下一个中间件
  next(action);
}
```



#### StoreProvider

`Store`的提供者，本质是`Widget`。一般包裹在根部`Widget`, 给整个`Widget Tree` 提供`Store`。

如何使用`StoreProvider`:

```dart
class MyApp extends StatelessWidget {
 
    @override
  	Widget build(BuildContext context) {
        return StoreProvider<CountState>(store: store, 
                                         child: ...);
    }
 
}
```

`StoreProvider<T>`中`T`指`Store`中存储的状态类型。



#### StoreConnector

`StoreConnector`也是一个`Widget`,通过它可以连接到`StoreProvider`存储的状态。用它来包裹局部`Widget`, 并将`Store.state`与`Widget`绑定。

如何创建一个`StoreConnector`:

```dart
class _MyHomePageState extends State<MyHomePage> {

  @override
  Widget build(BuildContext context) {
 
    return StoreConnector<CountState, int>(
      converter: (state) => state.state.count,
      builder: (context, count) {
        return //...;
      },
    );
  }
}
```

`StoreConnector<T,ViewModel>`中`T`指`Store`中存储的状态类型，`ViewModel`指本`Widget`需要的状态类型（上面的例子中`int`就是需要的状态类型）。

`T->ViewModel`要如何转换呢？

在创建`StoreConnector`时需要一个入参`converter`, 它就是负责这个转换逻辑的。它也是一个函数，具体定义：

```dart
typedef StoreConverter<S, ViewModel> = ViewModel Function(
  Store<S> store,
);
final StoreConverter<S, ViewModel> converter;
```



#### Dispatcher

如何通知状态更新呢？通过`store.dispatch`：

```dart
StoreProvider.of<CountState>(context).dispatch(Action.INCREMENT);
//或者如果能拿到 Store 的话
store.dispatch(Action.INCREMENT)
```

`StoreProvider.of<T>(context)` 返回`Store`,`T`指状态类型，`context`是`Widget` build时的上下文。



### Redux页面更新流程

基于上面的这些概念，`Redux`内部是如何运作的?

![Flutter-Redux页面更新流程图]({{site.baseurl}}/assets/img/pexels/flutter_redux.png)



### Redux的实现原理

`Redux`将`state`与页面UI绑定，通过更新`State`来更新页面。若页面有多处UI与同一状态值有关联，修改状态能够达到牵一发而动全身的效果。越是复杂的页面，越能体现出`Redux`的优势。

那`Redux`是如何实现UI自更新的呢？是通过常用的`setState`方法吗？`dispatch action`会导致所有组件都刷新吗？...

#### Store的部分源码

```dart
class Store<State> {
  Reducer<State> reducer;
  //通过它来传递状态的
  final StreamController<State> _changeController;
  State _state;
  List<NextDispatcher> _dispatchers;

  Store(
    this.reducer, {
    State initialState,
    List<Middleware<State>> middleware = const [],//中间件
    bool syncStream: false,
    bool distinct: false,//若是true，`reducer`返回的state与current state相等的话，流程就停止。见方法_createReduceAndNotify
  })
      : _changeController = new StreamController.broadcast(sync: syncStream) {
    _state = initialState;
    _dispatchers = _createDispatchers(
      middleware,
      _createReduceAndNotify(distinct),
    );//这里可以看出，是middleware先运行，最后是reducer
  }
  
  Stream<State> get onChange => _changeController.stream;
    
  //将reducer包装成NextDispatcher
  NextDispatcher _createReduceAndNotify(bool distinct) {
    return (dynamic action) {
      final state = reducer(_state, action);
	  //distinct为true且前后状态一样，直接return
      if (distinct && state == _state) return;

      _state = state;
      //将新的状态添加到stream中
      _changeController.add(state);
    };
  }
    
  List<NextDispatcher> _createDispatchers(
    List<Middleware<State>> middleware,
    NextDispatcher reduceAndNotify,
  ) {
    final dispatchers = <NextDispatcher>[]..add(reduceAndNotify);

    // Convert each [Middleware] into a [NextDispatcher]
    for (var nextMiddleware in middleware.reversed) {
      final next = dispatchers.last;

      dispatchers.add(
        (dynamic action) => nextMiddleware(this, action, next),
      );
    }

    return dispatchers.reversed.toList();
  }
  
  //dispach action之后，依次运行middleware,最后运行reducer
  void dispatch(dynamic action) {
    _dispatchers[0](action);
  }
    
}
```

在发起`store.dispatch`后，会依次运行`middleware`,最后运行`reducer`。并且，会将`new state`添加到`_changeController`中。`Store`的工作到这里就结束了，好像看不出是哪里通知了子视图刷新的。往下看`StoreConnector`.



#### StoreConnector的部分源码

```dart
class StoreConnector<S, ViewModel> extends StatelessWidget {
    
 //...   
 
 StoreConnector({
    Key key,
    @required this.builder,
    @required this.converter,
    this.distinct = false,
    this.onInit,
    this.onDispose,
    this.rebuildOnChange = true,
    this.ignoreChange,
    this.onWillChange,
    this.onDidChange,
    this.onInitialBuild,
  })  : assert(builder != null),
        assert(converter != null),
        super(key: key);

  @override
  Widget build(BuildContext context) {
    return _StoreStreamListener<S, ViewModel>(
      store: StoreProvider.of<S>(context),
      builder: builder,
      converter: converter,
      distinct: distinct,
      onInit: onInit,
      onDispose: onDispose,
      rebuildOnChange: rebuildOnChange,
      ignoreChange: ignoreChange,
      onWillChange: onWillChange,
      onDidChange: onDidChange,
      onInitialBuild: onInitialBuild,
    );
  }
}
```

`_StoreStreamListener`源码：

```dart
class _StoreStreamListener<S, ViewModel> extends StatefulWidget {
	//...
	_StoreStreamListener({
        Key key,
        @required this.builder,
        @required this.store,
        @required this.converter,
        this.distinct = false,
        this.onInit,
        this.onDispose,
        this.rebuildOnChange = true,
        this.ignoreChange,
        this.onWillChange,
        this.onDidChange,
        this.onInitialBuild,
      }) : super(key: key);
    

}
```

最后到了`_StoreStreamListener`，源码：

```dart
class _StoreStreamListenerState<S, ViewModel>
    extends State<_StoreStreamListener<S, ViewModel>> {
  Stream<ViewModel> stream;
  ViewModel latestValue;

  @override
  void initState() {
    _init();

    super.initState();
  }

  void _init() {
    if (widget.onInit != null) {
      widget.onInit(widget.store);
    }
	//将原state转成需要使用的状态类型
    latestValue = widget.converter(widget.store);

    if (widget.onInitialBuild != null) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        widget.onInitialBuild(latestValue);
      });
    }
	//这就是Store中_changeController的流。跟Store的状态联系了起来。
    var _stream = widget.store.onChange;

    //将状态流经过一系列的变形过滤
    if (widget.ignoreChange != null) {
      _stream = _stream.where((state) => !widget.ignoreChange(state));
    }

    stream = _stream.map((_) => widget.converter(widget.store));

    //如果设置了distinct标识符，若前后两转换值一致的话，就丢弃。通过它可达到组件只关注与之有关的状态变化。不用任何的状态过来都去刷新。
    if (widget.distinct) {
      stream = stream.where((vm) {
        final isDistinct = vm != latestValue;

        return isDistinct;
      });
    }

    stream =
        stream.transform(StreamTransformer.fromHandlers(handleData: (vm, sink) {
      latestValue = vm;

      if (widget.onWillChange != null) {
        widget.onWillChange(latestValue);
      }

      if (widget.onDidChange != null) {
        WidgetsBinding.instance.addPostFrameCallback((_) {
          widget.onDidChange(latestValue);
        });
      }

      sink.add(vm);
    }));
  }

  @override
  Widget build(BuildContext context) {
     //StoreConnector.rebuildOnChange的默认值是true。所以，最后是使用了StreamBuilder包裹子Widget，且将stream传入其中。
    return widget..rebuildOnChange
        ? StreamBuilder<ViewModel>(
            stream: stream,
            builder: (context, snapshot) => widget.builder(
                  context,
                  snapshot.hasData ? snapshot.data : latestValue,
                ),
          )
        : widget.builder(context, latestValue);
  }
}
```

`StreamBuilder`接收一个`Stream`,一旦`Stream`中有新的状态过来，就会重新`build`返回新的`widget`。而这个`Stream`是经过`store.onchange`(`store._changeController.stream`)变换过来的。

#### 整理一下

- 首先，`dispatch action`
- `reducer`返回一个新的`state`, 并将它添加到`store._changeController`
- `StreamBuilder`的`stream`收到新的`state`之后，重新`build`

> `StreamBuilder`本质上也是通过`setState`去更新UI的，有兴趣的话可以继续深入源码。

