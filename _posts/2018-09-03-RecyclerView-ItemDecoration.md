---
layout: post
title: 自定义RecyclerView ItemDecoration
tags: [Android, RecyclerView, ItemDecoration]
---



### ItemDecoration简单介绍

`ItemDecoration` ， 与 `LayoutManager` 类似， 是 `RecyclerView` 的静态内部类。从它的名字上，就能简单看出它的作用：给Item进行装饰。最简单的应用：实现分割线功能，其他更深入的方面如分组、分组跟随等。

它的使用也很简单：

```kotlin
recyclerView.addItemDecoration(LinearItemDecoration())
```

<br />



### 如何自定义ItemDecoration

介绍下 `ItemDecoration` 的几个重要方法：

- onDraw(Canvas, RecyclerView, RecyclerView.State)。利用Canvas, 可以在指定位置绘制内容
- onDrawOver(Canvas, RecyclerView, RecyclerView.State)。与onDraw类似，不同点在于onDrawOver的绘制会覆盖Item视图。
- getItemOffsets(Rect, View, RecyclerView, RecyclerView.State)。 入参Rect是一个空矩阵，通过修改它，设置当前View的Offset值（View的位置外扩值）。如Rect.top = 10, 则View.top上面10px的范围空间，可以通过onDraw、onDrawOver来绘制内容。

<br />



### 实现分割线功能

1. **_垂直布局上的分割线_**

```kotlin
import android.graphics.Canvas;
import android.graphics.Color;
import android.graphics.Paint;
import android.graphics.Rect;
import android.support.v7.widget.RecyclerView;
import android.view.View;

public class LinearItemDecoration extends RecyclerView.ItemDecoration {

	private int color;
	private int dividerHeight;
	private Paint mPaint;

	public LinearItemDecoration() {
		this(Color.parseColor("#ded6d6"), 1);
	}

	public LinearItemDecoration(int color, int dividerHeight) {
		this.color = color;
		this.dividerHeight = dividerHeight;
		mPaint = new Paint(Paint.ANTI_ALIAS_FLAG);
		mPaint.setColor(color);
		mPaint.setStyle(Paint.Style.FILL);
	}

	@Override
	public void onDraw(Canvas c, RecyclerView parent, RecyclerView.State state) {
		super.onDraw(c, parent, state);
		for (int i = 0; i < parent.getChildCount(); i++) {
			View view = parent.getChildAt(i);
			int left = view.getLeft();
			int right = view.getRight();
			int top = view.getBottom();
			int bottom = top + dividerHeight;
			c.drawRect(left, top, right, bottom, mPaint);
		}
	}

	@Override
	public void getItemOffsets(Rect outRect, View view, RecyclerView parent, RecyclerView.State state) {
		super.getItemOffsets(outRect, view, parent, state);
		outRect.bottom = dividerHeight;
	}
}
```

> 设置 `outRect.bottom` 值，设定 item view 的底部分割线位置空间，然后在 `onDraw` 方法中，绘制出分割线。

2. **_网格布局的分割线_**

