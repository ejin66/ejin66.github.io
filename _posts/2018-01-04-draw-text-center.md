---
layout: post
title: Android文字绘制居中
tags: [Android]
---

**问题：**

通过canvas.drawText来绘制文字时，把x,y中心点坐标设置进去，最后绘制出来的文字并没有在中间。 

**分析：**

相关方法：

```java
/**
* x 从x轴 x处开始绘制
* y 要绘制的text的baseline的y坐标
**/
canvas.drawText(String text,float x,float y,Paint paint)
```

该参数中的x值中心点x坐标，<font color=Red>y指文字baseline的y坐标</font>，问题就出在参数y上。之前传入的y值，并不是baseline的y坐标，而是绘制位置的中心坐标。导致最后绘制出来的文字不在中心位置。 

在解决这个问题之前，需要了解下baseline相关知识。 

![居中绘制文字]({{ site.baseurl }}/assets/img/pexels/drawtextcenter.png)

如图，baseline便是图中蓝色水平线的位置，相对中心点偏下。
因此，既然要居中绘制文字，就要算出该字体baseline的y值坐标。

**结论：**

假设绘制的区域为rect,那么，baseline的y坐标为：

```java
Paint.FontMetrics fontMetrics = paint.getFontMetrics();
(bottom + top)/2 + Math.abs(fontMetrics.ascent)/2 - Math.abs(fontMetrics.descent)/2
```

x坐标：

```java
centerX - mPaint.measureText("draw text")/2
```

