---
layout: post
title: Flutter中Json序列化之--支持泛型
tags: [Flutter]
---

在Flutter中，`Json`的序列化与反序列化会比较麻烦，官方推荐的方式是通过`JsonSerializable` 注解自动生成的代码来实现相似的功能。但是有一个比较棘手的问题：该方式是不支持泛型的。而且，由于不能使用反射的缘故，导致无法像其他语言一样ORM。那有没有其他的途径来达到相似的功能呢？

思路是这样：

> 将项目中的所有模型类集合在一起, 根据不同的入参拿不同的模型。

这样就可能实现类似ORM的操作。

<br/>



### 首先，看下效果：

创建模型：

```dart
import 'package:jsoninflater/jsonInflater.dart';

part 'model_test.g.dart';

@JsonInflater()
class TestNonGenerics with PartOfTestNonGenerics {

  String msg;

  TestNonGenerics(this.msg);

  @override
  String toString() {
    return 'TestNonGenerics{msg: $msg}';
  }
}

@JsonInflater()
class TestGenerics1<K> with PartOfTestGenerics1 {

  String msg;
  K data;

  TestGenerics1(this.msg, this.data);

  @override
  String toString() {
    return 'TestGenerics1{msg: $msg, data: $data}';
  }
}

@JsonInflater()
class TestGenerics2<K> with PartOfTestGenerics2 {

  String msg;
  K data;

  TestGenerics2(this.msg, this.data);


  @override
  String toString() {
    return 'TestGenerics2{msg: $msg, data: $data}';
  }
}
```

测试：

```dart
import 'package:jsoninflater/model_test.dart';


main() {
  var nonGenericsModel = TestNonGenerics("non generics");
  var nonGenericsParse = parse<TestNonGenerics>(nonGenericsModel.toJson());
  print("$nonGenericsParse, ${nonGenericsParse.runtimeType}"); // -> TestNonGenerics{msg: non generics}, TestNonGenerics


  var genericsModel1 = TestGenerics1<TestNonGenerics>("generics model 1", nonGenericsModel);
  var genericsModel1Parse = parse2<TestGenerics1<TestNonGenerics>, TestNonGenerics>(genericsModel1.toJson());
  print("$genericsModel1Parse, ${genericsModel1Parse.runtimeType}"); // -> TestGenerics1{msg: generics model 1, data: TestNonGenerics{msg: non generics}}, TestGenerics1<TestNonGenerics>


  var genericsModel2 = TestGenerics2<TestGenerics1<List<String>>>("generics model 2", TestGenerics1("generics model 1", ["list 1", "list 2"]));
  var genericsModel2Parse = parse4<TestGenerics2<TestGenerics1<List<String>>>, TestGenerics1<List<String>>, List<String>, String>(genericsModel2.toJson());
  print("$genericsModel2Parse, ${genericsModel2Parse.runtimeType}"); // -> TestGenerics2{msg: generics model 2, data: TestGenerics1{msg: generics model 1, data: [list 1, list 2]}}, TestGenerics2<TestGenerics1<List<String>>>
}
```

> 这里面存在一些问题，无法完全支持泛型的嵌套。后面会说明。

<br/>



### 实现原理

