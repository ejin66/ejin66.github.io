---
layout: post
title: WebRTC 总结
tags: [WebRTC]
---

### 前言

接触 `WebRTC` 一年左右，用来实现多人实时音视频功能，目前项目已经趋于稳定了。现在来记录总结下 `WebRTC` 基础知识点。

`WebRTC` 是Google开源的一项实时音视频项目，支持Android、iOS、PC、Broswer，可以说各个端都支持。而且在 FireFox、Chrome浏览器上，默认是内置的。

官网地址：[WebRTC](https://webrtc.org/) 。

火狐官方的`WebRTC` API说明文档：[WebRTC API](https://developer.mozilla.org/en-US/docs/Web/API/WebRTC_API)

<br/>



### WebRTC结构

一套完整的WebRTC框架，分为 Server端、Client端两大部分。

Server端：

- Stun服务器
- Turn服务器
- 信令服务器

Client端，正如前面介绍的，有四大端：

- Android
- iOS
- PC
- Broswer

`Stun服务器` 是用来协助进行端到端打洞的，即P2P通信。 `WebRTC` 默认是基于 P2P 传输，这样做的好处是能够减轻服务器的压力，减少服务器的流量。

可理想总是太美好，现实太骨感。当前运营商网络的环境，让P2P打洞成了一纸空谈。好在还有另一条路：转发。`Turn服务器` 便是在P2P打洞失败的情况下，进行多媒体流转发的服务器。虽然这样压力都聚集到的服务器端，但也是没有办法的办法了。

`信令服务器` 的作用主要有两方面：

- 负责端到端的连接。两端在连接之初，需要交换信令，如sdp、candidate等，都是通过`信令服务器` 进行转发交换的。
- 具体业务上的信息转发。

> 根据项目经验，`Turn` 、`Stun` 服务器有第三方项目，可以直接部署。而`信令服务器` ，则要自己开发，包括制定信令格式、转发逻辑等。

Client端的实现，可以去`WebRTC`官网看看相应的Library库。

Broswer端的话，浏览器（FireFox、Chrome）是内置的，都不需要另外添加库。

Andorid、iOS端都有相应的库能够使用。

PC端暂时不清楚有没有现成的库，如果没有的话，需要自己编译。

<br/>



### WebRTC流程

开门见山，直接上图（从网络上找的一张图）：

![WebRTC流程图]({{site.baseurl}}/assets/img/pexels/20170427174239969.png)

其实流程图已经很清楚了，这里再简单描述一下：

1. 终端连接到 `信令服务器`，准备为两端进行信令交换。
2. 发起端创建 PeerConnection，生成Offer 信令（SDP），通过 `信令服务器` 转发给另一端。
   - SDP：描述建立音视频连接的一些属性，如音频的编码格式、视频的编码格式、是否接收/发送音视频等等。
3. 响应端收到Offer 信令之后，生成Answer 信令（SDP）, 返回给发起端。
4. 交换完SDP之后，相互发送自己的Candidate信息。
   - Candidate：主要包含了相关方的IP信息，包括自身局域网的ip、公网ip、turn服务器ip、stun服务器ip等。
5. 有了ip信息之后， 开始尝试进行 P2P打洞（打洞过程是框架实现的，如想知道打洞原理，可自行百度）。若打洞不成功，则会改用服务器转发。
6. 无论是打洞还是转发，只要有一条路是成功的，那PeerConnection就算是成功建立了。接下来就可以进行音视频通话了。

<br/>



### SDP 格式

上面的Offer、 Answer 信令，都是SDP，全称是：Session Description Protocol。关于SDP的描述以及协议解析，可以看下维基百科的描述：[SDP 描述](https://en.wikipedia.org/wiki/Session_Description_Protocol)。

以下是一个真实的SDP例子：

```yaml
v=0
o=- 782260694725067566 2 IN IP4 127.0.0.1
s=-
t=0 0
a=group:BUNDLE audio video
a=msid-semantic: WMS ARDAMS
m=audio 9 UDP/TLS/RTP/SAVPF 103 111 9 102 0 8 105 13 110 113 126
c=IN IP4 0.0.0.0
a=rtcp:9 IN IP4 0.0.0.0
a=ice-ufrag:RW5e
a=ice-pwd:4axiI6AFe0kZEQKy41e+/5je
a=ice-options:trickle renomination
a=fingerprint:sha-256 67:B8:B0:0E:1C:30:88:FF:CD:CF:B3:B4:28:89:8A:7D:BC:D7:01:A6:C8:4D:03:A8:B9:D7:BA:5B:76:38:EE:7A
a=setup:actpass
a=mid:audio
a=extmap:1 urn:ietf:params:rtp-hdrext:ssrc-audio-level
a=sendrecv
a=rtcp-mux
a=rtpmap:103 ISAC/16000
a=rtpmap:111 opus/48000/2
a=rtcp-fb:111 transport-cc
a=fmtp:111 minptime=10;useinbandfec=1
a=rtpmap:9 G722/8000
a=rtpmap:102 ILBC/8000
a=rtpmap:0 PCMU/8000
a=rtpmap:8 PCMA/8000
a=rtpmap:105 CN/16000
a=rtpmap:13 CN/8000
a=rtpmap:110 telephone-event/48000
a=rtpmap:113 telephone-event/16000
a=rtpmap:126 telephone-event/8000
a=ssrc:1835132917 cname:TF7FgfIqBl6pQ0eE
a=ssrc:1835132917 msid:ARDAMS ARDAMSa0
a=ssrc:1835132917 mslabel:ARDAMS
a=ssrc:1835132917 label:ARDAMSa0
m=video 9 UDP/TLS/RTP/SAVPF 98 96 97 99 100 101 127
c=IN IP4 0.0.0.0
a=rtcp:9 IN IP4 0.0.0.0
a=ice-ufrag:RW5e
a=ice-pwd:4axiI6AFe0kZEQKy41e+/5je
a=ice-options:trickle renomination
a=fingerprint:sha-256 67:B8:B0:0E:1C:30:88:FF:CD:CF:B3:B4:28:89:8A:7D:BC:D7:01:A6:C8:4D:03:A8:B9:D7:BA:5B:76:38:EE:7A
a=setup:actpass
a=mid:video
a=extmap:2 urn:ietf:params:rtp-hdrext:toffset
a=extmap:3 http://www.webrtc.org/experiments/rtp-hdrext/abs-send-time
a=extmap:4 urn:3gpp:video-orientation
a=extmap:5 http://www.ietf.org/id/draft-holmer-rmcat-transport-wide-cc-extensions-01
a=extmap:6 http://www.webrtc.org/experiments/rtp-hdrext/playout-delay
a=extmap:7 http://www.webrtc.org/experiments/rtp-hdrext/video-content-type
a=extmap:8 http://www.webrtc.org/experiments/rtp-hdrext/video-timing
a=sendrecv
a=rtcp-mux
a=rtcp-rsize
a=rtpmap:98 VP9/90000
a=rtcp-fb:98 goog-remb
a=rtcp-fb:98 transport-cc
a=rtcp-fb:98 ccm fir
a=rtcp-fb:98 nack
a=rtcp-fb:98 nack pli
a=rtpmap:96 VP8/90000
a=rtcp-fb:96 goog-remb
a=rtcp-fb:96 transport-cc
a=rtcp-fb:96 ccm fir
a=rtcp-fb:96 nack
a=rtcp-fb:96 nack pli
a=rtpmap:97 rtx/90000
a=fmtp:97 apt=96
a=rtpmap:99 rtx/90000
a=fmtp:99 apt=98
a=rtpmap:100 red/90000
a=rtpmap:101 rtx/90000
a=fmtp:101 apt=100
a=rtpmap:127 ulpfec/90000
a=ssrc-group:FID 1479805214 300866078
a=ssrc:1479805214 cname:TF7FgfIqBl6pQ0eE
a=ssrc:1479805214 msid:ARDAMS ARDAMSv0
a=ssrc:1479805214 mslabel:ARDAMS
a=ssrc:1479805214 label:ARDAMSv0
a=ssrc:300866078 cname:TF7FgfIqBl6pQ0eE
a=ssrc:300866078 msid:ARDAMS ARDAMSv0
a=ssrc:300866078 mslabel:ARDAMS
a=ssrc:300866078 label:ARDAMSv0
```

1. 其中， `a=group:BUNDLE audio video` 表示是两路多媒体流。

   > 目前`WebRTC` 使用的是PLAN B协议(最新的是Unified PLAN)，支持一个PeerConnection发送多路流媒体。而PLAN A只能一个PeerConnection一路流。

2. `m=audio 9 UDP/TLS/RTP/SAVPF 103 111 9 102 0 8 105 13 110 113 126` 表示：从这里往下的描述是与audio相关的。
3. `m=video 9 UDP/TLS/RTP/SAVPF 98 96 97 99 100 101 127` 表示：从这里往下的描述是与video相关的。
4. 后面跟着的数字表示所支持的编码格式，越靠前的代表越优先选择。如audio的103， 表示本端优先支持音频ISAC编码；video的98，表示优先支持视频VP9编码。在其下方内容中也能够找到：`a=rtpmap:103 ISAC/16000` , `a=rtpmap:98 VP9/90000`
5. 每段media描述中，都有一个mid。音频是：`a=mid:audio` ， 视频是： `a=mid:video`
6. 每段media描述中，都有一个media的收发状态。该例子中的音视频收发状态都是： `a=sendrecv`。总共有三种状态：
   - sendrecv  既发送也接受对应的media
   - sendonly  只发送本地的media，不接收对方的media
   - recvonly  只接收对方的media，不发送自己的media

> 以上是对我对SDP的简单分析，其他的信息也不是太清楚其作用，欢迎大家深入了解。

<br/>



### 结尾

关于`WebRTC`，暂时先写到这里，改天有时间的话在补充。

留下一个思考：

- 既然PeerConnection是端到端的连接， 那要如何实现多人音视频？





