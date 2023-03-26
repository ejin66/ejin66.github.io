---
layout: post
title: 自定义Widget： 左滑删除
tags: [Flutter]
---

### 左滑删除的原理图

实现原理如下：

![原理图]({{site.baseurl}}/assets/img/pexels/slide.jpg)

事先在屏幕右侧外面绘制一个button，通过手势滑动拖出该button，来实现左滑删除。

<br/>



### 实现思路一： 利用Positioned

最开始的思路是：

> Stack + Positioned + GestureDetector + Animation + StatefulWidget

利用GestureDetector监听水平方向的移动，通过StatefulWidget 的setState 更新Positioned的 left、top的值，实现Widget的移动。在手指抬起时，根据当前的位置，决定抽屉是打开还是关闭。最后，借助Animation来实现打开、关闭的动画。

**但是，通过这种方式会有一个问题：自定义出来的widget，width & height是没有限制的。**

**因为，Stack的 width & height,，默认是依赖非positioned child的最大宽高，若它只有一个positioned的child，它的 width & height就会没有限制。**

在RenderStack的performLayout中相关源码：

```dart
	while (child != null) {
      final StackParentData childParentData = child.parentData;

      if (!childParentData.isPositioned) {
        hasNonPositionedChildren = true;

        child.layout(nonPositionedConstraints, parentUsesSize: true);

        final Size childSize = child.size;
        width = math.max(width, childSize.width);
        height = math.max(height, childSize.height);
      }

      child = childParentData.nextSibling;
    }

    if (hasNonPositionedChildren) {
      size = new Size(width, height);
      assert(size.width == constraints.constrainWidth(width));
      assert(size.height == constraints.constrainHeight(height));
    } else {
      size = constraints.biggest;
    }
```

<br/>



### 实现思路二：自定义Widget & RenderBox

实现思路跟上面差不多，不过不是利用Positioned来实现位移，借助Paint方法来实现。

