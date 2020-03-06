### Physics
```dart
///计算理论上overscroll的长度.
///ScrollPosition.setPixels方法中被调用。
///position: 最近一个位置信息
///value: 当前应该在的位置（此时该pixel还未设置到positoin.pixel）
///return: 返回理论上overscroll的长度。
double applyBoundaryConditions(ScrollMetrics position, double value) {
    if (parent == null)
        return 0.0;
    return parent.applyBoundaryConditions(position, value);
}
```

ListView -> BoxScrollView -> ScrollView(StatelessWidget) -> Scrollable(StatefulWidget)





### Keep Alive

PageView -> Scrollable -> Viewport -> SliverFillViewport  -> _SliverFillViewportRenderObjectWidget -> KeyedSubtree -> AutomaticKeepAlive-> KeepAlive

其中，

keepAlive 继承自 ParentDataWidget<SliverWithKeepAliveWidget>

_SliverFillViewportRenderObjectWidget 继承自 SliverWithKeepAliveWidget

keep alive的主要逻辑在 RenderSliverMultiBoxAdaptor

