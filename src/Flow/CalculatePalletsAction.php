<?php

namespace PalletCalculator\Flow;

use Plenty\Modules\Flow\Models\Filter;
use Plenty\Modules\Flow\Models\Output;
use Plenty\Modules\Flow\StepActions\Definitions\Models\Plugin\PluginFlowStepActionDefinition;

use PalletCalculator\Services\PalletCalculatorService;
use PalletCalculator\Services\PalletOrderPropertyService;

class CalculatePalletsAction extends PluginFlowStepActionDefinition
{
    public const IDENTIFIER =
        'PalletCalculator::calculatePallets';


    /**
     * Unique identifier.
     *
     * @return string
     */
    public function getIdentifier(): string
    {
        return self::IDENTIFIER;
    }


    /**
     * Icon used in the flow path.
     *
     * @return string
     */
    public function getPathIcon(): string
    {
        return 'shipping';
    }


    /**
     * Display name in PlentyFlow.
     *
     * @return string
     */
    public function getName(): string
    {
        return 'Paletten berechnen';
    }


    /**
     * Icon of the action.
     *
     * @return string
     */
    public function getIcon(): string
    {
        return 'shipping';
    }


    /**
     * Description.
     *
     * @return string
     */
    public function getDescription(): string
    {
        return 'Berechnet die Palettenanzahl aus normalen Varianten und Bundle-Komponenten.';
    }


    /**
     * Tooltip.
     *
     * @return string
     */
    public function getTooltip(): string
    {
        return 'Addiert nur Varianten mit der Eigenschaft Palettenrelevant und berücksichtigt enthaltene Bundle-Komponenten.';
    }


    /**
     * Description for AI-assisted Flow creation.
     *
     * @return string
     */
    public function getAIDescription(): string
    {
        return 'Calculates pallet requirements for an order. Normal pallet-relevant variations and pallet-relevant bundle components are summed. Bundle headers and non-pallet items are ignored.';
    }


    /**
     * Plugin display name.
     *
     * @return string
     */
    public function getPluginName(): string
    {
        return 'PalletCalculator';
    }


    /**
     * No Flow-specific configuration fields are needed.
     *
     * Configuration is handled through plugin config.
     *
     * @return array
     */
    public function getUIConfigFields(): array
    {
        return [];
    }


    /**
     * Execute Flow action.
     *
     * @param array $inputs
     * @param array $configFields
     * @param Filter|null $filter
     * @param array $extraParams
     *
     * @return array
     */
    public function performTask(
        array $inputs,
        array $configFields,
        Filter $filter = null,
        array $extraParams = []
    ): array {

        /** @var PalletCalculatorService $calculator */
        $calculator = pluginApp(
            PalletCalculatorService::class
        );


        /** @var PalletOrderPropertyService $propertyService */
        $propertyService = pluginApp(
            PalletOrderPropertyService::class
        );


        $outputs = [];


        $inputObjects =
            $inputs[$this->getObjectType()] ?? [];


        foreach ($inputObjects as $input) {

            $orderId = (int)$input->value;


            try {

                /*
                 * Palettenzahl berechnen.
                 */
                $result = $calculator->calculate(
                    $orderId
                );


                /*
                 * Ergebnis am Auftrag speichern.
                 */
                $propertyService->save(
                    $orderId,
                    (int)$result['palletCount']
                );


                /*
                 * Flow-Historie.
                 */
                $this->writeHistoryInfo(
                    $extraParams['flowName'] ?? '',
                    $extraParams['workflowName'] ?? null,
                    sprintf(
                        'Palettenberechnung abgeschlossen: %.2f palettenrelevante Stück, davon %.2f normale Varianten und %.2f Bundle-Komponenten = %d Palette(n).',
                        $result['relevantQuantity'],
                        $result['normalVariationQuantity'],
                        $result['bundleComponentQuantity'],
                        $result['palletCount']
                    ),
                    [
                        'orderId' =>
                            $orderId,

                        'relevantQuantity' =>
                            $result['relevantQuantity'],

                        'normalVariationQuantity' =>
                            $result['normalVariationQuantity'],

                        'bundleComponentQuantity' =>
                            $result['bundleComponentQuantity'],

                        'quantityPerPallet' =>
                            $result['quantityPerPallet'],

                        'palletCount' =>
                            $result['palletCount'],

                        'positions' =>
                            $result['relevantPositions']
                    ]
                );

            } catch (\Throwable $e) {

                /*
                 * Fehler in PlentyFlow-Historie schreiben.
                 */
                $this->writeHistoryError(
                    $extraParams['flowName'] ?? '',
                    $extraParams['workflowName'] ?? null,
                    'PalletCalculator: ' .
                    $e->getMessage(),
                    [
                        'orderId' => $orderId
                    ]
                );


                /*
                 * Auftrag bei Fehler nicht an den nächsten
                 * Flow-Schritt weitergeben.
                 */
                continue;
            }


            /*
             * Erfolgreich verarbeiteten Auftrag
             * an den nächsten PlentyFlow-Schritt weiterreichen.
             */
            $outputs[] = pluginApp(
                Output::class,
                [
                    'name' =>
                        $this->getObjectType(),

                    'value' =>
                        (string)$orderId
                ]
            );
        }


        return $outputs;
    }
}
