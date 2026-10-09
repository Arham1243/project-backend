<?php

namespace App\Support\Notifications;

/**
 * In-app notification target for a tenant QMS user (no central users row).
 */
class TenantNotificationRecipient
{
    public function __construct(
        public int $id,
        public ?string $name = null,
        public ?int $companyId = null,
    ) {}

    public function getKey(): int
    {
        return $this->id;
    }
}
