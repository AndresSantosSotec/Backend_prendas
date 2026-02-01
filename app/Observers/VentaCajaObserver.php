<?php

namespace App\Observers;

use App\Models\Venta;

class VentaCajaObserver
{
    /**
     * Handle the Venta "created" event.
     */
    public function created(Venta $venta): void
    {
        //
    }

    /**
     * Handle the Venta "updated" event.
     */
    public function updated(Venta $venta): void
    {
        //
    }

    /**
     * Handle the Venta "deleted" event.
     */
    public function deleted(Venta $venta): void
    {
        //
    }

    /**
     * Handle the Venta "restored" event.
     */
    public function restored(Venta $venta): void
    {
        //
    }

    /**
     * Handle the Venta "force deleted" event.
     */
    public function forceDeleted(Venta $venta): void
    {
        //
    }
}
