<?php

namespace PalletCalculator\Providers;

use Plenty\Plugin\ServiceProvider;
use Plenty\Modules\Flow\Contracts\PluginFlowRegistrationService;

use PalletCalculator\Flow\CalculatePalletsAction;

class PalletCalculatorServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register()
    {
    }

    /**
     * Register the PlentyFlow action.
     *
     * @param PluginFlowRegistrationService $flowRegistrationService
     */
    public function boot(
        PluginFlowRegistrationService $flowRegistrationService
    )
    {
        $flowRegistrationService->registerAction(
            pluginApp(CalculatePalletsAction::class)
        );
    }
}
