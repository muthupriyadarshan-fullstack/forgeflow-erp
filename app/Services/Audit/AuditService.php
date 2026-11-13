<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditService
{
    public static function log(
        string $event,
        Model $model,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?int $companyId = null,
        ?int $userId = null
    ): AuditLog {
        $user = Auth::user();
        $uId = $userId ?? ($user ? $user->id : null);
        $cId = $companyId ?? ($model->company_id ?? ($user ? $user->company_id : null));

        return AuditLog::create([
            'company_id' => $cId,
            'user_id' => $uId,
            'event' => $event,
            'auditable_type' => get_class($model),
            'auditable_id' => $model->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => Request::ip() ?? '127.0.0.1',
            'user_agent' => substr(Request::userAgent() ?? 'ForgeFlow CLI / API', 0, 255),
            'created_at' => now(),
        ]);
    }
}

