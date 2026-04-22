# 基于 PHP 7.4 + Apache
FROM php:7.4-apache

# 替换为国内 apt 镜像，加速构建
RUN sed -i 's|http://deb.debian.org|http://mirrors.aliyun.com|g' /etc/apt/sources.list && \
    sed -i 's|http://security.debian.org|http://mirrors.aliyun.com|g' /etc/apt/sources.list

# 安装必要的系统依赖和 PHP 扩展
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd \
    && docker-php-ext-install pdo pdo_mysql mysqli zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# 启用 Apache rewrite 模块（如需伪静态）
RUN a2enmod rewrite

# 设置工作目录
WORKDIR /var/www/html

# 复制项目文件到容器
COPY . /var/www/html/

# 设置目录权限（允许写入 config.php 和创建 install.lock）
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# 暴露 80 端口
EXPOSE 80
