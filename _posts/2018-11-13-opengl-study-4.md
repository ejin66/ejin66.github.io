---
layout: post
title: GLSurfaceView实现相机预览以及相机功能的扩展
tags: [OpenGL]
---

要实现相机预览的功能，一般是用`SurfaceView`、`TextureView`。默认就是支持相机预览的，只需要将`surfaceTexture` 设置给`Camera2 API`，无需其他额外操作。但是对于`GLSurfaceView` 就不一样，它是没有`surfaceTexture`的，借助纹理可实现相机的预览。

<br/>

#### 1. 首先，使用`Camera2 API`来预览相机。（具体细节见下方源码）

> 在预览相机时通过`ImageFormat.JPEG`选择的分辨率，发现设置的相机分辨率跟实际返回数据的分辨率不一致，导致画面被拉伸。

<br/>

#### 2. 创建相机预览的纹理

```kotlin
private fun createTexture(): Int {
        val texture = IntArray(1)
        GLES20.glGenTextures(1, texture, 0)
        GLES20.glBindTexture(GLES11Ext.GL_TEXTURE_EXTERNAL_OES, texture[0])
        //设置缩小过滤为使用纹理中坐标最接近的一个像素的颜色作为需要绘制的像素颜色
        GLES20.glTexParameterf(GLES11Ext.GL_TEXTURE_EXTERNAL_OES, GL10.GL_TEXTURE_MIN_FILTER, GL10.GL_NEAREST.toFloat())
        //设置放大过滤为使用纹理中坐标最接近的若干个颜色，通过加权平均算法得到需要绘制的像素颜色
        GLES20.glTexParameterf(GLES11Ext.GL_TEXTURE_EXTERNAL_OES,GL10.GL_TEXTURE_MAG_FILTER,GL10.GL_LINEAR.toFloat())
        //设置环绕方向S，截取纹理坐标到[1/2n,1-1/2n]。将导致永远不会与border融合
        GLES20.glTexParameterf(GLES11Ext.GL_TEXTURE_EXTERNAL_OES, GL10.GL_TEXTURE_WRAP_S,GL10.GL_CLAMP_TO_EDGE.toFloat())
        //设置环绕方向T，截取纹理坐标到[1/2n,1-1/2n]。将导致永远不会与border融合
        GLES20.glTexParameterf(GLES11Ext.GL_TEXTURE_EXTERNAL_OES, GL10.GL_TEXTURE_WRAP_T,GL10.GL_CLAMP_TO_EDGE.toFloat())
        return texture[0]
    }
```

> 创建相机预览纹理，必须要求纹理类型是`GLES11Ext.GL_TEXTURE_EXTERNAL_OES`

<br/>

#### 3. 根据纹理ID创建`SurfaceTexture`

```kotlin
SurfaceTexture(textureId).apply {
    setOnFrameAvailableListener(SurfaceTexture.OnFrameAvailableListener {
            glView.requestRender()
        })
}
```

> 当`surfaceTexture` 有数据过来时，通过`requestRender` 触发`GLSurfaceView`绘制

#### 4. 绘制纹理

1. 调用`surfaceTexture.updateTexImage` 方法，将内容流中最近的图像更新到`SurfaceTexture`对应的GL纹理对象。

2. 将纹理设置到片元着色器

   ```kotlin
   GLES20.glActiveTexture(GLES20.GL_TEXTURE0)   
   GLES20.glBindTexture(GLES11Ext.GL_TEXTURE_EXTERNAL_OES, textureId)
   GLES20.glUniform1i(textureHandler, 0)
   ```
<br/>

#### 5. 扩展

因为是通过纹理绘制来实现的相机预览，因此上一篇中对图片的一些操作也可以用在相机预览上，如：黑白相机、相机放大镜、相机模糊等。只需要修改片元着色器的代码，就能够实现。

<br/>

#### 6. 完整源码

`Camera2` 相机预览：

