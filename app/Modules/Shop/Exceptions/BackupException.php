<?php

namespace App\Modules\Shop\Exceptions;

use RuntimeException;

/**
 * 备份/还原过程中的可读业务错误（中文可直接展示给用户）。
 */
class BackupException extends RuntimeException
{
}
