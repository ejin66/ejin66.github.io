---
layout: post
title: RecyclerView的滚动截屏实现
tags: [Android]
---



### 前言

刷博客看到有关滚动截屏的内容，加上之前早有耳闻锤子手机上的这个功能，决定忙里偷闲来重复造个轮子。

<br/>

### 原理

关于屏幕截屏，以前做项目有接触过。是为了实现毛玻璃效果，需要截屏然后做高斯模糊。截屏是通过 `drawingCache` 实现的，代码如下：

```kotlin
view.isDrawingCacheEnabled = true
view.drawingCacheQuality = View.DRAWING_CACHE_QUALITY_HIGH
view.buildDrawingCache()
val bitmap = view.drawingCache
//do something...
view.destroyDrawingCache()
view.isDrawingCacheEnabled = false
```

那滚动截屏能通过这种方法实现吗？答案是肯定的。

思路如下：

1. 开始截屏时， 截取当前屏幕的RecyclerView，暂存bitmap

2. 每滚动RecyclerView.getHeight / 2 的距离时，获取bitmap并截取相对应的位置（具体实现看代码）。

   >  为什么是滚动RecyclerView高度的一半才截图呢？其实并没有限定，可以改成1/3、2/3等等。主要的目的是防止滑动过快导致部分视图没有机会被截到。设置为1/2时将会有另外1/2的缓冲机会，而设置为1的话大概率会截取不完整。

3. 停止截图时，获取bitmap并截取底部部分位置。

   > 当停止时，超过上一次的截图位置但尚未到下一个RecyclerView.getHeight / 2的部分，也是需要截取保存的。

4. 借助Canvas将暂存的Bitmap拼接起来，最后写到本地。

<br/>

### 源码

思路有了，代码实现就简单了。本着职责单一、解耦、不入侵的原则，将功能写在一个单独的类中，既简单清晰，使用起来也很方便。

```kotlin
import android.graphics.Bitmap
import android.graphics.Canvas
import android.support.v7.widget.RecyclerView
import android.util.Log
import android.view.View
import java.io.File
import java.io.FileOutputStream

class ScrollCapture(var recyclerView: RecyclerView, var fullPath: String) : RecyclerView.OnScrollListener() {

    private var scrollDistanceX = 0
    private var scrollDistanceY = 0
    private var endCaptureHeight = 0
    private var captureFlag = false
    private var bitmapCaches = mutableListOf<Bitmap>()

    fun start() {
        if (captureFlag) return

        captureFlag = true
        scrollDistanceX = 0
        scrollDistanceY = 0
        endCaptureHeight = 0
        bitmapCaches.clear()

        recyclerView.addOnScrollListener(this)

        endCaptureHeight = scrollDistanceY + getHeight()
        //开始截屏，截取完整的recyclerView
        capture(scrollDistanceY, endCaptureHeight)
    }

    fun stop() {
        if (!captureFlag) return

        recyclerView.removeOnScrollListener(this)
        captureFlag = false
		
        //停止截屏，截取底部的部分位置
        capture(endCaptureHeight, scrollDistanceY + getHeight())
        //拼接bitmap并保存到本地
        saveToLocal()
    }

    fun toggle() {
        if (captureFlag) {
            stop()
        } else {
            start()
        }
    }

    override fun onScrolled(recyclerView: RecyclerView?, dx: Int, dy: Int) {
        super.onScrolled(recyclerView, dx, dy)
        scrollDistanceX += dx
        scrollDistanceY += dy

        if (captureFlag && scrollDistanceY + getHeight() - endCaptureHeight > getHeight() / 2) {
            //截半屏
            capture(endCaptureHeight, endCaptureHeight + getHeight() / 2)
            endCaptureHeight += getHeight() / 2
        }
    }

    private fun getWidth() = recyclerView.width

    private fun getHeight() = recyclerView.height

    private fun capture(startHeight: Int, endHeight: Int) {
        if (endHeight <= startHeight) return

        recyclerView.isDrawingCacheEnabled = true
        recyclerView.drawingCacheQuality = View.DRAWING_CACHE_QUALITY_HIGH
        recyclerView.buildDrawingCache()
        val bitmap = recyclerView.drawingCache
        val topHeight = scrollDistanceY
        Bitmap.createBitmap(bitmap, 0, startHeight - topHeight, getWidth(), endHeight - startHeight).run {
            bitmapCaches.add(this)
        }
        recyclerView.destroyDrawingCache()
        recyclerView.isDrawingCacheEnabled = false
    }

    private fun saveToLocal() {
        val tmpFile = File(fullPath)
        if (!tmpFile.parentFile.exists() && !tmpFile.parentFile.mkdirs()) {
            //the path is error
            Log.e(javaClass.simpleName, "can't create the full path: $fullPath")
            return
        }

        var distHeight = 0
        var distWight = 0
        bitmapCaches.forEach {
            distHeight += it.height
            distWight = it.width
        }
        val distBitmap = Bitmap.createBitmap(distWight, distHeight, Bitmap.Config.ARGB_8888)
        val canvas = Canvas(distBitmap)

        var tmpHeight = 0F
        bitmapCaches.forEach {
            canvas.drawBitmap(it, 0F, tmpHeight, null)
            tmpHeight += it.height
            it.recycle()
        }
        FileOutputStream(fullPath).let {
            distBitmap.compress(Bitmap.CompressFormat.JPEG, 100, it)
            it.flush()
            it.close()
            distBitmap.recycle()
        }
    }

}
```

关于截图，还有另外一种方式，通过view.draw()方法。上方的capture方法也可以这样写：

```kotlin
private fun capture(startHeight: Int, endHeight: Int) {
        if (endHeight <= startHeight) return

        val tmpBitmap = Bitmap.createBitmap(getWidth(), getHeight(), Bitmap.Config.ARGB_8888)
        val canvas = Canvas(tmpBitmap)
        recyclerView.draw(canvas)
        val topHeight = scrollDistanceY
        Bitmap.createBitmap(tmpBitmap, 0, startHeight - topHeight, getWidth(), endHeight - startHeight).run {
            bitmapCaches.add(this)
        }
}
```

<br/>

### 使用

使用的话，就很简单了：

```kotlin
val scrollCapture = ScrollCapture(recyclerView, externalCacheDir.absolutePath + File.separator + System.currentTimeMillis() + ".jpg")
//开始或停止截屏
scrollCapture.toggle();
```

<br/>

###  结尾

本文主要是针对RecyclerView的滚动截图实现，但思路也是可以用到其他Layout上。