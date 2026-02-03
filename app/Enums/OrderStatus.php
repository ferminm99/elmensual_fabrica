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
    case Standby = 'standby';
    case Dispatched = 'dispatched'; // Este será "Cargado en Viajante"
    // Removemos Delivered del Enum si no lo vas a usar, o lo dejamos pero sin label duplicado
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Draft => 'Borrador / Cargando',
            self::Processing => 'Para Armar (Depósito)',
            self::Assembled => 'Armado / Listo',
            self::Checked => 'Verificado (Facturado)',
            self::Standby => 'En Standby',
            self::Dispatched => 'Cargado en Viajante',
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
            self::Standby => 'warning',
            self::Dispatched => 'success',
            self::Paid => 'success',
            self::Cancelled => 'danger',
        };
    }
}