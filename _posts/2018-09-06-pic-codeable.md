---
layout: post
title: 图片字符化实践
tags: [Android]
---

### 效果图

首先，看下原图：

![原图]({{site.baseurl}}/assets/img/pexels/image_codeable_before.jpg)

处理之后的效果图：

![处理效果图]({{site.baseurl}}/assets/img/pexels/image_codeable_after.jpg)

<br/>

### 实现原理

原理其实很简单：获取每一个像素点，进行灰度处理；再根据灰度值，匹配对应的ascii字符 , 最后保存到图片。

<br/>

### 实践

实际处理过程中，会遇到几个问题：

1. _如何进行灰度处理：_

   根据百度搜索的结果，灰度计算有以下几个方法，选取其中一种就可以：

   - 浮点算法：Gray=R * 0.3+G * 0.59+B * 0.11
   - 整数方法：Gray=(R * 30+G * 59+B * 11) / 100
   - 移位方法：Gray =(R * 77+G * 151+B * 28) >> 8
   - 平均值法：Gray=（R+G+B）/ 3
   - 仅取绿色：Gray=G

2. _灰度值应该对应哪些 ascii 字符：_

   灰度值的范围是[0 - 255]，ascii字符可以自己选择，只要保证字符大小线性递增/递减就可以了；我选择的字符数组为：

   ```kotlin
   private val templateAscii = charArrayOf('@', '#', '&', '$', '%', '*', 'x', 'e', 'o', 'c', 'i', '、',  ';', ':',  ',', '.', ' ')
   ```

3. _如何将转换后的字符保存到图片_

   通过Canvas来保存，具体是将相应字符绘制到canvas上，然后将canvas的bitmap转成JPG写到文件。

   在字符绘制的过程中，也会有几个问题需要被考虑（解决方案见下方源码）：

   - 每个像素点都换成一个字符，当图片分辨率本就很大时，很容易造成OOM
   - 绘制的字符是一个宽短长高的形状，导致最后生成的图片与原图片的宽高比不一致
   - 每个字符的宽高也不同，如空格的绘制宽度只有一般字符的一半，那要如何保证图片不变形

<br/>

### 源码

```kotlin
class PicToActivity: AppCompatActivity() {

    val PIC_REQUEST_CODE = 1000

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_pic)
        btn.setOnClickListener {
            Intent().apply {
                type = "image/*"
                action = Intent.ACTION_GET_CONTENT
                startActivityForResult(this, PIC_REQUEST_CODE)
            }
        }
    }

    private fun convert(bitmap: Bitmap) {
        val paint = Paint().apply {
            color = Color.BLACK
        }

        //计算字符的宽高
        val rect = Rect()
        paint.getTextBounds(templateAscii[0].toString(),0,1, rect)
        //设置字符等高等宽
        val textWidth = Math.max(rect.width(), rect.height())
        val textHeight = textWidth
        //计算绘制时的baseline
        val baseline = textHeight / 2 + Math.abs(paint.fontMetrics.ascent)/2 - Math.abs(paint.fontMetrics.descent)/2
        //预先设置最大图片像素
        val maxPixels = 1920 * 1920.0
        //未做处理时的像素
        val shouldPixels = bitmap.width * textWidth * bitmap.height * textHeight
		//两者的比例
        //要兼顾低分辨率、高分辨率
        //低分辨率下不需要做格外处理，不然会导致最后的效果图不理想
        //高分辨率下需要跳过部分像素点，否则会导致OOM
        val rate = Math.round(Math.max(Math.sqrt(shouldPixels / maxPixels), 1.0)).toInt()

        val codeBitmap = Bitmap.createBitmap((bitmap.width * textWidth / rate), (bitmap.height * textHeight / rate), Bitmap.Config.RGB_565)
        val canvas = Canvas(codeBitmap)
        canvas.drawColor(Color.WHITE)

        var tmpHeight = baseline
        var tmpWidth = 0F
        // 只能逐个绘制，不能一行一行的绘制。因为不同字符的宽高不一定相同，逐行绘制会导致最后效果图扭曲。
        (0 until bitmap.height step rate).forEach {
            val heightIndex = it
            (0 until bitmap.width step rate).forEach {
                val c = getAscii(bitmap.getPixel(it, heightIndex))
                //不是空字符才绘制
                if (c != ' ') {
                    canvas.drawText(c.toString(), tmpWidth, tmpHeight, paint)
                }
                tmpWidth += textWidth
            }
            tmpHeight += textHeight
            tmpWidth = 0F
        }
        iv.setImageBitmap(codeBitmap)
        bitmap.recycle()

        if (cb.isChecked) {
            val fullpath = externalCacheDir.absolutePath + File.separator + System.currentTimeMillis() + ".jpg"
            FileOutputStream(fullpath).apply {
                codeBitmap.compress(Bitmap.CompressFormat.JPEG, 100, this)
                this.close()
            }
            Toast.makeText(this, "已保存到$fullpath", Toast.LENGTH_SHORT).show()
        }
    }

    private val templateAscii = charArrayOf('@', '#', '&', '$', '%', '*', 'x', 'e', 'o', 'c', 'i', '、',  ';', ':',  ',', '.', ' ')

    private fun getAscii(pixel: Int): Char {
        val gray = 0.299F * Color.red(pixel) + 0.578F * Color.green(pixel) + 0.114F * Color.blue(pixel)
        val index = Math.round(templateAscii.size * gray / 0xFF)
        return if (index >= templateAscii.size) {
            templateAscii.last()
        } else {
            templateAscii[index]
        }
    }

    override fun onActivityResult(requestCode: Int, resultCode: Int, data: Intent?) {
        super.onActivityResult(requestCode, resultCode, data)
        if (requestCode == PIC_REQUEST_CODE && resultCode == Activity.RESULT_OK) {
            data?.data?.let {
                val bitmap = BitmapFactory.decodeStream(contentResolver.openInputStream(it), Rect(), BitmapFactory.Options())
                convert(bitmap)
            }
        }
    }

}
```

layout文件：

```xml
<?xml version="1.0" encoding="utf-8"?>
<android.support.constraint.ConstraintLayout xmlns:android="http://schemas.android.com/apk/res/android"
    xmlns:tools="http://schemas.android.com/tools"
    android:layout_width="match_parent"
    android:layout_height="match_parent"
    xmlns:app="http://schemas.android.com/apk/res-auto">

    <Button
        android:id="@+id/btn"
        android:layout_width="wrap_content"
        android:layout_height="wrap_content"
        android:text="选择图片"
        />

    <CheckBox
        android:id="@+id/cb"
        android:layout_width="wrap_content"
        android:layout_height="wrap_content"
        app:layout_constraintLeft_toRightOf="@id/btn"
        app:layout_constraintTop_toTopOf="@id/btn"
        app:layout_constraintBottom_toBottomOf="@id/btn"
        android:layout_marginLeft="10dp"
        android:text="保存到本地"/>

    <ImageView
        android:id="@+id/iv"
        android:layout_width="match_parent"
        android:layout_height="0dp"
        android:scaleType="centerInside"
        android:src="@mipmap/ic_launcher"
        app:layout_constraintLeft_toLeftOf="parent"
        app:layout_constraintRight_toRightOf="parent"
        app:layout_constraintBottom_toBottomOf="parent"
        app:layout_constraintTop_toBottomOf="@id/btn"/>

</android.support.constraint.ConstraintLayout>
```

