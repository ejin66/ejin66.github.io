---
layout: post
title: Dart学习笔记
tags: [Dart]
---

0. built-in types:
      - Numbers
        - int. 8个字节[-2^63, 2^63；若转成javaScript, 区间是[-2^53, 2^53）

        - double. 8个字节
          （int/double 都是num的子类型）
      - Strings
        - String。 UTF-16编码。

        - 支持引号内$variable / ${expression}

        - 支持单引号/双引号。

        - 用'''或""" 定义多行。

        - 可以用'+'连接两个string（'+'能省略）。

        - r'' 来创建一个raw string.
      - Booleans
        - bool
      - Lists. In dart, list = array。
        - List<T>.
        - var list = [1,2,3], list的类型会被推断为List<int>.
      - Maps
          -  Map. 
          -  var m = {"key1":"value1", "key2":"value2"}, list的类型会被推断为Map<String, String>.
          -  var m = Map()
          -  Runes. the UTF-32 code points of a string. ?
      - Symbols. ?
<br/>
	
1. 变量定义时：dynamic/var/final/const/其他确定的类型
<br/>
	
2. dymamic 与 object的区别?
<br/>

3. everything is inherited from object, include null
<br/>

4. 支持方法嵌套。可以在方法体内再定义方法。
<br/>

5. 没有权限修饰符（如public/private。若class/function/variable 名称以 '_'开头, 只对所在library可见。
<br/>

6. 跟java一样，赋值是引用传递。
<br/>

7. 未初始化的值默认都是null, 包括numbers(int/double)。
<br/>

8. 定义的方法也是对象，类型是Function。 因此方法可以像变量一样进行传递。
<br/>

9. 当能推断返回值类型时，方法返回类型可省略。当方法体内只有一行时，可使用 ... => ...。
<br/>

10. 方法定义中两种可选形式：
	- Optional named parameters
		- void test({bool arg1, String arg2})
		- 设置必须的入参：void test({bool arg1, @required String arg2})
		- 设置入参默认值： void test({bool arg1, String arg2 = "str123"})
	- Optional positioned parameters
		- String say(String from, String msg, [String device])
		- 设置入参默认值：String say(String from, String msg, [String device = "android"])
<br/>

11. 匿名方法。
	```dart
	var loudify = (msg) => '!!! ${msg.toUpperCase()} !!!';
	list.forEach((item) => print('{list.indexOf(item)}: item'));
	```
<br/>

12. 特殊的分配操作符'??='。 只有在变量为null时才会给变量分配。
	```dart
		b ??= 1 
		//等价于
		b = b ?? 1.
	```
<br/>

13. 判空操作符'??'。
	```dart
	String playerName(String name) => name ?? 'Guest';
	```
<br/>

14. 瀑布式操作符'..'。
	```dart
	void main() {
		querySelector('#sample_text_id')
		..text = 'Click me!'
		..function-call()
	}
	```
<br/>

15. 非空操作符'?.'。
	- a?.print(). 当a为非空变量时，调用print()方法。
<br/>

16. 抛异常/捕获异常。
	- throw ...; 可以抛出任何类型，包括 Exception/Error/any objects

	- 捕获异常时，若没有指定具体类型，表示捕获所有的被抛出的object
	```dart
	try {
		breedMoreLlamas();
	} on OutOfLlamasException {
		 // A specific exception
		 buyMoreLlamas();
	} on Exception catch (e) {
		 // Anything else that is an exception
		 print('Unknown exception: e');
	} catch (e) {
		 // No specified type, handles all
		 print('Something really unknown: e');
	}
	```

	- catch(e, s) 若catch定义两个入参，则's'表示报异常方法的调用栈信息。

	- rethrow; 在catch中调用rethrow可继续抛出该异常。
<br/>

17. object.runtimeType 获取变量的类型。
<br/>

18. 构造方法中的糖语法：
	```dart
	class Point {
		num x, y;

		Point(this.x, this.y);

		Point(num x, num y) {
		   this.x = x;
		   this.y = y;
		}
	}
	```
<br/>

19. 若没有定义构造方法，默认有一个没有入参的构造方法。
<br/>

20. 构造方法不能被继承。但可通过'Named constructors'实现。
<br/>

21. 实例初始化的过程： Initializer list -> super class constructor -> constructor.
	```dart
	Point.fromJson(Map<String, num> json)
		: x = json['x'],y = json['y'] {
		print('In Point.fromJson(): (x, y)');
	}
	```
   > fromJson是类Point的Named constructors, ':'后面的是 Initializer list。在Initializer list中，无法使用关键字'this'.
<br/>

22. 成员变量的 getter/setter.
	```dart
	class Rectangle {
		...
		num get right => left + width;
		set right(num value) => left = value - width;
		num get bottom => top + height;
		set bottom(num value) => top = value - height;
	}
	```
<br/>

23. 每个类都默认定义了一个包括了所有成员的接口。其他的类可通过关键字'implements'来继承。
<br/>

24. 重写操作符。
	```dart
	class Vector {
		final int x, y;

		Vector(this.x, this.y);

		Vector operator +(Vector v) => Vector(x + v.x, y + v.y);
		Vector operator -(Vector v) => Vector(x - v.x, y - v.y);
	}
	```
   > 如果要重写'=='操作符，必须重写hashcode方法。
<br/>

25. mixins. 通过关键字'with'实现代码复用。被复用的类不能定义构造方法，且如果被复用类没有其他的用途，可使用关键字'mixin'代替'class'.
	```dart
	mixin Musical {
		bool canPlayPiano = false;
		bool canCompose = false;
		bool canConduct = false;

		void entertainMe() {
			if (canPlayPiano) {
			  print('Playing piano');
			} else if (canConduct) {
			  print('Waving hands');
			} else {
			  print('Humming to self');
			}
		}
	}
	class Musician extends Performer with Musical {
		// ···
	}
	```
<br/>

26. 库的懒加载。使用关键字'deferred as xxx'来实现。在使用该库时，需要先手动加载。
	```dart
	Future greet() async {
		await hello.loadLibrary();
		hello.printGreeting();
	}
	```
<br/>

27. 异步方法：await/async.
	- await expression. expression 返回一个Future<T>类型，若原返回类型不是Future类型，会自动包装成Future类型。await expression会返回T object类型

	- 要使用await, 必须在async方法体中

	- 可使用try/catch 捕获 await expression 中的异常

	- 若在async方法中，没有定义返回值，默认返回Future<Void>
<br/>
	
28. Generators. 分同步生成器、异步生成器两种。同步生成器使用sync* + yield，返回Iterable类型。异步生成器使用async* + yield 返回Stream类型。
	- 同步生成器:
	```dart
	Iterable<int> naturalsTo(int n) sync* {
		int k = 0;
		while (k < n) yield k++;
	}
	```

	- 异步生成器（通过aysnc for 使用 stream）：
	```dart
	Stream<int> asynchronousNaturalsTo(int n) async* {
		int k = 0;
		while (k < n) yield k++;
	}
	```
<br/>
	
29. Callable classes. 允许类实例像方法一样调用。要求class先实现call方法。
	```dart
	class WannabeFunction {
		call(String a, String b, String c) => 'a b $c!';
	}

	main() {
		var wf = WannabeFunction();
		var out = wf("Hi","there,","gang");
		print('$out');
	}
	```
<br/>

30. typedef 关键字。 给一个方法类型设定一个类型别名。
	```dart
	typedef Compare = int Function(Object a, Object b);
	```
<br/>

31. Metadata(注解). Two annotations are available to all Dart code: @deprecated and @override.



    


	
	
	
	
	
	
	
	
	
	
