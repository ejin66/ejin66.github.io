---
layout: post
title: Android 存储访问框架（SAF）整理
tags: [Android]
---

### 关于存储访问框架

存储访问框架（Storage Access Framework），是Google在Andorid 4.4引入的。通过它，用户可以轻松的浏览并打开文档、图像以及其他文件。

SAF包含以下内容：

- **文档提供程序** — 一种内容提供程序，可让存储服务（如 Google Drive）显示其管理的文件。文档提供程序以 `DocumentsProvider` 类的子类形式实现。文档提供程序的架构基于传统的文件层次结构，但其实际的数据存储方式由您决定。Android 平台包含若干内置文档提供程序，如 Downloads、Images 和 Videos。
- **客户端应用** — 一种自定义应用，它会调用 `ACTION_OPEN_DOCUMENT` 和/或 `ACTION_CREATE_DOCUMENT` Intent 并接收文档提供程序返回的文件。
- **选择器** — 一种系统界面，可让用户访问所有满足客户端应用搜索条件的文档提供程序内的文档。

以下为 SAF 提供的部分功能：

- 让用户浏览所有文档提供程序的内容，而不仅仅是单个应用的内容。
- 让您的应用获得对文档提供程序所拥有文档的长期、持续性访问权限。用户可通过此访问权限添加、编辑、保存和删除提供程序上的文件。
- 支持多个用户帐户和临时根目录，如只有在插入驱动器后才会出现的 USB 存储提供程序。

用户APP与文档提供者之间并不直接接触，而是通过选择器（一种系统界面）进行交流。这样的好处是，可以轻松的整合其他第三方的提供者。第三方应用只需要实现`DocumentsProvider`（系统不关心你提供的文档是本地的还是云端的还是其他什么），系统就会将它加入到选择器中。

以下示意图就展示了这种结构：

![storage data flow]({{ site.baseurl }}/assets/img/pexels/storage_dataflow.png)

从左到右分别是：客户端应用、选择器、文档提供程序

系统选择器页面如下图：

![system picker]({{ site.baseurl }}/assets/img/pexels/storage_picker.png)



从Andorid Q开始，无法直接操作存储空间了，需要借助SAF进行操作。

> 使用SAF不需要申请读写权限？

### SAF使用示例

#### 添加一个文档

发起意图

```kotlin
val intent = Intent(Intent.ACTION_CREATE_DOCUMENT).apply {
    //过滤选择器中的文档。要求是能够通过ContentResolver.openFileDescriptor(Uri, String)打开的文档
    addCategory(Intent.CATEGORY_OPENABLE)
    //过滤选择器中的文档。要求类型是 text/plain
    type = "text/plain"
    //设置新创建的文档名称
    putExtra(Intent.EXTRA_TITLE, "test1.txt")
}
//打开选择器
startActivityForResult(intent, createDocumentCode)
```

接着，用户在选择器中选择保存路径。完成后，通过`onActivityResult`接收：

```kotlin
override fun onActivityResult(requestCode: Int, resultCode: Int, data: Intent?) {
    super.onActivityResult(requestCode, resultCode, data)
    data ?: return

    if (resultCode == Activity.RESULT_OK) {
        if (requestCode == createDocumentCode) testActionCreateDocumentResult(data)
    }
}

private fun testActionCreateDocumentResult(data: Intent) {
    val uri = data.data ?: return
    //log: content://com.android.externalstorage.documents/document/primary%3Atest1.txt
    Log.d(javaClass.name, uri.toString())
    //通过uri获取到一个output流
    val outputStream = contentResolver.openOutputStream(uri)
    //写入内容
    outputStream?.use {
        it.write("use ACTION_CREATE_DOCUMENT".toByteArray())
        it.flush()
        it.close()
    }
}
//这样，就完成了一个文件的创建以及写入内容。
```

#### 读取、编辑、删除文档

发起意图

```kotlin
private fun testActionOpenDocument() {
    val intent = Intent(Intent.ACTION_OPEN_DOCUMENT).apply {
        addCategory(Intent.CATEGORY_OPENABLE)
        type = "image/*"
    }
    startActivityForResult(intent, openDocumentCode)
}
```

接着，用户选择一个图片并返回。通过`onActivityResult`接收：

