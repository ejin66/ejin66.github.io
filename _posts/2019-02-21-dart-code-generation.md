---
layout: post
title: Flutter中使用metadata生成代码
tags: [Flutter]
---

Dart 中的 `metadata` ，就是Java 中的注解。我们知道，利用注解可以在Java编译期生成代码，那Dart的`metadata`也能够生成代码吗？

答案是肯定的。在Flutter处理`Json`时就是利用了注解`JsonSerializable`帮助我们生成了一部分代码，关于Json的基础操作可以参考 [这一篇](https://ejin66.github.io/2019/02/19/flutter-develop-summary-1.html).

<br/>



接下来，要如何生成代码？

0. 添加依赖。

   ```dart
   dev_dependencies:
     build_runner: ^1.0.0
     source_gen: ^0.9.0
   ```

1. 创建一个注解。

   ```dart
   class TestMetadata {
     
     const TestMetadata();
     
   }
   ```

2. 创建`GeneratorForAnnotation`子类。该类就是用来生成具体内容的。

   ```dart
   class TestGenerator extends GeneratorForAnnotation<TestMetadata> {
     @override
     generateForAnnotatedElement(Element element, ConstantReader annotation, BuildStep buildStep) {
      
       return "//TestMetadata generate this";
     }
     
   }
   ```

   > Element就是注解标注的那个元素，可以是类、变量、方法等。

3. 创建一个方法体，并返回一个`Builder`。

   ```dart
   Builder testBuilder(BuilderOptions options) => 
       SharedPartBuilder([TestGenerator()], "test_metadata");
   ```

   > `SharedPartBuilder` 的第一个入参是生成器的集合， 第二个入参可以理解为builder的一个别名。

4. 在根目录下创建一个文件: `build.yaml`。关于这个配置文件的详细说明，参考[这一篇](https://github.com/dart-lang/build/blob/master/build_config/README.md)。

   ```yaml
   targets:
     $default:
       builders:
         demo2|test_metadata:
           enabled: true
   
   builders:
     test_metadata:
       import: "package:demo2/test.dart"
       builder_factories: ["testBuilder"]
       build_extensions: {".dart": ["test_metadata.g.part"]}
       auto_apply: dependents
       build_to: cache
       applies_builders: ["source_gen|combining_builder"]
   ```

5. 创建一个测试类，并添加注解。

   ```dart
   import 'package:demo2/test.dart';
   
   part 'test2.g.dart';
   
   @TestMetadata()
   class TestModel {
   
   }
   ```

6. 在项目根目录运行命令：

   ```bash
   flutter packages pub run build_runner build
   ```

7. 一切顺利的话，会在同目录下生成`test2.g.dart`文件。

   ```dart
   // GENERATED CODE - DO NOT MODIFY BY HAND
   
   part of 'test2.dart';
   
   // **************************************************************************
   // TestGenerator
   // **************************************************************************
   
   //TestMetadata generate this
   ```

<br/>



扩展一下，在第3步中，创建了一个继承自`GeneratorForAnnotation` 的子类 `TestGenerator`。这里可能会有一个小问题：当有多个地方被注解，在执行代码生成时，彼此是孤立的，不知道对方的存在。无法生成全局性的代码。

此时，可以直接继承`Generator`，可以对多个Element做集中处理。`GeneratorForAnnotation` 就是继承自`Generator`，贴一下它的源码：

```dart
abstract class GeneratorForAnnotation<T> extends Generator {
  const GeneratorForAnnotation();

  TypeChecker get typeChecker => TypeChecker.fromRuntime(T);

  @override
  FutureOr<String> generate(LibraryReader library, BuildStep buildStep) async {
    var values = Set<String>();

    for (var annotatedElement in library.annotatedWith(typeChecker)) {
      var generatedValue = generateForAnnotatedElement(
          annotatedElement.element, annotatedElement.annotation, buildStep);
      await for (var value in normalizeGeneratorOutput(generatedValue)) {
        assert(value == null || (value.length == value.trim().length));
        values.add(value);
      }
    }

    return values.join('\n\n');
  }

  generateForAnnotatedElement(
      Element element, ConstantReader annotation, BuildStep buildStep);
}
```

> 在`generate`方法中，通过`library.annotatedWith(typeChecker)`可以拿到所有特定注解的元素

<br/>



Demo地址: [demo](https://github.com/ejin66/CodeGenerationDemo)
