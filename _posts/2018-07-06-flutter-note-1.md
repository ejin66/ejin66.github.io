---
layout: post
title: Flutter 初次上手笔记
tags: [Flutter]
---

### 1. Flutter 安装

Flutter是Google开源的一套跨平台框架，开发语言是 [Dart](https://www.dartlang.org/)，目前处于pre-release阶段。

Flutter 安装官方指南：[https://flutter.io/get-started/install/](https://flutter.io/get-started/install/)。 

<br />



### 2. 项目结构

Flutter项目结构：

> - android
> - ios
> - lib
> - pubspec.yaml

<br/>



- 配置文件pubspec.yaml<br/>

  _app图标更改貌似只能在android/ios目录下修改原配置文件?_

  ```yaml
  # app name
  name: demo
  description: A new Flutter application.
  
  dependencies:
    flutter:
      sdk: flutter
  
    # 第三方依赖
    # The following adds the Cupertino Icons font to your application.
    # Use with the CupertinoIcons class for iOS style icons.
    cupertino_icons: ^0.1.2 
    english_words: ^3.1.0
  
  dev_dependencies:
    flutter_test:
      sdk: flutter
  
  
  flutter:
  
    # The following line ensures that the Material Icons font is
    # included with your application, so that you can use the icons in
    # the material Icons class.
    uses-material-design: true
    
    # 资源文件
    assets:
        - images/macat.jpg
  ```

<br/>



- Flutter 的几个特点<br/>  

  _1. 入口函数_

  ```dart
  void main() => runApp(MyApp());//runApp是内置方法，需传入Widget实例，是程序的入口
  ```

  _2. Flutter中大部分布局相关类都是Widget的子类，像Navigator, Center等。接触到两个相似的类：StatelessWidget,  StatefulWidget:_

  > StatelessWidget：
  >
  > 它是一个抽象类， 需要实现: Widget build(BuildContext context)， 通过它来提供视图。
  >
  > 从命名就可以看出， 它的state是不能够更改的，即不能通过setState来刷下页面。

  > StatefulWidget:
  >
  > 它的状态时可以更改的，通过方法setState， 可以使自身刷新重绘。（调用setState的widget重绘还是StatefulWidget整重绘？）
  >
  > 与StatelessWidget不同，StatefulWidget需要实现： createState()方法，返回State<？ extends StatefulWidget>继承类，再通过state类的 Widget build(BuildContext context) 方法来提供视图。

  _3. Dart语言的特点，在变量前加入下划线，该变量就变成了私有变量_

  ```dart
  final _biggerFont = const TextStyle(fontSize: 18.0);
  ```

  _4. 由于在配置文件中设置了 uses-material-design: true ，可以直接使用Icons类下的图标_

  ```dart
  Icons.favorite;
  Icons.favorite_border;
  ```

<br/>



- 接触到的Widget 实现类

  - MaterialApp

  - Scaffold   UI布局脚手架，可以设置appbar, body, drawer, bottomNavigation等，大部分的页面布局都可以用它来实现。

    - appBar
    - body

  - Center   让child居中显示，它自身的尺寸是这样的：

    > ```tex
    > This widget will be as big as possible if its dimensions are constrained and
    > [widthFactor] and [heightFactor] are null. 
    > 
    > If a dimension is unconstrained
    > and the corresponding size factor is null then the widget will match its
    > child's size in that dimension. 
    > 
    > If a size factor is non-null then the
    > corresponding dimension of this widget will be the product of the child's
    > dimension and the size factor. 
    > 
    > For example if widthFactor is 2.0 then
    > the width of this widget will always be twice its child's width.
    > ```

  - Navigator

  - ListView

  - ListTile        ListView的模板Item

  - Text

    - softWrap          若为false，将视作text主轴上有无限空间，绘制时不会自动换行。若为true，会自动换行。

  - Icon  图标Widget

  - Row，Column

    - children
    - mainAxisAlignment         主轴，column的主轴是Y轴
    - crossAxisAlignment         辅轴，column的辅轴是X轴
    - mainAxisSize
      - MainAxisSize.min      相当于wrap_content
      - MainAxisSize.max      相当于match_parent

  - Expanded        填满主轴的剩余空间；若主轴上有多个Expanded，则按child的factor来分配空间

    - child    必须是 Row/Column/Flex 其中的一种

  - Container         相当于给child widget套上一层容器，布局上可以设置padding/margin/transform等

    - child
    - padding

  - Image

    - 加载asset布局文件

      ```dart
      Image.asset(
          'images/macat.jpg',
          width: 600.0,
          height: 240.0,
          fit: BoxFit.cover
      )
      ```

    - 还有其他用法，好像很厉害的样子(还未研究)

      ```dart
      Image.Image();
      Image.network();
      Image.file();
      Image.memory();
      ```

<br/>



- Flutter代码在lib下，例子如下：

  ```dart
  import 'package:demo/demo2.dart';
  import 'package:flutter/material.dart';
  import 'package:english_words/english_words.dart';
  
  void main() => runApp(new MyApp2());
  
  class MyApp extends StatelessWidget {
  
    @override
    Widget build(BuildContext context) {
      return MaterialApp(
        title: "flutter title",
        theme: ThemeData(
            primaryColor: Colors.white
        ),
        home: Scaffold(
          body: Center(
            child: RandomWords()
          ),
        ),
      );
    }
  }
  
  class RandomWords extends StatefulWidget {
  
    createState() => RandomWordsState();
  
  }
  
  class RandomWordsState extends State<RandomWords> {
    final _suggestions = <WordPair>[];
    final _saved = Set<WordPair>();
    final _biggerFont = const TextStyle(fontSize: 18.0);
  
    @override
    Widget build(BuildContext context) {
      return Scaffold (
        appBar: AppBar(
          title: Text('Startup Name Generator'),
          actions: <Widget>[
            IconButton(icon: Icon(Icons.list), onPressed: _pushSaved,)
          ],
        ),
        body: _buildSuggestions(),
      );
    }
  
    void _pushSaved() {
      Navigator.of(context).push(
        MaterialPageRoute(
            builder: (context) {
              final tiles = _saved.map(
                  (item) {
                    return ListTile(
                      title: Text(
                        item.asPascalCase,
                        style: _biggerFont,
                      ),
                    );
                  }
              );
              final divided = ListTile.divideTiles(context: context, tiles: tiles).toList();
              return Scaffold(
                appBar: AppBar(
                  title: Text('My favorite')
                ),
                body: ListView(children: divided,),
              );
            }
        )
      );
    }
  
    Widget _buildSuggestions() {
      return ListView.builder(
          padding: const EdgeInsets.all(16.0),
          itemBuilder: (context, i) {
            if (i.isOdd) return Divider();
  
            final index = i ~/2;
            if (index >= _suggestions.length) {
              _suggestions.addAll(generateWordPairs().take(10));
            }
            return _buildRow(_suggestions[index]);
          }
      );
    }
  
    Widget _buildRow(WordPair suggestion) {
      final alreadySaved = _saved.contains(suggestion);
      return ListTile(
        title: Text(
          suggestion.asPascalCase,
          style: _biggerFont,
        ),
        trailing: Icon(
          alreadySaved ? Icons.favorite : Icons.favorite_border,
          color: alreadySaved ? Colors.red : null,
        ),
        onTap: () {
          setState(() {
            if (alreadySaved) {
              _saved.remove(suggestion);
            } else {
              _saved.add(suggestion);
            }
          });
        },
      );
    }
  }
  ```

<br/>





**目前只接触到Flutter的冰山一角，等后续再慢慢补充吧！**
