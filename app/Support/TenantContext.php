<?php

namespace App\Support;

class TenantContext
{
    protected static ?string $tenantId = null;

    public static function set(?string $tenantId): void
    {
        self::$tenantId = $tenantId;
    }

    public static function id(): ?string
    {
        return self::$tenantId;
    }
}

