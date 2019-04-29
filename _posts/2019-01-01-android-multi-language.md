---
layout: post
title: [Android多语言的适配总结]
tags: [Android]
---

### 前言

前段时间在做多语言功能时，发现适配起来相当麻烦，不得不感慨`android`版本发布得越来越快了。本文就来总结一下多语言的适配问题。



### 多语言适配

`android` 7.0之后，语言设置偏好支持添加多个语言，而且在应用中`activity`的语言默认是跟随系统的。因此，在设置多语言时，大概有以下几步：

1. **修改语言配置**

   ```java
   public static void changeLanguage(Context context, Locale locale) {
           Configuration configuration = context.getResources().getConfiguration();
           DisplayMetrics displayMetrics = context.getResources().getDisplayMetrics();
   
           if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.N) {
               //这部分逻辑应该可以省略
               LocaleList localeList = new LocaleList(locale);
               configuration.setLocales(localeList);
               context.createConfigurationContext(configuration);
           } else if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.JELLY_BEAN_MR1) {
               configuration.setLocale(locale);
               context.getResources().updateConfiguration(configuration, displayMetrics);
           } else {
               configuration.locale = locale;
               context.getResources().updateConfiguration(configuration, displayMetrics);
           }
           SharedPreferencesUtils.setParam(context, FILENAME, APP_LANGUAGE, locale.getLanguage());
   }
   ```

   

2. **7.0之后修改context**

   7.0之后，`activity`的语言设置默认是跟随系统的。因此不管在第一步怎么设置，对7.0之后的系统都没有用。

   所以，需要在`activity.attachBaseContext`时更新这个`context`，添加上语言信息：

   ```java
   override fun attachBaseContext(newBase: Context) {
           if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.N) {
               super.attachBaseContext(LanguageUtil.wrapperContext(newBase))
           } else {
               super.attachBaseContext(newBase)
           }
   }
   ```

   `wrapperContext`的逻辑是：

   ```java
   @TargetApi(24)
   public static Context wrapperContext(Context context) {
           Locale locale = currentLocale(context);
           Configuration configuration = context.getResources().getConfiguration();
           configuration.setLocale(locale);
           LocaleList localeList = new LocaleList(locale);
           LocaleList.setDefault(localeList);
           configuration.setLocales(localeList);
           return context.createConfigurationContext(configuration);
   }
   ```

   通过`context.createConfigurationContext(configuration)`得到一个新的包含语言信息的`context`给到`activity`，来实现语言的设置。

   在7.0之后，需要自己保存用户设置的语言信息。如上面例子中的`currentLocale`方法，逻辑如下：

   ```java
   public static Locale currentLocale(Context context) {
       	//这里就保存了用户设置的语言信息
           String language = (String) SharedPreferencesUtils.getParam(context, FILENAME, APP_LANGUAGE, "");
           if (TextUtils.isEmpty(language)) {
               //如果没有设置，就获取系统的默认语言
               language = getDefaultLocale().getLanguage();
           }
           return getLocaleBy(language);
   }
   
   public static Locale getDefaultLocale() {
           if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.N) {
               //7.0有多语言设置获取顶部的语言
               return Resources.getSystem().getConfiguration().getLocales().get(0);
           } else {
               return Locale.getDefault();
           }
   }
   ```

   

3. **重启所有的activity**

   设置完语言之后，需要重启创建`activity`才能加载设置的语言。