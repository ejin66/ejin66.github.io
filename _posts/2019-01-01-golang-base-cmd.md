---
layout: post
title: Golang的基础命令
tags: [Golang]
---

#### GOPATH与GOROOT

`GOROOT`是go的安装目录，`GOPATH`是go项目的工作路径。一般情况下：一个`GOROOT` + N个`GOPATH`。

可以一个项目一个`GOPATH`，也可以所有项目共用一个`GOPATH`。目前，我倾向的是只有一个`GOPATH`，具体结构是：

- gopath dir
  - bin
  - pkg
  - src
    - project 1
    - project 2
    - project 3

其中，`bin、pkg、src`是`GOPATH`下约定的3个目录，`bin`存放生成的可执行文件（通过go install命令），`pkg`存放编译中生成的中间文件（如.a），`src`存放源代码。在`src`下，创建各项目目录，存放项目代码。

<br/>

#### go build

用来编译并生成项目的可执行文件，命令执行的目录是：`gopath/src/project1/`

`go build [-o $name][path]` , 可以不指定路径或指定一个路径。若不指定，默认是当前路径，等价于`go build ./`。

`-o $name`指定生成的可执行文件名称。

<br/>

#### go run

编译并执行项目，但不会生成可执行文件，适合开发中使用。

`go run [path]`， 它与`go build`一样，可以不指定路径或指定一个路径。

<br/>

#### go install

编译并安装，与`go build`类似。生成的可执行文件会放在`gopath/bin/`下，中间文件放在`gopath/pkg/`下。

<br/>

#### go test

单元测试命令。单元测试代码建议与被测试代码放在同一个代码包中，并以 "_test.go" 为后缀，测试函数建议以 "Test" 为名称前缀。该命令可以对代码包进行测试，也可以指定某个测试代码文件运行（要一并带上被测试代码文件）

<br/>

#### go get [package]

下载第三方包并编译安装。如：

```bash
//-u 下载更新包
go get -u github.com/go-sql-driver/mysql
```

<br/>

#### go env

打印`golang`的环境变量。