<?php

declare(strict_types=1);

namespace App\Modules\Shop\Services\System;

use App\Modules\Shop\Exceptions\BackupException;
use PDO;

/**
 * SQLite 版备份执行器（演示站专用）。
 *
 * 生产环境源项目用 mysqldump/mysql；本演示站五库都是 SQLite，
 * 因此用 PDO 导出成 SQL 文本（结构与数据），接口与 MysqlDumper 完全一致，
 * 备份包里的 database.sql、sha256 校验、还原流程都不变。
 */
class SqliteDumper implements DatabaseDumper
{
    public function dump(string $path): void
    {
        $pdo = $this->connect();
        $out = "-- demo-website shop backup " . now()->toDateTimeString() . "\n";

        $tables = $pdo->query(
            "select name, sql from sqlite_master where type='table' and name not like 'sqlite_%' order by name"
        )->fetchAll(PDO::FETCH_ASSOC);

        foreach ($tables as $table) {
            $name = $table['name'];
            $out .= "\n-- table {$name}\n";
            $out .= "DROP TABLE IF EXISTS \"{$name}\";\n";
            $out .= rtrim((string) $table['sql'], ';') . ";\n";

            $rows = $pdo->query("select * from \"{$name}\"")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $cols = [];
                $vals = [];
                foreach ($row as $col => $val) {
                    $cols[] = '"' . $col . '"';
                    $vals[] = $val === null ? 'NULL' : $pdo->quote((string) $val);
                }
                $out .= sprintf("INSERT INTO \"%s\" (%s) VALUES (%s);\n", $name, implode(', ', $cols), implode(', ', $vals));
            }
        }

        if (@file_put_contents($path, $out) === false) {
            throw new BackupException('SQLite 备份写入失败：' . $path);
        }
    }

    public function restore(string $path): void
    {
        if (! is_file($path)) {
            throw new BackupException('备份文件不存在：' . $path);
        }

        $sql = (string) file_get_contents($path);
        $pdo = $this->connect();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // 简易按分号切分：本项目的建表/插入语句不含跨行分号字面量，够用
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
            if ($stmt === '' || str_starts_with($stmt, '--')) {
                continue;
            }
            $pdo->exec($stmt);
        }
    }

    private function connect(): PDO
    {
        $file = (string) config('database.connections.shop.database');
        if (! is_file($file)) {
            throw new BackupException('SQLite 数据库文件不存在：' . $file);
        }

        $pdo = new PDO('sqlite:' . $file);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return $pdo;
    }
}
