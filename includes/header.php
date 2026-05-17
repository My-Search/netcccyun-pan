<?php
@header('Content-Type: text/html; charset=UTF-8');
?><!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="renderer" content="webkit">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<title><?php echo $title?></title>
<meta name="keywords" content="<?php echo $conf['keywords']?>">
<meta name="description" content="<?php echo $conf['description']?>">
<!-- Mobile support -->
<meta name="viewport" content="width=device-width,height=device-height,inital-scale=1.0,maximum-scale=1.0,user-scalable=no;">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black">
<meta name="format-detection" content="telephone=no">
<!-- Bootstrap Material Design -->
<link href="https://s4.zstatic.net/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css" rel="stylesheet">
<link href="https://s4.zstatic.net/ajax/libs/twitter-bootstrap/3.4.1/css/bootstrap.min.css" rel="stylesheet">
<link href="https://s4.zstatic.net/ajax/libs/bootstrap-material-design/0.5.10/css/bootstrap-material-design.min.css" rel="stylesheet">
<link href="https://s4.zstatic.net/ajax/libs/bootstrap-material-design/0.5.10/css/ripples.min.css" rel="stylesheet">
<?php if($is_file){?><link rel="stylesheet" href="https://s4.zstatic.net/ajax/libs/aplayer/1.10.1/APlayer.min.css"><link href="assets/css/ckplayer.css" rel="stylesheet"><?php }?>
<link href="assets/css/style.css?v=<?php echo VERSION?>" rel="stylesheet">
<style>
/* Modern Navbar Styles */
.modern-navbar {
  background: #fff !important;
  border: none !important;
  box-shadow: 0 2px 8px rgba(0,0,0,0.06) !important;
  border-radius: 0 0 10px 10px;
  margin-bottom: 20px;
}
.modern-navbar .navbar-brand {
  color: #333 !important;
  font-weight: 600;
  font-size: 18px;
  padding: 15px 20px;
  transition: all 0.2s ease;
}
.modern-navbar .navbar-brand:hover {
  color: #4facfe !important;
  transform: translateY(-1px);
}
.modern-navbar .navbar-nav > li > a {
  color: #555 !important;
  font-weight: 500;
  padding: 15px 18px;
  transition: all 0.2s ease;
  border-radius: 8px;
  margin: 8px 4px;
}
.modern-navbar .navbar-nav > li > a:hover {
  color: #333 !important;
  background: #f8f9fa !important;
  transform: translateY(-1px);
}
.modern-navbar .navbar-nav > li > a:focus,
.modern-navbar .navbar-brand:focus,
.modern-navbar .dropdown-menu > li > a:focus {
  outline: none !important;
  box-shadow: none !important;
}
.modern-navbar .navbar-nav > li:not(.active) > a:focus {
  color: #555 !important;
  background: transparent !important;
  transform: none;
}
.modern-navbar .navbar-nav > li > a:focus-visible,
.modern-navbar .navbar-brand:focus-visible,
.modern-navbar .dropdown-menu > li > a:focus-visible {
  outline: 2px solid #4facfe !important;
  outline-offset: 2px;
}
.modern-navbar .navbar-nav > li.active > a {
  color: #4facfe !important;
  background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%) !important;
}
.modern-navbar .navbar-nav > li.active > a:hover {
  background: linear-gradient(135deg, #e0f2fe 0%, #bae6fd 100%) !important;
}
/* User Dropdown Trigger */
.modern-navbar .user-dropdown > a {
  display: flex !important;
  align-items: center;
  padding: 8px 15px !important;
  border-radius: 25px !important;
  background: #f8f9fa;
  margin: 8px 4px;
  border: 1px solid #e9ecef;
  transition: all 0.2s ease;
}
.modern-navbar .user-dropdown > a:hover {
  background: #fff !important;
  border-color: #4facfe;
  box-shadow: 0 2px 8px rgba(79,172,254,0.15);
  transform: translateY(-1px);
}
.modern-navbar .user-dropdown.open > a {
  background: #fff !important;
  border-color: #4facfe;
  box-shadow: 0 2px 8px rgba(79,172,254,0.2);
}
.modern-navbar .nav-user-avatar {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  object-fit: cover;
  margin-right: 8px;
  border: 2px solid #fff;
  box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}
.modern-navbar .nav-user-icon {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-right: 8px;
  font-size: 14px;
}
.modern-navbar .nav-username {
  font-weight: 500;
  color: #333;
  max-width: 100px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.modern-navbar .caret {
  margin-left: 6px;
  border-top-color: #666;
}
/* Modern Dropdown Menu */
.modern-navbar .dropdown-menu {
  border: none;
  border-radius: 12px;
  box-shadow: 0 4px 20px rgba(0,0,0,0.1);
  padding: 8px;
  margin-top: 8px;
  min-width: 220px;
  background: #fff;
}
.modern-navbar .dropdown-menu::before {
  content: '';
  position: absolute;
  top: -6px;
  right: 20px;
  width: 12px;
  height: 12px;
  background: #fff;
  transform: rotate(45deg);
  box-shadow: -2px -2px 4px rgba(0,0,0,0.05);
}
.modern-navbar .dropdown-menu > li > a {
  padding: 10px 16px;
  border-radius: 8px;
  color: #555;
  font-weight: 500;
  transition: all 0.15s ease;
  display: flex;
  align-items: center;
}
.modern-navbar .dropdown-menu > li > a:hover {
  background: #f8f9fa;
  color: #333;
  transform: translateX(2px);
}
.modern-navbar .dropdown-menu > li > a > i {
  margin-right: 10px;
  width: 18px;
  text-align: center;
  color: #888;
}
.modern-navbar .dropdown-menu > li > a:hover > i {
  color: #4facfe;
}
.modern-navbar .dropdown-menu .divider {
  margin: 8px 0;
  background: #f0f0f0;
}
/* User Card in Dropdown */
.modern-navbar .user-card {
  padding: 16px;
  border-radius: 10px;
  background: linear-gradient(135deg, #f8f9fa 0%, #fff 100%);
  border: 1px solid #f0f0f0;
}
.modern-navbar .user-card:hover {
  background: linear-gradient(135deg, #f8f9fa 0%, #fff 100%) !important;
}
.modern-navbar .user-card-header {
  display: flex;
  align-items: center;
  margin-bottom: 12px;
}
.modern-navbar .user-card-avatar {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  object-fit: cover;
  margin-right: 12px;
  border: 3px solid #fff;
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
.modern-navbar .user-card-initial {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
  font-weight: 600;
  margin-right: 12px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
.modern-navbar .user-card-info {
  flex: 1;
  min-width: 0;
}
.modern-navbar .user-card-name {
  font-weight: 600;
  font-size: 14px;
  color: #333;
  margin-bottom: 4px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.modern-navbar .user-card-badge {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 12px;
  font-size: 11px;
  font-weight: 500;
}
.modern-navbar .user-card-badge.qq {
  background: #e3f2fd;
  color: #1976d2;
}
.modern-navbar .user-card-badge.wx {
  background: #e8f5e9;
  color: #388e3c;
}
.modern-navbar .user-card-badge.local {
  background: #f5f5f5;
  color: #666;
}
.modern-navbar .user-card-meta {
  font-size: 12px;
  color: #888;
  border-top: 1px solid #e9ecef;
  padding-top: 10px;
}
.modern-navbar .user-card-meta > div {
  margin-bottom: 3px;
}
/* Login Button */
.modern-navbar .nav-login-btn {
  background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%) !important;
  color: #fff !important;
  border-radius: 20px !important;
  padding: 8px 20px !important;
  margin: 11px 4px;
  font-weight: 500;
  transition: all 0.2s ease;
  box-shadow: 0 2px 8px rgba(79,172,254,0.3);
  display: inline-flex;
  align-items: center;
}
.modern-navbar .nav-login-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(79,172,254,0.4);
  color: #fff !important;
}
.modern-navbar .nav-login-btn i {
  margin-right: 6px;
}
/* Mobile Toggle Button */
.modern-navbar .navbar-toggle {
  border: none;
  background: transparent;
  padding: 12px;
  margin-top: 8px;
  margin-bottom: 8px;
}
.modern-navbar .navbar-toggle:hover,
.modern-navbar .navbar-toggle:focus {
  background: #f8f9fa;
  border-radius: 8px;
}
.modern-navbar .navbar-toggle .icon-bar {
  background: #555;
  height: 2px;
  border-radius: 2px;
}
/* Desktop: align navbar items vertically */
@media (min-width: 768px) {
  .modern-navbar .navbar-collapse {
    display: flex;
    align-items: center;
  }
  .modern-navbar .navbar-nav.navbar-right {
    display: flex;
    align-items: center;
    margin-left: auto;
  }
}
/* Responsive adjustments */
@media (max-width: 767px) {
  .modern-navbar {
    border-radius: 0 0 8px 8px;
  }
  .modern-navbar .navbar-collapse {
    border-top: 1px solid #f0f0f0;
    margin-top: 8px;
    padding-top: 8px;
  }
  .modern-navbar .navbar-nav > li > a {
    margin: 4px 0;
    padding: 12px 16px;
  }
  .modern-navbar .user-dropdown > a {
    margin: 4px 0;
    border-radius: 8px !important;
  }
  .modern-navbar .nav-username {
    max-width: 200px;
  }
  .modern-navbar .dropdown-menu {
    border-radius: 8px;
    margin-top: 4px;
  }
  .modern-navbar .dropdown-menu::before {
    display: none;
  }
  .modern-navbar .nav-login-btn {
    margin: 8px 16px;
    text-align: center;
    display: block;
  }
}
/* Preserve original user-dropdown hover behavior */
.user-dropdown:hover .user-dropdown-menu { display: block; }
.user-dropdown-menu { margin-top: 0; }
.user-dropdown-menu .user-card:hover { background: transparent; cursor: default; }
</style>
<!--[if lt IE 9]>
<script src="https://s4.zstatic.net/ajax/libs/html5shiv/3.7.3/html5shiv.min.js"></script>
<script src="https://s4.zstatic.net/ajax/libs/respond.js/1.4.2/respond.min.js"></script>
<![endif]-->
<script type="text/javascript" src="https://s4.zstatic.net/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
</head>
<body>

<nav class="navbar navbar-default modern-navbar">
  <div class="container">
    <div class="navbar-header">
      <button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".navbar-responsive-collapse">
        <span class="icon-bar"></span>
        <span class="icon-bar"></span>
        <span class="icon-bar"></span>
      </button>
      <a class="navbar-brand" href="./"><?php echo $conf['title']?></a>
    </div>
    <div class="navbar-collapse collapse navbar-responsive-collapse">
      <ul class="nav navbar-nav">
        <?php if($is_file){?>
        <li class="<?php echo checkIfActive('file')?>"><a href=""><i class="fa fa-file" aria-hidden="true"></i> 文件查看</a></li>
        <?php }?>
      </ul>
      <ul class="nav navbar-nav navbar-right">
        <li class="<?php echo checkIfActive('index')?>"><a href="./"><i class="fa fa-home" aria-hidden="true"></i> 首页</a></li>
        <li class="<?php echo checkIfActive('mine')?>"><a href="./mine.php"><i class="fa fa-folder-open" aria-hidden="true"></i> 我的文件</a></li>
        <?php if($conf['userlogin']){?>
        <?php if($islogin2){?>
        <li class="dropdown user-dropdown">
          <a data-target="#" class="dropdown-toggle" data-toggle="dropdown">
            <?php if(!empty($userrow['faceimg']) && (strpos($userrow['faceimg'],'http')===0 || strpos($userrow['faceimg'],'/')===0 || strpos($userrow['faceimg'],'assets/')===0 || strpos($userrow['faceimg'],'data:')===0)){?>
            <img class="nav-user-avatar" src="<?php echo (strpos($userrow['faceimg'],'http')===0||strpos($userrow['faceimg'],'data:')===0)?$userrow['faceimg']:'./'.$userrow['faceimg'];?>">
            <i id="navUserIcon" class="fa fa-<?php if($userrow['type']=='qq')echo 'qq';elseif($userrow['type']=='wx')echo 'wechat';else echo 'user';?>" aria-hidden="true" style="display:none;"></i>
            <?php }else{?>
            <img id="navUserAvatar" class="nav-user-avatar" src="" style="display:none;">
            <div class="nav-user-icon"><i class="fa fa-<?php if($userrow['type']=='qq')echo 'qq';elseif($userrow['type']=='wx')echo 'wechat';else echo 'user';?>" aria-hidden="true"></i></div>
            <?php }?>
            <span class="nav-username"><?php echo htmlspecialchars($userrow['nickname'])?></span>
            <span class="caret"></span>
          </a>
          <ul class="dropdown-menu user-dropdown-menu">
            <li class="user-card">
              <div class="user-card-header">
                <?php if(!empty($userrow['faceimg']) && (strpos($userrow['faceimg'],'http')===0 || strpos($userrow['faceimg'],'/')===0 || strpos($userrow['faceimg'],'assets/')===0 || strpos($userrow['faceimg'],'data:')===0)){?>
                <img class="user-card-avatar" src="<?php echo (strpos($userrow['faceimg'],'http')===0||strpos($userrow['faceimg'],'data:')===0)?$userrow['faceimg']:'./'.$userrow['faceimg'];?>">
                <?php }else{?>
                <div class="user-card-initial"><?php echo mb_substr($userrow['nickname'],0,1)?></div>
                <?php }?>
                <div class="user-card-info">
                  <div class="user-card-name"><?php echo htmlspecialchars($userrow['nickname'])?></div>
                  <?php if($userrow['type']=='qq'){?><span class="user-card-badge qq"><i class="fa fa-qq" style="margin-right:4px;"></i>QQ登录</span><?php }elseif($userrow['type']=='wx'){?><span class="user-card-badge wx"><i class="fa fa-wechat" style="margin-right:4px;"></i>微信登录</span><?php }else{?><span class="user-card-badge local"><i class="fa fa-user" style="margin-right:4px;"></i>本地账号</span><?php }?>
                </div>
              </div>
              <div class="user-card-meta">
                <div><i class="fa fa-id-card-o" style="margin-right:6px; width:14px;"></i>UID: <?php echo $userrow['uid']?></div>
                <div><i class="fa fa-clock-o" style="margin-right:6px; width:14px;"></i>注册时间: <?php echo $userrow['addtime']?></div>
              </div>
            </li>
            <li class="divider"></li>
            <li><a href="./profile.php"><i class="fa fa-user" aria-hidden="true"></i> 个人信息</a></li>
            <li><a href="./login.php?logout=1" onclick="return confirm('是否确定退出登录？')"><i class="fa fa-sign-out" aria-hidden="true"></i> 退出登录</a></li>
          </ul>
        </li>
        <?php }else{?>
        <li class="<?php echo checkIfActive('login')?>"><a href="./login.php" class="nav-login-btn"><i class="fa fa-user-circle" aria-hidden="true"></i> 未登录</a></li>
        <?php }?>
        <?php }?>
      </ul>
    </div>
  </div>
</nav>
