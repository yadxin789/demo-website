<?php

namespace App\Modules\Shop\Exceptions;

use RuntimeException;

/**
 * CSV 导入异常：文件格式/编码/行列结构问题，消息可直接展示给用户。
 */
class ImportException extends RuntimeException
{
}
