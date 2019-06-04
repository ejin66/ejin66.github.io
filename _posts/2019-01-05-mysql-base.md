---
layout: post
title: mysql基础
tags: [Mysql]
---

### 连接数据库

1. 数据库连接

   ```bash
   mysql -h $host -u $username -p
   ```

2. 设置用户密码

   ```bash
   mysqladmin -u $username password $password
   ```

3. 修改用户密码

   ```bash
   mysqladmin -u $username -p password $new_password
   ```

<br/>

### 用户管理

1. 创建用户

   ```bash
   create user $username@$ip identified by $password;
   ```

   `用户名@ip`的作用是限定用户在哪些ip下可以访问数据库，如：

   - `ejin@192.168.1.1`. 表示`ejin`只有在`192.168.1.1`的ip下才能访问到数据库。
   - `ejin@192.168.1.%`. 表示`ejin`能在`192.168.1.*`这个ip段下访问数据库。
   - `ejin@%`. 表示`ejin`能在任意ip下访问数据库。

2. 删除用户

   ```bash
   drop user $username@$ip;
   ```

3. 修改用户

   ```bash
   rename user $username@$ip to $new_username@$ip;
   ```

4. 修改密码

   ```bash
   set password for $username@$ip = Password($new_password)
   ```

5. 查看用户权限

   ```bash
   show grants for $username@$ip;
   ```

6. 授权

   ```bash
   grant $privileges on $db_name.$table_name to $username@$ip;
   ```

   `$privileges`的权限有：

   - select
   - insert
   - create
   - drop
   - update
   - ...

   同时授权多个，用逗号分隔，如：`select,insert`。

7. 取消授权

   ```bash
   revoke $privileges on $db_name.$table_name from $username@$ip;
   ```

<br/>

### 数据库操作

1. 显示所有的数据库

   ```bash
   show databases;
   ```

2. 创建数据库

   ```bash
   create database $db_name default charset utf8 collate utf8_genral_ci;
   ```

3. 打开数据库

   ```bash
   use $db_name;
   ```

<br/>

### 数据表操作

1. 显示数据表

   ```bash
   show tables;
   ```

2. 创建数据表

   ```bash
   create table $table_name(
     $column_name $type null/not null [default $value] [auto_increment] [primary key],
     $column_name $type not null,
       [
         index [index_name]($column_name, $column_name),
         primary key($column_name, $column_name),
         unique key [unique_name]($column_name, $column_name)
       ]
   )engine=InnoDB default charset=utf8
   ```

   `engine`的区别是：

   ![engine]({{site.baseurl}}/assets/img/pexels/mysql-db-type.png)

3. 删除表

   ```bash
   drop table $table_name;
   ```

4. 清空表

   ```bash
   # 支持事务，可以回滚
   delete from $table_name;
   # 即时生效，不支持事务，不能回滚
   truncate table $table_name;
   ```

5. 修改表

   ```bash
   # 添加栏位
   alter table $table_name add $column_name $type;
   #删除栏位
   alter table $table_name drop column $column_name;
   # 修改栏位类型
   alter table $table_name modify column $column_name $type;
   # 修改栏位名称+类型
   alter table $table_name change $old_column_name $new_column_name $type;
   # 添加主键
   alter table $table_name add primary key($column_name);
   # 删除主键
   alter table $table_name drop primary key;
   ```

6. 查看表结构

   ```bash
   desc $table_name;
   ```

7. 新增数据

   ```bash
   # 插入一条
   insert into $table_name($cloumn1,$column2,...) values($value1,$value2,...)
   # 插入多条
   insert into $table_name($cloumn1,$column2,...) values($value1,$value2,...),($value1,$value2,...),...
   # 若插入包含了所有的栏位，insert可以忽略表栏位
   insert into $table_name values($value1,$value2,...)
   ```

8. 删除数据

   ```bash
   delete from $table_name;
   delete from $table_name where ...;
   ```

9. 更新数据

   ```bash
   update $table_name set $column_name = $value where ...;
   ```

10. 查询数据

   ```bash
   select * from $table where ...;
   # 排序，限制
   select * from $table where ... order by $column_name aes/desc limit $start_line, $length;
   select * from $table where ... limit $length [offset $start_line];
   ```

11. 分组查询

    ```bash
    select $column1, $column2, $column3,... from $table where ... group by $column1,$column2;
    ```

12. 多表查询

    ```bash
    # left join, 以左表为主
    select * from A left join B on A.id = B.id where ...;
    # right join, 以右表为主
    select * from A right join B on A.id = B.id where ...;
    # 显示所有满足条件的数据
    select * from A inner join B on A.id = B.id where ...;
    
    # 组合, 要求组合的栏位一致，默认去重
    select $column1, $column2 from A 
    union 
    select $column3, $column4 from B;
    # 不去重，显示所有
    select $column1, $column2 from A 
    union all
    select $column3, $column4 from B;
    ```

13. 栏位类型

    - `bit[(m)]`. 二进制， 默认m = 1

    - `tinyint[(m)] [unsigned] [zerofill]`. 小整数。

      有符号：-128 ~ 127；无符号：0 ~ 255；

      `tinyint(1)`表示布尔值。

    - `int[(m)] [unsigned] [zerofill]`. 整数。

    - `bigint[(m)] [unsigned] [zerofill]`. 大整数。

    - `decimal[(m[,d])] [unsigned] [zerofill]`. m是数字个数， d是小数点后个数。 

    - `float[(m, d)] [unsigned] [zerofill]`. 单精度浮点数。

    - `double[(m, d)] [unsigned] [zerofill]`. 双精度浮点数。

    - `char(m)`. 固定长度的字符串。需要注意的是，即使数据小于设定长度，也会占用m长度。

    - `varchar(m)`. 变长的字符串。

    - `text`. 变长的大字符串。

    - `mediumtext`.

    - `longtext`.

    - `DATETIME`.  不做改变，原值输入输出。

    - `TIMESTAMP`.  它会把客户端插入的时间从当前时区转化为UTC进行存储；查询时，将其又转化为客户端当前时区时间。

