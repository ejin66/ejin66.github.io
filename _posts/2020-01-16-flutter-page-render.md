---
layout: post
title: Flutter页面绘制梳理
tags: [Flutter]
---

## RenderObject

### markNeedsCompositingBitsUpdate()

#### 主要变量

- `_needsCompositingBitsUpdate`
- `needsCompositing`. 若为`true`, `layer`需要被创建，在绘制时进行图层合成

#### 作用

将合成状态置为`dirty`, 也就是，`needsCompositing`变量需要被重新计算（重新计算时机是`PipelineOwner.flushCompositingBits`）

#### 处理逻辑

除将自身的`_needsCompositingBitsUpdate`置为`true`之外，还会通知`parent.markNeedsCompositingBitsUpdate()`，形成链式调用，直到`parent`非`RenderObject`子类，或者自身或者`parent`的`isRepaintBoundary= true`

#### 源码

```dart
void markNeedsCompositingBitsUpdate() {
    if (_needsCompositingBitsUpdate)
      return;
    _needsCompositingBitsUpdate = true;
    if (parent is RenderObject) {
      final RenderObject parent = this.parent;
      if (parent._needsCompositingBitsUpdate)
        return;
      if (!isRepaintBoundary && !parent.isRepaintBoundary) {
        parent.markNeedsCompositingBitsUpdate();
        return;
      }
    }
    assert(() {
      final AbstractNode parent = this.parent;
      if (parent is RenderObject)
        return parent._needsCompositing;
      return true;
    }());
    // parent is fine (or there isn't one), but we are dirty
    if (owner != null)
      owner._nodesNeedingCompositingBitsUpdate.add(this);
  }
```

在断言中的`parent._needsCompositing == true`背后的逻辑：

-  能允许到这里，说明`parent.isRepaintBoundary == true`

- `RenderObject`的构造方法:

  ```dart
  RenderObject() {
      _needsCompositing = isRepaintBoundary || alwaysNeedsCompositing;
  }
  ```

所以就有了：`parent._needsCompositing == true`

最后一行代码：`owner._nodesNeedingCompositingBitsUpdate.add(this)`, 将向上遍历到尽头的`RenderObject`添加到`owner._nodesNeedingCompositingBitsUpdate`队列。在下次`PipelineOwner.flushCompositingBits`时，便会遍历该队列，重新计算`RenderObject`的`needsCompisiting`值。`PipelineOwner`的部分源码如下：

```dart
final List<RenderObject> _nodesNeedingCompositingBitsUpdate = <RenderObject>[];

void flushCompositingBits() {
    if (!kReleaseMode) {
        Timeline.startSync('Compositing bits');
    }
    _nodesNeedingCompositingBitsUpdate.sort((RenderObject a, RenderObject b) => a.depth - b.depth);
    for (RenderObject node in _nodesNeedingCompositingBitsUpdate) {
        if (node._needsCompositingBitsUpdate && node.owner == this)
            node._updateCompositingBits();
    }
    _nodesNeedingCompositingBitsUpdate.clear();
    if (!kReleaseMode) {
        Timeline.finishSync();
    }
}
```

接着，会调用`RenderObject._updateCompositingBits()`方法，去更新`RenderObject`的`needsCompisiting`值，看源码：

```dart
void _updateCompositingBits() {
    if (!_needsCompositingBitsUpdate)
      return;
    final bool oldNeedsCompositing = _needsCompositing;
    _needsCompositing = false;
    visitChildren((RenderObject child) {
      child._updateCompositingBits();
      if (child.needsCompositing)
        _needsCompositing = true;
    });
    if (isRepaintBoundary || alwaysNeedsCompositing)
      _needsCompositing = true;
    if (oldNeedsCompositing != _needsCompositing)
      markNeedsPaint();
    _needsCompositingBitsUpdate = false;
}
```

只有在`_needsCompositingBitsUpdate == true`时才会计算`_needsCompositing`, 这与上面的结论是一致的。

该方法会一直向下遍历，只要有一个`child`的`needsCompositing == true`, 那自身也会置为`true`。并且，一旦`needsCompositing `有变化，便会调用`markNeedsPaint()`重绘。

#### 流程图

markNeedsCompositingBitsUpdate() -> parent1 -> parent2... -> parent3

↓

PipelineOwner._nodesNeedingCompositingBitsUpdate add parent3

↓

PipelineOwner.flushCompositingBits()

↓

_updateCompositingBits() -> child2 -> child1 -> ... -> child0



### markNeedsPaint()

#### 主要变量

- `_needsPaint`
- `isRepaintBoundary`

#### 作用

标记该`RenderObject`需要被重新绘制。等待下一次集中绘制。

#### 处理逻辑

