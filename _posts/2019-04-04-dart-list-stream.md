---
layout: post
title: Dart中Iterable、Stream的糖语法
tags: [Dart]
---

### Iterable

##### setRange

将`iterable`跳过`skipCount`之后的值，依次赋值到本list的[start,end)上。

```dart
List<String> list = ["a", "b", "c", "d", "e"];
List<String> list2 = ["1", "2", "3", "4", "5"];
list.setRange(0, 2, list2, 2);
print(list);

//[3, 4, c, d, e]
```

##### sublist

返回list的[start, end)部分。

> list方法中所有涉及到start、end参数的，都是[start, end)左闭右开的。

```dart
//list: [3, 4, c, d, e]
print(list.sublist(0, 2));

//[3, 4]
```

##### replaceRange

将list的[start, end)部分元素替换成`replacement`的元素。

```dart
//list: [3, 4, c, d, e]
list.replaceRange(0, 2, ["a", "b"]);
print(list);

//[a, b, c, d, e]
```

##### retainWhere

只保留满足条件的元素。

```dart
//list: [a, b, c, d, e]
list.retainWhere((e) => e.compareTo("b") > 0 && e.compareTo("e") < 0);
print(list);

//[c, d]
```

##### fillRange

使用`fillValue`替换[start, end)范围的元素值。

```dart
//list: [c, d]
list.fillRange(0, 1, "x");
print(list);

//[x, d]
```

##### removeWhere

只移除满足条件的元素，与`retainWhere`相反。

```dart
//list: [x, d]
list.removeWhere((e) => "x" == e);
print(list);

//[d]
```

##### shuffle

随机打乱list元素顺序。

```dart
//list: ["a", "b", "c", "d", "e"]
list.shuffle();
print(list);

//[d, a, e, c, b]
```

##### join

通过连词符将list元素连成字符串。

```dart
//list: ["a", "b", "c", "d", "e"]
print(list.join(","));

//a,b,c,d,e
```

##### where

`where`与`retainWhere`不同在于： 它不返回真实列表数据，而是满足条件的计算表达式。真正使用时才逐个计算出值。因此它可以多次使用，相互不受影响。

```dart
//list: ["a", "b", "c", "d", "e"]
var tempList = list.where((e) => e.compareTo("b") > 0 && e.compareTo("e") < 0);
tempList.forEach(print);

//c
//d
```

##### skipWhile

依次跳过满足条件的元素，直到遇到第一个不满足的元素x，并停止筛选。返回从x开始的所有元素。跟`where`返回一样，是一个`lazy Iterable`。

```dart
//list: ["a", "b", "c", "d", "e"]
tempList = list.skipWhile((e) => e.compareTo("c") < 0 || e.compareTo("d") > 0);
print(tempList);

//(c, d, e)
```

##### takeWhile

依次选择满足条件的元素，直到遇到第一个不满足的元素x，并停止选择。返回从0开始并截止到x前一个元素的所有元素，是一个`lazy Iterable`。

```dart
//list: ["a", "b", "c", "d", "e"]
tempList = list.takeWhile((e) => e.compareTo("c") < 0 || e.compareTo("d") > 0);
print(tempList);

//(a, b)
```

##### every

列表中所有的元素是否都满足条件。

```dart
//list: ["a", "b", "c", "d", "e"]
var result = list.every((e) => e.compareTo("a") >= 0);
print(result);

//true
```

#####  reduce

将列表元素逐个联合。

```dart
//list: ["a", "b", "c", "d", "e"]
var result2 = list.reduce((e1, e2) => "$e1-$e2");
print(result2);

//a-b-c-d-e
```

##### fold

设置头`initialValue`, 将头与列表元素逐个联合。

```dart
//list: ["a", "b", "c", "d", "e"]
var result = list.fold("start:", (e1, e2) => "$e1 $e2");
print(result);

//start: a b c d
```

##### singleWhere

列表中若只有一个元素满足条件时返回这个值；若有多个会报错；若一个没有返回`orElse`返回的值；

```dart
//list: ["a", "b", "c", "d", "e"]
var result3 = list.singleWhere((e) => e.compareTo("e") >= 0, orElse: () => "null");
print(result3);

//e
```

##### expand

等价于`flatMap`。

```dart
//list: ["a", "b", "c", "d", "e"]
tempList = list.expand((e) => [e, e]);
print(tempList);

//(a, a, b, b, c, c, d, d, e, e)
```

<br/>

### Stream

#### Stream的生成

##### Stream.periodic

周期性发射事件的流。

```dart
//每3秒发射一次。0,1,4,9,16,25，...
var stream = Stream.periodic(Duration(seconds: 3), (count) => count * count);
```

