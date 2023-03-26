---
layout: post
title: 阿里百川热修复用法介绍
tags: [Android, Hotfix]
---



#### 1. 申请账号

获取到以下值：             

```
App Key             
App Secret            
RSA             
APP ID ( 初始化HotFixManager时用到 ) 
```

#### 2. 添加依赖仓库

```java
 //仓库：
 maven {
 	url "http://repo.baichuan-android.taobao.com/content/groups/BaichuanRepositories"
 }
 //依赖：
 compile 'com.taobao.android:alisdk-hotfix:1.4.0'
```

#### 3. 添加权限

```xml
<uses-permission android:name="android.permission.INTERNET" />
<uses-permission android:name="android.permission.ACCESS_NETWORK_STATE" />
<uses-permission android:name="android.permission.ACCESS_WIFI_STATE" />
<uses-permission android:name="android.permission.READ_EXTERNAL_STORAGE"/>
```

#### 4. Proguard文件

```text
-keep class * extends java.lang.annotation.Annotation
-keepclasseswithmembernames class * {
	native <methods>;
}
-keep class com.alipay.euler.andfix.**{
	*;
}
-keep class com.taobao.hotfix.aidl.**{*;}
-keep class com.ta.utdid2.device.**{*;}
-keep class com.taobao.hotfix.HotFixManager{
	public *;
} 
```

#### 5. SDK接口使用

```java
//在application.oncreate()中调用如下代码:
HotFixManager.getInstance().setContext(this)
        .setAppVersion(BuildConfig.VERSION_NAME)//设置当前APP版本
        .setAppId(BuildConfig.BC_APPID)//设置阿里百川APP ID
        .setAesKey(null)//设置补丁包的密钥
        .setSupportHotpatch(true)//是否及时生效（热部署有一定风险）
        .setEnableDebug(true)
        .setPatchLoadStatusStub(new PatchLoadStatusListener() {
            @Override
            public void onload(final int mode, final int code, final String info, final int handlePatchVersion) {
                // 补丁加载回调通知
                if (code == PatchStatusCode.CODE_SUCCESS_LOAD) {
                    // 补丁加载成功
                } else if (code == PatchStatusCode.CODE_ERROR_NEEDRESTART) {
                    // 表明新补丁生效需要重启. 业务方可自行实现逻辑, 提示用户或者强制重启, 建议: 用户可以监听进入后台事件, 然后应用自杀
                } else if (code == PatchStatusCode.CODE_ERROR_INNERENGINEFAIL) {
                    // 内部引擎加载异常, 推荐此时清空本地补丁, 但是不清空本地版本号, 防止失败补丁重复加载
                    HotFixManager.getInstance().cleanPatches(false);
                } else {
                    // 其它错误信息, 查看PatchStatusCode类说明
                }
            }
        }).initialize();
```

#### 6. 生成补丁命令

![生成补丁命令说明]({{site.baseurl}}/assets/img/pexels/alibaichuan.png)

```java
java -jar BCFixPatchTools-1.3.0.jar -c patch -s old.apk -f new.apk -w patch-out -k test.keystore -p test123 -a test123 -e test123 -y 1234567891234567
-l filterClass.txt
```

#### 7. 上传补丁以及测试

![上传补丁]({{site.baseurl}}/assets/img/pexels/alibaichuan_1.png)

安装阿里提供的调试工具：hotfix_debug_tool.apk。如下： 

![上传补丁]({{site.baseurl}}/assets/img/pexels/alibaichuan_2.png)

```text
·  mode: 补丁模式, 0:正常请求模式 1:扫码模式 2:本地补丁模式
·  code: 补丁加载状态码, 详情查看PatchStatusCode类说明
·  info: 补丁加载详细说明, 详情查看PatchStatusCode类说明
·  handlePatchVersion: 当前处理的补丁版本号, 0:无 -1:本地补丁 其它:后台补丁
```

#### 8. 注意点

​	1. 检查当前项目结构jniLibs中是否有armeabi-v7a, arm64-v8a目录。有的话，需要添加so库。（不同cpu架构不一样）     

​    	2. patch是针对客户端具体某个版本的，patch和具体版本绑定

​            eg. 应用当前版本号是1.1.0, 那么只能在后台查询到1.1.0版本对应发布的补丁, 而查询不到之前1.0.0旧版本发布的补丁

​    	3. 针对某个具体版本发布的新补丁, 必须包含所有的bugfix, 而不能依赖补丁递增修复的方式, 因为应用仅可能加载一个补丁

​    	4. 不允许直接添加/修改全局实例变量(包括静态变量), 不允许修改构造函数, 不允许直接添加新的方法

​    	5. 方法参数说明

​    		a.   参数包括：long, double, float基本类型的方法不能被patch. 比如:test(Context context, long value)。

   		b.   注意这几种基本类型的封装类是支持的. 比如:test(Context context, Long value)这样是支持的

​    		c.   参数超过8的方法不能被patch.

​    		d.   泛型参数的方法不能被patch. 比如:test(Context context, T t);