```kotlin
@TargetApi(Build.VERSION_CODES.LOLLIPOP)
object Camera2Tool {

    private var isCameraRunning = false
    private var mCamera: CameraDevice? = null
    private var mSession: CameraCaptureSession? = null
    private var mHandler: Handler? = null

    fun openCamera(context: Context, surfaceTexture: SurfaceTexture, width: Int, height: Int, callback: (Int, Int) -> Unit) {

        if (ActivityCompat.checkSelfPermission(context, Manifest.permission.CAMERA) != PackageManager.PERMISSION_GRANTED) {
            return
        }

        if (isCameraRunning) return

        isCameraRunning = true

        val handlerThread = HandlerThread("camera 2 thread")
        handlerThread.start()
        mHandler = Handler(handlerThread.looper)

        val cameraManager = context.getSystemService(Context.CAMERA_SERVICE) as CameraManager
        //一般前置摄像头的id是1, 相机默认旋转角度是270; 后置摄像头的id是0, 默认旋转角度是90
        val cameraCharacteristics =  cameraManager.getCameraCharacteristics(cameraManager.cameraIdList[0])

        val configMap = cameraCharacteristics.get(CameraCharacteristics.SCALER_STREAM_CONFIGURATION_MAP)
        val supportedSize = configMap.getOutputSizes(SurfaceTexture::class.java)

        val windowRotation = (context.getSystemService(Context.WINDOW_SERVICE) as WindowManager).defaultDisplay.rotation
        val sensorOrientation = cameraCharacteristics.get(CameraCharacteristics.SENSOR_ORIENTATION)

        val selectedSize = selectOutputSize(supportedSize, width, height, windowRotation)

        var previewSize = selectedSize
        when (ScreenRotation.find(windowRotation)) {
            ScreenRotation.ROTATION_0, ScreenRotation.ROTATION_180 -> if (sensorOrientation == 90 || sensorOrientation == 270) {
                previewSize = Size(selectedSize.height, selectedSize.width)
            }
            ScreenRotation.ROTATION_90, ScreenRotation.ROTATION_270 -> if (sensorOrientation == 0 || sensorOrientation == 180) {
                previewSize = Size(selectedSize.height, selectedSize.width)
            }
        }

        callback.invoke(previewSize.height, previewSize.width)
        surfaceTexture.setDefaultBufferSize(previewSize.width, previewSize.height)

        cameraManager.openCamera(cameraManager.cameraIdList[0], object : CameraDevice.StateCallback() {
            override fun onOpened(camera: CameraDevice?) {
                Log.d(Camera2Tool.javaClass.simpleName, "camera opened")
                mCamera = camera
                camera?.let {
                    preview(it, surfaceTexture)
                }
            }

            override fun onDisconnected(camera: CameraDevice?) {
                mCamera = camera
                clear()
            }

            override fun onError(camera: CameraDevice?, error: Int) {
                mCamera = camera
                clear()
            }
        }, mHandler)

    }

    fun stopCamera() {
        clear()
    }

    private fun selectOutputSize(outputSizes: Array<out Size>, width: Int, height: Int, windowRotation: Int): Size {
        val viewRatio = when (ScreenRotation.find(windowRotation)) {
            ScreenRotation.ROTATION_0, ScreenRotation.ROTATION_180 -> width / height.toFloat()
            else -> height / width.toFloat()
        }
        val offset = 0.2F
        return outputSizes.filter {
            (it.width / it.height.toFloat() > viewRatio * (1 - offset)
                ||  it.width / it.height.toFloat() < viewRatio * (1 + offset))
            && Math.max(it.width, it.height) <= Math.max(width, height)
        }
                .sortedBy { Math.max(it.width, it.height) }
                .lastOrNull() ?:Size(width, height)
    }

    private fun preview(camera: CameraDevice, surfaceTexture: SurfaceTexture) {
        val surface = Surface(surfaceTexture)
        camera.createCaptureRequest(CameraDevice.TEMPLATE_STILL_CAPTURE).apply {
            set(CaptureRequest.CONTROL_AE_MODE, CaptureRequest.CONTROL_AF_MODE_CONTINUOUS_PICTURE)
            addTarget(surface)
        }.build().let {
            camera.createCaptureSession(listOf(surface), object : CameraCaptureSession.StateCallback() {
                override fun onConfigureFailed(session: CameraCaptureSession?) {
                    mSession = session
                    clear()
                }

                override fun onConfigured(session: CameraCaptureSession?) {
                    mSession = session
                    tryCatch({
                        session?.setRepeatingRequest(it, object : CameraCaptureSession.CaptureCallback() {}, mHandler)
                    }, {
                        it.printStackTrace()
                        clear()
                    })
                }
            }, mHandler)
        }
    }

    private fun clear() {
        mCamera?.close()
        mSession?.close()
        mHandler?.looper?.quitSafely()

        mCamera = null
        mSession = null
        mHandler = null
        isCameraRunning = false
    }

    enum class ScreenRotation(var rotation: Int) {
        ROTATION_0(90),
        ROTATION_90(0),
        ROTATION_180(270),
        ROTATION_270(180);

        companion object {
            fun find(rotation: Int): ScreenRotation {
                return values().firstOrNull { it.rotation == rotation } ?: values()[0]
            }
        }

    }
}
```

