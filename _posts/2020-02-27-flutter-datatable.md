---
layout: post
title: Flutter DataTable 无法居中的BUG以及优化
tags: [Flutter]
---

# `Flutter DataTable`的bug

在使用原生控件`DataTable`时，会发现表内单元格的内容无法居中，单元格右侧会多出一段。这实际是`DataTable`的一个bug, `DataTable`的部分源码：

```dart
class DataTable extends Stateless {
    
    //...
        
    Widget build(BuildContext context) {
      	//...
        tableRows[0].children[displayColumnIndex] = _buildHeadingCell(
            context: context,
            padding: padding,
            label: column.label,
            tooltip: column.tooltip,
            numeric: column.numeric,
            //1.这里的onSort一定是不等于null的
            onSort: () => column.onSort != null ? column.onSort(dataColumnIndex, sortColumnIndex != dataColumnIndex || !sortAscending) : null,
            sorted: dataColumnIndex == sortColumnIndex,
            ascending: sortAscending,
          );
    }
    
    //...
    
    Widget _buildHeadingCell({
        BuildContext context,
        EdgeInsetsGeometry padding,
        Widget label,
        String tooltip,
        bool numeric,
        VoidCallback onSort,
        bool sorted,
        bool ascending,
      }) {
        //2.导致这个if判断是一定会执行的
        if (onSort != null) {
          //arrow的宽度是16
          final Widget arrow = _SortArrow(
            visible: sorted,
            down: sorted ? ascending : null,
            duration: _sortArrowAnimationDuration,
          );
          //arrowPadding的宽度是2
          const Widget arrowPadding = SizedBox(width: _sortArrowPadding);
          //这样，会导致单元格的后面，多出18的长度
          label = Row(
            textDirection: numeric ? TextDirection.rtl : null,
            children: <Widget>[ label, arrowPadding, arrow ],
          );
        }
        //...
    }
}
```

所以，需要将上面注释1的代码，改成下面这样就可以了:

```dart
onSort: column.onSort != null ? () => column.onSort(dataColumnIndex, sortColumnIndex != dataColumnIndex || !sortAscending) : null,
```

# 升级`DataTable`

原生`DataTable`其实不是很好用，单元格无法对齐，无法设置背景颜色，没有网格线等等。

通过封装的`EnhanceDataTable`支持设置背景颜色、单元格自动居中、支持网格线、首行首列固定的优点。

```dart
import 'package:flutter/material.dart'
    hide DataTable, DataColumn, DataRow, DataCell;

import 'data_table.dart';

class EnhanceDataTable<T> extends StatefulWidget {
  //是否固定第一行
  final bool fixedFirstRow;
  //是否固定第一列
  final bool fixedFirstCol;
  //包括header以及cell内容的list
  final List<List<T>> rowsCells;
  //支持自定义单元格格式
  final Widget Function(T data) cellBuilder;
  //单元格高度
  final double cellHeight;
  //单元格两边空白cellSpacing/2
  final double cellSpacing;
  //设置不同列的宽度
  final double Function(int columnIndex) columnWidth;
  //表格头的背景颜色
  final Color headerColor;
  final TextStyle headerTextStyle;
  //表格内容的颜色交替
  final List<Color> cellAlternateColor;
  //表格内容的字体颜色
  final TextStyle cellTextStyle;
  //是否显示网格线
  final bool showBorderLine;
  //网格线颜色
  final Color borderColor;

  EnhanceDataTable({
    this.fixedFirstRow = false,
    this.fixedFirstCol = false,
    @required this.rowsCells,
    @required this.columnWidth,
    this.cellBuilder,
    this.cellHeight = 56.0,
    this.cellSpacing = 10.0,
    this.headerColor,
    this.cellAlternateColor,
    this.headerTextStyle,
    this.cellTextStyle,
    this.showBorderLine = true,
    this.borderColor,
  })  : assert(cellAlternateColor == null || cellAlternateColor.length == 2),
        assert(borderColor == null || showBorderLine);

  @override
  State<StatefulWidget> createState() => EnhanceDataTableState();
}
```

注意到一开始的`import`:

```dart
import 'package:flutter/material.dart'
    hide DataTable, DataColumn, DataRow, DataCell;
import 'data_table.dart';
```

首先，是移除掉原生`DataTable`相关控件，然后，使用我们自己的`data_table.dart`文件。这个是将源码文件`data_table.dart`拷贝到本地，并修复了上面说的bug。

源码：[EnhanceDataTable](https://github.com/ejin66/enhancedatatable)