---
layout: post
title: NestedScrollView 总结
tags: [Android,NestedScrollView]
---



关于NestedScrollView的相关流程总结，如下：

![NestedScrollView流程图]({{ site.baseurl }}/assets/img/pexels/NestedScroll.png)


> **dispatchNestedPreScroll**
>   （dx, dy, scrollConsumed,scrollOffset）
>
>   dx: 用户在screen x轴上操作的位移长度
>
>   dy: 用户在screeny轴上操作的位移长度（手指向下滑动，dy为负；手指向上滑动，dy为正）
>
>   scrollConsumed: parent已消费的距离{x1, y1}
>
>   scrollOffset: 在parent消费之后， child相对screen产生的位移
>
> 其中，scrollConsumed, scrollOffset均是parent调用onNestedPreScroll之后才确认的int[]。
> 在调用onNestedPreScroll之前，scrollOffset会被清零成{0， 0}
> 该方法是为了让parent有机会在child消费前消费一些或全部位移长度


> **onNestedPreScroll**
>   (dx, dy, scrollConsumed)
>
>   dx: 用户在screen x轴上操作的位移
>
>   dy: 用户在screeny轴上操作的位移
>
>   scrollConsumed: child dispatchNestedPreScroll带过来的值
>
> 当parent想消费一部分位移，如x1,y1, 需要将scrollConsumed设置成{x1, y1}, 这样便通知给了child


> **dispatchNestedScroll**
>   (consumeX, consumeY, unConsumeX, unConsumeY, scrollOffest)
>
>   consumeX: child消费的x轴上的长度
>
>   consumeY: child消费的y轴上的长度
>
>   unConsumeX: 剩余的未消费的x长度
>
>   unConsumeY: 剩余的未消费的y长度
>
>   scrollOffest: 同上上
>
>   consumeX + unConsumeX = dx - x1
>
>   consumeY + unConsumeY = dy - y1


> **onNestedScroll**
>   (consumeX, consumeY, unConsumeX, unConsumeY)
>
>   parent第二次消费位移长度的机会
