<?php

namespace App\Enums;

enum MembershipRole: string
{
    case CLIENT_ADMIN = 'client_admin';
    case APPROVER = 'approver';
    case REQUESTER = 'requester';

    public function label(): string
    {
        return match ($this) {
            self::CLIENT_ADMIN => 'Administrador del cliente',
            self::APPROVER => 'Aprobador',
            self::REQUESTER => 'Solicitante',
        };
    }

    public function canManage(): bool
    {
        return $this === self::CLIENT_ADMIN;
    }

    public function canApprove(): bool
    {
        return $this === self::APPROVER;
    }

    public function canRequest(): bool
    {
        return $this === self::APPROVER || $this === self::REQUESTER;
    }
}
