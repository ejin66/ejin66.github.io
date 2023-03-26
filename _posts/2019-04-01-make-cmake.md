---
layout: post
title: make & cmake 基础
tags: [make, cmake]
---

# make

根据`Makefile`的内容，通过`make`来生成目标文件，类似打包的过程。其中最关键的，就是`Makefile`的书写。



### Makefile的规则

`Makefile`的最核心的规制是：

```makefile
target: prerequisites
	command # command必须以tab开头
	
# 不换行写法
target: prerequisites; command
```

**target**可以是目标文件、中间目标文件、可执行文件。也可以是一个标签（伪目标，如clean），通过`.PHONY: targetName`标记为伪目标。

**prerequisites**是生成那个target所需要的文件或是目标, 多个文件可通过空格分开。若文件多，通过`\`换行。

**command**是make需要执行的命令（任意的Shell命令）。

一个`Makefile`中可以有多个`target`, 第一个`target`会被视为最终的目标文件。

例子：

```makefile
edit: main.o kbd.o command.odisplay.o\
insert.o search.o files.outils.o
	cc -o edit main.o kbd.o command.o display.o\
insert.o search.o files.o utils.o

main.o: main.c defs.h
	cc –c main.c

kbd.o: kbd.c defs.h command.h
	cc –c kbd.c

command.o: command.c defs.hcommand.h
	cc –c command.c

display.o: display.c defs.hbuffer.h
	cc –c display.c

insert.o: insert.c defs.hbuffer.h
	cc -c insert.c

search.o: search.c defs.hbuffer.h
	cc -c search.c

files.o: files.c defs.hbuffer.h command.h
	cc -c files.c

utils.o: utils.c defs.h
	cc –c utils.c

.PHONY: clean
clean:
# "-"表示,即使可能某些文件有问题，不存在或者怎么样，都不要中断命令执行。
	-rm edit main.o kbd.o command.odisplay.o\
