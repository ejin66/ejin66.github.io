---
layout: post
title: 编译时注解处理实践
tags: [Android, Annotation Processor]
---



### 前言

编译时注解是在项目编译时对相关注解进行处理，主要是自动生成java source code, 能够降低代码的耦合度，减少重复代码等等。像ButterKnife、EventBus等框架，其实现原理都是利用了编译时注解。

> 编译时注解只能根据注解生成源代码，而不能修改已有的代码



### 项目结构

- project
  - app module
  - annotation module
  - compile module

> 项目结构大概只能这样分-，-
>
> app module：
>
> > 需要引入其他两个库：
> >
> > ```groovy
> > apply plugin: 'kotlin-kapt' //kotlin下导入apt插件
> > 
> > android {
> >     defaultConfig {
> >         ...
> >         //设置options
> >         javaCompileOptions {
> >             annotationProcessorOptions {
> >                 includeCompileClasspath = true
> >             }
> >         }
> >     }
> > }
> > 
> > dependencies {
> >     ...
> >     kapt project(':compile') //必须是kapt, 不能是implementation/api/apt
> >     implementation project(':annotation')
> >     ...
> > }
> > ```

> compile module :
>
> > 必须是java library，否则javax.annotation.processing.AbstractProcessor类不能使用
> >
> > ```groovy
> > apply plugin: 'java-library'
> > 
> > dependencies {
> >     implementation 'com.google.auto.service:auto-service:1.0-rc2' //向jvm注册注解处理器的库
> >     api 'com.squareup:javapoet:1.11.1' //源代码生成框架
> >     compile project(path: ':annotation')
> > }
> > 
> > sourceCompatibility = "1.7"
> > targetCompatibility = "1.7"
> > ```



### 具体代码

1. **创建一个注解（annotation module）**

   ```java
   @Retention(RetentionPolicy.CLASS) //作用域
   @Target(ElementType.METHOD) //目标类型
   public @interface InvokeMethod {
   }
   ```

2. **使用该注解( app module )**

   ```kotlin
   class MainActivity : AppCompatActivity() {
   
       override fun onCreate(savedInstanceState: Bundle?) {
           super.onCreate(savedInstanceState)
           setContentView(R.layout.activity_main)
       }
   
       @InvokeMethod
       fun test() {
   
       }
   
       @InvokeMethod
       fun test2(value: String, adapter: BaseAdapter) {
   
       }
   
       @InvokeMethod
       fun test3(value: String): String {
           return ""
       }
   
   }
   ```