```kotlin
import android.graphics.Canvas;
import android.graphics.Color;
import android.graphics.Paint;
import android.graphics.Rect;
import android.support.v7.widget.GridLayoutManager;
import android.support.v7.widget.RecyclerView;
import android.support.v7.widget.StaggeredGridLayoutManager;
import android.view.View;

/**
 * Created by j17420 on 2017/7/18.
 */

public class GridItemDecoration extends RecyclerView.ItemDecoration {

    private int color;
    private int dividerHeight;
    private Paint mPaint;

    public GridItemDecoration() {
        this(Color.parseColor("#ded6d6"), CommonUtils.dp2px(ApplicationHolder.getApplicationContext(), 1)/2);
    }

    public GridItemDecoration(int color, int dividerHeight) {
        this.color = color;
        this.dividerHeight = dividerHeight;
        mPaint = new Paint(Paint.ANTI_ALIAS_FLAG);
        mPaint.setColor(color);
        mPaint.setStyle(Paint.Style.FILL);
    }

    @Override
    public void onDraw(Canvas c, RecyclerView parent, RecyclerView.State state) {
        super.onDraw(c, parent, state);
        int spanCount = getSpanCount(parent);
        for (int i = 0; i < parent.getChildCount(); i++) {
            View view = parent.getChildAt(i);

            if (i + 1 <= parent.getChildCount() / spanCount * spanCount) {
                //水平线
                int left = view.getLeft();
                int right = view.getRight();
                int top = view.getBottom();
                int bottom = top + dividerHeight;
                c.drawRect(left, top, right, bottom, mPaint);
            }

            if (parent.getChildCount() != i + 1) {
                //垂直线
                int left = view.getRight();
                int right = left + dividerHeight;
                int top = view.getTop();
                int bottom = view.getBottom();
                c.drawRect(left, top, right, bottom, mPaint);
            }
        }
    }

    @Override
    public void onDrawOver(Canvas c, RecyclerView parent, RecyclerView.State state) {
        super.onDrawOver(c, parent, state);
    }

    @Override
    public void getItemOffsets(Rect outRect, View view, RecyclerView parent, RecyclerView.State state) {
        super.getItemOffsets(outRect, view, parent, state);
        int spanCount = getSpanCount(parent);
        int adapterPosition = parent.getChildAdapterPosition(view);

        outRect.right = dividerHeight;
        outRect.bottom = dividerHeight;

        if (adapterPosition % spanCount == spanCount - 1) {
            outRect.right = 0;
        }
    }

    //列数
    private int getSpanCount(RecyclerView parent) {
        int spanCount = -1;
        RecyclerView.LayoutManager layoutManager = parent.getLayoutManager();
        if (layoutManager instanceof GridLayoutManager) {
            spanCount = ((GridLayoutManager) layoutManager).getSpanCount();
        } else if (layoutManager instanceof StaggeredGridLayoutManager) {
            spanCount = ((StaggeredGridLayoutManager) layoutManager).getSpanCount();
        }
        return spanCount;
    }
}
```

<br />



### 实现分组功能

> 思路与分割线的功能类似。如果是组头， 设置组头Tag的位置空间；否则不设置绘制空间。

```kotlin
import android.content.Context;
import android.graphics.Canvas;
import android.graphics.Paint;
import android.graphics.Rect;
import android.support.v7.widget.RecyclerView;
import android.view.View;

/**
 * Created by j17420 on 2017/7/26.
 */

public class SelectionItemDecoration extends RecyclerView.ItemDecoration {

	private GroupInformation groupInformation;
	private int topDistance;
	private Paint mPaint;
	private Paint.FontMetrics fontMetrics;
	private int backgroundColor;
	private int frontColor;

	public SelectionItemDecoration(Context context, GroupInformation groupInformation) {
		this.groupInformation = groupInformation;
		topDistance = CommonUtils.dp2px(context, 40);
		mPaint = new Paint(Paint.ANTI_ALIAS_FLAG);
		mPaint.setStyle(Paint.Style.FILL);
		mPaint.setTextSize(40);
		backgroundColor = context.getResources().getColor(R.color.drawerBarColor);
		frontColor = context.getResources().getColor(R.color.textColorNormal);
		fontMetrics = mPaint.getFontMetrics();
	}

	@Override
	public void onDraw(Canvas c, RecyclerView parent, RecyclerView.State state) {
		super.onDraw(c, parent, state);
		int left = parent.getPaddingLeft() + topDistance/3;
		int right = parent.getWidth() - parent.getPaddingRight();
		for (int i = 0 ; i < parent.getChildCount() ; i++) {
			View child = parent.getChildAt(i);
			int adapterPosition = parent.getChildAdapterPosition(child);
			if (isGroupFirstItem(adapterPosition)) {
				int top = child.getTop() - topDistance;
				int bottom = child.getTop();
				float baseline = (bottom + top)/2 + Math.abs(fontMetrics.ascent)/2 - Math.abs(fontMetrics.descent)/2;
				mPaint.setColor(backgroundColor);
				c.drawRect(left, top, right, bottom, mPaint);
				mPaint.setColor(frontColor);
				c.drawText(groupInformation.groupName(adapterPosition), left, baseline, mPaint);
			}
		}
	}

	@Override
	public void getItemOffsets(Rect outRect, View view, RecyclerView parent, RecyclerView.State state) {
		super.getItemOffsets(outRect, view, parent, state);
		int adapterPosition = parent.getChildAdapterPosition(view);

		if (isGroupFirstItem(adapterPosition)) {
			outRect.top = topDistance;
		} else {
			outRect.top = 0;
		}
	}

	public boolean isGroupFirstItem(int position) {
		String groupName = groupInformation.groupName(position);
		if (groupName == null) {
			return false;
		}
		if (position != 0 && 
            groupInformation.groupName(position)
            .equals(groupInformation.groupName(position - 1))) {
			return false;
		}
		return true;
	}

	public interface GroupInformation {
		String groupName(int position);
	}
}
```

