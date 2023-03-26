---
layout: post
title: Flutter如何调用动态链接库
tags: ["Flutter"]
---

### Android如何调用so库

官方推荐使用NDK工具，通过CMake的方式创建动态库。不过，一些简单的库，也是可以直接使用命令行生成的。

android 默认使用clang编译C/C++。sdk中clang在：*%ANDROID_SDK%\ndk\21.0.6113669\toolchains\llvm\prebuilt\windows-x86_64\bin*

使用clang生成动态库时，需要指定`target`，如x86的模拟器下：

```bash
clang -target=i686-linux-android26-clang -shared xxx.c -o libxxx.so
```

**示例**

1. 创建一个外部so库

   hello.c

   ```c
   #include <stdio.h>
   #include <stdlib.h>
   #include <string.h>
   #include "hello.h"
   
   int main(int total, char *args[]) {
   	if (total == 1) {
   		printf("%s", "argument absent");
   		return 1;
   	}
   	
   	char* str = args[1];
   	size_t len = strlen(str);
   	//char* result = (char *)malloc(len);
   	char result[len];
   	convert(str, len, result);
   	printf("result: %s\n", result);
   	//free(result);
   	return 0;
   }
   
   void convert(char *source, int len, char *result) {
   	for (int i = 0; i < len; i++) {
   		result[len - 1 - i] = source[i];
   	}
   	result[len] = '\0';
   }
   ```

   hello.h

   ```c
   void convert(char*, int, char*);
   ```

2. 使用clang创建so库

   ```bash
   clang -target=i686-linux-android26-clang -shared hello.c -o libhello.so
   ```

3. JNI调用该so库

   test.c

   ```c
   #include <jni.h>
   #include <string.h>
   #include "hello.h"
   
   JNIEXPORT jstring JNICALL
   Java_com_ejin_libso_MainActivity_convert(JNIEnv *env, jobject instance, jstring s_) {
       const char *s = (*env)->GetStringUTFChars(env, s_, 0);
   	
   	size_t len = strlen(s);
   	
   	char result[len];
   	
   	convert(s, len, result);
   
       return (*env)->NewStringUTF(env, result);
   }
   ```

   将hello.h/libhello.so 放到test.c的同目录下之后，生成libtest.so

   ```bash
   clang -target=i686-linux-android26-clang -shared test.c libhello.so -o libtest.so
   ```

4. 修改gradle

   ```groovy
   android {
       ...
           
       defaultConfig {
           ...
   
           ndk {
               abiFilters 'x86'
           }
       }
       
       ...
   
       sourceSets {
           main {
               jniLibs.srcDirs = ['libs']
           }
       }
   
   }
   ```

   在libs下创建x86文件夹，并将libtest.so/libhello.so拷贝进去。

5. 编辑android 代码

   ```kotlin
   class MainActivity : AppCompatActivity() {
   
       companion object {
           init {
               System.loadLibrary("test")
           }
       }
   
       override fun onCreate(savedInstanceState: Bundle?) {
           super.onCreate(savedInstanceState)
           setContentView(R.layout.activity_main)
           tv.text = convert("123456")
       }
   
   
       external fun convert(s: String): String
   }
   ```

6. 运行之后，发现屏幕上显示的是“654321”，表示动态库的编译调用是成功的。

### Flutter如何调用so库

dart是支持调用so库的，主要的api在`dart:ffi`和`package:ffi/ffi.dart`下面。调用流程如下：

1. 生成so库。如何生成so库，在上一篇已经有详细说明。

2. so库在项目中的位置。

   原生调用so库时，so库怎么放置，在flutter项目中也是一致的。以android为例：

   - 首先，修改build.gradle, 设置jni libs:

     ```groovy
     android {
         sourceSets {
             main {
                 jniLibs.srcDirs = ['libs']
             }
         }
     }
     ```

   - 在libs下创建${abi}文件夹。如模拟器一般是x86架构，所以在模拟器上创建x86文件夹，并将生成的so库拷贝进来。

     > 与android不同的是，不需要通过jni的方式间接调用。如上面的两个so库：libhello.so、libtest.so. 这里只需要libhello.so.

     

3. 接着，就可以使用dart代码直接调用了。

     **导入第三方库**

     ```yaml
     ffi: ^0.1.3
     ```

     **dart调用库示例**

     ```dart
     import 'dart:ffi' as ffi;
     
     import 'package:ffi/ffi.dart';
     
     final solib = ffi.DynamicLibrary.open("libhello.so");
     
     typedef ConvertNative = ffi.Void Function(ffi.Pointer<Utf8>, ffi.Int32, ffi.Pointer<Utf8>);
     
     typedef Convert = void Function(ffi.Pointer<Utf8>, int, ffi.Pointer<Utf8>);
     
     final Convert convert = solib
     		.lookup<ffi.NativeFunction<ConvertNative>>('convert')
     		.asFunction();
     ```

     > ffi.Pointer<Utf8> 代表 char*, ffi.Int32 代表 int

     更多资料，参考dart官网：[C interop using dart:ffi](https://dart.dev/guides/libraries/c-interop)

     **使用示例**

     ```dart
     var source = Utf8.toUtf8("ABCDEF");
     var len = Utf8.strlen(source);
     var p = allocate<Utf8>(count: len);
     convert(source, len, p);
     //txt: FEDCBA
     var txt = Utf8.fromUtf8(p);
     free(p);
     ```