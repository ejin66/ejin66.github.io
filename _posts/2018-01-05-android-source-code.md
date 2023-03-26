---
layout: post
title: 源码整理
tags: [Android, SourceCode]
---



#### 1. Android Camera2整理

```java
public class CameraHelper {
    //http://blog.csdn.net/lincyang/article/details/45951225
    private static volatile CameraHelper cameraHelper;

    private boolean isCameraOpened;
    private CameraDevice mCamera;
    private CameraCaptureSession mSession;
    private CaptureRequest.Builder mBuilder;
    private ImageReader mImageReader;
    private int mSensorOrientation;

    private static final SparseIntArray ORIENTATIONS = new SparseIntArray();

    static {
        ORIENTATIONS.append(Surface.ROTATION_0, 90);
        ORIENTATIONS.append(Surface.ROTATION_90, 0);
        ORIENTATIONS.append(Surface.ROTATION_180, 270);
        ORIENTATIONS.append(Surface.ROTATION_270, 180);
    }

    public static CameraHelper getCameraHelper() {
        if(cameraHelper == null) {
            synchronized (CameraHelper.class) {
                if(cameraHelper == null) {
                    cameraHelper = new CameraHelper();
                }
            }
        }
        return  cameraHelper;
    }

    private CameraHelper () {

    }

    public void closeCamera() {
        isCameraOpened = false;
        if(mCamera != null) {
            mCamera.close();
        }
        if(mSession != null) {
            mSession.close();
        }
        if(mImageReader != null) {
            mImageReader.close();
        }
        mCamera = null;
        mSession = null;
        mImageReader = null;
    }

    private void initImageReader(int width,int height,final Handler handler) {
        mImageReader = ImageReader.newInstance(width, height, ImageFormat.JPEG, /*maxImages*/2);
        mImageReader.setOnImageAvailableListener(new ImageReader.OnImageAvailableListener() {
            @Override
            public void onImageAvailable(final ImageReader reader) {
                handler.post(new ImageSaver(reader.acquireNextImage(), getPath()));

            }
        }, handler);
    }

    public void openCamera(Context context, final TextureView textureView , final Handler handler , final CameraCaptureSession.CaptureCallback captureCallback) {
        initImageReader(textureView.getWidth(),textureView.getHeight(),handler);
        try {
            openCamera(context, new CameraDevice.StateCallback() {
                @Override
                public void onOpened(CameraDevice camera) {
                    mCamera = camera;
                    isCameraOpened = true;
                    try {
                        previewCamera(mCamera,textureView,handler,captureCallback);
                    } catch (CameraAccessException e) {
                        e.printStackTrace();
                    }
                }

                @Override
                public void onDisconnected(CameraDevice camera) {
                    mCamera = camera;
                    closeCamera();
                }

                @Override
                public void onError(CameraDevice camera, int error) {
                    mCamera = camera;
                    closeCamera();
                }
            },handler);
        } catch (CameraAccessException e) {
            e.printStackTrace();
        }
    }

    private void openCamera(Context context, CameraDevice.StateCallback stateCallback, Handler handler) throws CameraAccessException {
        if(isCameraOpened) {
            return;
        }
        CameraManager cameraManager = (CameraManager) context.getSystemService(Context.CAMERA_SERVICE);

        //获取相机列表
        String[] cameraIds = cameraManager.getCameraIdList();
        //设置相机
        CameraCharacteristics cameraCharacteristics = cameraManager.getCameraCharacteristics(cameraIds[0]);
        cameraCharacteristics.get(CameraCharacteristics.INFO_SUPPORTED_HARDWARE_LEVEL);
        if (ActivityCompat.checkSelfPermission(context, Manifest.permission.CAMERA) != PackageManager.PERMISSION_GRANTED) {
            return;
        }
        //获取camera方向
        CameraCharacteristics characteristics = cameraManager.getCameraCharacteristics(cameraIds[0]);
        mSensorOrientation = characteristics.get(CameraCharacteristics.SENSOR_ORIENTATION);
        cameraManager.openCamera(cameraIds[0], stateCallback, handler);
    }

    private void previewCamera(CameraDevice cameraDevice, TextureView textureView , final Handler handler, final CameraCaptureSession.CaptureCallback captureCallback) throws CameraAccessException {
        SurfaceTexture surfaceTexture = textureView.getSurfaceTexture();
        surfaceTexture.setDefaultBufferSize(textureView.getWidth(),textureView.getHeight());
        Surface surface = new Surface(surfaceTexture);
        mBuilder = cameraDevice.createCaptureRequest(CameraDevice.TEMPLATE_STILL_CAPTURE);
        //持续对焦
        mBuilder.set(CaptureRequest.CONTROL_AF_MODE, CaptureRequest.CONTROL_AF_MODE_CONTINUOUS_PICTURE);
        mBuilder.addTarget(surface);
        cameraDevice.createCaptureSession(Arrays.asList(surface,mImageReader.getSurface()), new CameraCaptureSession.StateCallback() {
            @Override
            public void onConfigured(CameraCaptureSession session) {
                mSession = session;
                try {
                    mSession.setRepeatingRequest(mBuilder.build(),captureCallback,handler);
                } catch (CameraAccessException e) {
                    e.printStackTrace();
                }
            }

            @Override
            public void onConfigureFailed(CameraCaptureSession session) {
                mSession = session;
            }
        },handler);
    }

    public void takePicture(final Activity activity,final Handler handler) {
        //聚焦
        mBuilder.set(CaptureRequest.CONTROL_AF_TRIGGER, CameraMetadata.CONTROL_AF_TRIGGER_START);
        try {
            mSession.capture(mBuilder.build(), new CameraCaptureSession.CaptureCallback() {
                @Override
                public void onCaptureCompleted(CameraCaptureSession session, CaptureRequest request, TotalCaptureResult result) {
                    Integer afState = result.get(CaptureResult.CONTROL_AF_STATE);
                    if (afState == null) {
                        capturePicture(activity,handler);
                    } else if (CaptureResult.CONTROL_AF_STATE_FOCUSED_LOCKED == afState ||
                            CaptureResult.CONTROL_AF_STATE_NOT_FOCUSED_LOCKED == afState) {
                        // CONTROL_AE_STATE can be null on some devices
                        Integer aeState = result.get(CaptureResult.CONTROL_AE_STATE);
                        if (aeState == null ||
                                aeState == CaptureResult.CONTROL_AE_STATE_CONVERGED) {
                            capturePicture(activity,handler);
                        } else {

                        }
                    }
                }
            }, handler);
        } catch (CameraAccessException e) {
            e.printStackTrace();
        }
    }

    private void reset(Handler handler) {
        //取消
        mBuilder.set(CaptureRequest.CONTROL_AF_TRIGGER, CameraMetadata.CONTROL_AF_TRIGGER_CANCEL);
        try {
            CaptureRequest request = mBuilder.build();
            mSession.capture(mBuilder.build(), null, handler);
            mSession.setRepeatingRequest(request, null, handler);
        } catch (CameraAccessException e) {
            e.printStackTrace();
        }
    }


    private void capturePicture(Activity activity,final Handler handler) {
        // This is the CaptureRequest.Builder that we use to take a picture.
        try {
            final CaptureRequest.Builder captureBuilder = mCamera.createCaptureRequest(CameraDevice.TEMPLATE_STILL_CAPTURE);
            captureBuilder.addTarget(mImageReader.getSurface());

            captureBuilder.set(CaptureRequest.CONTROL_AF_MODE,CaptureRequest.CONTROL_AF_MODE_CONTINUOUS_PICTURE);

            // 设置方向
            int rotation = activity.getWindowManager().getDefaultDisplay().getRotation();
            captureBuilder.set(CaptureRequest.JPEG_ORIENTATION, getOrientation(rotation));

            CameraCaptureSession.CaptureCallback captureCallback
                    = new CameraCaptureSession.CaptureCallback() {

                @Override
                public void onCaptureCompleted(@NonNull CameraCaptureSession session,
                                               @NonNull CaptureRequest request,
                                               @NonNull TotalCaptureResult result) {
                    reset(handler);
                }
            };

            mSession.stopRepeating();
            mSession.capture(captureBuilder.build(), captureCallback, handler);
        } catch (CameraAccessException e) {
            e.printStackTrace();
        }

    }

    public static String getPath() {
        String cachePath =  CameraApplication.mContext.getExternalCacheDir().getAbsolutePath();
        File file = new File(cachePath);
        if (!file.exists()) {
            file.mkdirs();
        }
        Log.e("-------getPath",cachePath+"/"+System.currentTimeMillis()+".jpg");
        return cachePath+"/"+System.currentTimeMillis()+".jpg";
    }

    /**
     * Retrieves the JPEG orientation from the specified screen rotation.
     *
     * @param rotation The screen rotation.
     * @return The JPEG orientation (one of 0, 90, 270, and 360)
     */
    private int getOrientation(int rotation) {
        // Sensor orientation is 90 for most devices, or 270 for some devices (eg. Nexus 5X)
        // We have to take that into account and rotate JPEG properly.
        // For devices with orientation of 90, we simply return our mapping from ORIENTATIONS.
        // For devices with orientation of 270, we need to rotate the JPEG 180 degrees.
        return (ORIENTATIONS.get(rotation) + mSensorOrientation + 270) % 360;
    }
}


public class ImageSaver implements Runnable {

    /**
     * The JPEG image
     */
    private final Image mImage;
    /**
     * The file we save the image into.
     */
    private final String mPath;

    public ImageSaver(Image image, String path) {
        mImage = image;
        mPath = path;
    }

    @Override
    public void run() {
        Log.e("-------begin save",mPath);
        ByteBuffer buffer = mImage.getPlanes()[0].getBuffer();
        byte[] bytes = new byte[buffer.remaining()];
        buffer.get(bytes);
        FileOutputStream output = null;
        try {
            output = new FileOutputStream(new File(mPath));
            output.write(bytes);
        } catch (IOException e) {
            e.printStackTrace();
        } finally {
            mImage.close();
            if (null != output) {
                try {
                    output.close();
                } catch (IOException e) {
                    e.printStackTrace();
                }
            }
        }
    }
}
```