实现相机预览的`Render`:

```kotlin
abstract class Camera: GLSurfaceView.Renderer {

    var surfaceTexture: SurfaceTexture? = null
    var dataWidth = 1F
    var dataHeight = 1F
    var viewWidth = 1F
    var viewHeight = 1F

    private val vertexShaderCode = "attribute vec4 vPosition;" +
            "attribute vec2 vCoordinate;" +
            "uniform mat4 vMatrix;" +
            "varying vec2 aCoordinate;" +
            "void main() {" +
            " gl_Position = vMatrix * vPosition;" +
            " aCoordinate = vCoordinate;" +
            "}"

    //texture2D GSLS内置函数，用于2D纹理取样
    //samplerExternalOES 纹理采样器
    //要在头部增加使用扩展纹理的声明#extension GL_OES_EGL_image_external : require
    private val fragmentShaderCode = "#extension GL_OES_EGL_image_external : require\n" +
            "precision mediump float;" +
            "uniform samplerExternalOES vTexture;" +
            "varying vec2 aCoordinate;" +
            "uniform int vType;" +
            "float t(float f) {" +
            " if (f <= 0.0) { " +
            "   return max(abs(f), 0.01);" +
            " } else if (f >= 1.0){" +
            "  return min(2.0 - f, 0.99);" +
            " } else {" +
            "   return f;" +
            " }" +
            "}" +
            "vec2 amend(vec2 v) {" +
            " return vec2(t(v.x), t(v.y));" +
            "}" +
            "void main() {" +
            " vec4 nColor = texture2D(vTexture, aCoordinate);" +
            " if (vType == 1) {" +
            "  float c = nColor.r * 0.3 + nColor.g * 0.59 + nColor.g * 0.11;" +
            "  gl_FragColor = vec4(c, c, c, nColor.a);" +
            " } else if (vType == 2) {" +
            "  vec2 center = vec2(0.4, 0.4);" +
            "  float r1 = 0.25;" +
            "  float distance = distance(vec2(aCoordinate.x, aCoordinate.y), center);" +
            "  if (distance < r1) {" +
            "   gl_FragColor = texture2D(vTexture, vec2(aCoordinate.x/2.0 + center.x/2.0, aCoordinate.y/2.0 + center.y/2.0));" +
            "  } else { " +
            "   gl_FragColor = nColor;" +
            "  }" +
            " } else if (vType == 3) {" +
            "  float a1 = 0.01;" +
            "  nColor += texture2D(vTexture, amend(vec2(aCoordinate.x - a1, aCoordinate.y - a1)));" +
            "  nColor += texture2D(vTexture, amend(vec2(aCoordinate.x - a1, aCoordinate.y + a1)));" +
            "  nColor += texture2D(vTexture, amend(vec2(aCoordinate.x + a1, aCoordinate.y - a1)));" +
            "  nColor += texture2D(vTexture, amend(vec2(aCoordinate.x + a1, aCoordinate.y + a1)));" +
            "  nColor += texture2D(vTexture, amend(vec2(aCoordinate.x - a1, aCoordinate.y)));" +
            "  nColor += texture2D(vTexture, amend(vec2(aCoordinate.x + a1, aCoordinate.y)));" +
            "  nColor += texture2D(vTexture, amend(vec2(aCoordinate.x, aCoordinate.y - a1)));" +
            "  nColor += texture2D(vTexture, amend(vec2(aCoordinate.x, aCoordinate.y + a1)));" +
            "  gl_FragColor = nColor/9.0;" +
            " } else {" +
            "  gl_FragColor = nColor;" +
            " }" +
            "}"

    private val sPos = floatArrayOf(
            -1F, 1F,
            -1F, -1F,
            1F, 1F,
            1F, -1F
    )

    private val sCoord = floatArrayOf(
            0F, 0F,
            0F, 1F,
            1F, 0F,
            1F, 1F
    )

    private val projectMatrix = FloatArray(16)
    private val viewMatrix = FloatArray(16)
    private val matrix = FloatArray(16)
    private var mProgram = -1
    private var positionHandler = -1
    private var coordinateHandler = -1
    private var matrixHandler = -1
    private var textureHandler = -1
    private var typeHandler = -1
    private var textureId = -1

    private var listener: SurfaceTexture.OnFrameAvailableListener? = null

    var type = 0

    fun setSize(dataWidth: Int, dataHeight: Int, viewWidth: Int, viewHeight: Int) {
        this.dataWidth = dataWidth.toFloat()
        this.dataHeight = dataHeight.toFloat()
        this.viewWidth = viewWidth.toFloat()
        this.viewHeight = viewHeight.toFloat()
        calculateMatrix()
    }

    private fun calculateMatrix() {
        val viewRatio = viewWidth / viewHeight
        val dataRatio = dataWidth / dataHeight

        if (viewRatio > dataRatio) {
            Matrix.orthoM(projectMatrix, 0, -1F, 1F, -dataRatio/viewRatio, dataRatio/viewRatio, 3F, 7F)
        } else {
            Matrix.orthoM(projectMatrix, 0, -viewRatio/dataRatio, viewRatio/dataRatio, -1F, 1F, 3F, 7F)
        }

        Matrix.setLookAtM(viewMatrix, 0, 0F, 0F, 7F, 0F, 0F, 0F, 0F, 1F, 0F)

        val moveMatrix = MatrixUtil.start()
                .rotate(270F)
                .getMatrix()

        Matrix.multiplyMM(moveMatrix, 0, viewMatrix, 0, moveMatrix, 0)
        Matrix.multiplyMM(matrix, 0, projectMatrix, 0, moveMatrix, 0)
    }

    fun setFrameAvailableListener(listener: SurfaceTexture.OnFrameAvailableListener) {
        this.listener = listener
    }

    override fun onDrawFrame(gl: GL10?) {
        GLES20.glClear(GLES20.GL_COLOR_BUFFER_BIT or GLES20.GL_DEPTH_BUFFER_BIT)

        surfaceTexture?.updateTexImage()

        GLES20.glUseProgram(mProgram)
        GLES20.glUniformMatrix4fv(matrixHandler, 1, false, matrix, 0)

        GLES20.glUniform1i(typeHandler, type)

        GLES20.glEnableVertexAttribArray(positionHandler)
        GLES20.glEnableVertexAttribArray(coordinateHandler)

        GLES20.glVertexAttribPointer(positionHandler, 2, GLES20.GL_FLOAT, false, 0, Util.getFloatBuffer(sPos))
        GLES20.glVertexAttribPointer(coordinateHandler, 2, GLES20.GL_FLOAT, false, 0, Util.getFloatBuffer(sCoord))

        //代表纹理单元的索引，0/1/2...依次排下来的。即绑定到GLES20.GL_TEXTURE_2D纹理的索引
        GLES20.glActiveTexture(GLES20.GL_TEXTURE0)
        Log.d("Camera", "bind texture: $textureId")
        GLES20.glBindTexture(GLES11Ext.GL_TEXTURE_EXTERNAL_OES, textureId)
        Log.d("Camera", "bind texture end")
        GLES20.glUniform1i(textureHandler, 0)

        GLES20.glDrawArrays(GLES20.GL_TRIANGLE_STRIP, 0, sPos.size / 2)

        GLES20.glDisableVertexAttribArray(positionHandler)
        GLES20.glDisableVertexAttribArray(coordinateHandler)
    }

    override fun onSurfaceChanged(gl: GL10?, width: Int, height: Int) {
        GLES20.glViewport(0, 0, width, height)
        viewWidth = width.toFloat()
        viewHeight = height.toFloat()
        calculateMatrix()
    }

    override fun onSurfaceCreated(gl: GL10?, config: EGLConfig?) {
        mProgram = Util.createProgram(Util.loadShader(GLES20.GL_VERTEX_SHADER, vertexShaderCode),
                Util.loadShader(GLES20.GL_FRAGMENT_SHADER, fragmentShaderCode))

        positionHandler = GLES20.glGetAttribLocation(mProgram, "vPosition")
        coordinateHandler = GLES20.glGetAttribLocation(mProgram, "vCoordinate")
        matrixHandler = GLES20.glGetUniformLocation(mProgram, "vMatrix")
        textureHandler = GLES20.glGetUniformLocation(mProgram, "vTexture")
        typeHandler = GLES20.glGetUniformLocation(mProgram, "vType")

        GLES20.glClearColor(0.5F, 0.5F, 0.5F, 1F)

        textureId = createTexture()
        surfaceTexture = SurfaceTexture(textureId).apply {
            setOnFrameAvailableListener(listener)
        }
    }

    //创建相机预览的texture。相机预览需要texture类型是GLES11Ext.GL_TEXTURE_EXTERNAL_OES
    private fun createTexture(): Int {
        val texture = IntArray(1)
        GLES20.glGenTextures(1, texture, 0)
        GLES20.glBindTexture(GLES11Ext.GL_TEXTURE_EXTERNAL_OES, texture[0])
        //设置缩小过滤为使用纹理中坐标最接近的一个像素的颜色作为需要绘制的像素颜色
        GLES20.glTexParameterf(GLES11Ext.GL_TEXTURE_EXTERNAL_OES, GL10.GL_TEXTURE_MIN_FILTER, GL10.GL_NEAREST.toFloat())
        //设置放大过滤为使用纹理中坐标最接近的若干个颜色，通过加权平均算法得到需要绘制的像素颜色
        GLES20.glTexParameterf(GLES11Ext.GL_TEXTURE_EXTERNAL_OES,GL10.GL_TEXTURE_MAG_FILTER,GL10.GL_LINEAR.toFloat())
        //设置环绕方向S，截取纹理坐标到[1/2n,1-1/2n]。将导致永远不会与border融合
        GLES20.glTexParameterf(GLES11Ext.GL_TEXTURE_EXTERNAL_OES, GL10.GL_TEXTURE_WRAP_S,GL10.GL_CLAMP_TO_EDGE.toFloat())
        //设置环绕方向T，截取纹理坐标到[1/2n,1-1/2n]。将导致永远不会与border融合
        GLES20.glTexParameterf(GLES11Ext.GL_TEXTURE_EXTERNAL_OES, GL10.GL_TEXTURE_WRAP_T,GL10.GL_CLAMP_TO_EDGE.toFloat())
        return texture[0]
    }
}
```