3. **编译期处理注解（ compile module ）**

   - 编译时注解处理， 需要实现一个继承AbstractProcessor 的类

     ```java
     @AutoService(Processor.class) //向jvm注册该注解处理类
     public class MyAnnotationProcessor extends AbstractProcessor {
         func init; //初始化方法， 可获取一些变量
         func process; //最关键的方法，在这里处理注解
         func getSupportedAnnotationTypes; //设置支持的注解类型，做过滤
         func getSupportedSourceVersion; //设置支持的java版本
     }
     ```

   - 在初始化方法中， 获取源代码输出依赖的类Filer

     ```java
     filer = processingEnvironment.getFiler();
     ```

   - 设置支持的注解类型

     ```java
     @Override
     public Set<String> getSupportedAnnotationTypes() {
         Set<String> supports = new LinkedHashSet<>();
         supports.add(InvokeMethod.class.getCanonicalName());
         return supports;
     }
     ```

   - 设置支持的java版本

     ```java
     @Override
     public SourceVersion getSupportedSourceVersion() {
         return SourceVersion.latestSupported();//如非有特殊要求，一般这样写就可以了
     }
     ```

   - 核心逻辑在方法process中

     > 大概的思想是： 
     >
     > 先过滤出对应注解的Elements, 按照所在的类进行分组，然后在生成java code。

     查找添加了特定注解的内容

     ```java
     Map<TypeElement, List<ExecutableElement>> maps = new LinkedHashMap<>();
     //查找添加了注解的elements
     for (Element item : roundEnvironment.getElementsAnnotatedWith(InvokeMethod.class)) {
         //对注解使用的正确性进行判断
         if (item.getKind() != ElementKind.METHOD) {
             error("Annotation MainThread must be annotated at method ");
             continue;
         }
         //获取添加了注解的element的修饰符
         Set<Modifier> modifiers = item.getModifiers();
         if (modifiers.contains(Modifier.PRIVATE) || modifiers.contains(Modifier.STATIC)) {
             error("Method %s must not be private or static", item.getSimpleName());
             continue;
         }
         //获取element的父类， 也就是该element所在的class elment
         //按照parent class进行分组
         TypeElement parent = (TypeElement) item.getEnclosingElement();
         if (maps.containsKey(parent)) {
             maps.get(parent).add((ExecutableElement) item);
         } else {
             List<ExecutableElement> temp = new ArrayList<>();
             temp.add((ExecutableElement) item);
             maps.put(parent, temp);
         }
     }
     generateJavaFile(maps, InvokeMethod.class.getSimpleName());
     ```

     

     生成java file，使用框架javapoet

     - 获取使用了该注解的类的package name, class name

       ```java
       TypeMirror typeMirror = typeElement.asType();
       TypeName typeName = TypeName.get(typeMirror);
       
       //获取类的package
       String packageName = MoreElements.getPackage(typeElement).getQualifiedName().toString();
       
       //获取类的名称
       String className = typeElement.getSimpleName().toString();
       ```

     - 设置生成类的构造函数

       ```java
       MethodSpec constructMS = MethodSpec.constructorBuilder()
           .addModifiers(Modifier.PUBLIC)
           .addParameter(typeName, "target")
           .addStatement("this.target = target")
           .build();
       ```

     - 设置生成类的方法，目标是调用被注解类的被注解方法，且同名、同参、同返回。

       ```java
       //同一个类中，需要生成的方法集合
       List<MethodSpec> tempList = new ArrayList<>();
       for (ExecutableElement e : maps.get(typeElement)) {
           //需要设置的方法的入参详情集合
           List<ParameterSpec> parameterSpecs = new ArrayList<>();
           //获取方法的入参
           List<? extends VariableElement> ps = e.getParameters();
           for (int i = 0; i < ps.size(); i++) {
               //设置参数类型，参数变量名称
               ParameterSpec spec = ParameterSpec.builder(TypeName.get(ps.get(i).asType()), ps.get(i).getSimpleName().toString()).build();
               parameterSpecs.add(spec);
           }
           //拼接方法块内的具体代码, 主要是调用target实例中对应的方法
           StringBuilder sb = new StringBuilder();
           //如果方法返回类型不是void, 添加return
           if (e.getReturnType().getKind() != TypeKind.VOID) {
               sb.append("return ");
           }
           sb.append("target.");
           sb.append(e.getSimpleName().toString())
               .append("(");
           for (int i = 0; i < parameterSpecs.size(); i++) {
               sb.append(parameterSpecs.get(i).name);
               if (i != parameterSpecs.size() - 1) {
                   sb.append(",");
               }
           }
           sb.append(")");
           //创建方法
           MethodSpec ms = MethodSpec.methodBuilder(e.getSimpleName().toString())
               .returns(TypeName.get(e.getReturnType()))
               .addModifiers(Modifier.PUBLIC)
               .addParameters(parameterSpecs)
               .addStatement(sb.toString())
               .build();
           tempList.add(ms);
       }
       ```

     - 有了构造方法、方法，创建具体的类

       ```java
       TypeSpec ts = TypeSpec.classBuilder(className + "_" + suffix)
           .addModifiers(Modifier.PUBLIC, Modifier.FINAL)
           .addField(typeName, "target", Modifier.PRIVATE)
           .addMethod(constructMS)
           .addMethods(tempList)
           .build();
       ```

     - 生成java file

       ```java
       JavaFile javaFile = JavaFile.builder(packageName, ts).build();
       try {
           javaFile.writeTo(filer);
       } catch (IOException e) {
           error(e.getMessage());
       }
       ```

       

   - Processor类完整代码

     ```java
     @AutoService(Processor.class)
     public class MyAnnotationProcessor extends AbstractProcessor {
     
         private Filer filer;
     
         @Override
         public synchronized void init(ProcessingEnvironment processingEnvironment) {
             super.init(processingEnvironment);
             filer = processingEnvironment.getFiler();
         }
     
         @Override
         public boolean process(Set<? extends TypeElement> set, RoundEnvironment roundEnvironment) {
     
             Map<TypeElement, List<ExecutableElement>> maps = new LinkedHashMap<>();
     
             for (Element item : roundEnvironment.getElementsAnnotatedWith(InvokeMethod.class)) {
                 if (item.getKind() != ElementKind.METHOD) {
                     error("Annotation MainThread must be annotated at method ");
                     continue;
                 }
     
                 Set<Modifier> modifiers = item.getModifiers();
                 if (modifiers.contains(Modifier.PRIVATE) || modifiers.contains(Modifier.STATIC)) {
                     error("Method %s must not be private or static", item.getSimpleName());
                     continue;
                 }
     
                 TypeElement parent = (TypeElement) item.getEnclosingElement();
                 if (maps.containsKey(parent)) {
                     maps.get(parent).add((ExecutableElement) item);
                 } else {
                     List<ExecutableElement> temp = new ArrayList<>();
                     temp.add((ExecutableElement) item);
                     maps.put(parent, temp);
                 }
             }
             generateJavaFile(maps, InvokeMethod.class.getSimpleName());
     
             return false;
         }
     
         private void generateJavaFile(Map<TypeElement, List<ExecutableElement>> maps, String suffix) {
             for (TypeElement typeElement : maps.keySet()) {
                 TypeMirror typeMirror = typeElement.asType();
                 TypeName typeName = TypeName.get(typeMirror);
     
                 //获取类的package
                 String packageName = MoreElements.getPackage(typeElement).getQualifiedName().toString();
                 //获取类的名称
                 String className = typeElement.getSimpleName().toString();
                 MethodSpec constructMS = MethodSpec.constructorBuilder()
                         .addModifiers(Modifier.PUBLIC)
                         .addParameter(typeName, "target")
                         .addStatement("this.target = target")
                         .build();
     
                 List<MethodSpec> tempList = new ArrayList<>();
                 for (ExecutableElement e : maps.get(typeElement)) {
                     List<ParameterSpec> parameterSpecs = new ArrayList<>();
                     List<? extends VariableElement> ps = e.getParameters();
                     for (int i = 0; i < ps.size(); i++) {
                         print("parameter: " + ps.get(i).getSimpleName());
                         ParameterSpec spec = ParameterSpec.builder(TypeName.get(ps.get(i).asType()), ps.get(i).getSimpleName().toString()).build();
                         parameterSpecs.add(spec);
                     }
                     StringBuilder sb = new StringBuilder();
                     if (e.getReturnType().getKind() != TypeKind.VOID) {
                         sb.append("return ");
                     }
                     sb.append("target.");
                     sb.append(e.getSimpleName().toString())
                             .append("(");
                     for (int i = 0; i < parameterSpecs.size(); i++) {
                         sb.append(parameterSpecs.get(i).name);
                         if (i != parameterSpecs.size() - 1) {
                             sb.append(",");
                         }
                     }
                     sb.append(")");
     
                     MethodSpec ms = MethodSpec.methodBuilder(e.getSimpleName().toString())
                             .returns(TypeName.get(e.getReturnType()))
                             .addModifiers(Modifier.PUBLIC)
                             .addParameters(parameterSpecs)
                             .addStatement(sb.toString())
                             .build();
                     tempList.add(ms);
                 }
     
                 TypeSpec ts = TypeSpec.classBuilder(className + "_" + suffix)
                         .addModifiers(Modifier.PUBLIC, Modifier.FINAL)
                         .addField(typeName, "target", Modifier.PRIVATE)
                         .addMethod(constructMS)
                         .addMethods(tempList)
                         .build();
                 JavaFile javaFile = JavaFile.builder(packageName, ts).build();
                 try {
                     javaFile.writeTo(filer);
                 } catch (IOException e) {
                     error(e.getMessage());
                 }
     
             }
         }
     
         @Override
         public Set<String> getSupportedAnnotationTypes() {
             Set<String> supports = new LinkedHashSet<>();
             supports.add(InvokeMethod.class.getCanonicalName());
             return supports;
         }
     
         @Override
         public SourceVersion getSupportedSourceVersion() {
             return SourceVersion.latestSupported();
         }
     
         private void error(String errorMsg) {
             processingEnv.getMessager().printMessage(Diagnostic.Kind.ERROR, errorMsg);
         }
     
         private void error(String format, Object... args) {
             processingEnv.getMessager().printMessage(Diagnostic.Kind.ERROR, String.format(format, args));
         }
     
         private void print(String msg) {
             processingEnv.getMessager().printMessage(Diagnostic.Kind.NOTE, msg);
         }
     
     }
     ```

     

### 自动生成的代码

```java
package com.ejin.annotationdemo;

import android.widget.BaseAdapter;
import java.lang.String;

public final class MainActivity_InvokeMethod {
  private MainActivity target;

  public MainActivity_InvokeMethod(MainActivity target) {
    this.target = target;
  }

  public void test() {
    target.test();
  }

  public void test2(String value, BaseAdapter adapter) {
    target.test2(value,adapter);
  }

  public String test3(String value) {
    return target.test3(value);
  }
}
```

> 在kotlin下，自动生成的代码在目录 app/build/kapt/debug/{package name}/下