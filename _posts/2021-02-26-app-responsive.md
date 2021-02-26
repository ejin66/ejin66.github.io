---
layout: post
title: Flutter 响应式框架app responsive
tags: ["Flutter"]
---

## 简介

`app_responsive` 是基于google `provider`的响应式框架，本框架主要目的是更简化更方便的处理UI与controller(逻辑)之间的关系。

我们先来看看`provider`有哪些问题或者有哪些不方便的地方：

1. 代码量偏多。在使用`prodiver`时，布局中需要包含Prodiver widget、Consumer，以及颗粒度更细的Selector。它们不仅使页面布局显得很臃肿，而且基本上每个页面都要重复的写这些代码。这在日常的开发中，很不方便。
2. `prodiver` 仅仅负责UI的控制，对逻辑层并未涉及。比如，我们的页面，加载数据、刷新、分页时的UI呈现与逻辑层强关联时，都得手动实现逻辑，且也是基本每个页面都需要的。能不能封装一下。
3. 页面间的数据共享。使用`provider`做数据共享，一般需要提前在`MaterialApp`外包裹指定数据类型的provider，这种方式比较死板、不够灵活。可不可以做到动态的共享数据。

现在来看看本框架是如何解决这些痛点的，主要分三个方面来表述：页面UI刷新控制、页面数据加载逻辑的封装、页面间的数据共享。

> 本框架不仅是响应式UI框架，针对逻辑层也做了相应的封装。一个完整的页面通常由`IPage、Istate、IController`三个角色组合完成。

## 阐述

###  1. 页面UI刷新控制

看下面的动图，使用我们的框架是怎样实现的

<img src="https://ejin66.github.io/assets/img/pexels/loaded.gif" width = "400px" /> 

首先，每个页面必不可少的`IController`: 

```dart
class ExampleController extends IController {
  String text = "android";

  @override
  Future<int> load([int page]) async {
    Future.delayed(Duration(seconds: 1), () {
        text = "flutter";
        get<PPage>().notify();
    });
  }
}
```

> load()方法会在State.initState之后自动触发

再看看布局时怎么写的：

```dart
class ExampleState extends IState<ExamplePage, ExampleController> {
  final ExampleController _controller = ExampleController();

  @override
  Widget buildChild(BuildContext context) {
    return Scaffold(
	appBar: AppBar(
	  title: Text("app responsive demo"),
	),
	body: buildBody.watch<PPage>()(context),
    );
  }

  Widget buildBody(BuildContext context) {
    return Center(
	  child: Text(controller.text),
    );
  }

  @override
  ExampleController get controller => _controller;
}
```

通过`buildBody.watch<PPage>()(context)`，`buildBody`代表的widget将被纳入`PPage`的控制范围。当我们触发`get<PPage>.notify()`时，`PPage`范围内的widget就会自动刷新了。

> PPage是框架自带的一个UI控制点。它的基类`Level`是所有UI控制点的基类。一个控制点，可以管理它范围内的Widget。通过`.watch<PPage>()`的方式将Widget纳入自己的管理范围内。

> 框架自带的UI控制点有：PPage、Load、Scope、Child。本质上都是一样的继承自`Level`, 但我们约定它们控制范围大小关系：PPage > Load > Scope > Child。



### 2. 页面的数据加载逻辑封装

*数据的加载、刷新、分页、加载空数据*

<img src="https://ejin66.github.io/assets/img/pexels/loading.gif" width = "400px" />  <img src="https://ejin66.github.io/assets/img/pexels/no_data.gif" width = "400px" /> 

上面的动图，展示了页面的首次加载、空数据情况下的UI、刷新、分页。这些个功能，从头开始写也是很烦人的。看看框架是怎么写的。

首先，看看`IController`:

