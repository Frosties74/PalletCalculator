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
     */
    public function boot()
    {
        /** @var PluginFlowRegistrationService $flowRegistrationService */
        $flowRegistrationService = pluginApp(
            PluginFlowRegistrationService::class
        );

        $flowRegistrationService->registerAction(
            pluginApp(CalculatePalletsAction::class)
        );
    }
}
