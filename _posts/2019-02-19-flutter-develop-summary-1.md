---
layout: post
title: Flutter开发总结一
tags: [Flutter]
---

1. ### Json的序列化与反序列化

   由于`Flutter` 不支持运行时反射，无法实现像`Gson`等ORM框架。在`Flutter`中，官方给出了两种方式：

   - 借助`dart:convert`库进行`JSON`序列化并手动设置到模型。它跟下面注解生成代码后的原理一样。一个是手动写的，一个是自动生成的。

   - 利用注解`JsonSerialization` 来自动生成代码

     添加依赖：

     ```yaml
     dependencies:
       json_annotation: ^2.0.0
     
     dev_dependencies:
       build_runner: ^1.0.0
       json_serializable: ^2.0.0
     ```

     写一个模型。其中`part` 的部分，标识代码需要生成的dart文件名称，作为该源码的一部分。

     ```dart
     import 'package:json_annotation/json_annotation.dart';
     
     part 'user.g.dart';
     
     @JsonSerializable()
     class User {
     
       String name;
       String email;
     
       User(this.name, this.email);
     
       factory User.fromJson(Map<String, dynamic> json) => _$UserFromJson(json);
     
       Map<String, dynamic> toJson() => _$UserToJson(this);
     }
     ```

     接着，命令行执行代码生成。有两种方式：

     ```bash
     # 一次性生成
     flutter packages pub run build_runner build
     
     # 持续性生成，会在后台运行一个watcher。后续的都会自动生成代码。
     flutter packages pub run build_runner watch
     ```

     运行完命令之后，会看到同目录下多了一个dart文件：`user.g.dart`。

     ```dart
     // GENERATED CODE - DO NOT MODIFY BY HAND
     
     part of 'user.dart';
     
     // **************************************************************************
     // JsonSerializableGenerator
     // **************************************************************************
     
     User _$UserFromJson(Map<String, dynamic> json) {
       return User(json['name'] as String, json['email'] as String);
     }
     
     Map<String, dynamic> _$UserToJson(User instance) =>
         <String, dynamic>{'name': instance.name, 'email': instance.email};
     
     ```

     使用示例如下：

     ```dart
     //反序列化
     Map userMap = jsonDecode(jsonString);
     var user = User.fromJson(userMap);
     //序列化
     //这里不需要再调用`user.toJson()`，因为`jsonEncode` 会内部调用该方法。
     String json = jsonEncode(user);
     ```

     <br/>



2. ### 状态栏、底部虚拟按键、全屏的代码设置

   ```dart
   static fullScreen() {
       //不显示顶部状态栏以及底部虚拟按键
       SystemChrome.setEnabledSystemUIOverlays([]);
   }
   
   static normalScreen() {
       SystemChrome.setEnabledSystemUIOverlays([SystemUiOverlay.top, SystemUiOverlay.bottom]);
   }
   ```

   <br/>

3. ### ThemeData 简单解析

   `MaterialApp` 下的`theme`可以设置一系列的主题颜色，包括暗黑主题、ButtonThemeData、InputDecorationTheme等。后续再深入。

   <br/>



4. ### Button控件

   按钮控件有`RaisedButton`、`DropdownButton`、 `SimpleDialogOption`、`IconButton`、`InkWell`、`RawMaterialButton`。通过包裹`ButtonTheme`可以设置按钮的宽高、背景颜色等。

   <br/>



5. ### 让子控件充满父控件的宽度

   通过设置`width: double.infinity` 可以实现，某些情况下会报错（跟父控件的BoxConstraints有关，待深入）。

   <br/>

6. ### 关于`Stack`控件

   - 类似android中的`FrameLayout`, 适合有视图重叠的绝对布局

   - 它是多孩子模型；它的孩子又分成：

     - `positioned` widget。 即用`Positioned`控件包裹的widget。
     - `non-positioned` widget

   - `stack`会调整自身的size, 去包含所有的`non-positioned`的widget。然后，`positioned`的widget根据该size去布局。

   - `positioned` widget 通过调整`top/bottom/left/right` 来改变位置大小。如设置`left:0;right:0` 可使自身与`stack`同宽。

   <br/>

7. ### TabBar 的简单使用

   先看代码：

   ```dart
   class ... with TickerProviderStateMixin {
      
       TabController _tabController = TabController(length: 2, vsync: this);
   
       TabBar(
           tabs: [Tab(text: "外部用户"), Tab(text: "内部用户")],
           controller: _tabController,
           isScrollable: true,
           indicatorColor: Colors.white,
           indicatorWeight: 2,
           indicatorSize: TabBarIndicatorSize.tab,
           labelPadding: EdgeInsets.only(left: 45, right: 45),
           labelColor: Colors.white,
           labelStyle:
           TextStyle(fontSize: 17, fontWeight: FontWeight.bold),
           unselectedLabelColor: Colors.white,
           unselectedLabelStyle: TextStyle(fontSize: 16),
       )
       
   }
   
   ```

   - `TabBar`涉及到动画，需要minix `TickerProviderStateMixin`

   - `TabBar`中indicator相关的都是与底部线条相关， label与tab文件相关：

     - `indicatorColor` ： 底部线条颜色
     - `indicatorWeight` : 底部线条厚度
     - `indicatorSize` : 底部线条大小。有两种：`TabBarIndicatorSize.tab` （跟Tab同宽）、`TabBarIndicatorSize.label`(跟字体同宽)

   - 通过`TabController.index`可以拿到当前选中的`tab`位置

   <br/>

