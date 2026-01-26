<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum OrderStatus: string implements HasLabel, HasColor
{
    case Draft = 'draft';
    case Processing = 'processing';
    case Assembled = 'assembled';
    case Checked = 'checked';
    case Standby = 'standby'; // <--- NUEVO: Esperando al hijo para consolidar
    case Dispatched = 'dispatched';
    case Delivered = 'delivered';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Draft => 'Borrador / Cargando',
            self::Processing => 'Para Armar (Depósito)',
            self::Assembled => 'Armado / Listo',
            self::Checked => 'Verificado',
            self::Standby => 'En Standby (Esperando Consolidación)', // <--- Label
            self::Dispatched => 'Enviado (En camino)',
            self::Delivered => 'Entregado (A Cobrar)',
            self::Paid => 'Pagado / Cerrado',
            self::Cancelled => 'Cancelado',
        };
    }

    public function getColor(): string | array | null
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Processing => 'warning',
            self::Assembled => 'info',
            self::Checked => 'primary',
            self::Standby => 'warning', // <--- Color de alerta suave
            self::Dispatched => 'gray',
            self::Delivered => 'danger',
            self::Paid => 'success',
            self::Cancelled => 'danger',
        };
    }
}