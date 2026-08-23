<?php

use App\Platform\Features\TechnicalFeatures;
use Laravel\Pennant\Feature;

beforeEach(function () {
    TechnicalFeatures::register();
});

test('pennant feature flags are registered with default values', function () {
    expect(Feature::active('async-bulk-export'))->toBeTrue()
        ->and(Feature::active('ai-anamnesis-assistant'))->toBeFalse()
        ->and(Feature::active('advanced-3d-odontogram'))->toBeFalse();
});

test('feature flags can be dynamically overridden for testing rollouts', function () {
    Feature::activate('ai-anamnesis-assistant');

    expect(Feature::active('ai-anamnesis-assistant'))->toBeTrue();

    Feature::deactivate('ai-anamnesis-assistant');

    expect(Feature::active('ai-anamnesis-assistant'))->toBeFalse();
});
