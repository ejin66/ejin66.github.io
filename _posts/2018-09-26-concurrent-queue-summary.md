---
layout: post
title: concurrent包中相关类整理
tags: [Java, Concurrent]
---



### 阻塞队列 `BlockingQueue`

`BlockingQueue` 是一个队列接口类，定义了一系列的方法，主要是针对 `producer-comsumer` 的场景，解决不同线程下的同步存取问题。

#### `BlockingQueue` 方法详解

`BlockingQueue` 有以下几套存取方法：

- 若操作无法成功时，会抛出异常的
  - add(e)	
    - 本质是调offer(e), 若不成功会抛 `throw new IllegalStateException("Queue full");`
  - remove()
    - 本质是调poll(). 若不成功会抛 `throw new NoSuchElementException();`
  - element()
    - 本质是调peek(), 获取队头元素。 若不成功会抛 `throw new NoSuchElementException();`
- 有返回值的
  - offer(e)
    - 将元素加到队尾。 若成功返回 `true`, 否则返回 `false`
  - poll()
    - 移除队头元素。 若成功返回被移除的元素，否则返回 `null`
  - peek()
    - 获取队头元素。 若成功返回队头元素， 否则返回 `null`
- 导致阻塞的
  - put(e)
    - 将元素加到队尾。若不成功， 直接阻塞线程
  - take()
    - 移除队头元素。若成功返回队头元素，否则阻塞线程
- 超时的
  - offer(e, timeout, TimeUnit)
    - 在超时时间之内成功加入的返回 `true` ， 否则返回 `false`
  - poll(timeout, TimeUnit)
    - 在超时时间之内成功移除的返回被移除的元素，否则返回 `null`

#### `BlockingQueue` 的实现类

基于`BlockingQueue`的实现类有：

- `ArrayBlockingQueue`

  - 基于数据实现

  - 有界的
  - 先进先出

- `DelayQueue`

  - 基于`PriorityQueue`
  - 无界的

  - 队列元素需继承 `Delayed` 接口类
  - 通过 `Delayed.getDelayed()` 方法返回动态延时阻塞时间
  - 通过 `Delayed.compareTo()` 方法进行排序

- `LinkedBlockingQueue`

  - 链式结构
  - 不限上线的话， 默认 `Integer.MAX_VALUE`
  - 先进先出 

- `PriorityBlockingQueue`

  - 基于`PriorityQueue`
  - 无界的
  - 元素需继承 `Comparable` 接口类

- `SynchronousQueue`

  - 只有单个元素

<br/>

### 双端阻塞队列 `BlockingDeque`

`BlockingQueue` 是一个队列接口类，它允许线程在队列两端进行插入、提取。

#### `BlockingDeque的实现类`

- `LinkedBlockingDeque` 

<br/>

### `CountDownLatch`

使用方法：

```kotlin
//主线程在调用await之后阻塞，等待两个countDown才能重新唤醒主线程
val latch = CountDownLatch(2)

//main thread
latch.await()

//thread 1
latch.countDown()

//thread 2
latch.countDown()
```

利用`CountDownLatch`，可以实现异步转同步的功能。如：将异步网络请求回调转为同步请求。

<br/>

### `Semaphore`

计数信号量。初始化的时候，可以设置一个数量的“许可”。每`acquire()`或者`acquire(n)`，同时申请一个或者n个“许可”，若能申请成功，执行接下来的流程；若无法申请，会阻塞线程并等待“许可”通过；每`release()`会释放一个或者n个“许可”。

初始化`Semaphore`:

```kotlin
val semaphore = Semaphore(2)
```

初始化设置申请信号量是否强制公平：

```kotlin
val semaphore = Semaphore(2，true)
```

> 默认是不公平。设置为强制公平会影响性能。

申请信号量：

```kotlin
//申请一个信号量
semaphore.acquire()
//or
//同时申请两个信号量
semaphore.acquire(2)
```

申请信号量，不成功的话，返回`false`, 不需要阻塞线程：

```kotlin
semaphore.tryAcquire()
//or
//等待一定时间之后再返回
semaphore.tryAcquire(long var1, TimeUnit var3)
```

释放信号量：

```kotlin
semaphore.release()
```

<br/>

### 借助`LinkedBlockingQueue` 源码，认识`ReentrantLock`与`Condition`

贴部分源码：

```java
private final ReentrantLock takeLock;
private final Condition notEmpty;
private final ReentrantLock putLock;
private final Condition notFull;

public LinkedBlockingQueue(int var1) {
  	...
    this.takeLock = new ReentrantLock();
    this.notEmpty = this.takeLock.newCondition();
    this.putLock = new ReentrantLock();
    this.notFull = this.putLock.newCondition();
    ...
}

public void put(E var1) throws InterruptedException {
    if (var1 == null) {
        throw new NullPointerException();
    } else {
        boolean var2 = true;
        LinkedBlockingQueue.Node var3 = new LinkedBlockingQueue.Node(var1);
        ReentrantLock var4 = this.putLock;
        AtomicInteger var5 = this.count;
        var4.lockInterruptibly();

        int var9;
        try {
            while(var5.get() == this.capacity) {
                this.notFull.await();
            }

            this.enqueue(var3);
            var9 = var5.getAndIncrement();
            if (var9 + 1 < this.capacity) {
                this.notFull.signal();
            }
        } finally {
            var4.unlock();
        }

        if (var9 == 0) {
            this.signalNotEmpty();
        }

    }
}


public E take() throws InterruptedException {
    boolean var2 = true;
    AtomicInteger var3 = this.count;
    ReentrantLock var4 = this.takeLock;
    var4.lockInterruptibly();

    Object var1;
    int var8;
    try {
        while(var3.get() == 0) {
            this.notEmpty.await();
        }

        var1 = this.dequeue();
        var8 = var3.getAndDecrement();
        if (var8 > 1) {
            this.notEmpty.signal();
        }
    } finally {
        var4.unlock();
    }

    if (var8 == this.capacity) {
        this.signalNotFull();
    }

    return var1;
}


private void signalNotEmpty() {
    ReentrantLock var1 = this.takeLock;
    var1.lock();

    try {
        this.notEmpty.signal();
    } finally {
        var1.unlock();
    }

}

private void signalNotFull() {
    ReentrantLock var1 = this.putLock;
    var1.lock();

    try {
        this.notFull.signal();
    } finally {
        var1.unlock();
    }

}
```

从上面的源码，可以知道`ReentrantLock`基本的用法：

- `Condition`由`ReentrantLock`产生
- 通过`ReentrantLock.lockInterruptibly()`获取锁，`ReentrantLock.unlock()`释放锁
- `Condition.await()`阻塞线程，通过`Condition.signal()`来通知线程

但是源码看下来有个问题：

1. 当`take()`方法因为队列中没有元素会调用`notEmpty.await`阻塞线程，此时该线程拿到了`takeLock`锁；
2. 另一线程通过`put()`方法添加了一个元素后会调用`signalNotEmpty()`方法；
3. 而`signalNotEmpty()`却需要获取`takeLock`之后才调用`notEmpty.signal()`，可该锁不是被第一个线程keep住了吗？

后来我了解到，`ReentrantLock` 有一个特别的属性：**即使一个线程获取到锁，但是如果该线程处于休眠状态时，便会自动释放锁；其他线程可以重新获取该锁。当线程被唤醒时，会重新争取锁。**

所以，在调用`notEmpty.await`之后，`takeLock`锁就被当前线程给释放了。