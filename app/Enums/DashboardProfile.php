<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Vista del dashboard. Se elige por permisos (DashboardService::resolveProfile), nunca por nombre de rol.
 */
enum DashboardProfile: string
{
    case Admin = 'admin';
    case Production = 'production';
    case Plant = 'plant';
    case Commercial = 'commercial';
    case None = 'none';
}
