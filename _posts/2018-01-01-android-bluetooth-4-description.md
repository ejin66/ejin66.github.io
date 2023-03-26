---
layout: post
title: Android蓝牙4.0基本使用
tags: [Android, Bluetooth]
---

#### 蓝牙4.0是一项低耗能蓝牙技术，它使用一套全新的api（区别于传统蓝牙），要求android 4.3以上版本。

#### api调用示例：

1. 蓝牙搜索

   ```java
   BluetoothAdapter mAdapter = BluetoothAdapter.getDefaultAdapter();
   mAdapter.startLeScan(new BluetoothAdapter.LeScanCallback() {
           public void onLeScan(final BluetoothDevice device, final int rssi, final byte[] scanRecord) {
                //code
            }
   });
   ```

   每搜索到一个ibeacon，就回掉onLeScan方法一次。startLeScan属于耗时操作，最好不要放在UI线程中。 



2. 关闭蓝牙搜索

   ```ja
   mAdapter.stoptLeScan(BluetoothAdapter.LeScanCallback);
   ```

   

3. onLeScan参数说明

   ```
   BluetoothDevice device  搜索到的蓝牙设备
   int rssi  信号强度
   byte[] scanRecord  暂不了解，不过通过它可以算出UUID
   ```



4. 信号强度与距离的关系

   ```java
   int absRssi = Math.abs(rssi);
   float power = (absRssi-59)/(10*2.0f); //59代表相隔一米时的rssi， 2.0代表环境衰减因子,需根据实际测试给出合适值
   ```



5. 获取device uuid

   ```java
   public String parseUUID(byte[] scanRecord) {   
           int startByte = 2;   
           boolean patternFound = false;   
           while(startByte <=5 ) {     
                   if (((int) scanRecord[startByte + 2] & 0xff) == 0x02 && //Identifies an iBeacon           
                           ((int) scanRecord[startByte + 3] & 0xff) == 0x15) { //Identifies correct data length           
                           patternFound = true;           
                           break;      
                    }
                  startByte++;  
            }  
   
            if (patternFound) {       
                   byte[] uuidBytes = new byte[16];       
                   System.arraycopy(scanRecord,startByte+4,uuidBytes,0,16);       
                   String hexString = bytesToHexString(uuidBytes);       
                   String uuid = hexString.substring(0,8)+"-"+
                          hexString.substring(8,12)+"-"+
                          hexString.substring(12,16)+"-"+
                          hexString.substring(16,20)+"-"+
                          hexString.substring(20,32);       
                   return uuid;   
           } else {       
                   return "";   
           }
   }
   ```

   
