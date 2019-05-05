---
layout: post
title: kotlin 协程的总结
tags: [Kotlin]
---

### 什么是协程

协程(`Coroutine`)，本质上是更轻量级的线程。在一个线程上可以同时跑多个协程。与线程相比，它更轻量、资源占用更少。



### suspend关键字

`suspend`关键字修饰方法，标识它是`suspend function`。`suspend function`有两个特点：

- 该方法只能在协程中被调用
- 该方法体内可以调用其他`suspend`方法

像`delay`方法就是`suspend`方法，它只能在协程中调用，它的作用是阻塞协程。类似线程中的`Thread.sleep`方法。



### 如何创建一个协程

#### launch

通过`launch`方法创建协程

```kotlin
GlobalScope.launch {
    //do something...
}
```

`launch`方法源码如下：

```kotlin
public fun CoroutineScope.launch(
    context: CoroutineContext = EmptyCoroutineContext,
    start: CoroutineStart = CoroutineStart.DEFAULT,
    block: suspend CoroutineScope.() -> Unit
): Job {
    //...
    return coroutine
}
```

可以看到，`launch`方法是挂靠在接口`CoroutineScope`上的方法。像上面的`GlobalScope`，就是接口`CoroutineScope`的一个实现类。

`launch`方法的生命周期与当前线程的生命周期一致。

`launch`方法有三个入参、一个出参，分别来分析下它们的作用：

- `context: CoroutineContext`。

  `CoroutineContext`是一系列元素的集合，最主要的两个元素是：`Job`、`Dispatcher`。

  `Job`控制协程的开始、取消等，`Dispatcher`负责协程在哪个线程中执行。

- `start: CoroutineStart`。

  该入参主要是控制协程是直接执行还是`Lazy start`。若是`CoroutineStart.LAZY`, 需要通过`job.start`方法主动开启协程。

- `block: suspend CoroutineScope.() -> Unit`。

  协程要执行的代码段。从定义看，`block`是一个`suspend`匿名方法，且是挂靠在接口`CoroutineScope`下。

  所有，代码段中的`this`关键字，直接代表了`CoroutineScope`。

- `Job`。`Job`是`launch`方法的返回值，它就是用来控制协程的运行状态的。`Job`中有几个关键方法：

  - start。如果是`CoroutineStart.LAZY`创建出来的协程，调用该方法开启协程。
  - cancel。取消正在执行的协程。如协程处于运算状态，则不能被取消。也就是说，只有协程处于阻塞状态时才能够取消。
  - join。阻塞父协程，直到本协程执行完。
  - cancelAndJoin。等价于`cancel` + `join`。

在`block`中，用`try-finally`来包裹代码段，当调用`job.cancel`时，代码段会执行到`finally`中。通常情况下，`finally`中不能够再调用`suspend`方法，否则会抛出`CancellationException`。但是也有例外，如：

```dart
try {
    //do something
} finally {
    //通过withContext(NonCancellable)来调用suspend方法
    withContext(NonCancellable) {
        delay(1000)
        print("lalalalala")
    }
}
```



#### runBlocking

通过`runBlocking`创建协程：

```dart
runBlocking {
    //do something...
}
```

`runBlocking`会阻塞当前线程，直到它的协程结束。由`runBlocking`发起的协程，它的生命周期是它内部所有的协程都完成才算结束。

`runBlocking`的定义：

```kotlin
public fun <T> runBlocking(context: CoroutineContext = EmptyCoroutineContext, block: suspend CoroutineScope.() -> T): T {
    ...
    return coroutine.joinBlocking()
}
```

两个入参与方法`launch`的入参一致。

它有一个返回值，类型是`T`。返回值的调用写法如下：

```kotlin
fun main() {
    val result = runBlocking<Int> {
        delay(500)
        1
    }
    println("result: $result")
}

//result: 1
```



#### async

通过`async`创建协程：

```kotlin
GlobalScope.async {
    //do something
}
```

`async`源码如下：

```kotlin
public fun <T> CoroutineScope.async(
    context: CoroutineContext = EmptyCoroutineContext,
    start: CoroutineStart = CoroutineStart.DEFAULT,
    block: suspend CoroutineScope.() -> T
): Deferred<T> {
    //...
    return coroutine
}
```

它与`launch`类似，差别在于返回值。`async`方法返回一个`Deferred<T>`类型。

`Deferred`继承自`Job`，最主要的是增加了`await`方法，通过`await`方法返回`T`。`Deferred.await`在等待返回值时会阻塞当前的协程。

`async`方法调用的例子：

