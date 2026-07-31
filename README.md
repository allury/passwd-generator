# 安全密码生成器

一个无需数据库和外部依赖的单文件 PHP 密码生成工具。

[![Quality](https://github.com/allury/passwd-generator/actions/workflows/quality.yml/badge.svg)](https://github.com/allury/passwd-generator/actions/workflows/quality.yml)
![PHP](https://img.shields.io/badge/PHP-8.0%2B-777BB4?style=flat&logo=php)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

## 在线演示

👉 [立即体验](https://tool.706632.xyz/passwd.php)

在线演示用于体验功能，涉及敏感用途时建议自行部署并通过 HTTPS 访问。

## 主要功能

- **加密安全生成**：使用 `random_int()` 生成高强度随机密码。
- **智能过滤**：默认过滤易混淆字符（`0O1lI`），并避免连续重复字符。
- **强制复杂度**：确保每种选中的字符类型至少出现一次。
- **实时交互**：修改选项后自动重新生成密码。
- **密码强度评估**：实时显示密码强度。
- **一键复制**：快速复制生成的密码。
- **主题切换**：支持浅色与深色主题，并保存用户偏好。
- **优雅降级**：禁用 JavaScript 时仍可使用。

## 环境要求

- PHP 8.0 或更高版本。
- Apache、Nginx、Caddy 等 Web 服务器，或 PHP 内置开发服务器。
- 现代浏览器。

## 快速开始

```bash
git clone https://github.com/allury/passwd-generator.git
cd passwd-generator
php -S 127.0.0.1:8080
```

浏览器访问 `http://127.0.0.1:8080/passwd.php`。

生产环境部署只需将 `passwd.php` 上传到支持 PHP 的站点目录，然后通过浏览器访问。项目无需安装依赖或配置数据库。

## 实现说明

### 后端

- 使用 `random_int()` 生成加密安全随机数。
- 默认排除相似字符并避免连续重复字符。
- 自动保证所选字符类型的多样性。
- 使用 Fisher–Yates 算法完成最终打乱。

### 前端

- 原生 HTML、CSS 和 JavaScript，无前端框架依赖。
- 支持响应式布局和浅色、深色主题。
- 支持 AJAX 实时生成与普通表单降级。
- 输出使用 `htmlspecialchars()` 编码。

## 项目结构

```text
.
├── passwd.php       # 完整应用
├── README.md        # 使用说明
├── CONTRIBUTING.md  # 贡献指南
├── SECURITY.md      # 安全报告方式
└── LICENSE          # MIT 许可证
```

## 开发与验证

提交前执行 PHP 语法检查：

```bash
php -l passwd.php
```

详细流程见 [贡献指南](CONTRIBUTING.md)。安全问题请按 [安全政策](SECURITY.md) 私下报告。

## 许可证

本项目基于 [MIT License](LICENSE) 开源。
