---
layout: post
title: OpenGL基础整理
tags: [OpenGL]
---

### A. `GLSurfaceView` 基础知识

`GLSurfaceView` 继承自 `SurfaceView`，并在其基础上增加了 `OpenGL` 环境，使我们能够快速的开发 `OpenGL`。

#### a. 关于`GLSurfaceView`的几个重要概念：

- 需要实现 `GLSurfaceView.Render` 接口，并通过方法 `setRender()` 设置到 `GLSurfaceView`
- 在设置完 `Render` 之后，`GLSurfaceView` 会创建子线程 `GLThread` , 后面所有的绘制都会在这个线程
- 通过 `setRenderMode` 设置渲染模式：
  - `GLSurfaceView.RENDERMODE_CONTINUOUSLY` 不间断绘制，该模式是默认模式。
  - `GLSurfaceView.RENDERMODE_WHEN_DIRTY` 只在屏幕变脏时绘制 
- 在`GLSurfaceView.RENDERMODE_WHEN_DIRTY`模式下，有两种情况发起绘制：
  - `GLSurfaceView.onResume()`
  - `GLSurfaceView.requestRender()`
- `setEGLContextClientVersion`  该方法创建 `OpenGLES` 环境。如设置`setEGLContextClientVersion(2)` , 前提是在`AndroidManifest.xml` 增加 `<uses-feature android:glEsVersion="0x00020000" />`

#### b. 简单代码示例：

```kotlin
class DemoActivity : AppCompatActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_demo)
        glView.setEGLContextClientVersion(2)
        glView.setRenderer(getRender())
		glView.renderMode = GLSurfaceView.RENDERMODE_WHEN_DIRTY
    }

    override fun onResume() {
        super.onResume()
        glView.onResume()
    }

    override fun onPause() {
        super.onPause()
        glView.onPause()
    }
}
```

<br/>

### B. `OpenGLES` 基础知识

`OpenGLES` 是 `OpenGL` 的定制版本，下面以`OpenGLES 2.0` 为例。

#### a. `OpenGLES` 下我当前接触到的一些概念：

- `顶点着色器`（vertex shader）
- `片元着色器`（fragment shader）
- 世界坐标系。遵循右手坐标系，即拇指、食指、其余指为x、y、z轴
- 透视投影
- 相机位置

#### b. `OpenGLES` 绘制的一个基本流程：

1. 初始化顶点坐标数组、颜色数组，并转换为对应类型 `Buffer`
2. 初始化`顶点着色器`源代码、`片元着色器`源代码
3. 创建`顶点着色器`、`片元着色器`
4. 创建一个空`OpenGLES` 程序
5. 将两个着色器添加到程序，并链接到程序
6. 设置透视投影矩阵、相机投影矩阵
7. 转换成屏幕坐标系转换矩阵？
8. 绘制时，将程序加入到`OpenGLES` 环境
9. 获取相应的变量句柄，设置相关值
10. 开始绘制

#### c. 关于`顶点着色器`/`片元着色器`源代码：

- `attribute` ：使用顶点数组封装每个顶点的数据，一般用于每个顶点都各不相同的变量，如顶点位置、颜色等只读的顶点数据，只能用在`顶点着色器`中，一个attribute可以是浮点数类型的标量，向量，或者矩阵。不可以是数组或则结构体
- `uniform` : `顶点着色器`使用的常量数据，不能被着色器修改，一般用于对同一组顶点组成的单个3D物体中所有顶点都相同的变量，如当前光源的位置。且它在顶点着色器与片元着色器中可以实现共享。
- `sampler`: 这个是可选的，一种特殊的uniform，表示`顶点着色器`使用的纹理。
- `mat4`: 表示4×4浮点数矩阵，该变量存储了组合模型视图和投影矩阵
- `vec4`: 表示包含了4个浮点数的向量
- `varying`: 是用于从`顶点着色器`传递到片元着色器或`FragmentShader`传递到下一步的输出变量
- `gl_Position`: 是`顶点着色器`内置的输出变量
- `gl_FragColor`: 是`片元着色器`内置的输出变量
- `precision mediump float`:  计算时float的精度。有三种精度：`highp`/`mediump`/`lowp`，默认是`highp`。这样做的好处就是：能帮助着色器程序提高运行效率，减少内存开支

#### d. `OpenGLES` 中，目前接触到的api：

- `GLES20.glCreateShader(type)` 创建着色器，有两种类型的着色器：

  - `GLES20.GL_VERTEX_SHADER` 顶点着色器
  - `GLES20.GL_FRAGMENT_SHADER` 片元着色器

- `GLES20.glShaderSource(shader, sourceCode)`  添加着色器源代码

- `GLES20.glCompileShader(shader)` 编译着色器源代码

- `GLES20.glCreateProgram()` 创建一个`OpenGL` 空的程序

- `GLES20.glAttachShader(program, shader)` 将着色器添加到程序

