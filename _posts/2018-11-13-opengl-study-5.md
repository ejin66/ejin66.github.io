---
layout: post
title: 使用Camera preview data 绘制视频
tags: [OpenGL]
---

### 1. 总体流程

get preview data(`YUV`)  ->  convert to `RGB` data  ->  `GLSurfaceView` render `RGB`

#### 1.1 获取`YUV`预览数据

`YUV`预览数据又可细分为 `YUV_420_NV21` 、`YUV_420_I420`、`YUV_420_NV12`、`YUV_420_YV12` 等。这里，使用 `NV21` 以及 `Camera 1 API` 来获取预览数据。

```kotlin
val parameters = camera.parameters
parameters.setPreviewSize(width, height)
parameters.previewFormat = ImageFormat.NV21
camera.parameters = parameters

camera.setPreviewCallback { data, c ->
	//data 便是 YUV_420_NV21格式的数据。
}
```

#### 1.2 `YUV`转成 `RGB` 数据

首先，提取出 `Y`、`U`、`V` 三个分量。`YUV_420_NV21`格式的数据排列如下：

![NV21格式]({{site.baseurl}}/assets/img/pexels/20180111114820185.png)

根据上图，提取三个分量数据：

```kotlin
//采集的分辨率
val size = width * height
val offset = size
val yBuff = ByteArray(size)
val uBuff = ByteArray(size / 4)
val vBuff = ByteArray(size / 4)

var i = 0
var k = 0
while (i < size) {
    val y1 = data[i]
    val y2 = data[i + 1]
    val y3 = data[width + i]
    val y4 = data[width + i + 1]

    val v = data[offset + k]
    val u = data[offset + k + 1]


    yBuff[i] = y1
    yBuff[i + 1] = y2
    yBuff[width + i] = y3
    yBuff[width + i + 1] = y4

    uBuff[k / 2] = u

    vBuff[k / 2] = v

    if (i != 0 && (i + 2) % width == 0)
    i += width
    i += 2
    k += 2
}
```

#### 1.3 将`YUV`数据转成`RGB`

通过`OpenGL` 将`YUV`数据转成`RGB`格式纹理(借助`GPU`计算)。

```kotlin
private val fragmentShaderCode = "precision mediump float;" +
            "varying vec2 aPosition;" +
            "uniform sampler2D ySampler;" +
            "uniform sampler2D uSampler;" +
            "uniform sampler2D vSampler;" +
            "void main() {" +
            " float y = texture2D(ySampler, aPosition).r;" +
            " float u = texture2D(uSampler, aPosition).r - 0.5;" +
            " float v = texture2D(vSampler, aPosition).r - 0.5;" +
            " vec3 rgb;" +
            " rgb.r = y + 1.403 * v;" +
            " rgb.g = y - 0.344 * u - 0.714 * v;" +
            " rgb.b = y + 1.770 * u;" +
            " gl_FragColor = vec4(rgb, 1.0);" +
            "}"
```

#### 1.4 `Camera` 的基本知识

```kotlin
1.屏幕方向
activity.windowManager.defaultDisplay.rotation
默认竖屏为0
2.前置摄像头
默认displayOrientation270度
cameraId 1
3.后置摄像头
默认displayOrientation90度
cameraId 0
4.只有设置了supportedPreviewSize中的一个，才会生效。setPreviewSize(width,height)是相对应displayOrientation =0 的值。
5.若displayOrientation 为90/270, val dataRatio = yuvHeight/ yuvWidth.toFloat()。若displayOrientation 为0/180， val dataRatio = yuvWidth/ yuvHeight.toFloat()
6.设置`DisplayOrientation` 的官方建议步骤：
private fun setCameraDisplayOrientation(activity: Activity, camera: Camera, cameraId: Int) {
    val rotation = activity.windowManager.defaultDisplay.rotation

    val info = Camera.CameraInfo()
    Camera.getCameraInfo(cameraId, info)

    camera.parameters.supportedPreviewSizes.forEach {
        Log.d("Camera", "${it.width}/${it.height}")
    }

    var degree = when (rotation) {
        Surface.ROTATION_0 -> 0
        Surface.ROTATION_90 -> 90
        Surface.ROTATION_180 -> 180
        else -> 270
    }

    var result = 0

    if (info.facing == Camera.CameraInfo.CAMERA_FACING_FRONT) {
        result = (info.orientation + degree) % 360
        result = (360 - result) % 360
    } else {
        result = (info.orientation - degree + 360) % 360
    }
    Log.d("Camera", "$degree / ${info.orientation}/ $cameraId/$result")
    camera.setDisplayOrientation(result)
}
```

<br/>

### 2. `OpenGL` 绘制部分的源代码

#### 2.1 注意点一

若 `camera.displayOrientation` 是 90/270° 时，计算data比例时需要调换width/height：

```kotlin
val dataRatio = yuvHeight / yuvWidth.toFloat()
```

若 `camera.displayOrientation` 是 0/180° 时：

```kotlin
val dataRatio = yuvWidth / yuvHeight.toFloat()
```

