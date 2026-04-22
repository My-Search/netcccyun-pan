# 🌈 彩虹外链网盘 (Rainbow Cloud Storage)

**彩虹外链网盘** 是一款功能强大的 PHP 网盘与外链分享系统。它不仅是一个高兼容性的文件存储器，更是集 **图床、音乐播放、视频试听** 为一体的综合多媒体分发平台。

<img width="1141" height="719" alt="image" src="https://github.com/user-attachments/assets/348ae6f2-2e25-4342-a153-1af24cfeacc2" />

### ✨ 核心特性

  * **全格式支持**：支持任意格式文件上传及外链生成。
  * **代码自动生成**：自动生成 UBB、HTML 等引用代码，方便直接插入论坛或网页。
  * **在线预览**：支持文本、图片、音乐、视频的实时在线预览。
  * **云存储对接**：完美适配阿里云 OSS、腾讯云 COS、华为云 OBS、又拍云、七牛云等。
  * **内容安全**：内置图片违规检测功能，保障存储内容的合规性。

> [\!TIP]
> 本仓库基于开源项目 [netcccyun/pan](https://github.com/netcccyun/pan) 进行二次开发与优化，感谢原作者的无私奉献。

-----

### 📅 更新日志

详细版本演进请查看：[CHANGELOG.md](https://www.google.com/search?q=./CHANGELOG.md)

-----

### 🚀 部署指南

#### 方案一：Docker 部署（推荐）

本项目已内置 `Dockerfile` 与 `docker-compose.yml`，支持一键实现 PHP + Apache 环境容器化。

**1. 克隆仓库**

```bash
git clone https://github.com/netcccyun/pan.git
cd pan
```

**2. 启动服务**

```bash
# 方式 A：仅启动应用（连接外部数据库）
docker-compose up -d

# 方式 B：完全体启动（包含内置 MySQL 容器）
# 请先编辑 docker-compose.yml，取消 db 服务相关注释
docker-compose up -d
```

**3. 执行安装**
访问 `http://localhost:5858/install/`，按照页面引导完成初始化。

| 内置 MySQL 配置项 | 默认值 |
| :--- | :--- |
| **数据库地址** | `db` (容器内互联名) |
| **数据库端口** | `3306` |
| **数据库名** | `cccpan` |
| **用户名** | `cccpan` |
| **密码** | `cccpan_123` |

-----

#### 方案二：传统环境部署

  * **环境要求**：`PHP` \>= 7.1 / `MySQL` \>= 5.5
  * **安装步骤**：
    1.  将源码上传至 Web 根目录。
    2.  设置网站运行目录为程序所在目录。
    3.  直接访问域名，进入安装向导。
  * **初始凭据**：
      * 后台地址：`/admin`
      * 默认账号：`admin`
      * 默认密码：`123456`

-----

### 🛠️ 运维与配置

#### 目录挂载与持久化

  * `./` → `/var/www/html`：代码挂载，支持实时修改。
  * `./assets/avatars`：持久化存储用户头像。
  * **自定义端口**：如需修改端口，请编辑 `docker-compose.yml` 中的 `ports` 段（例如 `8080:80`）。

-----

### 📜 开源协议

本项目遵循原作者的开源协议，请在遵守相关法律法规的前提下使用。