- `GLES20.glLinkProgram(program)` 链接到着色器

- `GLES20.glClearColor(r, g, b, a)` 设置清屏时的颜色

- `GLES20.glEnable(GLES20.GL_DEPTH_TEST)` 开启深度测试

- `Matrix.frustumM（m, offset, left, right, bottom, top, near, far）` 设置透视投影矩阵

  - m:  保存透视投影矩阵的`float array`
  - offset:  the offset into float array m where the perspective matrix data is written
  - left/right/bottom/top:  近面上下左右的比例
  - near:  相机到近面的距离
  - far:  相机到远面的距离

  > 后六个参数设定了一个可见的视锥体，不在该范围内的就被裁剪掉，不会显示出来。视锥体内的物体最后会映射到近面并展现到屏幕上。

- `Matrix.setLookAtM(rm, rmOffset, eyeX, eyeY, eyeZ, centerX, centerY, centerZ, upX, upY, upZ)`

  - rm:  保存相机矩阵的`float array`
  - rmOffset:  index into rm where the result matrix starts
  - eyeX/eyeY/eyeZ:  相机在世界坐标系的坐标
  - centerX/centerY/centerZ:  目标中心点坐标（相机视点坐标），相机坐标与中心点坐标构成一个视线。近面与远面应该是垂直于该视线的。
  - upX/upY/upZ:  相机上方的坐标。从（0,0,0）到up坐标的向量，标识相机的up方向

- `Matrix.multiplyMM(result, resultOffset, lhs, lhsOffset, rhs, rhsOffset)` 两个矩阵相乘

  - result:  保存结果矩阵的`float array`
  - lhs:  左矩阵`float array`
  - rhs:  右矩阵`float array`

- `GLES20.glClear(GLES20.GL_DEPTH_BUFFER_BIT or GLES20.GL_COLOR_BUFFER_BIT)` 清除深度缓存，清除屏幕画面（清除后的颜色为之前设置的清屏颜色）

- `GLES20.glUseProgram(mProgram)` 使用`OpenGL` 程序

- `GLES20.glGetAttribLocation(mProgram, "变量名")` 获取程序中某个变量名的句柄

- `GLES20.glEnableVertexAttribArray(positionHandler)`

- `GLES20.glDisableVertexAttribArray(positionHandler)`

- `GLES20.glVertexAttribPointer(index, size, type, normalized, stride, Buffer)` 顶点赋值

  - index:  变量句柄
  - size:  单个变量描述需要的size。如顶点坐标的是3，颜色的是4
  - type:  表示元素的类型。如`GLES20.GL_FLOAT`
  - stride:  如果数据连续存放，则为0或size*sizeof(type)
  - Buffer:  保存的内容。如顶点坐标buffer或者颜色buffer

- `GLES20.glGetUniformLocation(mProgram, "变量名")` 获取某个`Uniform`变量句柄

- `GLES20.glUniformMatrix4fv(location, count, transpose, value, offset)` 设置矩阵值

  - location:  句柄
  - count: 数量
  - value:  保存矩阵的`float array`

- `GLES20.glDrawArrays(mode, first, count)` 顶点绘制

  - mode:  绘制模式
    - `GL_POINTS`  将传入的顶点坐标作为单独的点绘制
    - `GL_LINES`  将传入的坐标作为单独线条绘制，ABCDEFG六个顶点，绘制AB、CD、EF三条线
    - `GL_LINE_STRIP`  将传入的顶点作为折线绘制，ABCD四个顶点，绘制AB、BC、CD三条线
    - `GL_LINE_LOOP`  将传入的顶点作为闭合折线绘制，ABCD四个顶点，绘制AB、BC、CD、DA四条线。
    - `GL_TRIANGLES` 将传入的顶点作为单独的三角形绘制，ABCDEF绘制ABC,DEF两个三角形
    - `GL_TRIANGLE_FAN` 将传入的顶点作为扇面绘制，ABCDEF绘制ABC、ACD、ADE、AEF四个三角形
    - `GL_TRIANGLE_STRIP`  将传入的顶点作为三角条带绘制，ABCDEF绘制ABC,BCD,CDE,DEF四个三角形
  - first:  0
  - count:  顶点个数，而非array.size

- `GLES20.glDrawElements(mode, count, type, indicesBuffer)`  按给定顺序绘制而非默认的顶点顺序。根据indicesBuffer表示的顶点顺序进行绘制

#### e. 透视投影的示例图（网图）

![layout流程图]({{site.baseurl}}/assets/img/pexels/perspective_1.jpg)

![layout流程图]({{site.baseurl}}/assets/img/pexels/perspective_2.jpg)

<br/>

### C. 正方体的代码示例