insert.o search.o files.outils.o
```



### make的工作流程

一般情况下，我们直接敲`make`命令：

```bash
make
```

此时，`make`会在当前目录下找`Makefile`或者`makefile`。

找到之后，查找`Makefile`中的第一个`target`，并把它作为最终目标。

接着，`make`会分析本地已存在的文件，以上面的例子为例：

- `edit`文件不存在，即最终目标不存在
  - 执行命令生成`edit`
- `edit`文件存在，`edit`依赖的其他`target`是否存在
  - 不存在，执行特定`target`命令生成该`target`。
  - 存在
    - 比较`edit`与其他`target`(如main.o/insert.o), 若`edit`的生成时间任一`target`都早，则表示`target`已经过期，需要重新生成。

`make`的过程，是从最终`target`开始，一层一层向下查找依赖的过程。然后从最底层依赖开始执行命令，生成中间依赖文件。最后，生成最终文件。



### Makefile的一些技巧

1. 定义变量

   `Makefile`中重复的部分，可以通过定义变量来简化，如：

   原来的文件：

   ```makefile
   edit: main.o kbd.o command.odisplay.o\
   insert.o search.o files.outils.o
   	cc -o edit main.o kbd.o command.o display.o\
   insert.o search.o files.o utils.o
   ```

   优化成：

   ```makefile
   objects = main.o kbd.o command.odisplay.o\
   insert.o search.o files.outils.o
   
   edit: $(objects)
   	cc -o edit $(objects)
   ```

2. 自动推导(隐晦规则)

   针对`*.o`的target, 会自动推导出需要加入依赖`*.c`，不需要我们显示的写出来。同时，也会自动推导出命令：`cc -c *.c`。所以：

   形如：

   ```makefile
   main.o: main.c defs.h
   	cc –c main.c
   ```

   可以优化为：

   ```makefile
   main.o: defs.h
   ```

3. 引用其他的`Makefile`

   ```makefile
   include foo.make *.mk $(bar)
   ```

   > 支持相对路径，通配符，变量

   一般情况下，`make`会在当前目录下查找，会去特定目录下查找。`make -I` 或者`make --include-dir`指定的特定目录。

4. 命令打印

   通常，`make`会把执行的命令输出到控制台。通过在命令前加上`@`, 该条命令就不会被输出，如：

   ```makefile
   echo 我在执行echo命令
   
   # 输出：
   # echo 我在执行echo命令
   # 我在执行echo命令
   
   @echo 我在执行echo命令
   # 输出：
   # 我在执行echo命令
   ```

5. 命令执行

   若想让后一条命令依赖前一条命令的结果，需要将两台命令写在同行，并用`;`隔开。若换行，后一条命令并不会被前一条命令的执行影响。如：

   ```makefile
   test:
   	cd build
   	pwd
   # pwd输出当前 Makefile_dir
   
   test:
   	cd build;pwd
   # pwd输出当前 Makefile_dir/build
   ```

   

参考：[跟我一起写Makefile](https://blog.csdn.net/haoel/article/details/2886)

# cmake

`cmake`，全名`cross platform make`, 是一个跨平台的安装编译工具。它根据`CMakeLists.txt`文件，可生成不同平台下的`Makefile`。

### cmake_minimum_required

要求的`cmake`最低版本

```cmake
cmake_minimum_required(VERSION 3.10)
```



### project

设置项目名称、版本

```cmake
project(projectName)
//或者带版本的
project(projectName VERSION 2.0)
```



### set

设置环境变量

```cmake
set(VariableName value)
```



### configure_file

将cmake的一些内容配置到头文件中（动态生成的）

```cmake
configure_file(TutorialConfig.h.in TutorialConfig.h)
```

`TutorialConfig.h.in`:

```c++
// the configured options and settings for Tutorial
#define Tutorial_VERSION_MAJOR @Tutorial_VERSION_MAJOR@
#define Tutorial_VERSION_MINOR @Tutorial_VERSION_MINOR@
```

根据cmake设定的内容，会动态的替换到`@xxx@`的值。如上面设置项目版本为2.0, 最后生成的内容：

`TutorialConfig.h`

```c++
// the configured options and settings for Tutorial
#define Tutorial_VERSION_MAJOR 2
#define Tutorial_VERSION_MINOR 0

```



### option

定义可选变量

```cmake
option(USE_MYMATH "Use tutorial provided math implementation" ON)

# 根据已选变量值，做不同处理
if(USE_MYMATH)
  add_subdirectory(MathFunctions)
  list(APPEND EXTRA_LIBS MathFunctions)
  list(APPEND EXTRA_INCLUDES "${PROJECT_SOURCE_DIR}/MathFunctions")
endif()
```

### list

创建列表，保存多个依赖库或者多个文件夹。最后可以一并link或者include

```cmake
list(APPEND EXTRA_LIBS MathFunctions)
list(APPEND EXTRA_INCLUDES "${PROJECT_SOURCE_DIR}/MathFunctions")

target_link_libraries(Tutorial PUBLIC ${EXTRA_LIBS})
target_include_directories(Tutorial PUBLIC
                           "${PROJECT_BINARY_DIR}"
                           ${EXTRA_INCLUDES}
                           )
```



### add_executable

创建可执行文件

```cmake
add_executable(executableName files)
```



### add_library

添加库

```cmake
add_library(libraryName files)
```



### add_subdirectory

添加其他文件夹（该文件夹下也有自身的CMakeLists.txt）

```cmake
add_subdirectory(sourceDir [binaryDir])
```



### target_link_libraries

将`target`与依赖库连接

```cmake
target_link_libraries(projectName/libraryName PUBLIC source_file)
```

- `PUBLIC`

  头文件、代码都依赖的库

- `PRIVATE`

  头文件不依赖，代码依赖的库

- `INTERFACE`

  头文件依赖，代码不依赖的库



### target_include_directories

添加`include`文件夹，`target`可以从里面查找需要的头文件

```cmake
# struct
target_include_directories(ProjectName Requirements directories...)


