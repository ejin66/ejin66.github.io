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
class JsonTest with PartOfJsonTest {

  String msg;

  JsonTest(this.msg);

  @override
  String toString() {
    return 'JsonTest{msg: $msg}';
  }
}

@JsonInflater()
class JsonTest2<K> with PartOfJsonTest2 {

  String msg;
  K data;

  JsonTest2(this.msg, this.data);



  @override
  String toString() {
    return 'JsonTest2{msg: $msg, data: $data}';
  }
}
```

测试：

```dart
import 'dart:convert';

import 'package:jsoninflater/model_test.dart';

main() {

  var test = JsonTest("json test");

  var test2 = JsonTest2("json test 2", [test]);

  var parseTest = parse<JsonTest>(test.toJson());

  print("$parseTest, ${parseTest.runtimeType}"); // -> JsonTest{msg: json test}, JsonTest

  var parseTest2 = parse<JsonTest2<List<JsonTest>>>(test2.toJson());

  print("$parseTest2, ${parseTest2.runtimeType}"); // -> JsonTest2{msg: json test 2, data: [JsonTest{msg: json test}]}, JsonTest2<dynamic>

  var parseTest3 = PartOfJsonTest2.parse<List>(test2.toJson());

  print("$parseTest3, ${parseTest3.runtimeType}"); // -> JsonTest2{msg: json test 2, data: [{msg: json test}]}, JsonTest2<List<dynamic>>
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
   class JsonTest with PartOfJsonTest {
   
     String msg;
   
     JsonTest(this.msg);
   
     @override
     String toString() {
       return 'JsonTest{msg: $msg}';
     }
   }
   
   @JsonInflater()
    class JsonTest2<K> with PartOfJsonTest2 {

      String msg;
      K data;

      JsonTest2(this.msg, this.data);

      @override
      String toString() {
        return 'JsonTest2{msg: $msg, data: $data}';
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

   - `parse<T>()`。本框架提供的一个将Map转换成模型的方法。

     ```dart
     var test = JsonTest("json test");
     var parseTest = parse<JsonTest>(test.toJson());
     print("$parseTest"); // -> JsonTest{msg: json test}
     ```

   - `PartOf${className}<T>.parse()`。本框架提供的另外一个将Map转换成模型的方法。

     ```dart
     var parseTest4 = PartOfJsonTest.parse(test.toJson());
     print("$parseTest4"); // -> JsonTest{msg: json test}
     ```

   - `parse<T>()` 与 `PartOf${className}<T>.parse()`的差别：

     - 前置支持泛型嵌套，后者不支持。

     - `parse<T>()`虽然支持泛型嵌套，但最后返回的类型与实际泛型有差异：

       ```dart
       main() {
       
         var test = JsonTest("json test");
       
         var test2 = JsonTest2("json test 2", [test]);
       
         var parseTest2 = parse<JsonTest2<List<JsonTest>>>(test2.toJson());
       
         print("$parseTest2, ${parseTest2.runtimeType}");
         // -> JsonTest2{msg: json test 2, data: [JsonTest{msg: json test}]}, JsonTest2<dynamic>
           
         //不支持嵌套泛型，否则报错。
         var parseTest3 = PartOfJsonTest2.parse<List>(test2.toJson());
          // -> JsonTest2{msg: json test 2, data: [{msg: json test}]}, JsonTest2<List<dynamic>>
       
         print("$parseTest3, ${parseTest3.runtimeType}");
           
           
         print(parseTest2 is JsonTest2);  // -> true
         print((parseTest2 as JsonTest2).data is List); // -> true
         print(((parseTest2 as JsonTest2).data as List).first is JsonTest); // -> true
       }
       ```

       虽然`parse<JsonTest2<List<JsonTest>>>()`的结果是`JsonTest2<dynamic>`， 与泛型不一致，但本质是按照泛型解析的，其`runtimeType`是一致的。

<br/>



### 项目地址

[JsonInflater](https://github.com/ejin66/JsonInflater)
