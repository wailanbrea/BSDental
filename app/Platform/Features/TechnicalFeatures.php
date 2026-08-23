<?php

namespace App\Platform\Features;

use Laravel\Pennant\Feature;

class TechnicalFeatures
{
    /**
     * Register technical feature flags with Pennant.
     */
    public static function register(): void
    {
        Feature::define('ai-anamnesis-assistant', fn () => false);
        Feature::define('advanced-3d-odontogram', fn () => false);
        Feature::define('async-bulk-export', fn () => true);
    }
}
