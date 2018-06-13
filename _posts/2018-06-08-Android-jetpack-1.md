---
layout: post
title: Android jetpack 相关类的理解
tags: [Jetpack, Android]
---



### Android Jetpack是`google`推出的帮助开发者快速开发应用的一整套组件的集合。官方文档地址：[Jetpack](https://developer.android.com/jetpack/arch/)。有一些新的概念，对开发还是很有用的，这里记录一下：
---------------------------------------

#### ViewModel
1. 概念



2. 代码



3. 如何使用

#### LiveData
//待整理

#### Room
//待整理

#### LifeCycle
//待整理

#### Dagger 2
##### 1. 概念

> Dagger2 是Google维护的一个依赖注入的框架。通过@Inject、@Component、@Module、@Provides、@Scope等注解实现类的注入。它的机制是在编译期根据注解生产相应代码，而非通过运行时反射，因此也不会有性能上的影响。

*@Inject:*  依赖注入的变量 以及 被注入的类构造方法 中用到

*@Module/@Provides:*  提供注入实例的类

*@Component:*  关联 注入类 和 被注入类 的必要类

*@Scope:*  实例的作用域范围



##### 2. 代码示例

1. 被注入的类 + Component + 需要注入的类

   ***被注入的类***

   ```kotlin
   class InjectObject @Inject constructor() {
       init {
           Log.d("InjectObject", "instant InjectObject")
       }
   }
   ```

   > 构造方法需要被 `@Inject` 注解

   

   ***Component***

   ```kotlin
   @Component
   interface DemoActivityComponent {
       fun inject(activity: DemoActivity)
   }
   ```

   > 方法inject 的入参必须严格是 `需要注入的类` ，不能是它的父类。

   

   ***需要注入的类***

   ```kotlin
   class DemoActivity: AppCompatActivity() {
       @Inject
       lateinit var injectObject: InjectObject
   
       override fun onCreate(savedInstanceState: Bundle?) {
           super.onCreate(savedInstanceState)
           DaggerDemoActivityComponent.create().inject(this)
           setContentView(R.layout.activity_main)
       }
   ]
   }
   ```

   > a. 被注入的变量需要被 `@Inject` 注解
   >
   > b. 调用 `DaggerDemoActivityComponent.create().inject(this) ` 开始注入
   >
   > c. 类 `DaggerDemoActivityComponent` 会在编译时自动生产



2. Module + Component + 需要注解的类

   ***Module 类***

   ```kotlin
   @Module
   class DemoModule {
       @Provides
       fun getObject(): InjectObject = InjectObject()
   }
   ```

   > a. 类需要被 `@Module` 注解
   >
   > b. 方法中需要有一个返回 `被注解的类` 的方法， 并且被 `@Module` 注解
   >
   > c. 若 `@Provides 方法` 与 `@Inject构造方法` 均存在， 优先使用第一个， 在找不到方法的情况下会去查找 `@Inject构造方法` 的类
   >
   > d. 若 `@Provides 方法` 需要入参，Dagger2 会在该Module中自动查找返回该入参的其他 `@Provides 方法`。

   

   ***Component 修改一下***

   ```kotlin
   @Component(modules = [(DemoModule::class)])
   interface DemoActivityComponent {
       fun inject(activity: DemoActivity)
   }
   ```

   > a. @Component 关联Module 类
   >
   > b. Component 除了可以扩展Module外， 还能dependencies Component。相当于继承，在dependencies之后， Component 便拥有了原来的功能。



3. Scope概念。借助 `@Singleton` 实现局部或全局单例

   ***Module类中方法增加 `@Singleton` 注解***

   ```kotlin
   @Module
   class DemoModule {
       @Singleton
       @Provides
       fun getObject(): InjectObject = InjectObject()
   }
   ```

   ***Component 增加 `@Singleton` 注解***

   ```kotlin
   @Singleton
   @Component(modules = [(DemoModule::class)])
   interface DemoActivityComponent {
       fun inject(activity: DemoActivity)
   }
   ```

   > a. `DaggerDemoActivityComponent.create() ` 产生的Component实例不是单例
   >
   > b. 同一个Component 多次调用inject 注入的实例是单例
   >
   > c. `@Singleton` 是`@Scope` 的一个子类， 可以自定义其他scope子类， 代表不同功能， 如： @ApplicationScope 、 @ActivityScope



##### 3. 使用

```groovy
//加入依赖
implementation 'com.google.dagger:dagger:2.16'
annotationProcessor 'com.google.dagger:dagger-compiler:2.16'
```

> a. 将annotationProcessor更换成kapt
>
> b. 引入插件 ‘ kotlin-kapt ’
>
> ```groovy
> apply plugin: 'kotlin-kapt'
> ...
> kapt {
>     generateStubs = true
> }
> ```



![关系图]({{site.baseurl}}/assets/img/pexels/final-architecture.png)