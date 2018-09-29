---
layot: post
title: Android零碎知识点整理
tags: [Android]
---



#### 1. Scroller类

Scroller只是滑动的一个帮助类，其本身并不会让任何view滑动。它会对滑动距离进行计算，并多次回调view 的computeScroll方法，我们只需要在复写computeScroll方法，便可实现view的平滑移动。 

**构造方法：**

```java
Scroller mScroll = new Sccroller(mContext);
//or
Scroller mScroll = new Sccroller(mContext,new DecelerateInterpolator());//支持加速度
```

**复写computeScroll方法：**

```java
public void cpmputeScroll() {
        if( mScroller.computeScrollOffset()) {
                //int curY = mScroll.getCurrY();
                //view 的滑动
        }
}
```

**开始滑动：**

```java
/**
* startX  开始位置的x轴坐标
* startY  开始位置的y轴坐标
* dx 需要滑动的x轴上的长度
* dy 需要滑动的y轴上的长度
* duration  过程时长
**/
mScroll.startScroll(int startX,int startY, int dX, int dY, int duration)
```

<br/>

#### 2.  在TextView中绘制图片

代码示例：

```java
final SpannableString ss = new SpannableString("easy");  
//得到drawable对象，即所要插入的图片  
Drawable d = getResources().getDrawable(id);  
d.setBounds(0, 0, d.getIntrinsicWidth(), d.getIntrinsicHeight());  
//用这个drawable对象代替字符串easy  
ImageSpan span = new ImageSpan(d, ImageSpan.ALIGN_BASELINE);  
//包括0但是不包括"easy".length()即：4。[0,4)。值得注意的是当我们复制这个图片的时候，实际是复制了"easy"这个字符串。  
ss.setSpan(span, 0, "easy".length(), Spannable.SPAN_INCLUSIVE_EXCLUSIVE);  
append(ss);  
```

<br/>

#### 3. ListView.onDraw中绘制的内容不在最顶层

**问题：**

在自定义View的时候，发现listview.onDraw()中绘制的内容没有显示在最顶层的图层上。一番查找之后，发现问题就出在onDraw方法上。

**分析：**

一般自定义view时，都是选择onDraw方法进行重绘，但是listview本身是继承自viewGroup的，那问题就来了：onDraw()方法是绘制自身，对于有child的viewgroup,是通过dispathDraw()方法来绘制子view的。在listview.onDraw()中绘制，自然不会处于最顶层，会被dispatchDraw()的图层覆盖。

**结论：**

在dispatchDraw()中绘制就可以了。

<br/>

#### 4. Fragment -- add/replace的区别

通过replace直接添加的fragment ,初次创建会调用onCreate，而onCreateView方法是每次都会调用。

通过add添加的fragment, hide、show方法来控制framgment，则不会再去调用onCreateView方法。

同时，fragment有一个懒加载机制，通过setUserVisibleHint方法，可以判断当前fragment是否可见，若可见，再处理相关逻辑。

**_Fragment_**  生命周期：

![Fragment生命周期]({{ site.baseurl }}/assets/img/pexels/fragmentLifeCycle.png)

<br/>

#### 5. Activity切换的简单处理

定义style:

```xml
<style name="ActivityInOutAnimation" parent="@android:style/Animation.Activity">
         <item name="android:activityOpenEnterAnimation">@anim/in_right</item>
         <item name="android:activityOpenExitAnimation">@anim/out_left</item>
         <item name="android:activityCloseEnterAnimation">@anim/in_left</item>
         <item name="android:activityCloseExitAnimation">@anim/out_right</item>
</style>
```

其中，4个`anim`如下：

```xml
in_left:
<?xml version="1.0" encoding="utf-8"?>
<set xmlns:android="http://schemas.android.com/apk/res/android">
    <translate 
        android:fromXDelta="-100%"
        android:toXDelta="0%"
        android:duration="200"/>
</set>

in_right:
<?xml version="1.0" encoding="utf-8"?>
<set xmlns:android="http://schemas.android.com/apk/res/android">
    <translate 
        android:fromXDelta="100%"
        android:toXDelta="0%"
        android:duration="200"/>
</set>

out_left:
<?xml version="1.0" encoding="utf-8"?>
<set xmlns:android="http://schemas.android.com/apk/res/android">
    <translate 
        android:fromXDelta="0%"
        android:toXDelta="-100%"
        android:duration="200"/>
</set>

out_right:
<?xml version="1.0" encoding="utf-8"?>
<set xmlns:android="http://schemas.android.com/apk/res/android">
    <translate 
        android:fromXDelta="0%"
        android:toXDelta="100%"
        android:duration="200"/>
</set>
```

定义主题：

```xml
<style name="NormalTheme" parent="android:Theme.Light.NoTitleBar">
        <item name="android:windowAnimationStyle">@style/ActivityInOutAnimation</item>
</style>
```

使用主题：

```xml
android:theme="@style/NormalTheme"
```