#### 2. Android WebView 封装源码 

```java
import android.content.Context;
import android.graphics.Bitmap;
import android.graphics.Color;
import android.net.http.SslError;
import android.util.AttributeSet;
import android.util.Log;
import android.view.Gravity;
import android.view.View;
import android.view.ViewGroup;
import android.webkit.SslErrorHandler;
import android.webkit.WebChromeClient;
import android.webkit.WebResourceError;
import android.webkit.WebResourceRequest;
import android.webkit.WebSettings;
import android.webkit.WebViewClient;
import android.widget.Button;
import android.widget.FrameLayout;
import android.widget.ImageView;
import android.widget.LinearLayout;
import android.widget.ProgressBar;
import android.widget.TextView;

/**
 * Created by ejin on 2017/4/24.
 */
public class TopLoadingWebView extends FrameLayout {

    private WebView mWebView;
    private ProgressBar mProgressBar;
    private LinearLayout noNetWorkView;
    private boolean loadError;

    public TopLoadingWebView(Context context) {
        this(context,null);
    }

    public TopLoadingWebView(Context context, AttributeSet attrs) {
        this(context, attrs,0);
    }

    public TopLoadingWebView(Context context, AttributeSet attrs, int defStyleAttr) {
        super(context, attrs, defStyleAttr);
        initWebView();
        initNoNetworkUI();
        initProgress();
    }

    private void initNoNetworkUI() {
        noNetWorkView = new LinearLayout(getContext());
        noNetWorkView.setBackgroundColor(Color.parseColor("#DFDFDF"));
        noNetWorkView.setOrientation(LinearLayout.VERTICAL);
        noNetWorkView.setGravity(Gravity.CENTER_HORIZONTAL);

        View top = new View(getContext());
        LinearLayout.LayoutParams lp1 = new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT,0);
        lp1.weight = 1;
        noNetWorkView.addView(top,lp1);

        ImageView noNetwork = new ImageView(getContext());
        noNetwork.setImageResource(R.drawable.no_netwok);
        noNetWorkView.addView(noNetwork);

        TextView textView = new TextView(getContext());
        textView.setText("哦,页面貌似打不开了！");
        textView.setTextColor(Color.BLACK);
        textView.setTextSize(18);
        textView.setGravity(Gravity.CENTER);
        textView.setPadding(0,ScreenUtil.dp2px(getContext(),60),0,ScreenUtil.dp2px(getContext(),20));
        noNetWorkView.addView(textView);

        Button button = new Button(getContext());
        button.setText("重新加载");
        button.setTextColor(Color.BLACK);
        button.setTextSize(16);
        button.setPadding(15,6,15,6);
        noNetWorkView.addView(button,new LinearLayout.LayoutParams(ViewGroup.LayoutParams.WRAP_CONTENT, ViewGroup.LayoutParams.WRAP_CONTENT));
        button.setOnClickListener(new OnClickListener() {
            @Override
            public void onClick(View v) {
                mWebView.reload();
            }
        });

        View bottom = new View(getContext());
        LinearLayout.LayoutParams lp2 = new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT,0);
        lp2.weight = 2;
        noNetWorkView.addView(bottom,lp2);

        addView(noNetWorkView,new FrameLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.MATCH_PARENT));
        noNetWorkView.setVisibility(GONE);
    }

    private void initProgress() {
        mProgressBar = new ProgressBar(getContext(),null,R.style.WebViewProgress);
        mProgressBar.setProgressDrawable(getContext().getResources().getDrawable(R.drawable.webview_loading_progress));
        mProgressBar.setMax(100);
        addView(mProgressBar,new LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ScreenUtil.dp2px(getContext(),3)));
    }

    public WebView getWebView() {
        return mWebView;
    }

    private void initWebView() {
        mWebView = new WebView(getContext());

        //声明WebSettings子类
        WebSettings webSettings = mWebView.getSettings();
        //如果访问的页面中要与Javascript交互，则webview必须设置支持Javascript
        webSettings.setJavaScriptEnabled(true);
        //设置自适应屏幕，两者合用
        webSettings.setUseWideViewPort(true); //将图片调整到适合webview的大小
        webSettings.setLoadWithOverviewMode(true); // 缩放至屏幕的大小
        //缩放操作
        webSettings.setSupportZoom(true); //支持缩放，默认为true。是下面那个的前提。
        webSettings.setBuiltInZoomControls(true); //设置内置的缩放控件。若为false，则该WebView不可缩放
        webSettings.setDisplayZoomControls(false); //隐藏原生的缩放控件
        //其他细节操作
        //缓存模式如下：
        //LOAD_CACHE_ONLY: 不使用网络，只读取本地缓存数据
        //LOAD_DEFAULT: （默认）根据cache-control决定是否从网络上取数据。
        //LOAD_NO_CACHE: 不使用缓存，只从网络获取数据.
        //LOAD_CACHE_ELSE_NETWORK，只要本地有，无论是否过期，或者no-cache，都使用缓存中的数据
        webSettings.setCacheMode(WebSettings.LOAD_CACHE_ELSE_NETWORK); //关闭webview中缓存
        webSettings.setAllowFileAccess(true); //设置可以访问文件
        webSettings.setJavaScriptCanOpenWindowsAutomatically(true); //支持通过JS打开新窗口
        webSettings.setLoadsImagesAutomatically(true); //支持自动加载图片
        webSettings.setDefaultTextEncodingName("utf-8");//设置编码格式

        mWebView.setWebViewClient(new WebViewClient() {
            @Override
            public boolean shouldOverrideUrlLoading(android.webkit.WebView view, String url) {
                view.loadUrl(url);
                return true;
            }

            @Override
            public void onPageStarted(android.webkit.WebView view, String url, Bitmap favicon) {
                super.onPageStarted(view, url, favicon);
                loadError = false;
                mProgressBar.setVisibility(VISIBLE);
                Log.i("WebView","onPageStarted");
            }

            @Override
            public void onPageFinished(android.webkit.WebView view, String url) {
                super.onPageFinished(view, url);
                mProgressBar.setVisibility(GONE);
                Log.i("WebView","onPageFinished");
                if(loadError) {
                    noNetWorkView.setVisibility(VISIBLE);
                } else {
                    noNetWorkView.setVisibility(GONE);
                }
            }

            @Override
            public void onReceivedSslError(android.webkit.WebView view, SslErrorHandler handler, SslError error) {
                handler.proceed();    //表示等待证书响应
                // handler.cancel();      //表示挂起连接，为默认方式
                // handler.handleMessage(null);    //可做其他处理
            }

            @Override
            public void onReceivedError(android.webkit.WebView view, WebResourceRequest request, WebResourceError error) {
//                super.onReceivedError(view, request, error);
                loadError = true;
//                noNetWorkView.setVisibility(VISIBLE);
            }
        });

        //设置WebChromeClient类
        mWebView.setWebChromeClient(new WebChromeClient() {
            //获取网站标题
            @Override
            public void onReceivedTitle(android.webkit.WebView view, String title) {
                super.onReceivedTitle(view, title);
            }

            //获取加载进度
            @Override
            public void onProgressChanged(android.webkit.WebView view, int newProgress) {
//                super.onProgressChanged(view, newProgress);
                mProgressBar.setProgress(newProgress);
                Log.i("WebView","newProgress:"+newProgress);
            }
        });
        addView(mWebView,new FrameLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.MATCH_PARENT));
    }

    public boolean onBackPressed() {
        if (mWebView.canGoBack()) {
            mWebView.goBack();
            return true;
        }
        return false;
    }

    public void destroy() {
        if (mWebView != null) {
            mWebView.loadDataWithBaseURL(null, "", "text/html", "utf-8", null);
            mWebView.clearHistory();
            mWebView.destroy();
            removeView(mWebView);
            mWebView = null;
        }
    }
}
```