除了标记自身`_needsPaint = true`外，还会调用`parent.markNeedsPaint()`，形成链式调用，直到`isRepaintBoundary == true`或者`parent`不是`RenderObject`.

#### 源码

```dart
void markNeedsPaint() {
    assert(owner == null || !owner.debugDoingPaint);
    if (_needsPaint)
      return;
    _needsPaint = true;
    if (isRepaintBoundary) {
      assert(() {
        if (debugPrintMarkNeedsPaintStacks)
          debugPrintStack(label: 'markNeedsPaint() called for $this');
        return true;
      }());
      // If we always have our own layer, then we can just repaint
      // ourselves without involving any other nodes.
      assert(_layer is OffsetLayer);
      if (owner != null) {
        owner._nodesNeedingPaint.add(this);
        owner.requestVisualUpdate();
      }
    } else if (parent is RenderObject) {
      final RenderObject parent = this.parent;
      parent.markNeedsPaint();
      assert(parent == this.parent);
    } else {
      assert(() {
        if (debugPrintMarkNeedsPaintStacks)
          debugPrintStack(label: 'markNeedsPaint() called for $this (root of render tree)');
        return true;
      }());
      // If we're the root of the render tree (probably a RenderView),
      // then we have to paint ourselves, since nobody else can paint
      // us. We don't add ourselves to _nodesNeedingPaint in this
      // case, because the root is always told to paint regardless.
      if (owner != null)
        owner.requestVisualUpdate();
    }
}
```

如果`RenderObject`的`isRepaintBoundary == true`, 表示上访就到此为止了，不能再向上传递了，否则要出大事。接着，将该`RenderObject`加入到`owner._nodesNeedingPaint`队列，等待被重绘，并且调用`owner.requestVisualUpdate()`。

如果`RenderObject`不是边界，表示还能继续上访，那就调用`parent.markNeedsPaint()`。

若`parent`压根就不是`RenderObject`, 则会调用`owner.requestVisualUpdate()`。

而，这个`owner.requestVisualUpdate()`就是告诉`owner`说：我已经准备好被重绘了，你快开始吧。源码如下：

```dart
class PipelineOwner {
    PipelineOwner({
        this.onNeedVisualUpdate,
        this.onSemanticsOwnerCreated,
        this.onSemanticsOwnerDisposed,
    });
    
    final VoidCallback onNeedVisualUpdate;
    
    void requestVisualUpdate() {
        if (onNeedVisualUpdate != null)
            onNeedVisualUpdate();
    }
    
}
```

`PipelineOwner`在实例化时，`onNeedVisualUpdate`就被传了进来。那`PipelineOwner`在哪里被实例化的呢？是`RendererBinding.initInstances()`时被实例化的，看源码：

```dart
mixin RendererBinding on BindingBase, ServicesBinding, SchedulerBinding, GestureBinding, SemanticsBinding, HitTestable {
    @override
    void initInstances() {
        super.initInstances();
        _instance = this;
        _pipelineOwner = PipelineOwner(
            onNeedVisualUpdate: ensureVisualUpdate,
            onSemanticsOwnerCreated: _handleSemanticsOwnerCreated,
            onSemanticsOwnerDisposed: _handleSemanticsOwnerDisposed,
        );
        window
            ..onMetricsChanged = handleMetricsChanged
            ..onTextScaleFactorChanged = handleTextScaleFactorChanged
            ..onPlatformBrightnessChanged = handlePlatformBrightnessChanged
            ..onSemanticsEnabledChanged = _handleSemanticsEnabledChanged
            ..onSemanticsAction = _handleSemanticsAction;
        initRenderView();
        _handleSemanticsEnabledChanged();
        assert(renderView != null);
        addPersistentFrameCallback(_handlePersistentFrameCallback);
        initMouseTracker();
    }
}
```

`onNeedVisualUpdate`被传入的是`ensureVisuaUpdate`，该方法在`SchedulerBinding`内部:

```dart
void ensureVisualUpdate() {
    switch (schedulerPhase) {
      case SchedulerPhase.idle:
      case SchedulerPhase.postFrameCallbacks:
        scheduleFrame();
        return;
      case SchedulerPhase.transientCallbacks:
      case SchedulerPhase.midFrameMicrotasks:
      case SchedulerPhase.persistentCallbacks:
        return;
    }
}
```

当我们调用`markNeedsPaint`之后，会调用到`ensureVisualUpdate`。而只有`schedulerPhase == SchedulerPhase.postFrameCallbacks|idle`, 才会去安排下一帧的绘制。其他情况下都不做反应。

还可以在追下去，`RendererBinding.initInstances()`什么时候被调用的呢？就是我们最熟悉的一个方法：`runApp`:

```dart
void runApp(Widget app) {
  WidgetsFlutterBinding.ensureInitialized()
    ..scheduleAttachRootWidget(app)
    ..scheduleWarmUpFrame();
}
```

