---
layout: post
title: MediaQueryData中的viewInsets、viewPadding、padding
tags: ["Flutter"]
---

读源码时，经常可以看到源码中有`MediaQuery.of(context).data`，获取viewInsets、viewPadding、padding，三个参数类型都一样，对各自代表的含义很模棱两可，今天来探究一番。

首先，将这三个参数的官方解释贴出来：

1. viewInsets

```dart
类：MediaQueryData

/// The parts of the display that are completely obscured by system UI,
/// typically by the device's keyboard.
///
/// When a mobile device's keyboard is visible `viewInsets.bottom`
/// corresponds to the top of the keyboard.
///
/// This value is independent of the [padding] and [viewPadding]. viewPadding
/// is measured from the edges of the [MediaQuery] widget's bounds. Padding is
/// calculated based on the viewPadding and viewInsets. The bounds of the top
/// level MediaQuery created by [WidgetsApp] are the same as the window
/// (often the mobile device screen) that contains the app.
///
/// See also:
///
///  * [ui.window], which provides some additional detail about this property
///    and how it relates to [padding] and [viewPadding].
final EdgeInsets viewInsets;


-----------------------------------------------------------------------------
类：Window
     
/// The number of physical pixels on each side of the display rectangle into
/// which the application can render, but over which the operating system
/// will likely place system UI, such as the keyboard, that fully obscures
/// any content.
///
/// When this property changes, [onMetricsChanged] is called.
///
/// The relationship between this [Window.viewInsets], [Window.viewPadding],
/// and [Window.padding] are described in more detail in the documentation for
/// [Window].
///
/// See also:
///
///  * [WidgetsBindingObserver], for a mechanism at the widgets layer to
///    observe when this value changes.
///  * [MediaQuery.of], a simpler mechanism for the same.
///  * [Scaffold], which automatically applies the view insets in material
///    design applications.
WindowPadding get viewInsets => _viewInsets;
WindowPadding _viewInsets = WindowPadding.zero;
```

2. viewPadding

```dart
类：MediaQueryData

/// The parts of the display that are partially obscured by system UI,
/// The parts of the display that are partially obscured by system UI,
/// typically by the hardware display "notches" or the system status bar.
///
/// This value remains the same regardless of whether the system is reporting
/// other obstructions in the same physical area of the screen. For example, a
/// software keyboard on the bottom of the screen that may cover and consume
/// the same area that requires bottom padding will not affect this value.
///
/// This value is independent of the [padding] and [viewInsets]: their values
/// are measured from the edges of the [MediaQuery] widget's bounds. The
/// bounds of the top level MediaQuery created by [WidgetsApp] are the
/// same as the window that contains the app. On mobile devices, this will
/// typically be the full screen.
///
/// See also:
///
///  * [ui.window], which provides some additional detail about this
///    property and how it relates to [padding] and [viewInsets].
final EdgeInsets viewPadding;
    
-----------------------------------------------------------------------------
类：Window
    
/// The number of physical pixels on each side of the display rectangle into
/// which the application can render, but which may be partially obscured by
/// system UI (such as the system notification area), or or physical
/// intrusions in the display (e.g. overscan regions on television screens or
/// phone sensor housings).
///
/// Unlike [Window.padding], this value does not change relative to
/// [Window.viewInsets]. For example, on an iPhone X, it will not change in
/// response to the soft keyboard being visible or hidden, whereas
/// [Window.padding] will.
///
/// When this property changes, [onMetricsChanged] is called.
///
/// The relationship between this [Window.viewInsets], [Window.viewPadding],
/// and [Window.padding] are described in more detail in the documentation for
/// [Window].
///
/// See also:
///
///  * [WidgetsBindingObserver], for a mechanism at the widgets layer to
///    observe when this value changes.
///  * [MediaQuery.of], a simpler mechanism for the same.
///  * [Scaffold], which automatically applies the padding in material design
///    applications.
WindowPadding get viewPadding => _viewPadding;
WindowPadding _viewPadding = WindowPadding.zero;
```

3. padding