8. ### TextField的简单使用

   - `obscureText` 设置输入是否隐藏
   - 通过`TextEditingController.text`获取输入值
   - `InputDecoration`中的border有三种类型：
     - `UnderlineInputBorder` 下划线，默认是这个
     - `OutlineInputBorder` 长方形的边框
     - `InputBorder.none` 无边框

   <br/>

9. ### 键盘弹出时window resize导致页面变形

   通过设置`Scaffold.resizeToAvoidBottomPadding: false` 来避免该问题。

   <br/>

10. ### 转场动画

  `Flutter`中默认的转场动画是自下而上，而主流的转场动画是水平移动。借助`PageRouteBuilder`可自定义转场动画，代码示例：

  ```dart
  import 'package:flutter/material.dart';
  
  class HorizontalSlideRoute extends PageRouteBuilder {
    HorizontalSlideRoute(Widget widget)
        : super(
              opaque: true,
              pageBuilder: (_, __, ___) => widget,
              transitionDuration: Duration(milliseconds: 200),
              transitionsBuilder: (_, animation, secondaryAnimation, child) {
                return SlideTransition(
                    position:
                        Tween<Offset>(begin: Offset(1.0, 0.0), end: Offset.zero)
                            .animate(animation),
                    child: child);
              });
  }
  ```

  其中最主要是`transitionsBuilder`属性，其中：

  - `Animation<double> animation` 指新入栈的页面的过程动画，`Animation<double> secondaryAnimation`指当前页面的过程动画。`child`就是`pageBuilder` 返回的widget, 也就是新的页面。
  - 借助`SlideTransition` 来实现水平移动动画。

  更进一步，封装一个转场类，避免每次都要传入该类：

  ```dart
  import 'package:demo1/util/HorizontalSlideRoute.dart';
  import 'package:flutter/widgets.dart';
  
  class PRoute {
  
    static PRoute _instance;
  
    Map<String, Widget> routeMap = {};
  
    PRoute._default();
  
    factory PRoute.get() {
      if (_instance == null) {
        _instance = PRoute._default();
      }
      return _instance;
    }
  
    pushNameH(BuildContext context, String name) {
      if (routeMap.containsKey(name)) {
        pushH(context, routeMap[name]);
      }
    }
  
    replaceNameH(BuildContext context, String name) {
      if (routeMap.containsKey(name)) {
        replaceH(context, routeMap[name]);
      }
    }
  
    pushH(BuildContext context, Widget widget) {
      Navigator.push(context, HorizontalSlideRoute(widget));
    }
  
    replaceH(BuildContext context, Widget widget) {
      Navigator.pushReplacement(context, HorizontalSlideRoute(widget));
    }
  
    back(BuildContext context) {
      Navigator.canPop(context)? Navigator.pop(context) : print("can not back");
    }
  
    dismiss(BuildContext context) {
      Navigator.canPop(context)? Navigator.pop(context) : print("can not dismiss");
    }
  
  }
  ```

  在`main()`开始时可设置route映射：

  ```dart
  void main() {
    PRoute.get().routeMap = {
      "login": Login(),
    };
    runApp(MyApp());
  }
  ```

  最后使用：

  ```dart
  PRoute.get().replaceNameH(context, "login");
  ```

  <br/>

11. ### 关于Redux

    在`react-native`中接触到这个概念，然后`Flutter`中也有这个。对它的印象不太良好，在`Flutter`中使用的话，UI入侵性很强，几个概念也比较晦涩难懂，总的来说就是不好用。

    作为替代框架，我自己撸了一个简易版，借鉴了android中`EventBus`的概念。源代码如下：

    ```dart
    typedef EventFunction<T> = Function(T t);
    
    class Driver {
    
      static Driver _instance;
    
      factory Driver.get() {
        if (_instance == null) {
          _instance = Driver._default();
        }
        return _instance;
      }
    
      Driver._default();
    
      Map<Type, Set<Function>> registers = {};
    
      register<T>(EventFunction<T> func) {
        Type t = T;
        if (registers.containsKey(t)) {
          var result = registers[t].add(func);
          print("add result: $result");
        } else {
          print("register $t, $func");
          registers[t] = Set();
          registers[t].add(func);
        }
      }
    
      unRegister<T>(EventFunction<T> func) {
        Type t = T;
        if (registers.containsKey(t)) {
          bool result = registers[t].remove(func);
          print("unregister $t $func, result $result");
        }
      }
    
      dispatch(dynamic event) {
        print("start dispatch: $event, ${event.runtimeType.toString()}");
        if (registers.containsKey(event.runtimeType)) {
          registers[event.runtimeType].forEach((f) {
            print("run $f");
            f(event);
          });
        }
      }
    
    }
    ```

    过程是这样：

    1. 将方法注册到`Driver`，方法体内可以调用`setState`以及event自身带的值来改变UI。
    2. 在其他地方，生成一个event(任何类型)。
    3. 通过`Driver.dispatch(event)` 分发出去，符合要求的已注册的方法会自动运行，相当于一个事件冒泡。

    <br/>

    代码示例：

    1. 创建一个event

    ```dart
    class TestEvent {
    
      int count;
    
      TestEvent(this.count);
    
      @override
      String toString() {
        return "count: $count";
      }
    }
    ```

    2. 在某个`StatefulWidget State`中创建一个事件并注册：

    ```dart
    class _LoginState extends State<Login> {
        
        _LoginState() {
            Driver.get().register(eventFunc);
        }
        
        void dispose() {
            super.dispose();
            Driver.get().unRegister(eventFunc);
        }
        
        eventFunc(TestEvent event) {
            setState() {
                counter = event.count;
            }
        }
        
    }
    ```

    3. 在其他页面中，通知页面更新：

    ```dart
    TestEvent event = TestEvent(2019);
    Driver.get().dispatch(event);
    ```