```kotlin
private fun testActionOpenDocumentResult(data: Intent) {
    val uri = data.data ?: return
    //log: content://com.android.providers.media.documents/document/image:40
    Log.d(javaClass.name, uri.toString())

    //获取元数据
    val cursor = contentResolver.query(uri, null, null, null, null)
    cursor?.use {
        if (it.moveToFirst()) {
            val displayName = it.getString(it.getColumnIndex(OpenableColumns.DISPLAY_NAME))
            val sizeIndex = it.getColumnIndex(OpenableColumns.SIZE)
            val size = if (it.isNull(sizeIndex)) {
                "Unknown"
            } else {
                it.getString(sizeIndex)
            }
            Log.d(javaClass.name, "name: $displayName, size: $size")
        }
        it.close()
    }

	//1.直接读取图片二进制数据
    val descriptor = contentResolver.openFileDescriptor(uri, "r") ?: return
    val imageBytes = FileInputStream(descriptor.fileDescriptor).readBytes()
    Log.d(javaClass.name, "image size: ${imageBytes.size}")
    
    //2.或者，编辑文件
    contentResolver.openFileDescriptor(uri, "w")?.use {
        FileOutputStream(it.fileDescriptor).use {
            it.write("use ACTION_OPEN_DOCUMENT to edit this file".toByteArray())
            it.flush()
            it.close()
        }
    }
    
    //3.或者，删除文件
    DocumentsContract.deleteDocument(contentResolver, uri)
}
```

#### 保留权限

一般情况下，通过`ACTION_OPEN_DOCUMENT`、`ACTION_CREATE_DOCUMENT` Intent获取的uri，系统授权的有效期到用户设备重启。而如果我们想保留系统向应用授予的权限，不受重启的影响，可以通过下面的操作实现：

```kotlin
//Intent.FLAG_GRANT_READ_URI_PERMISSION or Intent.FLAG_GRANT_WRITE_URI_PERMISSION
contentResolver.takePersistableUriPermission(uri, Intent.FLAG_GRANT_READ_URI_PERMISSION)
```

然后，通过`getPersistedUriPermissions`可以查看当前APP中所有的被持久授权的uri list:

```kotlin
contentResolver.persistedUriPermissions.forEach {
    Log.d(javaClass.name, it.toString())
}
```

### 与ACTION_PICK、ACTION_GET_CONTENT的差别

1. `ACTION_PICK`、`ACTION_GET_CONTENT`支持Android 4.4之前的版本，而`ACTION_OPEN_DOCUMENT`是从Android 4.4开始支持的。
2. 虽然都支持读取文档，但是`ACTION_PICK`、`ACTION_GET_CONTENT`只是一次性的读取数据，`ACTION_OPEN_DOCUMENT`支持让应用对文档持有长期、持续性的访问权限。

#### `ACTION_PICK`的简单使用：

```kotlin
private fun testActionPick() {
    //ACTION_PICK获取的是相册中的图片
    //且一定要设置intent.data, 否则会报错:ActivityNotFoundException: No Activity found to handle Intent
    //log: content://media/external/images/media
    Log.d(javaClass.name, MediaStore.Images.Media.EXTERNAL_CONTENT_URI.toString())
    val intent = Intent(ACTION_PICK)
    intent.data = MediaStore.Images.Media.EXTERNAL_CONTENT_URI
    startActivityForResult(intent, pickCode)
}

private fun testActionPickResult(data: Intent) {
    val uri = data.data ?: return
    //log: content://com.google.android.apps.photos.contentprovider/-1/1/content://media/external/images/media/40/ORIGINAL/NONE/184953308
    Log.d(javaClass.name, uri.toString())

    val descriptor = contentResolver.openFileDescriptor(uri, "r") ?: return

    val imageBytes = FileInputStream(descriptor.fileDescriptor).readBytes()
    Log.d(javaClass.name, "image size: ${imageBytes.size}")
}
```

#### `ACTION_GET_CONTENT`的简单使用：

```kotlin
private fun testActionGetContent() {
    //ACTION_GET_CONTENT 获取的是本地所有的图片
    val intent = Intent(ACTION_GET_CONTENT)
    intent.type = "image/*"
    startActivityForResult(intent, getContentCode)
}

private fun testActionGetContentResult(data: Intent) {
    val uri = data.data ?: return
    //log: content://com.android.providers.media.documents/document/image:40
    Log.d(javaClass.name, uri.toString())

    val descriptor = contentResolver.openFileDescriptor(uri, "r") ?: return

    val imageBytes = FileInputStream(descriptor.fileDescriptor).readBytes()
    Log.d(javaClass.name, "image size: ${imageBytes.size}")
}
```

