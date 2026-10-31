<?php

namespace App\Services\Updates;

use RuntimeException;

/**
 * خطای قابل نمایش به مدیر (پیام فارسی، بدون جزئیات حساس).
 */
class UpdateException extends RuntimeException
{
}
