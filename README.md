# 彩虹外链网盘

彩虹外链网盘，是一款PHP网盘与外链分享程序，支持所有格式文件的上传，可以生成文件外链、图片外链、音乐视频外链，生成外链同时自动生成相应的UBB代码和HTML代码，还可支持文本、图片、音乐、视频在线预览，这不仅仅是一个网盘，更是一个图床亦或是音乐在线试听网站。新版本支持对接阿里云OSS、腾讯云COS、华为云OBS、又拍云、七牛云等云存储，同时增加了图片违规检测功能。

当前仓库修改自：https://github.com/netcccyun/pan 感谢开源

### 更新日志

[CHANGELOG](./CHANGELOG.md)


### 演示地址
- https://cccimg.com/

### 部署方法

#### 方法一：传统部署

- 环境要求`PHP` >= 7.1、`MySQL` >= 5.5
- 上传后直接访问，按照提示安装
- 后台默认账号密码：admin/123456

#### 方法二：Docker 部署（推荐）

本项目已内置 `Dockerfile` 和 `docker-compose.yml`，可快速启动 PHP + Apache 环境。

**1. 克隆项目并进入目录**
```bash
git clone https://github.com/netcccyun/pan.git
cd pan
```

**2. 启动容器**
```bash
docker-compose up -d
```

默认仅启动 PHP 应用容器，数据库需自行准备（本地 MySQL、云数据库等）。

**3. 访问安装向导**
打开浏览器访问 `http://localhost:5858/install/`，按提示完成安装。

安装时数据库配置填写你自己的数据库信息即可。

---

**（可选）使用 Docker 内置 MySQL**

如果你希望一并启动 MySQL 容器，编辑 `docker-compose.yml`，取消 `db` 服务及相关注释：

```yaml
services:
  db:
    image: mysql:5.7
    ...

  app:
    ...
    depends_on:
      - db

volumes:
  db_data:
```

然后重新启动：
```bash
docker-compose down
docker-compose up -d
```

内置 MySQL 默认配置：
| 配置项 | 值 |
|---|---|
| 数据库地址 | `db`（容器内服务名） |
| 数据库端口 | `3306` |
| 数据库名 | `cccpan` |
| 用户名 | `cccpan` |
| 密码 | `cccpan_123` |

---

**常用命令**
```bash
# 查看运行状态
docker-compose ps

# 查看应用日志
docker-compose logs -f app

# 重启服务
docker-compose restart

# 停止并移除容器
docker-compose down

# 停止并移除容器及数据卷（会清空数据库）
docker-compose down -v
```

**目录挂载说明**
- `./` → `/var/www/html`：代码实时同步，本地修改立即生效
- `./assets/avatars` → `/var/www/html/assets/avatars`：头像文件持久化

**自定义端口**
如需修改映射端口，编辑 `docker-compose.yml` 中 `app` 服务的 `ports` 配置，例如改为 `8080:80`。