核心思路就是上面提到的，将项目中的所有模型集合起来。具体是通过代码生成的方式实现的，关于代码生成，参考这一篇：[Flutter中使用metadata生成代码](https://ejin66.github.io/2019/02/21/dart-code-generation.html)。

项目中的几个注意点：

1. 代码生成使用到的注解`JsonFlater`： 不仅要实现新的逻辑，原`JsonSerializable`支持的功能也必须保留。最简单的做法是将`JsonSerializable`的代码复制过来就可以。

   ```dart
   class JsonInflater {
   
     final bool anyMap;
   
     final bool checked;
   
     final bool createFactory;
   
     final bool createToJson;
   
     final bool disallowUnrecognizedKeys;
   
     final bool explicitToJson;
   
     final FieldRename fieldRename;
   
     final bool generateToJsonFunction;
   
     final bool includeIfNull;
   
     final bool nullable;
   
     final bool useWrappers;
   
     const JsonInflater({
       this.anyMap,
       this.checked,
       this.createFactory,
       this.createToJson,
       this.disallowUnrecognizedKeys,
       this.explicitToJson,
       this.fieldRename,
       this.generateToJsonFunction,
       this.includeIfNull,
       this.nullable,
       this.useWrappers,
     });
   }
   ```

2. 在代码生成类`JsonInflaterGenerator`中，同样需要将`JsonSerializableGenerator`的逻辑搬过来：

   ```dart
   class JsonInflaterGenerator extends Generator {
   
     ...
       
     generateForAnnotatedElement(Element element, ConstantReader annotation, BuildStep buildStep) {
       return JsonSerializableGenerator.withDefaultHelpers([GenericsHelper()]).generateForAnnotatedElement(element, annotation, buildStep);
     }
   
   
   }
   ```

3. `JsonSerializableGenerator`中默认的`helpers`是不支持处理泛型的，所以需要添加一个处理泛型的`helper`:

   ```dart
   class GenericsHelper extends TypeHelper {
     @override
     Object deserialize(DartType targetType, String expression, TypeHelperContext context) {
       if (targetType.element is TypeParameterElement) {
         return "parse<${targetType.name}>($expression, typeName ?? ${targetType.name}.toString())";
       }
       return null;
     }
   
     @override
     Object serialize(DartType targetType, String expression, TypeHelperContext context) {
       if (targetType.element is TypeParameterElement) {
         return "toMap($expression)";
       }
       return null;
     }
   
   }
   ```

<br/>



### 如何使用

1. 创建模型，添加注解`JsonFlater`以及`mixin`类。如：

   ```dart
   @JsonInflater()
   class TestNonGenerics with PartOfTestNonGenerics {
   
     String msg;
   
     TestNonGenerics(this.msg);
   
     @override
     String toString() {
       return 'TestNonGenerics{msg: $msg}';
     }
   }
   
   @JsonInflater()
   class TestGenerics1<K> with PartOfTestGenerics1 {
   
     String msg;
     K data;
   
     TestGenerics1(this.msg, this.data);
   
     @override
     String toString() {
       return 'TestGenerics1{msg: $msg, data: $data}';
     }
   }
   
   @JsonInflater()
   class TestGenerics2<K> with PartOfTestGenerics2 {
   
     String msg;
     K data;
   
     TestGenerics2(this.msg, this.data);
   
   
     @override
     String toString() {
       return 'TestGenerics2{msg: $msg, data: $data}';
     }
   }
   ```

   > `mixin`类是代码生成的，格式是：PartOf${className}。功能是提供`toJson()`、`parse()`方法。

2. 运行命令，生成代码：

   ```bash
   flutter packages pub run build_runner build
   ```

3. 使用：

   - `toJson`.

     ```dart
     var test = JsonTest("json test");
     print(test.toJson()); // -> {msg: json test}
     ```

   - `parse<T>()`。本框架提供的一个将Map转换成模型的方法。提供5种不同泛型数量的方法：

     - `parse<T>`
     - `parse2<T1, T2>`
     - `parse3<T1, T2, T3>`
     - `parse4<T1, T2, T3, T4>`
     - `parse5<T1, T2, T3, T4, T5>`

     比较特殊的是，泛型需要按照如下规则：后一个泛型必须是前面泛型的泛型。如：

     ```dart
     T1 : List<Model<String>>
     T2 : Model<String>
     T3 : String
     ```

     用这样的方式解决泛型嵌套的问题。

     例子：

     ```dart
     var genericsModel1 = TestGenerics1<TestNonGenerics>("generics model 1", nonGenericsModel);
       var genericsModel1Parse = parse2<TestGenerics1<TestNonGenerics>, TestNonGenerics>(genericsModel1.toJson());
       print("$genericsModel1Parse, ${genericsModel1Parse.runtimeType}"); // -> TestGenerics1{msg: generics model 1, data: TestNonGenerics{msg: non generics}}, TestGenerics1<TestNonGenerics>
     ```

   - `PartOf${className}<T>.parse()`。本框架提供的另外一个将Map转换成模型的方法。

     ```dart
     var parseTest4 = PartOfJsonTest.parse(test.toJson());
     print("$parseTest4"); // -> JsonTest{msg: json test}
     ```

   - `parse<T>()` 与 `PartOf${className}<T>.parse()`的差别：前者支持泛型嵌套，后者不支持。

<br/>



### 项目地址

[JsonInflater](https://github.com/ejin66/JsonInflater)
