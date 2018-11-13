---
layout: post
title: OpenGL中的移动、缩放、旋转
tags: [OpenGL]
---

`OpenGL`中关于 移动、缩放、旋转 等的操作，本质上都是矩阵变换的结果。在`OpenGL`中，已经内置了相应的API，使得我们不用去关心它的实现细节。矩阵变换相关的接口在`android.opengl.matrix`中，暂时使用到的有：

- `Matrix.rotateM(m, offset, a, x, y, z)`  旋转

  - m  表示原矩阵
  - offset 表示矩阵数组是用m的哪个index开始
  - a  表示旋转的角度，不是弧度
  - x,y,z 表示向量。已该向量为轴，逆时针旋转

- `Matrix.translateM(m, offset, x, y, z)`

  - m  表示原矩阵
  - offset 表示矩阵数组是用m的哪个index开始
  - x,y,z 表示位移因子

- `Matrix.scaleM(m, offset, x, y, z)`

  - m  表示原矩阵
  - offset 表示矩阵数组是用m的哪个index开始
  - x,y,z 表示缩放因子。m * (x,y,z)

<br/>

简单的工具类封装：

```kotlin
object MatrixUtil {

    private var currentMatrix = FloatArray(16)

    fun start(): MatrixUtil{
        currentMatrix = floatArrayOf(
                1F, 0F, 0F, 0F,
                0F, 1F, 0F, 0F,
                0F, 0F, 1F, 0F,
                0F, 0F, 0F, 1F
        )
        return this
    }

    fun rotate(r: Float): MatrixUtil {
        Matrix.rotateM(currentMatrix, 0, r, 0F, 0F, 1F)
        return this
    }

    fun translate(x: Float, y: Float, z: Float): MatrixUtil {
        Matrix.translateM(currentMatrix, 0, x, y, z)
        return this
    }

    fun scale(s: Float): MatrixUtil {
        Matrix.scaleM(currentMatrix, 0, s, s, s)
        return this
    }

    fun getMatrix() = currentMatrix
}
```

