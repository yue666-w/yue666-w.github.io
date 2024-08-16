<?php
include("includes/common.php");
@header('Content-Type: text/html; charset=UTF-8');
@header('Access-Control-Allow-Origin:*');
$act = isset($_GET['act']) ? $_GET['act'] : null;
switch ($act) {
    case 'getInfo':
        $id = isset($_REQUEST['id']) ? (int)trim(daddslashes($_REQUEST['id'])) : '';
        if ($id == '') {
            $result = array("code" => -1, "msg" => "系统错误1");
            exit(json_encode($result));
        }
        $tres = $DB->get_row("select * from dwz_title where id=:id limit 1", [':id' => $id]);
        if (!$tres) {
            $result = array("code" => -1, "msg" => "系统错误2");
            exit(json_encode($result));
        }
        $DB->exec("update dwz_title set views=views+1 where id=:id", [':id' => $id]);
        if ($id == 5) {
            $rs = $DB->query("select * from dwz_list where tid=:id order by id desc limit 7", [':id' => $id]);
        } else {
            $rs = $DB->query("select * from dwz_list where tid=:id", [':id' => $id]);
        }
        $data = array();
        while ($row = $rs->fetch()) {
            $data[$row['cid']][] = [
                "title" => $row['title'],
                "content" => $row['content'],
                "videoList" => $row['videoList']
            ];
        }
        $result = array(
            "code" => 0,
            "msg" => "获取成功",
            "data" => array(
                "title" => $tres['title'],
                "onShare" => $tres['onShare'],
                "miaoshu" => $tres['miaoshu'],
                "imgUrl" => $tres['imgUrl'],
                "shareUrl" => $tres['shareUrl'],
                "headBgImg" => $conf['bgImgUrl'],
                "headBgNotice" => $conf['ggImgUrl'],
                "footQrcode" => $conf['qrImgUrl'],
                "noticeTitle1" => $conf['gg1_title'],
                "noticeContent1" => $conf['gg1_content'],
                "noticeTitle2" => $conf['gg2_title'],
                "noticeContent2" => $conf['gg2_content'],
                "kl" => $conf['kskl'],
                "data" => $data
            )
        );
        exit(json_encode($result));
        break;
    case 'getInfo2':
        $result = array(
            "code" => 0,
            "msg" => "获取成功",
            "data" => array(
                "headBgImg" => $conf['bgImgUrl'],
                "headBgNotice" => $conf['ggImgUrl'],
                "footQrcode" => $conf['qrImgUrl'],
                "noticeTitle1" => $conf['gg1_title'],
                "noticeContent1" => $conf['gg1_content'],
                "noticeTitle2" => $conf['gg2_title'],
                "noticeContent2" => $conf['gg2_content'],
                "kl" => $conf['kskl'],
            )
        );
        exit(json_encode($result));
        break;
    case 'getAppInfo': //APP信息
        $arr = array(
            'btnText1' => $conf['btnText1'],
            'btnLink1' => $conf['btnLink1'],
            'btnText2' => $conf['btnText2'],
            'btnLink2' => $conf['btnLink2'],
            'btnText3' => $conf['btnText3'],
            'btnLink3' => $conf['btnLink3'],
            'btnText4' => $conf['btnText4'],
            'btnLink4' => $conf['btnLink4'],
            'bgUrl' => $conf['bgUrl'],
            'forceUpdate' => $conf['forceUpdate'],
            'version' => $conf['version'],
            'appDown' => $conf['appDown'],
            'updateText' => $conf['updateText'],
        );
        $result = array("code" => 0, "data" => $arr);
        exit(json_encode($result));
        break;
    case 'getEwm':
        $res = $DB->get_row("select * from dwz_ewm where allNum>0 and status=1 limit 1");
        if ($res) {
            $DB->exec("update dwz_ewm set allNum=allNum-1,views=views+1 where id=:id", [':id' => $res['id']]);
            $result = array(
                'code' => 1,
                'url' => $res['url'],
            );
            exit(json_encode($result));
        } else {
            $result = array(
                'code' => -1,
                'msg' => '暂无二维码'
            );
            exit(json_encode($result));
        }
        break;
    case 'getAdsInfo':
        $id = isset($_REQUEST['id']) ? trim(daddslashes($_REQUEST['id'])) : '';
        if ($id == '') {
            $result = array("code" => -1, "msg" => 'ID不存在');
            exit(json_encode($result));
        }
        $res = $DB->get_row("select * from dwz_adv where id=:id and status=1 limit 1", [':id' => $id]);
        if ($res) {
            $redisKey = $clientip . '_' . $id;
            if ($redis->exists($redisKey)) {
                $urlScheme = $redis->get($redisKey);
            } else {
                $vres = $DB->get_row("select wxLink,status from dwz_adv_view where aid=:aid and ip=:ip limit 1", [':aid' => $id, ':ip' => $clientip]);
                if ($vres) {
                    if ($vres['status'] == 1) {
                        $urlScheme = '已解锁';
                    } else {
                        $urlScheme = $vres['wxLink'];
                    }
                    $redis->set($redisKey, $urlScheme);
                    $redis->expire($redisKey, 3600);
                } else {
                    $uid = $res['uid'];
                    $userrow = $DB->get_row("select wx_appid,wx_secret,wx_adv_id from dwz_user where uid=:uid limit 1", [':uid' => $uid]);
                    $userKey = 'user_wx_' . $uid;
                    if ($redis->exists($userKey)) {
                        $token = $redis->get($userKey);
                    } else {
                        $ret = json_decode(get_curl('https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=' . $userrow['wx_appid'] . '&secret=' . $userrow['wx_secret']), true);
                        if ($ret['access_token'] != '') {
                            $redis->set($userKey, $ret['access_token']);
                            $redis->expire($userKey, 3600);
                            $token = $ret['access_token'];
                        } else {
                            $result = array("code" => -1, "msg" => 'token获取失败');
                            exit(json_encode($result));
                        }
                    }
                    $urlScheme = getUrlScheme2($token, $id, $userrow['wx_adv_id']);
                    if ($urlScheme == "") {
                        $result = array("code" => -1, "msg" => '链接获取失败');
                        exit(json_encode($result));
                    }
                    $rs = $DB->exec("insert into dwz_adv_view(uid,aid,created,ip,wxLink) values('$uid',:id,'$date','$clientip',:wxLink)", [':id' => $id, ':wxLink' => $urlScheme]);
                    if ($rs) {
                        $redis->set($redisKey, $urlScheme);
                        $redis->expire($redisKey, 3600);
                    } else {
                        $result = array("code" => -1, "msg" => '服务器错误，请重试');
                        exit(json_encode($result));
                    }
                }
            }
            if ($urlScheme == "已解锁") {
                $status = 1;
            } else {
                $status = 0;
            }
            $DB->exec("update dwz_adv set view=view+1 where id=:id", [':id' => $id]);
            $data = [
                'id' => $id,
                'pwd' => $res['pwd'],
                'title' => $res['url'],
                'url' => $urlScheme,
                'status' => $status
            ];
            $result = array("code" => 0, "data" => $data);
            exit(json_encode($result));
        } else {
            $result = array("code" => -1, "msg" => 'ID不存在');
            exit(json_encode($result));
        }
        break;
    case 'getAdsStatus':
        $id = isset($_REQUEST['id']) ? trim(daddslashes($_REQUEST['id'])) : '';
        $redisKey = 'status_' . $clientip . '_' . $id;
        if ($redis->exists($redisKey)) {
            $status = $redis->get($redisKey);
        } else {
            $res = $DB->get_row("select status from dwz_adv_view where aid=:id and ip=:ip limit 1", [':id' => $id, ':ip' => $clientip]);
            if ($res) {
                $status = $res['status'];
                $redis->set($redisKey, $status);
                $redis->expire($redisKey, 3600);
            } else {
                $result = array("code" => -1, "msg" => '系统错误');
                exit(json_encode($result));
            }
        }
        $result = array("code" => 0, "status" => $status);
        exit(json_encode($result));
        break;
    case 'getAdvInfo':
        $id = isset($_REQUEST['id']) ? trim(daddslashes($_REQUEST['id'])) : '';
        if ($id == '') {
            $result = array("code" => -1, "msg" => 'ID不存在');
            exit(json_encode($result));
        }
        $res = $DB->get_row("select * from dwz_adv where id=:id and status=1 limit 1", [':id' => $id]);
        if ($res) {
            $data = [
                'id' => $id,
                'pwd' => $res['pwd'],
                'title' => $res['url'],
                'forceNum' => 0,
                'backgroundImage' => 'https://qqq.gtimg.cn/music/photo_new/T053M0010045Zim92SrI3T.jpg'
            ];
            $result = array("code" => 0, "data" => $data);
            exit(json_encode($result));
        }
        break;
    case 'advViewData':
        $id = isset($_REQUEST['id']) ? trim(daddslashes($_REQUEST['id'])) : '';
        $status = isset($_REQUEST['status']) ? trim(daddslashes($_REQUEST['status'])) : '';
        $redisKey = 'status_' . $clientip . '_' . $id;
        $redis->set($redisKey, $status);
        $redis->expire($redisKey, 3600);
        $res = $DB->get_row("select status from dwz_adv_view where aid=:id and ip=:ip limit 1", [':id' => $id, ':ip' => $clientip]);
        if ($res) {
            if ($res['status'] != 1) {
                $DB->exec("update dwz_adv_view set status=:status where aid=:aid and ip=:ip", [':status' => $status, ':aid' => $id, ':ip' => $clientip]);
            }
            if ($status == 1) {
                $redisKey2 = $clientip . '_' . $id;
                $redis->set($redisKey2, '已解锁');
                $redis->expire($redisKey2, 3600);
            }
        }
        break;
    case 'geturl2':
        $t = isset($_REQUEST['t']) ? trim(daddslashes($_REQUEST['t'])) : '';
        if ($t == '') {
            $url = 'https://www.baidu.com';
            $result = array(
                'code' => 1,
                'url' => $url
            );
            exit(json_encode($result));
        } else {
            $res = $DB->get_row("select uid,url,pwd,xcx_img,remarks from dwz_url where code=:t and deleted is null", [':t' => $t]);
            if (!$res) {
                $url = 'https://www.baidu.com';
                $result = array(
                    'code' => 1,
                    'url' => $url
                );
                exit(json_encode($result));
            } else {
                $userrow = $DB->get_row("select qq_link,qun_link,qun_pay from dwz_user where uid=:uid limit 1", [':uid' => $res['uid']]);
                if (!$userrow) {
                    $url = 'https://www.baidu.com';
                    $result = array(
                        'code' => 1,
                        'url' => $url
                    );
                    exit(json_encode($result));
                }
                $DB->exec("update dwz_url set views=views+1,lasted='$date' where code=:code", [':code' => $t]);
                $url = $res['url'];
                $xcx = $res['xcx_img'];
                $result = array(
                    'code' => 1,
                    'url' => $res['url'],
                    'pwd' => $res['pwd'],
                    'xcx' => $xcx,
                    'remarks' => $res['remarks'],
                    'kl' => $conf['kskl'],
                    'url2' => $userrow['qq_link'],
                    'url3' => $userrow['qun_link'],
                    'url4' => $userrow['qun_pay']
                );
                exit(json_encode($result));
            }
        }
        break;
    case 'qunView':
        $id = isset($_REQUEST['id']) ? trim(daddslashes($_REQUEST['id'])) : '';
        if ($id != '') {
            $DB->exec("update qun_list set views=views+1 where id=:id", [':id' => $id]);
        }
        $result = array(
            'code' => 0,
            'msg' => 'suc'
        );
        exit(json_encode($result));
    case 'getImgRand':
        $res = $DB->get_row("select url from img_list order by rand() limit 1");
        if ($res) {
            header("Location:" . $res['url']);
        }
        break;
}
