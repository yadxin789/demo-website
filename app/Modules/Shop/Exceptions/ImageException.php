<?php

namespace App\Modules\Shop\Exceptions;

use RuntimeException;

/**
 * 图片上传/处理异常：类型不合法、体积超限、损坏文件等。
 * 消息为可直接展示给用户的中文提示。
 */
class ImageException extends RuntimeException
{
}