```dart
class ExampleController extends IController {
  List<String> data = [];
  int httpRows = 50;

  @override
  Future<int> load([int page]) async {
     final newData = await _load(page);
     if (newData == null) {
        return computeErrorState(page);
     }
     return computeLoadingState(data, newData, page, pageRows: httpRows);
  }

  /// 模拟网络请求
  Future<List<String>> _load(int page) async {
    await Future.delayed(Duration(seconds: 2));

    if (page > 2) return [];

    int size = httpRows;

    return List.generate(size, (index) {
      return ((page - 1) * httpRows + index + 1).toString();
    });
  }
}
```

`load`方法中的`page`就是当前的页数，它是自动管理的，无需手动修改。最关键的是`load`方法的返回值，根据返回的状态，框架会自动切换到相应的UI呈现上。

> computeLoadingState、computeErrorState是框架提供的帮助计算当前状态的方法。我们也可以直接返回`LoadState`中的某个状态值，如：`LoadState.loaded`

如图一，最终返回的状态是`LoadState.loaded | LoadState.moreLoad`，此时`loading...`UI就会切换到数据显示上，当我们触发刷新、或者分页时，框架会自动触发相应的loading UI，同时自动调用`Icontroller.load(currentPage)`。而这些过程，都无需再写代码实现了。

如图二，如果接口未加载到数据，`load`方法直接返回`LoadState.empty`, 此时页面UI就会如图二所示了。

看看布局的代码：

```dart
class ExampleState extends IState {
  
  ...
    
  @override
  Widget buildChild(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text("app responsive demo"),
      ),
      body:buildBody.load(refresh: true, loadMore: true).watch<PPage>()(context),
    );
  }
  	
  Widget buildBody(BuildContext context) {
    return ListView.separated(
        itemBuilder: (_, index) => ListTile(title: Text(controller.data[index]),),
        separatorBuilder: (_, __) => Divider(height: 1),
        itemCount: controller.data.length);
  }
    
}
```

通过`buildBody.load(refresh: true, loadMore: true)`方法，就能支持上面描述的所有功能了，就这么简单。

> load方法的入参refresh、loadMore， 可以控制页面是否支持刷新、分页功能。

### 3. 页面间数据共享

要实现页面间的数据共享，需要在`MaterialApp`外包裹`AppProvider`，无其他要求。如这样：

```dart
@override
Widget build(BuidContext context) {
    return AppProvider(
      child: MaterialApp(
      	...
      ),
    );
}
```



3.1 *共享当前数据*

共享页面数据，一般共享时的`icontroller`中的数据，在`IController`中：

```dart
class ExampleController extends IController {
    
  @override
  mount(BuildContext context) {
  	super.mount(context);
	get<PPage>().exposeToApp(buildContext);
    /// 或者
    AppProvider.expose(context, this);
  }
  
  ...
      
}
```

> `PPage.exposeToApp`本质上也是调用的`AppProvider.expose`.

这样，我们就把`IController`实例分享了出来。下面看看其他的页面是如何获取的？

3.2. *获取其他页面数据*
<br />
<img src="https://ejin66.github.io/assets/img/pexels/next_page.gif" width = "400px" />

上面动图展示了`page2` 获取前一个页面的数据。在3.1中，展示了如何将数据共享出来，现在看看如何获取其他页面的数据：

```dart
Widget buildBody(BuildContext context) {
  	return Center(
        child: "来自第一页的数据： ${Text(AppProvider.get<ExampleController>(context).text)}",
    );
}
```

可以看到，通过`AppProvider.get<ExampleController>(context)`即可获取到指定类型的`IController`了。

3.3. *监听其他页面的数据变化*

上面只是展示了如何获取其他页面的数据，当然，也可以监听它数据的变化，通过：

```dart
class ... extends IController {
    
  @override
  mount(BuildContext context) {
  	super.mount(context);
	AppProvider.watch<ExampleController, PPage>(this);
  }
    
  ...
}
```

通过`AppProvider.watch<ExampleController, PPage>`, 监听`ExampleController`中`PPage`的变化。一旦被监听页面中的`PPage`有通知，当前页也会相应的刷新。

## 仓库

项目的仓库地址：[app_responsive](https://github.com/ejin66/app_responsive)
