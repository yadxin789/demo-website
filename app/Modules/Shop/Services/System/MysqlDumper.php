<?php

namespace App\Modules\Shop\Services\System;

use App\Modules\Shop\Exceptions\BackupException;
use Symfony\Component\Process\Process;

/**
 * 基于 MySQL 官方客户端的备份执行器（离线本地部署，无第三方库）。
 * 口令通过 MYSQL_PWD 环境变量传入，不出现在命令行参数与进程列表里。
 */
class MysqlDumper implements DatabaseDumper
{
    public function dump(string $path): void
    {
        $connection = $this->connection();

        $command = [
            (string) config('shop.backup.mysqldump', 'mysqldump'),
            '--host=' . $connection['host'],
            '--port=' . $connection['port'],
            '--user=' . $connection['username'],
            '--single-transaction',
            '--quick',
            '--default-character-set=utf8mb4',
            '--routines',
            '--events',
            '--result-file=' . $path,
            $connection['database'],
        ];

        $this->run($command, ['MYSQL_PWD' => (string) $connection['password']], '数据库导出失败');
    }

    public function restore(string $path): void
    {
        $connection = $this->connection();

        $command = [
            (string) config('shop.backup.mysql', 'mysql'),
            '--host=' . $connection['host'],
            '--port=' . $connection['port'],
            '--user=' . $connection['username'],
            '--default-character-set=utf8mb4',
            $connection['database'],
        ];

        $this->run(
            $command,
            ['MYSQL_PWD' => (string) $connection['password']],
            '数据库还原失败',
            fopen($path, 'r') ?: throw new BackupException('无法读取备份 SQL 文件')
        );
    }

    /** @return array<string, mixed> */
    private function connection(): array
    {
        $connection = config('database.connections.mysql');

        if (! is_array($connection)) {
            throw new BackupException('未配置 MySQL 连接，无法备份数据库');
        }

        return $connection;
    }

    /**
     * @param  array<int, string>  $command
     * @param  array<string, string>  $env
     */
    private function run(array $command, array $env, string $failMessage, mixed $input = null): void
    {
        $process = new Process($command, base_path(), $env, null, 600);
        $process->setInput($input);

        try {
            $process->run();
        } catch (\Throwable $e) {
            throw new BackupException($failMessage . '：' . $e->getMessage());
        }

        if (! $process->isSuccessful()) {
            $error = trim($process->getErrorOutput()) ?: trim($process->getOutput());

            throw new BackupException($failMessage . ($error !== '' ? '：' . $error : '，请检查 MySQL 客户端路径与账号'));
        }
    }
}