##### Stream.eventTransformed

将当前流转换成`Stream<T>`（`T`为任意类型）的流。

```dart
class MySink implements EventSink<String> {
  EventSink<String> _originalSink;

  MySink(this._originalSink);

  @override
  void add(String event) {
    _originalSink.add(event + event);
  }

  @override
  void addError(Object error, [StackTrace stackTrace]) {
    _originalSink.addError(error, stackTrace);
  }

  @override
  void close() {
    _originalSink.close();
  }
}

//将当前的stream流转成Stream<String>类型的流，且是event -> event + event 的映射逻辑。
Stream.eventTransformed(stream, (EventSink<String> s) => MySink(s));
```

##### Stream.fromIterable

发射`Iterable`元素的流。

```dart
var stream = Stream.fromIterable(["a", "b", "c", "d"]);
```

##### Stream.fromFuture/Stream.fromFutures

接收一个或多个`Future`, 并等待`Future`完成后将结果发射出去的流。

##### Stream.empty

空的流，只会发射`done`事件。

> stream有三种类型的事件：data、done、error。若所有元素都发射完，最后会发射`done`事件。若出现error, 会发射`error`事件。

##### Asynchronous Generator

异步发射器也会生成流。

```dart
Stream<int> createStream() async* {
  var c = 1;
  while (c <= 5) {
    yield c++;
  }
}

var stream = createStream();
```

#### Stream的方法

`Stream`有很多方法与`Iterable`类似，可参考`Iterable`的部分。

##### listen

监听流的事件，包括`data`事件、`done`事件、`error`事件。

```dart
var stream = Stream.fromIterable(["a", "b", "c", "d"]);
stream.listen((e) => print("get: $e"), 
              onError: (err) => print("error: $err"), 
              onDone: () => print("done"));

//get: a
//get: b
//get: c
//get: d
//done
```

##### transform

将当前流转换成`Stream<T>`（`T`为任意类型）的流。

```dart
//将Stream<int>转成Stream<String>
var stream = Stream.fromIterable([1, 2, 3, 4]);
stream.transform(StreamTransformer.fromBind((e) => e.map((t) => utf8.decode([96 + t]))))
    .listen((e) => print("get: $e"), 
            onError: (err) => print("error: $err"), 
            onDone: () => print("done"));
//get: a
//get: b
//get: c
//get: d
//done
```

##### asBroadcastStream

将当前流变成广播流。普通的流只能被`listen`一次，而广播流可以被`listen`多次。

```dart
StreamSubscription<int> ss;
//将一个定时发射流变成广播流。
var stream2 = Stream.periodic(Duration(seconds: 3), (count) => count * count).asBroadcastStream(onListen: (e) {
  //保存广播流的订阅变量。它能够控制订阅的暂停、重新开始以及取消。
  ss = e;
});

//7秒后暂停订阅
Future.delayed(Duration(seconds: 7), () {
  print("pause");
  //暂停后，4秒之后重新开始订阅
  ss.pause(Future.delayed(Duration(seconds: 4)));
});

//可以监听多次。
stream2.listen(print);
stream2.listen(print);

/*
0
0
1
1
pause
4
4
*/
```

#####  drain

不关心`data`事件，只关心`done`或`error`事件。一旦流发射了`done`/`error`事件，`Future`完成并返回`futureValue`。

```dart
var stream = Stream.fromIterable([1, 2, 3, 4]);
stream.drain("finished").then(print);

//finished
```

##### handleError

包裹住当前的流，并且拦截满足`test`的`error`事件。若没有定义`test`，则默认拦截全部`error`事件。

```dart
var stream = Stream.fromIterable([1, 2, 3, 4]);
stream.map((e) => e as String).handleError((err) => print("here:$err"), test: (err) => true).listen(print, onError: print);

/*
here:type 'int' is not a subtype of type 'String' in type cast
here:type 'int' is not a subtype of type 'String' in type cast
here:type 'int' is not a subtype of type 'String' in type cast
here:type 'int' is not a subtype of type 'String' in type cast
*/
```

##### timeout

在监听过程中，若超过一定时间没有发射事件，就回调`onTimeout`方法。若没有定义`onTimeout`，直接报错`TimeoutException`。

```dart
Stream.periodic(Duration(seconds: 5))
    .timeout(Duration(seconds: 2), onTimeout: (sink) {
  sink.add("time out");
  sink.close();
}).listen((e) => print("get: $e"), 
          onError: (err) => print("error: $err"), 
          onDone: () => print("done"));


//get: time out
//done
```

<br/>

