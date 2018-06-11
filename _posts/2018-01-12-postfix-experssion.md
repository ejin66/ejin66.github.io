---
layout: post
title: 后缀表达式算法
tags: [Postfix Experssion]
---



​    如果有个功能需求：用户随便输入一个算数表达式，要求系统给出表达式结果。看似简单，其实表达式中各种运算符，各种括号优先级，写起来逻辑还是蛮复杂的。有什么简单的方法吗？我们可以用后缀表达式来实现。

​    在介绍什么是后缀表达式之前，先介绍一下中缀表达式。其实这个大家都很熟悉，形如 (2 + 1) * 3，我们叫它中缀表达式。如果换成后缀表达式，就要表示成 2 1 + 3 * 。

​    ***后缀表达式的定义：不包含括号，运算符放在两个运算对象的后面，所有的计算按运算符出现的顺序，严格从左向右进行（不再考虑运算符的优先规则。***

​    因此，实现这个功能，主要分为两步：

​    	1.将中缀表达式转为后缀表达式

​    	2.通过后缀表达式算法，算出结果



**中缀表达式转成后缀表达式的算法思想：**

​        若为数字时，加入后缀表达式；

​        若为运算符：

​            a. 若为 '('，入栈；

​            b. 若为 ')'，则依次把栈中的的运算符加入后缀表达式中，直到出现'('，从栈中删除'(' ；

​            c. 若为 除括号外的其他运算符， 当其优先级高于除'('以外的栈顶运算符时，直接入栈。否则从栈顶开始，依次弹出比当前处理的运算符优先级高和优先级相等的运算符，直到一个比它优先级低的或者遇到了一个左括号为止。

​        当扫描的中缀表达式结束时，栈中的的所有运算符出栈



**运用后缀表达式进行计算的具体做法：**

​        建立一个栈S   。从左到右读表达式，如果读到操作数就将它压入栈S中，如果读到n元运算符(即需要参数个数为n的运算符)则取出由栈顶向下的n项按操作数运算，再将运算的结果代替原栈顶的n项，压入栈S中  。如果后缀表达式未读完，则重复上面过程，最后输出栈顶的数值则为结束。

​    **示例：** (2 + 1) * 3

​    **第一步，将表达式转为后缀表达式：**

​            1. ( 不是数字，入栈。栈：（。后缀表达式：__(空)

​            2. 2是数字，放到后缀表达式中。栈：（。后缀表达式：2

​            3. +入栈。栈：（+。后缀表达式：2

​            4. 1放入后缀表达式。栈：（+。后缀表达式：2 1

​            5. ），循环出栈，直到遇到（，期间，符号放到后缀表达式中，弃掉（）。栈：__(空)。后缀表达式：2 1 +

​            6. * 入栈。栈：*。后缀表达式：2 1 +

​            7： 3加入后缀表达式。栈：*。后缀表达式：2 1 + 3

​            8：表达式结束，全部出栈，符号放入后缀表达式。栈：__(空)。后缀表达式：2 1 + 3 *

​            经过上述步骤，我们得到了该表达式对应的后缀表达式：2 1 + 3 *

​    **第二部，计算后缀表达式**

​            1. 2不是运算符，入栈。栈：2

​            2. 1不是运算符，入栈。栈：2 1

​            3. +是运算符，原栈2 1出栈，2+1=3，结果入栈。栈：3

​            4. 3不是运算符，入栈。 栈： 3 3

​            5. *是运算符，原栈3 3出栈，3*3=9，结果入栈。 栈： 9

​            2 1 + 3 * = 9

​            (2 + 1) * 3 = 9

​            两个表达式结果是一致的。



**用java代码的简单实现：**

```java
/**
 * 将中缀表达式转为后缀表达式
 *
 * @param standardExp
 * @return
 */
public static String convert(String standardExp) {
    //1.判断表达式的合法性
    //2.转成后缀表达式

    Stack<Character> stacks = new Stack<Character>();
    StringBuffer buffer = new StringBuffer();

    char[] charArray = standardExp.toCharArray();
    for (char tmp : charArray) {
        if(isNumber(tmp)) {
            buffer.append(tmp);
        } else if(isSymbol(tmp)) {

            if(stacks.empty()) {
                stacks.push(tmp);
            } else {
                char c = stacks.peek();//拿到栈顶的对象
                if (tmp == 40) { //左括号
                    stacks.push(tmp);
                } else if (tmp == 41) {//右括号
                    while(true) {
                        if(c != 40) {
                            buffer.append(stacks.pop());
                        } else {
                            stacks.pop();
                            break;
                        }
                        if(stacks.empty()) {
                            break;
                        }
                        c = stacks.peek();
                    }
                } else {
                    if(c == 40) {
                        stacks.push(tmp);
                    } else {
                        int flag = compareSymbol(tmp,c);
                        if(flag > 0) {
                            stacks.push(tmp);
                        } else {
                            while (true) {
                                buffer.append(stacks.pop());
                                if(stacks.empty()) {
                                    break;
                                }
                                c = stacks.peek();//拿到栈顶的对象
                                if(c == 40) {
                                    break;
                                }
                                flag = compareSymbol(tmp,c);
                                if (flag > 0) {
                                    break;
                                }
                            }
                            stacks.push(tmp);
                        }
                    }
                }
            }
        }
    }
    while (!stacks.empty()) {
        buffer.append(stacks.pop());
    }
    return buffer.toString();
}
/**
 * 根据后缀表达式计算出结果
 *
 * @param suffixExp
 * @return
 */
public static long calculate(String suffixExp) {

    Stack<Long> stacks = new Stack<Long>();
    char[] charArray = suffixExp.toCharArray();
    for(char tmp : charArray) {
        if(isNumber(tmp)) {
            stacks.push(Long.parseLong(String.valueOf(tmp)));
        } else if(isSymbol(tmp)) {
            long num1 = stacks.pop();
            long num2 = stacks.pop();
            switch (tmp) {
                case '+' :
                    stacks.push(num1+num2);
                    break;
                case '-' :
                    stacks.push(num2-num1);
                    break;
                case '*' :
                    stacks.push(num2*num1);
                    break;
                case '/' :
                    stacks.push(num2/num1);
                    break;
            }
        }
    }

    return stacks.pop();
}
```