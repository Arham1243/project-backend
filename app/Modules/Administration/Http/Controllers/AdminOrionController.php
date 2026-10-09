<?php

namespace App\Modules\Administration\Http\Controllers;

use Orion\Http\Controllers\Controller as OrionController;

abstract class AdminOrionController extends OrionController
{
    /**
     * Orion's default uses the primary key (getKeyName()). Admin app URLs use the same
     * key as Laravel implicit route binding (getRouteKeyName()), e.g. uuid.
     */
    protected function keyName(): string
    {
        $resourceModelClass = $this->resolveResourceModelClass();

        return (new $resourceModelClass)->getRouteKeyName();
    }
}