`WidgetsFlutterBinding`的源码：

```dart
class WidgetsFlutterBinding extends BindingBase with GestureBinding, ServicesBinding, SchedulerBinding, PaintingBinding, SemanticsBinding, RendererBinding, WidgetsBinding {
  static WidgetsBinding ensureInitialized() {
    if (WidgetsBinding.instance == null)
      WidgetsFlutterBinding();
    return WidgetsBinding.instance;
  }
}

abstract class BindingBase {
  
  BindingBase() {
    ...
    initInstances();
    ...
  }
    ...
}
```

总结一下，`runApp`运行后，会调用`WidgetsFlutterBinding.ensureInitialized()`, 该方法会实例化`WidgetsFlutterBinding`对象，而在实例化过程中，会调用`initInstances`方法，此时，`RendererBinding.initInstances`方法被调用了。

#### 流程图

markNeedsPaint -> parent1->parent2->....->parent3( repaintBoundary or not RenderObject )

↓

PipelineOwner._nodesNeedingPaint add parent3（if repaintBoundary）

↓

owner.requestVisualUpdate()

↓

SchedulerBinding.ensureVisualUpdate() -> SchedulerBinding.scheduleFrame()

↑

RendererBinding.initInstances()

↑

WidgetsFlutterBinding() 对象实例化

↑

WidgetsFlutterBinding.ensureInitialized()

↑

runApp()

### markNeedsLayout()

#### 主要变量

- _needsLayout
- _relayoutBoundary

#### 作用

标记该节点需要被重新`layout`

#### 处理逻辑

如果该节点是布局边界了，那直接将本节点加到`pipelineOwner._nodesNeedingLayout`中，等待下一帧的集中处理。

如果该节点不是布局边界，调用`markParentNeedsLayout`，通知父节点需要被重新`layout`

### markParentNeedsLayout()

调用`parent.markNeedsLayout`

### markNeedsLayoutForSizedByParentChange()

当`sizedByParent`值变化后，自身以及父节点都需要被重新`layout`，所以直接调用：

- markNeedsLayout
- markParentNeedsLayout

### layout

#### 主要变量

- parentUsesSize.  是否依赖子节点，在调用`child.layout`时作为入参传入。
- sizedByParent. 是否只受父节点自身约束的影响。如果是，在`performlayout`之前需要先计算`performSize`。
- constraints.  在调用`child.layout`时作为入参传入, 代表当前节点的约束。
- _relayoutBoundary. 是否是布局的边界. 四个条件，满足一个就可：!parentUsesSize / sizedByParent / constraints.isTight / 自身不是RenderObject
- _needsLayout. 标记是否需要layout

#### 作用

`render object`开始`layout`的入口。父节点都是通过调用`child.layout`来传递的。

#### 处理逻辑

首先，设置本`render object`的`layout boundary`, 若`boundary`有变化，通知子节点清空之前的`boundary`信息，直到遇到是`boundary`的子节点为止。

接着，`sizedByParent`是`true`的话，先通过`performResize`方法计算出`render object`的大小。

然后，再调用`performLayout`, 该方法也需要调用所有子节点的`layout`方法，触发子节点开始布局。

最后，调用`markNeedsPaint`,要求重新绘制。

### performLayout

`performLayout`不光需要`layout`自身，还需要调用所有`child.layout`。

### performResize

如果`sizedByParent == true`, 说明本`render object`只受制于`parent`的约束，在`layout`时，需要先`performResize`出自身的大小，再根据这个大小去`performLayout`.

### parentData

父节点通过`setupParentData`来给当前节点初始化`parentData`。一般在`mount`时被调用。

`ParentDataWidget` 通过`applyParentData`来主动更新某个`child`的`parentData`内容。

在`RenderObjectElement.mount->attachRenderObject`中，会向上找到`ParentDataWidget`的父类，并通过`applyParentData`来设置当前节点的`parentData`值。

#### 作用

父节点在`performLayout`时，会根据`child.parentData`的值算出这个`child`的`constraints`, 然后调用`child.layout`并传入该`contraints`。

#### 实例

`Stack-Positioned`

因为`Positioned`继承自`ParentDataWidget`, 它不是`RenderObjectElement`, 没有`RenderObject`,不会挂载在`RenderObject`树上。所以，在`RenderStack`的`performLayout`方法中，所有相关的`child`均是`RenderObject`. （`RenderObject`在挂载时，会自动向上找最近的一个`RenderObjectElement.renderObject`去挂载。） 

所以，通过`Positioned`将`left/right/top/bottom`信息设置到它的`child`上之后，`Stack`根据`child.parentData`的信息，计算出该`child`的`constraints`, 再调用`child.layout`进行布局。