最后`activity`:

```kotlin
class CameraActivity : AppCompatActivity() {

    companion object {
        fun start(activity: AppCompatActivity, type: Int) {
            Intent(activity, CameraActivity::class.java).let {
                it.putExtra("type", type)
                activity.startActivity(it)
            }
        }

        fun start(activity: AppCompatActivity) {
            start(activity, 0)
        }
    }

    val mCameraRender = getCameraRender()

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_demo_1)
        glView.setEGLContextClientVersion(2)
        glView.setRenderer(mCameraRender)
        glView.renderMode = GLSurfaceView.RENDERMODE_WHEN_DIRTY

        val type = intent.getIntExtra("type", 0)
        (mCameraRender as Camera).type = type
    }

    override fun onResume() {
        super.onResume()
        glView.onResume()
    }

    override fun onPause() {
        super.onPause()
        glView.onPause()
    }

    private fun getCameraRender(): GLSurfaceView.Renderer {
        val render = object : Camera() {
            override fun onSurfaceCreated(gl: GL10?, config: EGLConfig?) {
                super.onSurfaceCreated(gl, config)
                Camera2Tool.openCamera(this@CameraActivity, surfaceTexture!!, glView.width, glView.height, { width, height ->
                    (mCameraRender as Camera).setSize(width, height, glView.width, glView.height)
                })
            }
        }
        render.setFrameAvailableListener(SurfaceTexture.OnFrameAvailableListener {
            glView.requestRender()
        })
        return render
    }

    override fun onDestroy() {
        super.onDestroy()
        Camera2Tool.stopCamera()
    }
}
```

