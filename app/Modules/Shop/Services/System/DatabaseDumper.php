<?php

namespace App\Modules\Shop\Services\System;

/**
 * 数据库备份/还原执行器：生产用 mysqldump/mysql 客户端，
 * 测试可替换为内存实现，业务层不直接依赖外部命令。
 */
interface DatabaseDumper
{
    /** 把当前数据库导出为 SQL 文件（写入 $path）。 */
    public function dump(string $path): void;

    /** 用 SQL 文件覆盖还原当前数据库。 */
    public function restore(string $path): void;
}