1. **自定义一个可以平移的Widget**

   widget代码如下：

   ```dart
   class Slide2Widget extends SingleChildRenderObjectWidget {
     Offset offset;
   
     Slide2Widget({Key key, Widget child, this.offset: Offset.zero})
         : super(key: key, child: child);
   
     @override
     RenderObject createRenderObject(BuildContext context) => RenderSlideObject();
   
     @override
     void updateRenderObject(
         BuildContext context, RenderSlideObject renderObject) {
       renderObject._offset = offset;
       renderObject.markNeedsLayout();
     }
   }
   ```

   调用者传入的Offset，就是widget的偏移量。

   > 对Flutter具体的绘制流程还不够熟悉，大体流程是这样：
   >
   > 调用setState方法之后，会调用element.markedNeedBuild方法，将该widget变dirty，并加入到global list of widgets，等待下一帧绘制时，集中处理这些widget。然后，会调用updateRenderObject方法。
   >
   > 在Flutter中，真正绘制界面的是RenderObject，每个widget都对应一个RenderObject。

   关于Widget、Element、RenderObject的关系，可以看这篇：[Flutter，什么是Widgets、RenderObjects、Elements？](https://juejin.im/post/5b4c6054e51d4519475f1d5d)

   <br/>

   

   创建一个RenderBox:

   ```dart
   class RenderSlideObject extends RenderProxyBox {
     Offset _offset = Offset.zero;
   
     RenderSlideObject({RenderBox child}) : super(child);
   
     @override
     void paint(PaintingContext context, Offset offset) {
       context.pushClipRect(
           needsCompositing, offset, Offset.zero & size, defaultPaint);
     }
   
     void defaultPaint(PaintingContext context, Offset offset) {
       context.paintChild(child, offset + _offset);
     }
   
     @override
     performLayout() {
       BoxConstraints childConstraints = const BoxConstraints();
       child.layout(childConstraints, parentUsesSize: true);
       size = child.size - Offset(_buttonWidth, 0.0);
     }
   
     @override
     bool hitTestChildren(HitTestResult result, {Offset position}) {
       return child.hitTest(result, position: (position - _offset));
     }
   }
   ```

   这里有几处坑：

   1. 在performLayout中，调用child.layout时，需要创建一个没有限制的约束，而不能传入自身的约束。否则child在绘制的时候，当child的实际宽高超过了当前的限制时，会截取掉超出的部分，导致无论我们怎么平移，右侧的button都不会出现了。
   2. 在performLayout中，需要根据child的size，来设置自身的size。且size 要和自身的约束想匹配，否则会有警告。上面child.size其实是超过屏幕宽度的，而约束的最大宽度是屏幕宽度，因此减掉了超出的宽度。
   3. 复写hitTestChildren方法。这一步必不可少，这坑实在太大，困扰了我很久。我在测试中发现，我左滑出现了button，但是button上原先绑定的点击事件不起作用。最后我发现是这样：虽然我移动了widget，但是绑定的点击事件还在原来的位置。求助StackOverflow之后，才知道需要重写这个方法，并设置对应的偏移量。至于hitTestChildren的作用，应该与触摸事件分发有关，具体可以看这篇：[Flutter中的事件流和手势简析](https://segmentfault.com/a/1190000011555283)

   <br/>

   

2. **自定义Widget，继承StatefulWidget**

   ```dart
   class SlideWidget extends StatefulWidget {
       ...
   }
   ```

   <br/>

   

3. **在State类的build方法中构建视图**

   ```dart
   Widget build(BuildContext context) {
       return Slide2Widget(
         offset: Offset(_x, 0.0),
         child: IntrinsicHeight(
           child: Row(
             crossAxisAlignment: CrossAxisAlignment.stretch,
             children: <Widget>[
               Container(
                 width: screenSize,
                 child: GestureDetector(
                     onHorizontalDragDown: (detail) {
                       _lastOffset = detail.globalPosition;
                     },
                     onHorizontalDragUpdate: (detail) {
                       setState(() {
                         _x += detail.globalPosition.dx - _lastOffset.dx;
   
                         if (_x < -_buttonWidth) {
                           _x = -_buttonWidth;
                         }
   
                         if (_x > 0) {
                           _x = 0.0;
                         }
                         _lastOffset = detail.globalPosition;
                       });
                     },
                     onHorizontalDragEnd: (detail) {
                       if (_x > -_buttonWidth / 2) {
                         if (detail.velocity.pixelsPerSecond.dx <
                             -_effectiveSpeed) {
                           //open
                           isOpen = true;
                           _moveSmoothly(_x, -_buttonWidth);
                         } else {
                           //close
                           isOpen = false;
                           _moveSmoothly(_x, 0.0);
                         }
                       } else {
                         if (detail.velocity.pixelsPerSecond.dx >
                             _effectiveSpeed) {
                           //close
                           isOpen = false;
                           _moveSmoothly(_x, 0.0);
                         } else {
                           //open
                           isOpen = true;
                           _moveSmoothly(_x, -_buttonWidth);
                         }
                       }
                     },
                     onHorizontalDragCancel: () {
                       print("onHorizontalDragCancel");
                     },
                     child: child),
               ),
               Container(
                 width: _buttonWidth,
                 child: FlatButton(
                     padding: EdgeInsets.all(0.0),
                     shape: RoundedRectangleBorder(),
                     onPressed: () {
                       onButtonPressed();
                     },
                     color: buttonColor,
                     child: Text(
                       button,
                       style: TextStyle(color: Colors.white, fontSize: 16.0),
                     )),
               ),
             ],
           ),
         ),
       );
     }
   ```

   这里也有两个值得注意的地方：

   1. 为了隐藏删除按钮，child的宽度必须要水平满屏，如何获取屏幕宽度：

      ```dart
      double getWindowSize() => window.physicalSize.width / window.devicePixelRatio;
      ```

      

   2. 超出屏幕的button高度如何跟它左边Widget一样？

      ```dart
      IntrinsicHeight(
          child: Row(
          	crossAxisAlignment: CrossAxisAlignment.stretch,
          	children: ...
          )
      )
      ```

   <br/>

   

4.  **[完整代码](https://github.com/ejin66/SlideWidget)**

   