```kotlin
fun main() {
    runBlocking {
        val result = async {
            delay(1000)
            1
        }
        print("result: ${result.await()}")
    }
}

//result: 1
```



#### withContext

`withContext`接收一个`CoroutineContext`, 阻塞协程并等待协程返回`T`值。

```kotlin
fun main() = runBlocking {
    val result = withContext(this.coroutineContext) {
        println("thread name: ${Thread.currentThread().name}")
        1
    }

    println("result: $result")
}

/**
thread name: main
result: 1
**/
```



#### Dispatchers.Unconfined

在`launch`等方法调用时，可以设置`CoroutineContext`。`Kotlin.coroutine`库中实现了几种：

- `Dispatchers.Unconfined`。发起的协程会在当前线程中执行。但只要阻塞之后，协程将在线程中恢复，该线程完全由调用的挂起线程决定。

  ```kotlin
  fun main() {
      runBlocking {
          val job = launch(Dispatchers.Unconfined) {
              println("launch unconfined thread before: ${Thread.currentThread().name}")
              delay(100)
              println("launch unconfined thread after: ${Thread.currentThread().name}")
          }
  
          job.join()
  
          launch {
              println("launch thread before: ${Thread.currentThread().name}")
              delay(100)
              println("launch thread after: ${Thread.currentThread().name}")
          }
      }
  }
  
  /**
  launch unconfined thread before: main
  launch unconfined thread after: kotlinx.coroutines.DefaultExecutor
  launch thread before: main
  launch thread after: main
  **/
  ```

  

- `Dispatchers.Default`。 让发起的协程在默认的线程中允许。`launch(Dispatchers.Default){}` 与`GlobalScope.launch{}`一样，都是在默认的线程中执行。

  ```kotlin
  fun main() {
      GlobalScope.launch {
          println("GlobalScope launch thread: ${Thread.currentThread().name}")
      }
  
      runBlocking {
          launch(Dispatchers.Default) {
              println("launch Default thread: ${Thread.currentThread().name}")
          }
  
          launch {
              println("launch thread: ${Thread.currentThread().name}")
          }
  
          println("current thread: ${Thread.currentThread().name}")
      }
  }
  
  /**
  GlobalScope launch thread: DefaultDispatcher-worker-1
  launch Default thread: DefaultDispatcher-worker-2
  current thread: main
  launch thread: main
  **/
  ```

  

- `Dispatchers.Main`。让协程在android的主线程中执行。使用它需要添加额外的模块，`kotlinx-coroutines-android`。

- `Dispatchers.IO`。让协程在默认的共享线程池中执行。本质上与`Dispatchers.Default`共享一个线程池。



#### coroutineScope

使用`coroutineScope`创建一个新的CoroutineScope, 它的`coroutineContext`是由外部协程的`coroutineContext`提供的。

```kotlin
coroutineScope {
    //do something
}
```

它的作用是并行分解工作的。将一部分关联的工作放入该`coroutineScope`中，若其中一个子协程报错了，其他的子协程都会被`cancel`掉。

同时，它执行时会阻塞协程，并等待返回值`R`。

```kotlin
fun main() = runBlocking {
    try {
        coroutineScope {
            launch {
                try {
                    delay(2000)
                    println("launch coroutine delay 2000 ms")
                } finally {
                    println("launch coroutine is cancelled")
                }
            }

            launch {
                delay(1500)
                throw Exception("test exception")
            }

        }
    } catch (e: Exception) {
        println(e)
    }
    
    println("end")
}

/**
launch coroutine is cancelled
java.lang.Exception: test exception
end
**/
```



#### withTimeout

通过`withTimeout(millisecond) {}`,可以对协程做超时处理。

```kotlin
fun main() = runBlocking {
    val result = withTimeout(1000) {
        delay(500)
        1
    }

    println("result: $result")

    val result2 = withTimeout(1000) {
        delay(2000)
        2
    }

    println("result2: $result2")
}

//result: 1
//Exception in thread "main" kotlinx.coroutines.TimeoutCancellationException: Timed out waiting for 1000 ms
```

正常情况下，`withTimeout`等待协程返回值，并阻塞当前协程。当它发起的协程执行时间超过设定值时，会抛出异常`TimeoutCancellationException`。

可以使用`withTimeoutOrNull`代替`withTimeout`。它们的区别在于`withTimeoutOrNull`超时时不会抛出异常，而是返回`Null`。

将上面例子中的`withTimeout`替换成`withTimeoutOrNull`后的结果是：

```kotlin
//result: 1
//result2: null
```



### 协程的关系

不同方式产生的协程，彼此的关系如下图所示：

![关系表]({{site.baseurl}}/assets/img/pexels/coroutine_relation.jpg)