## Widget

### StatefulWidget

TODO



## 绘制流程

#### runApp时的绘制流程

runApp -> RenderObjectToWidgetAdapter( root widget ) -> attachToRenderTree -> RenderObjectToWidgetElement( root element ) -> set root element owner -> root-element-owner.buildScope -> renderObjectToWidgetElement.mount -> container( root render object ) / parent  = null / slot = null / depth = 1 -> renderObjectToWidgetElement._rebuild

↓

updateChild -> canUpdate -> update ...> (StatulElement) ...> state.didUpdateWidget -> state.build

↓

widget.createElement

↓

renderObjectElement.mount

↓

parent / slot / owner = parent.owner / depth = parent.depth + 1 （render object element 形成一颗树）

↓

widget.createRenderObject

↓

renderObjectElement.attachRenderObject

↓

ancestorRenderObjectElement.insertChildRenderObject （render object 形成一颗树）->adoptChild(->setupParentData)/dropChild -> attach/detach

↓

回到上面的updateChild( RenderObjectElement的子类，在mount方法中会调用该方法 )

...

↓

SchedulerBinding.instance.ensureVisualUpdate()

#### 页面刷新流程

SchedulerBinding.scheduleFrame()

↓

set window.onBeginFrame / window.onDrawFrame

↓

Window.scheduleFrame。 native方法，应该是注册同步信号监听。当有同步信号过来后，会调用上面的两个回调

↓

SchedulerBinding.handleDrawFrame()中，所有的`_persistentCallbacks`都会被回调。而在RendererBinding.initInstances中，就添加了一个回调监听：`_handlePersistentFrameCallback`

↓

RendererBinding._handlePersistentFrameCallback()

↓

WidgetsBinding.drawFrame(). 

> 仔细看源码的话，会发现这里好像不对，应该调用RendererBinding.drawFrame()。但其实是WidgetsBinding.drawFrame()方法重写了RendererBinding.drawFrame()方法。WidgetsBinding.super.drawFrame()就是指RendererBinding.drawFrame()方法。

↓

buildOwner.buildScope(renderViewElement)。跟第一次的绘制流程调的一个方法。

↓

RendererBinding.drawFrame(). 包括layout/compositingBits/paint/将layer-tree合并送给GPU绘制等。



#### 一帧的所有阶段

- 动画阶段。

  `Window.onBeginFrame`时会调用`handleBeginFrame`, 而`handleBeginFrame`会遍历瞬态帧注册回调队列`_transientCallbacks`。

  ```dart
  void handleBeginFrame(Duration rawTimeStamp) {
      Timeline.startSync('Frame', arguments: timelineWhitelistArguments);
      
      ...
  
      assert(schedulerPhase == SchedulerPhase.idle);
      _hasScheduledFrame = false;
      try {
        // TRANSIENT FRAME CALLBACKS
        Timeline.startSync('Animate', arguments: timelineWhitelistArguments);
        _schedulerPhase = SchedulerPhase.transientCallbacks;
        final Map<int, _FrameCallbackEntry> callbacks = _transientCallbacks;
        _transientCallbacks = <int, _FrameCallbackEntry>{};
        callbacks.forEach((int id, _FrameCallbackEntry callbackEntry) {
          if (!_removedIds.contains(id))
            _invokeFrameCallback(callbackEntry.callback, _currentFrameTimeStamp, callbackEntry.debugStack);
        });
        _removedIds.clear();
      } finally {
        _schedulerPhase = SchedulerPhase.midFrameMicrotasks;
      }
    }
  ```

  我们在写动画时，都会`minix`一个`Ticker`, 来提供同步信号，应该就是这里的回调。`Ticker`中的部分源码：

  ```dart
  class Ticker {
      ...
      void scheduleTick({ bool rescheduling = false }) {
          assert(!scheduled);
          assert(shouldScheduleTick);
          _animationId = SchedulerBinding.instance.scheduleFrameCallback(_tick, 				rescheduling: rescheduling);
      }
  }
  ```

  `Ticker`会帮助向`SchedulerBinding`中注册帧回调，在window.onBeginFrame时会告诉`Ticker`：时机到了，可以更新动画的值了。这时，`AnimationController`会计算出当前的值，并且通知`widget`更新。

- 微任务阶段。等待在第一步中回调之后产生的新的微任务完成。

- build阶段。

- layout阶段。

- 合成位阶段。

- paint阶段。

- 合成阶段。将layer-tree合并并送给GPU。

- 语意阶段（供盲人使用的）。

- The finalization phase in the widgets layer。部分Widget在绘制中被移除之后，调用state.dispose方法。

- The finalization phase in the scheduler layer。`handleDrawFrame`中回调通过`addPostFrameCallback`注册的监听帧完成回调。

  

  
