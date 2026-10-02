<?php

namespace PalletCalculator\Services;

use Plenty\Modules\Order\Property\Contracts\OrderPropertyRepositoryContract;
use Plenty\Plugin\ConfigRepository;

class PalletOrderPropertyService
{
    /**
     * Speichert die berechnete Palettenanzahl
     * in der konfigurierten Auftragseigenschaft.
     *
     * @param int $orderId
     * @param int $palletCount
     *
     * @return void
     */
    public function save(
        int $orderId,
        int $palletCount
    ): void {
        /** @var ConfigRepository $config */
        $config = pluginApp(
            ConfigRepository::class
        );

        $typeId = (int)$config->get(
            'PalletCalculator.pallet.orderPropertyTypeId'
        );

        if ($typeId <= 0) {
            throw new \RuntimeException(
                'Die Auftragseigenschafts-ID "Palettenanzahl" wurde nicht konfiguriert.'
            );
        }

        /** @var OrderPropertyRepositoryContract $repository */
        $repository = pluginApp(
            OrderPropertyRepositoryContract::class
        );

        /*
         * Prüfen, ob für den Auftrag bereits
         * eine Palettenanzahl gespeichert wurde.
         */
        $existingProperties = $repository->findByOrderId(
            $orderId,
            $typeId
        );

        $existingProperty = null;

        /*
         * Plenty liefert hier eine Collection.
         *
         * Kein is_iterable() verwenden, da diese
         * PHP-Funktion in PlentyONE Plugins nicht
         * erlaubt ist.
         */
        if ($existingProperties) {
            foreach ($existingProperties as $property) {
                if ((int)$property->typeId === $typeId) {
                    $existingProperty = $property;
                    break;
                }
            }
        }

        /*
         * Vorhandenen Wert aktualisieren.
         */
        if (
            $existingProperty
            &&
            isset($existingProperty->id)
        ) {
            $repository->update(
                [
                    'orderId' => $orderId,
                    'typeId' => $typeId,
                    'value' => (string)$palletCount
                ],
                (int)$existingProperty->id
            );

            return;
        }

        /*
         * Noch keine Palettenanzahl vorhanden:
         * neue Auftragseigenschaft erstellen.
         */
        $repository->create(
            [
                'orderId' => $orderId,
                'typeId' => $typeId,
                'value' => (string)$palletCount
            ]
        );
    }
}
