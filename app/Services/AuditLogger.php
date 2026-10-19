<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;

/** Writes important actions to the audit_logs table. */
class AuditLogger
{
    public function log(string $action, string $description, array $meta = [], ?User $user = null): void
    {
        $request = request();

        AuditLog::create([
            'user_id' => ($user ?? $request->user())?->id,
            'action' => $action,
            'description' => $description,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
            'meta' => $meta ?: null,
        ]);
    }
}