#### 2.2 注意点二

在设置`U`、`v`分量数据时：

```kotlin
GLES20.glTexImage2D(GLES20.GL_TEXTURE_2D, 0, GLES20.GL_LUMINANCE, yuvWidth / 2, yuvHeight / 2, 0, GLES20.GL_LUMINANCE, GLES20.GL_UNSIGNED_BYTE, uBuffer)
```

这里 `width/2`、`height/2` 是因为 `U`、`v`分量的数据只有`yuvWidth*yuvHeight/4`。



#### 2.3 完整源码

```kotlin
class YUV420Texture: GLSurfaceView.Renderer {

    private val vertexCoords = floatArrayOf(
            -1F, -1F, 0F,
            -1F, 1F, 0F,
            1F, -1F, 0F,
            1F, 1F, 0F
    )

    private val textureCoords = floatArrayOf(
            0F, 1F,
            0F, 0F,
            1F, 1F,
            1F, 0F
    )

    private val vertexShaderCode = "attribute vec4 vPosition;" +
            "attribute vec2 tPosition;" +
            "varying vec2 aPosition;" +
            "uniform mat4 vMatrix;" +
            "void main() {" +
            " gl_Position = vMatrix * vPosition;" +
            " aPosition = tPosition;" +
            "}"

    private val fragmentShaderCode = "precision mediump float;" +
            "varying vec2 aPosition;" +
            "uniform sampler2D ySampler;" +
            "uniform sampler2D uSampler;" +
            "uniform sampler2D vSampler;" +
            "void main() {" +
            " float y = texture2D(ySampler, aPosition).r;" +
            " float u = texture2D(uSampler, aPosition).r - 0.5;" +
            " float v = texture2D(vSampler, aPosition).r - 0.5;" +
            " vec3 rgb;" +
            " rgb.r = y + 1.403 * v;" +
            " rgb.g = y - 0.344 * u - 0.714 * v;" +
            " rgb.b = y + 1.770 * u;" +
            " gl_FragColor = vec4(rgb, 1.0);" +
            "}"

    private var mProgram = -1
    private var positionHandler = -1
    private var texPositionHandler = -1
    private var matrixHandler = -1
    private var ySamplerHandler = -1
    private var uSamplerHandler = -1
    private var vSamplerHandler = -1

    private val projectMatrix = FloatArray(16)
    private val viewMatrix = FloatArray(16)
    private val matrix = FloatArray(16)

    private var yuvWidth = -1
    private var yuvHeight = -1

    private var viewWidth = -1
    private var viewHeight = -1

    private var yBuffer: ByteBuffer? = null
    private var uBuffer: ByteBuffer? = null
    private var vBuffer: ByteBuffer? = null

    private val textureArray = IntArray(3)

    fun setYUVData(y: ByteArray, u: ByteArray, v: ByteArray) {
        yBuffer = ByteBuffer.wrap(y)
        uBuffer = ByteBuffer.wrap(u)
        vBuffer = ByteBuffer.wrap(v)
    }

    fun setYUVSize(yuvWidth: Int, yuvHeight: Int) {
        this.yuvWidth = yuvWidth
        this.yuvHeight = yuvHeight
        calculateMatrix()
    }

    override fun onDrawFrame(gl: GL10?) {
        if (yuvWidth <= 0 || yuvHeight <= 0 || yBuffer == null) {
            return
        }
        Log.d("Camera", "onDrawFrame")

        GLES20.glClear(GLES20.GL_COLOR_BUFFER_BIT)

        GLES20.glUseProgram(mProgram)
        GLES20.glUniformMatrix4fv(matrixHandler, 1, false, matrix, 0)

        GLES20.glEnableVertexAttribArray(positionHandler)
        GLES20.glVertexAttribPointer(positionHandler, 3, GLES20.GL_FLOAT, false, 0, Util.getFloatBuffer(vertexCoords))

        GLES20.glEnableVertexAttribArray(texPositionHandler)
        GLES20.glVertexAttribPointer(texPositionHandler, 2, GLES20.GL_FLOAT, false, 0, Util.getFloatBuffer(textureCoords))

        GLES20.glActiveTexture(GLES20.GL_TEXTURE0)
        GLES20.glBindTexture(GLES20.GL_TEXTURE_2D, textureArray[0])
        GLES20.glTexImage2D(GLES20.GL_TEXTURE_2D, 0, GLES20.GL_LUMINANCE, yuvWidth, yuvHeight, 0, GLES20.GL_LUMINANCE, GLES20.GL_UNSIGNED_BYTE, yBuffer)

        GLES20.glActiveTexture(GLES20.GL_TEXTURE1)
        GLES20.glBindTexture(GLES20.GL_TEXTURE_2D, textureArray[1])
        GLES20.glTexImage2D(GLES20.GL_TEXTURE_2D, 0, GLES20.GL_LUMINANCE, yuvWidth / 2, yuvHeight / 2, 0, GLES20.GL_LUMINANCE, GLES20.GL_UNSIGNED_BYTE, uBuffer)

        GLES20.glActiveTexture(GLES20.GL_TEXTURE2)
        GLES20.glBindTexture(GLES20.GL_TEXTURE_2D, textureArray[2])
        GLES20.glTexImage2D(GLES20.GL_TEXTURE_2D, 0, GLES20.GL_LUMINANCE, yuvWidth / 2, yuvHeight / 2, 0, GLES20.GL_LUMINANCE, GLES20.GL_UNSIGNED_BYTE, vBuffer)

        GLES20.glUniform1i(ySamplerHandler, 0)
        GLES20.glUniform1i(uSamplerHandler, 1)
        GLES20.glUniform1i(vSamplerHandler, 2)

        GLES20.glDrawArrays(GLES20.GL_TRIANGLE_STRIP, 0, vertexCoords.size / 3)

        yBuffer?.clear()
        uBuffer?.clear()
        vBuffer?.clear()
        yBuffer = null
        uBuffer = null
        vBuffer = null
        GLES20.glDisableVertexAttribArray(positionHandler)
        GLES20.glDisableVertexAttribArray(texPositionHandler)
    }

    override fun onSurfaceChanged(gl: GL10?, width: Int, height: Int) {
        viewWidth = width
        viewHeight = height
        GLES20.glViewport(0, 0, width, height)
        calculateMatrix()
    }

    private fun calculateMatrix() {
        val viewRatio = viewWidth / viewHeight.toFloat()

        val dataRatio = yuvWidth / yuvHeight.toFloat()

        if (dataRatio > viewRatio) {
            Matrix.orthoM(projectMatrix, 0, -1F, 1F, -dataRatio/viewRatio, dataRatio/viewRatio, 3F, 7F)
        } else {
            Matrix.orthoM(projectMatrix, 0, -viewRatio/dataRatio, viewRatio/dataRatio, -1F, 1F, 3F, 7F)
        }

        Matrix.setLookAtM(viewMatrix, 0, 0F, 0F, 7F, 0F, 0F, 0F, 0F, 1F, 0F)


        val rotateMatrix = floatArrayOf(
                1F, 0F, 0F, 0F,
                0F, 1F, 0F, 0F,
                0F, 0F, 1F, 0F,
                0F, 0F, 0F, 1F
        )

        Matrix.rotateM(rotateMatrix, 0, -90F, 0F, 0F, 1F)

        Matrix.multiplyMM(viewMatrix, 0, viewMatrix, 0, rotateMatrix, 0)

        Matrix.multiplyMM(matrix, 0, projectMatrix, 0, viewMatrix, 0)
    }

    override fun onSurfaceCreated(gl: GL10?, config: EGLConfig?) {
        mProgram = Util.createProgram(Util.loadShader(GLES20.GL_VERTEX_SHADER, vertexShaderCode),
                Util.loadShader(GLES20.GL_FRAGMENT_SHADER, fragmentShaderCode))
        positionHandler = GLES20.glGetAttribLocation(mProgram, "vPosition")
        texPositionHandler = GLES20.glGetAttribLocation(mProgram, "tPosition")
        matrixHandler = GLES20.glGetUniformLocation(mProgram, "vMatrix")
        ySamplerHandler = GLES20.glGetUniformLocation(mProgram, "ySampler")
        uSamplerHandler = GLES20.glGetUniformLocation(mProgram, "uSampler")
        vSamplerHandler = GLES20.glGetUniformLocation(mProgram, "vSampler")

        GLES20.glClearColor(0F, 0F, 0F, 1F)
        createTexture()
    }

    private fun createTexture() {

        GLES20.glGenTextures(3, textureArray, 0)

        textureArray.forEach {
            //它告诉OpenGL下面对纹理的任何操作都是对它所绑定的纹理对象的
            GLES20.glBindTexture(GLES20.GL_TEXTURE_2D, it)
            //设置缩小过滤为使用纹理中坐标最接近的一个像素的颜色作为需要绘制的像素颜色
            GLES20.glTexParameterf(GLES20.GL_TEXTURE_2D, GLES20.GL_TEXTURE_MIN_FILTER, GLES20.GL_NEAREST.toFloat())
            //设置放大过滤为使用纹理中坐标最接近的若干个颜色，通过加权平均算法得到需要绘制的像素颜色
            GLES20.glTexParameterf(GLES20.GL_TEXTURE_2D,GLES20.GL_TEXTURE_MAG_FILTER,GLES20.GL_LINEAR.toFloat())
            //设置环绕方向S，截取纹理坐标到[1/2n,1-1/2n]。将导致永远不会与border融合
            GLES20.glTexParameterf(GLES20.GL_TEXTURE_2D, GLES20.GL_TEXTURE_WRAP_S,GLES20.GL_CLAMP_TO_EDGE.toFloat())
            //设置环绕方向T，截取纹理坐标到[1/2n,1-1/2n]。将导致永远不会与border融合
            GLES20.glTexParameterf(GLES20.GL_TEXTURE_2D, GLES20.GL_TEXTURE_WRAP_T,GLES20.GL_CLAMP_TO_EDGE.toFloat())
        }
    }
}
```

