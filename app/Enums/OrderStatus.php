<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum OrderStatus: string implements HasLabel, HasColor
{
    case Draft = 'draft';           // Borrador
    case Processing = 'processing'; // Para Armar
    case Assembled = 'assembled';   // Armado
    case Checked = 'checked';       // Verificado
    case Dispatched = 'dispatched'; // Enviado (En camino)
    case Cancelled = 'cancelled';   // Cancelado

    // --- AGREGAMOS ESTOS DOS PARA QUE FUNCIONE EL COBRO ---
    case Delivered = 'delivered';   // Entregado (Acá se genera la deuda)
    case Paid = 'paid';             // Pagado (Deuda saldada)

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Draft => 'Borrador / Cargando',
            self::Processing => 'Para Armar (Depósito)',
            self::Assembled => 'Armado / Listo',
            self::Checked => 'Verificado (Listo p/ Enviar)',
            self::Dispatched => 'Enviado (En transito)',
            self::Cancelled => 'Cancelado',
            // Etiquetas nuevas
            self::Delivered => 'Entregado (A Cobrar)',
            self::Paid => 'Pagado / Cerrado',
        };
    }

    public function getColor(): string | array | null
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Processing => 'warning', // Naranja
            self::Assembled => 'info',     // Azul
            self::Checked => 'primary',    // Violeta
            self::Dispatched => 'gray',    // Gris oscuro
            self::Cancelled => 'danger',   // Rojo
            // Colores nuevos
            self::Delivered => 'danger',   // Rojo (Importante: Indica deuda pendiente)
            self::Paid => 'success',       // Verde (Finalizado ok)
        };
    }
}