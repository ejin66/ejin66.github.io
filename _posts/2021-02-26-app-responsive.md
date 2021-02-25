---
layout: post
title: Flutter 响应式框架app responsive
tags: ["Flutter"]
---

`app_responsive` 是基于google `provider`的响应式框架，本框架主要目的是更好的、更简化的处理UI与controller(逻辑)之间的关系。框架主要的功能：页面数据加载逻辑的封装、页面UI刷新控制、页面间的数据共享。

接下来下面来详细的介绍各个功能：

###  1. 页面UI刷新控制

<img src="https://ejin66.github.io/assets/img/pexels/loaded.gif" width = "300px" /> 

### 2. 页面的数据加载逻辑封装

*数据的加载、刷新、分页、加载空数据*

<img src="https://ejin66.github.io/assets/img/pexels/loading.gif" width = "200" />  <img src="https://ejin66.github.io/assets/img/pexels/no_data.gif" width = "200" /> 


### 3. 页面间数据共享

3.1. *获取其他页面数据*
<br />
<img src="https://ejin66.github.io/assets/img/pexels/next_page.gif" width = "200" />



3.2. *监听其他页面的数据变化*