# demo 1
target_include_directories(Tutorial PUBLIC
                           "${PROJECT_BINARY_DIR}"
                           ${EXTRA_INCLUDES}
                           )
                           
# demo 2
target_include_directories(MathFunctions
          INTERFACE ${CMAKE_CURRENT_SOURCE_DIR}
          )
```

- `INTERFACE`

  自己不需要，但是调用者需要。子`library`通过`INTERFACE`将头文件引用关联，其他`library`只需要通过`add_subdirectory(libraryName)`添加子`library`即可，无需再额外关联子`library`的头文件。



### install

指定各文件的生成路径。`DESTINATION`是可选的，默认是`CMAKE_INSTALL_PREFIX`，可通过`SET`设置。

通常，会附带生成一个`cmake_install.cmake`文件。在主`Makefile`的`install`目标中，会使用到该文件。

```cmake
install(TARGETS libraryName DESTINATION lib)
install(FILES xxx.h DESTINATION include)

install(TARGETS projectName DESTINATION bin)
install(FILES "${PROJECT_BINARY_DIR}/TutorialConfig.h"
  DESTINATION include
  )
```



### include

导入相关功能

```cmake
include(CheckSymbolExists)
```



### check_symbol_exists

检查当前平台是否有特定的代码

```cmake
check_symbol_exists(code heade flag_exist)
```

例子：

```cmake
include(CheckSymbolExists)
set(CMAKE_REQUIRED_LIBRARIES "m")
check_symbol_exists(log "math.h" HAVE_LOG)
check_symbol_exists(exp "math.h" HAVE_EXP)
```

在`TutorialConfig.h.in`中：

```c
#cmakedefine HAVE_LOG
#cmakedefine HAVE_EXP
```

最后，在代码中：

```c
#if defined(HAVE_LOG) && defined(HAVE_EXP)
  double result = exp(log(x) * 0.5);
  std::cout << "Computing sqrt of " << x << " to be " << result
            << " using log and exp" << std::endl;
#else
  double result = x;
# endif
```



### target_compile_definitions

...

例子：

```cmake
include(CheckSymbolExists)
set(CMAKE_REQUIRED_LIBRARIES "m")
check_symbol_exists(log "math.h" HAVE_LOG)
check_symbol_exists(exp "math.h" HAVE_EXP)

if(HAVE_LOG AND HAVE_EXP)
  target_compile_definitions(MathFunctions
                             PRIVATE "HAVE_LOG" "HAVE_EXP")
endif()
```





### enable_testing

开启测试功能

```cmake
enable_testing()
```



### add_test

添加测试项

```cmake
add_test(NAME testName COMMAND testCommand)
```



### set_tests_properties

设置测试项的预期结果

```cmake
set_tests_properties(testName
  PROPERTIES PASS_REGULAR_EXPRESSION testExpectedResult
  )
  
add_test(NAME Usage COMMAND Tutorial)
# testExpectedResult支持模糊匹配
set_tests_properties(Usage
  PROPERTIES PASS_REGULAR_EXPRESSION "Usage:.*number"
  )
```





### 自定义测试方法

```cmake
function(functionName targetName argument result)
	xxx
	xxx
endfunction(functionName)


# 例子
function(do_test target arg result)
  add_test(NAME Comp${arg} COMMAND ${target} ${arg})
  set_tests_properties(Comp${arg}
    PROPERTIES PASS_REGULAR_EXPRESSION ${result}
    )
endfunction(do_test)

do_test(Tutorial 4 "4 is 2")
```



[cmake官方教程](https://cmake.org/cmake/help/v3.17/guide/tutorial/index.html)

[cmake命令](https://cmake.org/cmake/help/latest/command/add_library.html)

[cmake源码](https://github.com/Kitware/CMake)

