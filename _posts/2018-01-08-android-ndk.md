---
layout: post
title: NDK开发总结
tags: [Android, NDK]
---



**1.  创建项目。**

下载NDK developer tool，在新建项目时，选择C++support，系统会默认在src/main下创建cpp文件夹。同时，在主module下会多出CMakeLists.txt配置文件。

1. 1. build.gradle

```
android {
    ...
    externalNativeBuild {
        cmake {
            path "CMakeLists.txt"
        }
    }
}
```

​        b.CMakeLists.txt

```
# For more information about using CMake with Android Studio, read the
# documentation: https://d.android.com/studio/projects/add-native-code.html

# Sets the minimum version of CMake required to build the native library.

cmake_minimum_required(VERSION 3.4.1)

# Creates and names a library, sets it as either STATIC
# or SHARED, and provides the relative paths to its source code.
# You can define multiple libraries, and CMake builds them for you.
# Gradle automatically packages shared libraries with your APK.

#设置生成的so动态库最后输出的路径
set(CMAKE_LIBRARY_OUTPUT_DIRECTORY ${PROJECT_SOURCE_DIR}/../jniLibs/${ANDROID_ABI})

#头文件路径
include_directories("src/main/cpp")

#配置多个文件
#file(GLOB native_srcs "src/main/cpp/*.c" "src/main/cpp/*.cpp")
#多级目录
file(GLOB_RECURSE native_srcs "src/main/cpp/*.c" "src/main/cpp/*.cpp")


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

find_library( # Sets the name of the path variable.
              log-lib

              # Specifies the name of the NDK library that
              # you want CMake to locate.
              log )

# Specifies libraries CMake should link to your target library. You
# can link multiple libraries, such as libraries you define in this
# build script, prebuilt third-party libraries, or system libraries.

target_link_libraries( # Specifies the target library.
                       native-lib

                       # Links the target library to the log library
                       # included in the NDK.
                       ${log-lib} )
```



 **2.  编译错误，无法找到头文件？**默认是C++，若是C语言开发，需要加上

```
#ifdef __cplusplus
extern "C" {
#endif
```

头文件与JNI接口文件都需要加上。当然，也可以直接编写.c文件。



**3. 实例**

​    a. native-lib.cpp

```
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

​    b. calc.c

```
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

​    c. calc.h

```
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