#### 3. WIFI常用方法整理

```kotlin
fun isApOn(): Boolean {
    try {
        val wifiManager = ApplicationHolder.getApplicationContext().getSystemService(Context.WIFI_SERVICE) as WifiManager
        val method: Method = wifiManager.javaClass.getDeclaredMethod("isWifiApEnabled")
        method.isAccessible = true
        return method.invoke(wifiManager) as Boolean
    } catch (e: Exception) {
        e.printStackTrace()
    }
    return false
}

fun isWifiOpen(): Boolean {
    val wifiManager = ApplicationHolder.getApplicationContext().getSystemService(Context.WIFI_SERVICE) as WifiManager
    return wifiManager.isWifiEnabled
}

fun getConnectWifi(): String? {
    val wifiManager = ApplicationHolder.getApplicationContext().getSystemService(Context.WIFI_SERVICE) as WifiManager
    return wifiManager.connectionInfo?.ssid
}

fun getHotspotSSID(): String? {
    try {
        val manager = ApplicationHolder.getApplicationContext().getSystemService(Context.WIFI_SERVICE) as WifiManager
        val method = manager.javaClass.getDeclaredMethod("getWifiApConfiguration")
        val configuration = method.invoke(manager) as WifiConfiguration
        return configuration.SSID
    } catch (e: NoSuchMethodException) {
        e.printStackTrace()
    } catch (e: InvocationTargetException) {
        e.printStackTrace()
    } catch (e: IllegalAccessException) {
        e.printStackTrace()
    }
    return ""
}

fun getHotspotKey(): String? {
    try {
        val manager = ApplicationHolder.getApplicationContext().getSystemService(Context.WIFI_SERVICE) as WifiManager
        val method = manager.javaClass.getDeclaredMethod("getWifiApConfiguration")
        val configuration = method.invoke(manager) as WifiConfiguration
        return configuration.preSharedKey
    } catch (e: NoSuchMethodException) {
        e.printStackTrace()
    } catch (e: InvocationTargetException) {
        e.printStackTrace()
    } catch (e: IllegalAccessException) {
        e.printStackTrace()
    }
    return ""
}

fun setWifiEnable(enable: Boolean): Boolean {
    val manager = ApplicationHolder.getApplicationContext().getSystemService(Context.WIFI_SERVICE) as WifiManager
    val result = manager.setWifiEnabled(enable)
    Logger.d("set wifi enable: $enable, result: $result")
    return result
}

fun setApEnable(context: Context, enable: Boolean, wifiConfiguration: WifiConfiguration): Boolean {
    var result = false
    val manager = ApplicationHolder.getApplicationContext().getSystemService(Context.WIFI_SERVICE) as WifiManager
    manager.isWifiEnabled = false
    if (Build.VERSION.SDK_INT < Build.VERSION_CODES.M || Settings.System.canWrite(context)) {
        try {
            val getWifiApConfigurationMethod = manager::class.java.getMethod("getWifiApConfiguration")
            val wc: WifiConfiguration = getWifiApConfigurationMethod.invoke(manager) as WifiConfiguration
            Logger.d("WifiConfiguration $wc")
            val setWifiApEnabledMethod = manager::class.java.getMethod("setWifiApEnabled", WifiConfiguration::class.java, java.lang.Boolean.TYPE)
            result = setWifiApEnabledMethod.invoke(manager, wc, enable) as Boolean
        } catch (e: NoSuchMethodException) {
            e.printStackTrace()
        } catch (e: InvocationTargetException) {
            e.printStackTrace()
        } catch (e: IllegalAccessException) {
            e.printStackTrace()
        }
    } else {
        Logger.e("sdk >= 23 and system can write is false")
    }
    Logger.d("set wifi ap enable: $enable, result: $result")
    return result
}

fun setApEnable(context: Context, ssid: String, key: String): Boolean {
    val wc = WifiConfiguration().apply {
        SSID = ssid
        preSharedKey = key
    }
    return setApEnable(context, true, wc)
}

fun connectWIFI(ssid: String, key: String): Boolean {
    val manager = ApplicationHolder.getApplicationContext().getSystemService(Context.WIFI_SERVICE) as WifiManager

    if (!manager.isWifiEnabled) {
        Logger.e("wifi is not open")
        return false
    }

    try {
        Thread.sleep(2000)
    } catch (e: Exception) {
        e.printStackTrace()
    }


    val scanSSid = manager.scanResults?.firstOrNull {
        Logger.d(it.SSID)
        it.SSID == ssid
    }

    if (scanSSid == null) {
        Logger.e("未扫描到WIFI: $ssid")
        return false
    }

    manager.configuredNetworks?.filter { it.SSID == "\"" + ssid + "\"" }?.forEach {
        manager.removeNetwork(it.networkId)
    }

    WifiConfiguration().apply {
        SSID = "\"" + ssid + "\""
        preSharedKey = "\"" + key + "\""
        allowedAuthAlgorithms.set(WifiConfiguration.AuthAlgorithm.OPEN)
        allowedGroupCiphers.set(WifiConfiguration.GroupCipher.TKIP)
        allowedKeyManagement.set(WifiConfiguration.KeyMgmt.WPA_PSK)
        allowedPairwiseCiphers.set(WifiConfiguration.PairwiseCipher.TKIP)
        allowedProtocols.set(WifiConfiguration.Protocol.WPA)
        allowedProtocols.set(WifiConfiguration.Protocol.RSN)
        allowedGroupCiphers.set(WifiConfiguration.GroupCipher.CCMP)
        allowedPairwiseCiphers.set(WifiConfiguration.PairwiseCipher.CCMP)
        status = WifiConfiguration.Status.ENABLED
        manager.addNetwork(this)
        Logger.d("add network: $this")
    }

    val configure = manager.configuredNetworks?.firstOrNull { it.SSID == "\"" + ssid + "\"" }
    if (configure == null) {
        Logger.e("ssid $ssid not found in configuredNetworks")
        return false
    }

    val result = manager.enableNetwork(configure.networkId, true)
    Logger.d("connect ssid $ssid: $result")

    return result
}
```



#### 4. RecyclerView.ItemDecoration 实现的几个功能

```java
//1. 分组， 头部跟随滑动
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

   //RecyclerView.Adapter要实现此接口
   public interface PinnedInterface {
      String getGroupName(int position);

      int getPinnedHeaderHeight(int position, @NonNull RecyclerView parent);

      View getPinnedHeader(int position, @NonNull RecyclerView parent);

      boolean isGroupFirstItem(int position);
   }
}

//adapter要实现PinnedSelectionItemDecoration.PinnedInterface接口
//recyclerView添加pinner decoration来增加这个功能
recyclerView.addItemDecoration(new PinnedSelectionItemDecoration())
    
    
    
//2. 分组
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
      if (position != 0 && groupInformation.groupName(position).equals(groupInformation.groupName(position - 1))) {
         return false;
      }
      return true;
   }

   //RecyclerView.Adapter要实现此接口
   public interface GroupInformation {
      String groupName(int position);
   }
}

recyclerView.addItemDecoration(new SelectionItemDecoration ())
    
    
//3. 分割线
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