<br />



### 实现分组跟随

实现思路：

​	当同一分组的在屏幕中显示的总高度大于要绘制的组Tag高度，通过onDrawOver方法绘制出组Tag（覆盖悬浮在组Item上方）。若总高度小于Tag高度，则绘制部分（通过设置绘制Rect.top为负值）。

```kotlin
import android.graphics.Canvas;
import android.graphics.Paint;
import android.graphics.Rect;
import android.graphics.Region;
import android.support.annotation.NonNull;
import android.support.v7.widget.RecyclerView;
import android.view.View;

import java.util.HashMap;
import java.util.Map;

public class PinnedSelectionItemDecoration extends RecyclerView.ItemDecoration {


	private Paint mPaint;
	/**
	 * 不同groupName，对应不同的top distance。做缓存，优化滑动效果
	 */
	private Map<String, Integer> topDistanceMap;


	public PinnedSelectionItemDecoration() {
		topDistanceMap = new HashMap<>();
		mPaint = new Paint(Paint.ANTI_ALIAS_FLAG);
		mPaint.setStyle(Paint.Style.FILL);
	}

	@Override
	public void onDrawOver(Canvas c, RecyclerView parent, RecyclerView.State state) {
		super.onDrawOver(c, parent, state);
		int itemCount = parent.getLayoutManager().getItemCount();
		int left = parent.getPaddingLeft();
		int right = parent.getWidth() - parent.getPaddingRight();
		String preGroupName, groupName = null;
		for (int i = 0; i < parent.getChildCount(); i++) {
			preGroupName = groupName;
			View child = parent.getChildAt(i);
			int adapterPosition = parent.getChildAdapterPosition(child);
			if (adapterPosition < 0) {
				return;
			}
			groupName = getPinnedInterface(parent).getGroupName(adapterPosition);
			if (groupName.equals(preGroupName)) {
				continue;
			}

			int topDistance = getTopDistance(parent, adapterPosition);
			int bottom = Math.max(child.getTop(), topDistance);
			for (int j = i + 1; j < parent.getChildCount(); j++) {
				if (adapterPosition + j - i < itemCount) {
					String nextGroupName = getPinnedInterface(parent).getGroupName(adapterPosition + j - i);
					int bottomTemp = parent.getChildAt(j - 1).getBottom();
					if (!nextGroupName.equals(groupName) && bottomTemp < topDistance) {
						bottom = bottomTemp;
						break;
					}
				}
			}

			int top = bottom - topDistance;

			Rect rect = new Rect(left, top, right, bottom);
			c.save();
			c.clipRect(rect, Region.Op.UNION);
			c.translate(0, top);
			getPinnedInterface(parent).getPinnedHeader(adapterPosition, parent).draw(c);
			c.restore();
		}
	}

	@Override
	public void getItemOffsets(Rect outRect, View view, RecyclerView parent, RecyclerView.State state) {
		super.getItemOffsets(outRect, view, parent, state);
		int adapterPosition = parent.getChildAdapterPosition(view);
		if (adapterPosition < 0) {
			return;
		}
		if (getPinnedInterface(parent).isGroupFirstItem(adapterPosition)) {
			outRect.top = getTopDistance(parent, adapterPosition);
		} else {
			outRect.top = 0;
		}
	}

	/**
	 * 计算头部的高度
	 *
	 * @param parent
	 * @param adapterPosition
	 * @return
	 */
	private int getTopDistance(RecyclerView parent, int adapterPosition) {
		String groupName = getPinnedInterface(parent).getGroupName(adapterPosition);
		if (topDistanceMap.containsKey(groupName)) {
			return topDistanceMap.get(groupName);
		}
		int distance = ((PinnedInterface) parent.getAdapter()).getPinnedHeaderHeight(adapterPosition, parent);
		topDistanceMap.put(groupName, distance);
		return distance;
	}

	private PinnedInterface getPinnedInterface(@NonNull RecyclerView parent) {
		return (PinnedInterface) parent.getAdapter();
	}

	public interface PinnedInterface {
		String getGroupName(int position);

		int getPinnedHeaderHeight(int position, @NonNull RecyclerView parent);

		View getPinnedHeader(int position, @NonNull RecyclerView parent);

		boolean isGroupFirstItem(int position);
	}
}
```

>  `RecyclerView` 的 `Adapter` 需要继承 `PinnedInterface` 接口。