```kotlin
class Cube : GLSurfaceView.Renderer {

    private val vertexShaderCode =
            "attribute vec4 vPosition;" +
                    "attribute vec4 aColor;" +
                    "varying vec4 vColor;" +
                    "uniform mat4 vMatrix;" +
                    "void main() {" +
                    "   gl_Position = vMatrix * vPosition;" +
                    "   vColor = aColor;" +
                    "}"

    private val fragmentShaderCode =
            "precision mediump float;" +
                    "varying vec4 vColor;" +
                    "void main() {" +
                    "   gl_FragColor = vColor;" +
                    "}"

    private val cubeVertexs = floatArrayOf(
            0.5F, 0.5F, 0.5F,
            0.5F, -0.5F, 0.5F,
            -0.5F, 0.5F, 0.5F,
            -0.5F, -0.5F, 0.5F,
            0.5F, 0.5F, -0.5F,
            0.5F, -0.5F, -0.5F,
            -0.5F, 0.5F, -0.5F,
            -0.5F, -0.5F, -0.5F
    )

    private val vertexColors = floatArrayOf(
            1F, 0F, 0F, 1F,
            0F, 1F, 0F, 1F,
            0F, 0F, 1F, 1F,
            1F, 0F, 0F, 1F,
            0F, 1F, 0F, 1F,
            0F, 0F, 1F, 1F,
            1F, 0F, 0F, 1F,
            0F, 1F, 0F, 1F
    )

    private val indexs = shortArrayOf(
            0, 1, 3, 0, 2, 3,
            4, 5, 6, 4, 7, 6,
            0, 1, 5, 0, 4, 5,
            3, 2, 6, 3, 6, 7,
            2, 0, 4, 2, 4, 6,
            3, 1, 5, 3, 5, 7
    )

    private val vertexBuffer: FloatBuffer = Util.getFloatBuffer(cubeVertexs)

    private val vertexColorBuffer: FloatBuffer = Util.getFloatBuffer(vertexColors)

    private val indexBuffer: ShortBuffer = Util.getShortBuffer(indexs)

    private var mProgram: Int = -1

    private val mProjectMatrix = FloatArray(16)

    private val mViewMatrix = FloatArray(16)

    private val mMVPMatrix = FloatArray(16)

    override fun onDrawFrame(gl: GL10?) {
        GLES20.glClear(GLES20.GL_COLOR_BUFFER_BIT or GLES20.GL_DEPTH_BUFFER_BIT)
        GLES20.glUseProgram(mProgram)

        val positionHandler = GLES20.glGetAttribLocation(mProgram, "vPosition")
        GLES20.glEnableVertexAttribArray(positionHandler)
        GLES20.glVertexAttribPointer(positionHandler, 3, GLES20.GL_FLOAT, false, 0, vertexBuffer)

        val colorHandler = GLES20.glGetAttribLocation(mProgram, "aColor")
        GLES20.glEnableVertexAttribArray(colorHandler)
        GLES20.glVertexAttribPointer(colorHandler, 4, GLES20.GL_FLOAT, false, 0, vertexColorBuffer)

        val matrixHandler = GLES20.glGetUniformLocation(mProgram, "vMatrix")
        GLES20.glUniformMatrix4fv(matrixHandler, 1, false, mMVPMatrix, 0)

        GLES20.glDrawElements(GLES20.GL_TRIANGLES, indexs.size, GLES20.GL_UNSIGNED_SHORT, indexBuffer)

        GLES20.glDisableVertexAttribArray(positionHandler)
        GLES20.glDisableVertexAttribArray(colorHandler)
    }

    override fun onSurfaceChanged(gl: GL10?, width: Int, height: Int) {
        GLES20.glViewport(0, 0, width, height)

        val ratio: Float = width / height.toFloat()
        Matrix.frustumM(mProjectMatrix, 0, -ratio, ratio, -1F, 1F, 3F, 10F)
        Matrix.setLookAtM(mViewMatrix, 0, 0F, 1F, 7F, 0F, 0F, 0F, 0F, 1F, 0F)
        Matrix.multiplyMM(mMVPMatrix, 0, mProjectMatrix, 0, mViewMatrix, 0)
    }

    override fun onSurfaceCreated(gl: GL10?, config: EGLConfig?) {
        mProgram = createProgram()
        GLES20.glClearColor(0.5F, 0.5F, 0.5F, 1F)
        GLES20.glEnable(GLES20.GL_DEPTH_TEST)
    }

    private fun loadShader(type: Int, shaderCode: String): Int {
        val shader = GLES20.glCreateShader(type)
        GLES20.glShaderSource(shader, shaderCode)
        GLES20.glCompileShader(shader)
        return shader
    }

    private fun createProgram(): Int {
        val vertexShader = loadShader(GLES20.GL_VERTEX_SHADER, vertexShaderCode)
        val fragmentShader = loadShader(GLES20.GL_FRAGMENT_SHADER, fragmentShaderCode)
        val program = GLES20.glCreateProgram()
        GLES20.glAttachShader(program, vertexShader)
        GLES20.glAttachShader(program, fragmentShader)
        GLES20.glLinkProgram(program)
        return program
    }
}
```

