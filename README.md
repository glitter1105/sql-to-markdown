# 数据库表结构转 Markdown 工具

本项目可以将多种数据库的表结构转换为 Markdown 文档。

## 支持的数据库类型

- MySQL
- PostgreSQL
- SQLite

## 使用方法

1. **配置数据库信息:**
    修改 `config.php` 文件，填写正确的数据库连接信息。

    ```php
    <?php

    return [
        'type' => 'mysql', // 支持的类型：mysql, postgresql, sqlite
        'db_host' => 'localhost',
        'db_port' => '3306',
        'db_name' => 'your_database_name',
        'db_user' => 'your_username',
        'db_password' => 'your_password',
        // SQLite 专用配置
        'sqlite_path' => 'path/to/database.sqlite',
    ];
    ```

2. **运行转换脚本:**
    在命令行中执行以下命令：

    ```bash
    # 显示帮助信息
    php convert.php --help
    
    # 转换 MySQL 数据库结构
    php convert.php -t mysql -o mysql_tables.md
    
    # 转换 PostgreSQL 数据库结构
    php convert.php -t postgresql -o pg_tables.md
    
    # 转换 SQLite 数据库结构
    php convert.php -t sqlite -o sqlite_tables.md
    ```

3. **查看结果:**
    脚本运行完成后，会在当前目录下生成一个名为 `table_structure.md` 的 Markdown 文件，其中包含所有表的结构信息。

## 文件说明

-   `config.php`: 数据库配置文件。
-   `convert.php`: 将 MySQL 表结构转换为 Markdown 的主程序。
-   `README.md`: 项目说明文档。
-   `table_structure.md`: 生成的 Markdown 文档。

## 注意事项

-   确保 PHP 环境已安装并配置好 PDO 扩展。
-   请根据实际情况修改 `config.php` 中的数据库连接信息。 