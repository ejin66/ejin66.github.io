# RenderObject

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

如果`RenderObject`的`isRepaintBoundary == true`, 表示上访就到此为止了，不能再向上传递了，否则要出大事。接着，将该`RenderObject`加入到`owner._nodesNeedingPaint`队列，等待会重绘，并且调用`owner.requestVisualUpdate()`。

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

当我们调用`markNeedsPaint`之后，会调用到`ensureVisualUpdate`。而只有`schedulerPhase == SchedulerPhase.postFrameCallbacks`, 才会去安排下一帧的绘制。其他情况下都不做反应。

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