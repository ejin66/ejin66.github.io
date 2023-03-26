---
layout: post
title: php中Session过期的问题
tags: [PHP, Session]
---



**问题：**

一般我们会配置[PHP](http://lib.csdn.net/base/php)中session的有效期，假设gc_maxlifetime=3600（s）。道理上，一个session的最近一次修改时间到现在超过了3600s，这个session应该过期，被GC回收才对。但事实是， 超过有效期之后，仍能获取到session。



**分析：**

每运行一个php文件，只有1%的概率会GC回收,session.gc_probability = 1,session.gc_divisor =  100,这两个参数就是配置这概率的。因此，当session过期，但是GC又没有跑起来的时候，就会造成session超过有效期，又能拿到的原因。 



**解决：**

可以通过代码来解决session的这种不确定因素。

假设我们想session的有效期是3600。

  1.gc_maxlifetime = 5400（大于1h即可）

  2.在设置session的时候，设置当前时间。$_SESSION['time'] = time()

  3.后面需要用的session的地方，先做判断：time()-$_SESSION['time'] >3600,则session已过期，unset掉相关session值。