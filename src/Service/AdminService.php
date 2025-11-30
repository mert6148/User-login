<?php

namespace App\Service;

class AdminService
{
    public function getSiteStats(): array
    {
        return [
            'total_users' => 120,
            'active_sessions' => 8,
            'orders_today' => 24
        ];
    }
}
