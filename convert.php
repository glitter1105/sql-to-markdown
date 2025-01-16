<?php

// 显示帮助信息的函数
function showHelp() {
    echo <<<HELP
数据库表结构转 Markdown 工具

用法：
    php convert.php [选项]

选项：
    -h, --help      显示帮助信息
    -t, --type      指定数据库类型 (mysql/postgresql/sqlite)
    -o, --output    指定输出文件名 (默认: table_structure.md)

示例：
    php convert.php --type mysql
    php convert.php -t postgresql -o pg_tables.md
    php convert.php -t sqlite -o sqlite_tables.md

支持的数据库类型：
    - mysql
    - postgresql
    - sqlite
HELP;
    exit(0);
}

// 显示错误信息并退出
function showError($message) {
    echo "\033[31m错误: {$message}\033[0m\n";
    echo "使用 --help 参数查看帮助信息\n";
    exit(1);
}

// 处理命令行参数
$options = getopt('t:o:h', ['type:', 'output:', 'help']);

// 显示帮助信息
if ($argc === 1 || isset($options['h']) || isset($options['help'])) {
    showHelp();
}

// 设置默认输出文件名
$outputFile = 'table_structure.md';

// 如果指定了输出文件名，则使用指定的文件名
if (isset($options['o'])) {
    $outputFile = $options['o'];
} elseif (isset($options['output'])) {
    $outputFile = $options['output'];
}

// 加载配置
if (!file_exists('config.php')) {
    showError('配置文件 config.php 不存在');
}

$config = require 'config.php';

// 处理数据库类型
$validTypes = ['mysql', 'postgresql', 'sqlite'];
if (isset($options['t'])) {
    $config['type'] = $options['t'];
} elseif (isset($options['type'])) {
    $config['type'] = $options['type'];
}

if (!in_array($config['type'], $validTypes)) {
    showError("不支持的数据库类型：{$config['type']}\n支持的类型：" . implode(', ', $validTypes));
}

// 创建数据库连接
function createConnection($config) {
    try {
        $pdo = null;
        switch ($config['type']) {
            case 'mysql':
                $pdo = new PDO(
                    "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']}",
                    $config['db_user'],
                    $config['db_password'],
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );
                break;
            case 'postgresql':
                $pdo = new PDO(
                    "pgsql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']}",
                    $config['db_user'],
                    $config['db_password'],
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );
                break;
            case 'sqlite':
                if (!file_exists($config['sqlite_path'])) {
                    showError("SQLite 数据库文件不存在：{$config['sqlite_path']}");
                }
                $pdo = new PDO(
                    "sqlite:{$config['sqlite_path']}",
                    null,
                    null,
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );
                break;
        }
        
        // 测试连接
        $pdo->query('SELECT 1');
        return $pdo;
        
    } catch (PDOException $e) {
        showError("数据库连接失败: " . $e->getMessage() . "\n请检查配置文件中的连接信息是否正确。");
    }
}

// 获取表名的查询
function getTablesQuery($type) {
    switch ($type) {
        case 'mysql':
            return "SHOW TABLES";
        case 'postgresql':
            return "SELECT tablename FROM pg_tables WHERE schemaname = 'public'";
        case 'sqlite':
            return "SELECT name FROM sqlite_master WHERE type='table'";
    }
}

// 获取表结构的查询
function getTableStructureQuery($type, $table) {
    switch ($type) {
        case 'mysql':
            return "SHOW FULL COLUMNS FROM `{$table}`";
        case 'postgresql':
            return "SELECT 
                    column_name as Field,
                    data_type as Type,
                    (CASE WHEN pk.column_name IS NOT NULL THEN 'PRI' ELSE '' END) as Key,
                    column_default as Default,
                    '' as Extra,
                    col_description((table_schema||'.'||table_name)::regclass::oid, ordinal_position) as Comment
                FROM information_schema.columns c
                LEFT JOIN (
                    SELECT ku.column_name
                    FROM information_schema.table_constraints tc
                    JOIN information_schema.key_column_usage ku
                        ON tc.constraint_name = ku.constraint_name
                    WHERE tc.constraint_type = 'PRIMARY KEY'
                        AND tc.table_name = '{$table}'
                ) pk ON c.column_name = pk.column_name
                WHERE table_name = '{$table}'";
        case 'sqlite':
            return "PRAGMA table_info('{$table}')";
    }
}

try {
    // 创建数据库连接
    $pdo = createConnection($config);
    
    // 获取所有表名
    $stmt = $pdo->query(getTablesQuery($config['type']));
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($tables)) {
        showError("数据库中没有找到任何表");
    }

    // Markdown 文档内容
    $markdown = "# 数据库表结构\n\n";

    // 遍历每个表
    foreach ($tables as $table) {
        $markdown .= "## 表名：`{$table}`\n\n";
        $markdown .= "| 字段名 | 类型 | 键 | 默认值 | 额外 | 注释 |\n";
        $markdown .= "|---|---|---|---|---|---|\n";

        // 获取表结构
        $stmt = $pdo->query(getTableStructureQuery($config['type'], $table));
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($columns as $column) {
            $markdown .= "| `{$column['Field']}` | `{$column['Type']}` | `{$column['Key']}` | `{$column['Default']}` | `{$column['Extra']}` | `{$column['Comment']}` |\n";
        }

        $markdown .= "\n";
    }

    // 检查文件是否存在
    // 检查文件名是否以.md结尾，如果不是，则自动加上.md结尾
    if (!preg_match('/\.md$/', $outputFile)) {
        $outputFile = $outputFile . '.md';
    }
    if (file_exists($outputFile)) {
        // 文件存在，询问是否覆盖
        echo "文件 {$outputFile} 已经存在，是否覆盖？(y/n): ";
        $answer = trim(fgets(STDIN));
        if (strtolower($answer) !== 'y') {
            // 如果不覆盖，则自动追加编号
            $i = 1;
            $baseName = preg_replace('/\.\w+$/', '', $outputFile);
            $newFileName = $baseName . "({$i})" . '.md';
            while (file_exists($newFileName)) {
                $i++;
                $newFileName = $baseName . "({$i})" . '.md';
            }
            $outputFile = $newFileName;
            echo "文件已重命名为 {$outputFile}。\n";
        }
    }

    // 输出 Markdown 内容到文件
    if (file_put_contents($outputFile, $markdown) === false) {
        showError("无法写入输出文件：{$outputFile}");
    } else {
        echo "\033[32m表结构已成功转换为 Markdown 文档：{$outputFile}\033[0m\n";
    }

} catch (Exception $e) {
    showError($e->getMessage());
}