```dart
类：MediaQueryData

/// The parts of the display that are partially obscured by system UI,
/// typically by the hardware display "notches" or the system status bar.
///
/// If you consumed this padding (e.g. by building a widget that envelops or
/// accounts for this padding in its layout in such a way that children are
/// no longer exposed to this padding), you should remove this padding
/// for subsequent descendants in the widget tree by inserting a new
/// [MediaQuery] widget using the [MediaQuery.removePadding] factory.
///
/// Padding is derived from the values of [viewInsets] and [viewPadding].
///
/// See also:
///
///  * [ui.window], which provides some additional detail about this
///    property and how it relates to [viewInsets] and [viewPadding].
///  * [SafeArea], a widget that consumes this padding with a [Padding] widget
///    and automatically removes it from the [MediaQuery] for its child.
final EdgeInsets padding;
    
-----------------------------------------------------------------------------
类：Window
    
/// The number of physical pixels on each side of the display rectangle into
/// which the application can render, but which may be partially obscured by
/// system UI (such as the system notification area), or or physical
/// intrusions in the display (e.g. overscan regions on television screens or
/// phone sensor housings).
///
/// This value is calculated by taking
/// `max(0.0, Window.viewPadding - Window.viewInsets)`. This will treat a
/// system IME that increases the bottom inset as consuming that much of the
/// bottom padding. For example, on an iPhone X, [EdgeInsets.bottom] of
/// [Window.padding] is the same as [EdgeInsets.bottom] of
/// [Window.viewPadding] when the soft keyboard is not drawn (to account for
/// the bottom soft button area), but will be `0.0` when the soft keyboard is
/// visible.
///
/// When this changes, [onMetricsChanged] is called.
///
/// The relationship between this [Window.viewInsets], [Window.viewPadding],
/// and [Window.padding] are described in more detail in the documentation for
/// [Window].
///
/// See also:
///
///  * [WidgetsBindingObserver], for a mechanism at the widgets layer to
///    observe when this value changes.
///  * [MediaQuery.of], a simpler mechanism for the same.
///  * [Scaffold], which automatically applies the padding in material design
///    applications.
WindowPadding get padding => _padding;
WindowPadding _padding = WindowPadding.zero;
```

下面说一下我自己的理解：

1. viewInsets

   每个方向上被系统UI（如键盘，不包括系统通知栏等）挡住的屏幕物理像素。
   如键盘弹起时，EdgeInsets(0.0, 0.0, 0.0, 337.5)，
   左上右的方向上没有被系统UI覆盖，底部方向上被键盘遮盖了337.5的长度。

   > 在Window中是WindowPadding 屏幕物理像素，在MediaQueryData是 EdgetInsets 逻辑像素。

   

2. viewPadding

   每个方向上被系统UI（如系统通知栏，iphoneX底部的功能键等）挡住的屏幕物理像素。
   如EdgeInsets(0.0, 34.9, 0.0, 0.0)，
   左右下的方向上没有被系统UI覆盖，上方向上被系统通知栏占据了34.9的长度。键盘显示与否不会影响该值。

3. padding

   它是由`max(0.0, Window.viewPadding - Window.viewInsets)`计算来的。

   它应该是指导我们在布局时，应该考虑在相应方向上增加对应的padding，防止与系统UI重叠。但仅仅是指导，并不是一定要遵守。

   比如，在iponeX下，它默认有底部功能键。所以看它的padding: EdgeInsets(0.0, 34.9, 0.0, 33.4)。`Scaffold`在build `bottomNavigationBar`时，并没有移除底部padding:
   ```dart
   if (widget.bottomNavigationBar != null) {
    _addIfNonNull(
        children,
        widget.bottomNavigationBar,
        _ScaffoldSlot.bottomNavigationBar,
        removeLeftPadding: false,
        removeTopPadding: true,
        removeRightPadding: false,
        removeBottomPadding: false,
        maintainBottomViewPadding: !_resizeToAvoidBottomInset,
    );
   }
   ```
   
它这是要告诉它的child: 底部有系统控件，我没有处理啊，你在布局的时候要空出padding.bottom的高度来呀。
   
但是，我们在设置`bottomNavigationBar`时，不理会不处理它，直接设置`Text("123")`时，没有理会这个padding.bottom, 最后导致它与系统功能键重叠。
   
   如果我们加上`SafeArea(child: Text("123"))`, 此时`SafeArea`替它处理掉了padding, 这样就不会与系统UI重叠了。
   
   `SafeArea`的部分源码：
   
   ```dart
   @override
   Widget build(BuildContext context) {
       assert(debugCheckHasMediaQuery(context));
       final MediaQueryData data = MediaQuery.of(context);
       EdgeInsets padding = data.padding;
       // Bottom padding has been consumed - i.e. by the keyboard
       if (data.padding.bottom == 0.0 && data.viewInsets.bottom != 0.0 && maintainBottomViewPadding)
           padding = padding.copyWith(bottom: data.viewPadding.bottom);
   
       return Padding(
           padding: EdgeInsets.only(
               left: math.max(left ? padding.left : 0.0, minimum.left),
               top: math.max(top ? padding.top : 0.0, minimum.top),
               right: math.max(right ? padding.right : 0.0, minimum.right),
               bottom: math.max(bottom ? padding.bottom : 0.0, minimum.bottom),
           ),
           child: MediaQuery.removePadding(
               context: context,
               removeLeft: left,
               removeTop: top,
               removeRight: right,
               removeBottom: bottom,
               child: child,
           ),
       );
   }
   ```
