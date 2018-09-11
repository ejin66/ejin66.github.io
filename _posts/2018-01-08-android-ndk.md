---
layout: post
title: NDK开发总结
tags: [Android, NDK]
---



### 1.  创建项目

下载NDK developer tool，在新建项目时，选择C++support，系统会默认在src/main下创建cpp文件夹。同时，在主module下会多出CMakeLists.txt配置文件。

1. build.gradle

   ```groovy
   android {
       ...
       defaultConfig {
           ...
           externalNativeBuild {
               cmake {
                   cppFlags ""
                   //配置生成的so库所支持的平台
                   abiFilters "armeabi", "armeabi-v7a", "arm64-v8a"
               }
           }
       }
       
       externalNativeBuild {
           cmake {
               path "CMakeLists.txt"
           }
       }
   }
   ```

2. CMakeLists.txt文件说明

   ```groovy
   # For more information about using CMake with Android Studio, read the
   # documentation: https://d.android.com/studio/projects/add-native-code.html
   
   # Sets the minimum version of CMake required to build the native library.
   
   #设置cmake支持的最小版本
   cmake_minimum_required(VERSION 3.4.1)
   
   # Creates and names a library, sets it as either STATIC
   # or SHARED, and provides the relative paths to its source code.
   # You can define multiple libraries, and CMake builds them for you.
   # Gradle automatically packages shared libraries with your APK.
   
   #设置生成的so动态库最后输出的路径
   set(CMAKE_LIBRARY_OUTPUT_DIRECTORY ${PROJECT_SOURCE_DIR}/../jniLibs/${ANDROID_ABI})
   
   #头文件路径
   include_directories("src/main/cpp")
   
   #配置多个文件。在add_library中使用，指生成的library中所包括的文件。
   #file(GLOB native_srcs "src/main/cpp/*.c" "src/main/cpp/*.cpp")
   #多级目录
   file(GLOB_RECURSE native_srcs "src/main/cpp/*.c" "src/main/cpp/*.cpp")
   
   #配置生成的so库的配置。
   #第一次参数：生产的so库名称
   #第二个参数：生成的so库的类型，SHARED代表动态链接库.so，STATIC代表静态库.a
   #第三个开始的参数：生成库所包括的代码文件。可以枚举，也可以用上面定义的文件集合：{native_srcs}
   add_library( # Sets the name of the library.
                native-lib
   
                # Sets the library as a shared library.
                SHARED
   
                # Provides a relative path to your source file(s).
                ${native_srcs} )
   
   # Searches for a specified prebuilt library and stores the path as a
   # variable. Because CMake includes system libraries in the search path by
   # default, you only need to specify the name of the public NDK library
   # you want to add. CMake verifies that the library exists before
   # completing its build.
   
   #查找库。
   #第一个参数：定义变量名，表示查找的库
   #第二个参数：需要查找的本地库
   find_library( # Sets the name of the path variable.
                 log-lib
   
                 # Specifies the name of the NDK library that
                 # you want CMake to locate.
                 log )
   
   # Specifies libraries CMake should link to your target library. You
   # can link multiple libraries, such as libraries you define in this
   # build script, prebuilt third-party libraries, or system libraries.
   
   #关联本地库到目标生成库
   #第一个参数：目标生成库的名称，与add_library第一个参数对应
   #第二个参数：被关联的本地库变量，与find_library对应
   target_link_libraries( # Specifies the target library.
                          native-lib
   
                          # Links the target library to the log library
                          # included in the NDK.
                          ${log-lib} )
   ```

<br/>

### 2. 编译错误，无法找到头文件？

默认是C++，若是C语言开发，需要加上

```
#ifdef __cplusplus
extern "C" {
#endif
```

头文件与JNI接口文件都需要加上。当然，也可以直接编写.c文件。

<br/>

### 3.实例

**项目结构如下：**

- src/
  - main
    - cpp
      - calc.c
      - calc.h
      - native-lib.cpp
- CMakeLists.txt

**具体代码如下：**<br/>

1. native-lib.cpp

   ```c++
   #include <jni.h>
   #include <string>
   #include "calc.h"
   
   
   #ifdef __cplusplus
   extern "C" {
   #endif
       jint Java_com_ejin_ndk_MainActivity2_calc(JNIEnv *env, jobject instance, jint a, jint b,
                                            jstring symbol_) {
           const char *symbol = env->GetStringUTFChars(symbol_, 0);
           return calc(a, b, *symbol);
       }
   #ifdef __cplusplus
   }
   #endif
   ```

2. calc.c

   ```c++
   #include "calc.h"
   #include <android/log.h>
   
   int calc(int a, int b, char symbol) {
   
       switch (symbol) {
           case '+':
               return a + b;
           case '-':
               return a - b;
           case '*':
               return a * b;
           case '/':
               if (b == 0) {
   //                __android_log_print(ANDROID_LOG_ERROR, "ERROR", "param b is zero.");
                   __android_log_assert(b != 0, "ERROR", "Param b is zero.");
               } else {
                   return a / b;
               }
           default:
               return 0;
       }
   }
   ```

3. calc.h

   ```c++
   #ifndef NDK_CALC_H
   #define NDK_CALC_H
   #ifdef __cplusplus
   extern "C" {
   #endif
   
   int calc(int a, int b, char symbol);
   
   #ifdef __cplusplus
   }
   #endif
   
   #endif //NDK_CALC_H
   ```

<br/>

### 4. 如何同时生成多个So库

要生成几个So库，就需要配置几个CMakeLists.txt。

项目结构如下：

- src/
  - main
    - cpp
      - calc.c
      - calc.h
      - native-lib.cpp
      - CMakeLists.txt
- CMakeLists.txt

主要改动有以下两点：

1. 新增的CMakeLists.txt内容如下：

   ```yaml
   #其他配置继承app/ 下的CMakeLists文件
   #用于生产多个so库
   ADD_LIBRARY(
       calc
   
       SHARED
       calc.c
   )
   
   target_link_libraries(
       calc
   
       log
   )
   ```

   表示要生成libcalc.so库。注意到，它只有很少的信息，因为其他的配置都可以从主CMakeList.txt文件继承过来。

2. 原src目录下的主CMakeList.text需要增加一项配置：

   ```yaml
   add_subdirectory(src/main/cpp)
   ```

   表示设置子目录地址，也就是新增的CMakeLists.txt文件目录。

<br/>

### 5. 总结JNI开发流程

- 创建NDK项目
- 创建java文件，并写出需要的native方法
- 根据native方法，自动生产.c文件
- 配置CMakeLists.txt文件
- 在java文件中增加加载so库的代码
- build app module，生成so库