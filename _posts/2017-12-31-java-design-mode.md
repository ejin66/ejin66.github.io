---
layout: post
title: Java设计模式记录
tags: [Java, Design Mode]
---



### 策略模式

***策略模式定义了一系列的算法，并将每一个算法封装起来，而且使它们还可以相互替换。策略模式让算法独立于使用它的客户而独立变化***

策略模式定义了一系列的算法，并将每一个算法封装起来，而且使它们还可以相互替换。策略模式让算法独立于使用它的客户而独立变化。

策略模式适用于一个主体对应多种可能的行为，各行为之间可以相互替换，并只能同时选择其中一种行为的情况。下面来看一个简单的例子。

现在大小商店都支持网上支付，就连街边摆摊卖水果的，都会在旁边放个二维码，实在是与时俱进。我们看网上支付这个事件，既可以使用微信，也可以支付宝，也可以网上银行付款。正好符合策略模式的适用范围。

```java
//定义一个网络支付的基类
public interface NetPay{
    void pay();
}
//具体的网络支付
//微信支付
public class WXPay implements NetPay {
    public void pay() {
        System.out.println("使用微信支付");
    }
}
//支付宝支付
public class AliPay implements NetPay {
    public void pay() {
        System.out.println("使用支付宝支付");
    }
}
//网银支付
public class WYPay implements NetPay {
    public void pay() {
        System.out.println("使用网银支付");
    }
}
//定义一个消费的主体
public class PersonContext {
    private NetPay netPay;
    
    public PersonContext(NetPay pay) {
        this.netPay = pay;
    }
    
    public void setNetPay(NetPay pay) {
        this.netPay = pay;
    }
    
    public void pay() {
        netPay.pay();
    }
}

public static void main(String[] args) {
    PersonContext xiaoming = new PersonContext(new WXPay());//小明掏出手机，选择了微信支付
    xiaoming.pay();//小明使用微信付款
    xiaoming.setNetPay(new AliPay());//小明发现微信没钱了，切换了支付方式，使用支付宝支付
    xiaoming.pay();// 
}
```





### 装饰者模式

***装饰者模式定义了在不必改变原类文件和使用继承的情况下，动态地扩展一个对象的功能。它是通过创建一个包装对象，也就是装饰来包裹真实的对象***

装饰者模式定义了在不必改变原类文件和使用继承的情况下，动态地扩展一个对象的功能。它是通过创建一个包装对象，也就是装饰来包裹真实的对象。

装饰者模式遵循的原则是对外扩展开放，对内修改关闭。

俗话说人靠衣装，衣服就是装饰，穿衣服的过程也是个装饰 模式。

```java
//定义一个人的基类
public interface Person {
    void decorate();
}
//定义一个装饰品类的基类
public interface Decorate extends Person {
}
//装饰品具体类
//衣服
public class ClothesDecorate implements Decorate {
    private Person person;
    public ClothesDecorate(Person person) {
        this.person = person;
    }
    public void decorate() {
        System.out.print(person.decorate() +" 穿上了衣服！");
    }
}
//内裤
public class NKDecorate implements Decorate {
    private Person person;
    public NKDecorate(Person person) {
        this.person = person;
    }
    public void decorate() {
        System.out.print(person.decorate() + " 穿上了内裤！");
    }
}
//定义一个具体的人
public class XiaoMing implements Person {
    public void decorate() {
        System.out.print("小明没有穿衣服哦！");
    }
}

public static void main(String[] args) {
    Person xiaoming = new XiaoMing();//小明啥都没穿
    Person xiaoming2 = new NKDecorate(xiaoming);//小明穿上了内裤。。。
    Person xiaoming3 = new ClothesDecorate(xiaoming2);//小明穿上了衣服
    xiaoming3.decorate();
    
    //可是小明一直觉得他是超人，那要怎么办？？
    Person superXiaoMing = new NKDecorate(new ClothesDecorate(new XiaoMing()));//恩。。。内裤外穿就可以了。。。
    superXiaoMing.decorate();
}
```





### 状态模式

***当一个对象的内在状态改变时允许改变其行为，这个对象看起来像是改变了其类。状态模式主要解决的是当控制一个对象状态的条件表达式过于复杂时的情况。把状态的判断逻辑转移到表示不同状态的一系列类中，可以把复杂的判断逻辑简化***

我们直接来看例子。

人的一生大致可以分为几个阶段：童年，青年，中年，老年。不同的阶段，吃、穿、住、行都不同。

若不用状态模式，代码如下：

```java
public class Person {
    public static final int STATUS_CHILD = 1;
    public static final int STATUS_YOUNG = 2;
    public static final int STATUS_MIDDLE = 3;
    public static final int STATUS_OLD = 4;
    private int status;

    public Person() {
        status = STATUS_CHILD;
    }
    
    public void setStatus(int status) {
        this.status = status;
    }
    
    public void eat() {
        switch(status) {
            case STATUS_CHILD:
                ...
                break;
            case STATUS_YOUNG :
                ...
                break;
            case STATUS_MIDDLE :
                ...
                break;
            case STATUS_OLD :
                ...
                break;
        }
    }
    
    public void wear() {
        switch(status) {
            case STATUS_CHILD:
                ...
                break;
            case STATUS_YOUNG :
                ...
                break;
            case STATUS_MIDDLE :
                ...
                break;
            case STATUS_OLD :
                ...
                break;
        }
    }
    
    public void stay() {
        //同上
    }
    
    public void walk() {
        //同上
    }
}
```

这样会让代码很臃肿，逻辑会变得复杂。

倘若这时，万恶的产品过来说，我们得增加一个状态：暮年。。。我们不得不去修改person类，光工作量大不说，一不小心把原来的代码改错了，那就等着被老板骂了。

我们换一种写法，用状态模式试试。

```java
//定义基类,有吃、穿、住、行4个方法
public interface Action {
    void eat();
    void wear();
    void stay();
    void walk();
}
//定义状态的基类
public interface Status extends Action {

}

//定义 童年、青年、中年、老年 4个状态
public class ChildStatus implements Status {
    public void eat() {
        System.out.print("童年：eat");
    }
    public void wear() {
        System.out.print("童年：wear");
    }
    public void stay() {
        System.out.print("童年：stay");
    }
    public void walk() {
        System.out.print("童年：walk");
    }
}
public class YoungStatus implements Status {
    public void eat() {
       System.out.print("青年：eat");
    }
    public void wear() {
       System.out.print("青年：wear");
    }
    public void stay() {
       System.out.print("青年：stay");
    }
    public void walk() {
       System.out.print("青年：walk");
    }
}
public class MiddleStatus implements Status {
    public void eat() {
       System.out.print("中年：eat");
    }
    public void wear() {
       System.out.print("中年：wear");
    }
    public void stay() {
       System.out.print("中年：stay");
    }
    public void walk() {
       System.out.print("中年：walk");
    }
}
public class OldStatus implements Status {
    public void eat() {
       System.out.print("老年：eat");
    }
    public void wear() {
       System.out.print("老年：wear");
    }
    public void stay() {
       System.out.print("老年：stay");
    }
    public void walk() {
       System.out.print("老年：walk");
    }
}

//定义个实体
public class Person implements Action {

    private Status status;
    
    public Person() {
        setStatus(new ChildStatus());
    }
    
    public void setStatus(Status status) {
        this.status = status;
    }
    
    public void eat() {
        status.eat();
    }    
    public void wear() {
        status.wear();
    }
    public void stay() {
        status.stay();
    }
    public void walk() {
        status.walk();
    }
}
```

采用了状态模式，person类的代码得到了极大的简化，后期维护也相当方便。