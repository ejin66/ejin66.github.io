---
layout: post
title: 基于GOLANG 的 API-Web Framework
tags: [Golang, Framework] 
---



**一.  首先，介绍下框架的主要功能：**

将如下url:

```
/controller/function/arg1/arg2...
```

路由到对应的方法:

```
Controller.function(arg1,arg2,...)
```

**二. 具体实现**

将所有请求都集中到一个handler中做处理

```go
func GetServeMux() *http.ServeMux {    
    var mServeMux http.ServeMux
    mServeMux.HandleFunc("/", defaultHandler)    
    return &mServeMux
}
```

根据url格式，解析请求的uri

```go
func defaultHandler(w http.ResponseWriter, req *http.Request) {    
    defer func() {        
        if err := recover(); err != nil {            
            //这里，主要是捕获调用函数参数不一致情况
            common.PrintError(err)
            io.WriteString(w, common.Error404())
        }
    }()    
    
    //获取请求uri
    uri := req.RequestURI


    fmt.Print(req.Method, " ", req.Host, " ", uri, " ",req.RemoteAddr, " " )    
    
    //若请求不带任何值, 则加上默认的controller(这个默认值是可以配置的)
    if uri == "/" {
        uri = "/" + config.GetConfig().DEFAULT_CONTROLLER
    }
    data := strings.Split(uri, "/")    
    
    //根据url格式，data[1]是路由的controller名称
    //在routerMap中查找controller name( routeMap是手动配置的路由表 )
    if v, ok := routeMap[strings.ToUpper(data[1])]; ok {        
        //匹配到，继续解析
        parse(&v, data[2:], &w, req)
    } else if len(data) == 2 {        
        //TODO 以后增加处理，如 /favicon.ico /sdsd.html 等等
        common.PrintError("No router found")
        io.WriteString(w, common.Error404())
    } else {
        common.PrintError("No router found")
        io.WriteString(w, common.Error404())
    }
}
```

parse方法中, 关键代码

```go
func parse(cfg *controller.Cfg, data []string, w *http.ResponseWriter, req *http.Request) {    
    // cfg.Cb 就是对应的controller实例的指针
    //考虑到并发, 要copy出一个实例
    //这里用反射实现
    b := reflect.New(reflect.ValueOf(cfg.Cb).Elem().Type()).Interface().(controller.Base)

    ......    
    
    //如果url格式没有方法名，默认加上index方法
    if len(data) == 0 {
        data = append(data, "index")
    }    
    
    //将方法名转换成{首字母大写、其余小写}的形式
    methodName := strings.ToUpper(string(data[0][0])) + strings.ToLower(string(data[0][1:]))

    ......    
    
    //利用反射调用方法
    mMethod := reflect.ValueOf(b).MethodByName(methodName)  
    
    if mMethod.IsValid() {        
        var params []reflect.Value   
        
        if len(data) > 1 {   
            //设置方法的参数
            //根据url格式，解析参数
            params = make([]reflect.Value, len(data)-1)            
            for i := range params {                
                switch mMethod.Type().In(i).Name() {                
                    case "int":                    
                        if v,err := strconv.Atoi(data[i+1]); err == nil {                        
                            params[i] = reflect.ValueOf(v)
                        } else {
                            common.PrintError("Arguments Type is not match!")
                            io.WriteString(*w, common.Error404())                        
                            return
                        }                
                    default:                    
                        params[i] = reflect.ValueOf(data[i+1])
                }

            }
        }        
        //最后，调用方法
        mMethod.Call(params)
        fmt.Println()
    } else {
        common.PrintError("Not found method in controller")
        io.WriteString(*w, common.Error404())
    }

}
```

项目的github地址: <https://github.com/ejin66/GoEjin>