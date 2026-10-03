<?php

namespace PalletCalculator\Services;

use Plenty\Modules\Order\Contracts\OrderRepositoryContract;
use Plenty\Modules\Item\Variation\Contracts\VariationRepositoryContract;
use Plenty\Plugin\ConfigRepository;

class PalletCalculatorService
{
    /**
     * PlentyONE OrderItem type IDs.
     */
    private const TYPE_VARIATION = 1;
    private const TYPE_ITEM_BUNDLE = 2;
    private const TYPE_BUNDLE_COMPONENT = 3;


    /**
     * Berechnet den Palettenbedarf eines Auftrags.
     *
     * Regeln:
     *
     * - normale Varianten werden geprüft
     * - Bundle-Köpfe werden nicht gezählt
     * - Bundle-Komponenten werden geprüft und gezählt
     * - andere Positionstypen werden ignoriert
     * - nur Varianten mit "Palettenrelevant" fließen ein
     * - Stückzahl- und Gewichtsgrenze werden parallel berücksichtigt
     *
     * @param int $orderId
     *
     * @return array
     */
    public function calculate(int $orderId): array
    {
        /** @var ConfigRepository $config */
        $config = pluginApp(
            ConfigRepository::class
        );

        $propertyId = (int)$config->get(
            'PalletCalculator.pallet.propertyId'
        );

        $quantityPerPallet = (float)$config->get(
            'PalletCalculator.pallet.quantityPerPallet',
            45
        );

        $maxPalletWeight = (float)$config->get(
            'PalletCalculator.pallet.maxWeight',
            0
        );


        if ($propertyId <= 0) {
            throw new \RuntimeException(
                'Die Eigenschafts-ID "Palettenrelevant" wurde nicht konfiguriert.'
            );
        }


        if ($quantityPerPallet <= 0) {
            throw new \RuntimeException(
                '"Stück pro Palette" muss größer als 0 sein.'
            );
        }


        if ($maxPalletWeight <= 0) {
            throw new \RuntimeException(
                '"Maximalgewicht der Palette" muss größer als 0 sein.'
            );
        }


        /** @var OrderRepositoryContract $orderRepository */
        $orderRepository = pluginApp(
            OrderRepositoryContract::class
        );


        $order = $orderRepository->findById(
            $orderId
        );


        if (!$order) {
            throw new \RuntimeException(
                'Auftrag ' . $orderId . ' wurde nicht gefunden.'
            );
        }


        /** @var PalletRelevantService $palletRelevantService */
        $palletRelevantService = pluginApp(
            PalletRelevantService::class
        );


        /** @var VariationRepositoryContract $variationRepository */
        $variationRepository = pluginApp(
            VariationRepositoryContract::class
        );


        $relevantQuantity = 0.0;
        $relevantWeightG = 0.0;

        $normalVariationQuantity = 0.0;
        $bundleComponentQuantity = 0.0;

        $relevantPositions = [];
        $variationWeights = [];


        foreach ($order->orderItems as $orderItem) {

            $typeId = (int)$orderItem->typeId;

            $variationId = (int)$orderItem->itemVariationId;

            $quantity = (float)$orderItem->quantity;


            /*
             * -------------------------------------------------
             * BUNDLE-KOPF
             * -------------------------------------------------
             *
             * Beispiel:
             *
             * 1 x Bundle
             *   -> 40 x Sack A
             *
             * Der Bundle-Kopf selbst darf NICHT als "1 Sack"
             * gerechnet werden.
             */
            if ($typeId === self::TYPE_ITEM_BUNDLE) {
                continue;
            }


            /*
             * -------------------------------------------------
             * NUR NORMALE VARIANTEN UND BUNDLE-KOMPONENTEN
             * -------------------------------------------------
             *
             * Ignoriert werden damit automatisch:
             *
             * Versandkosten
             * Gutscheine
             * Zuschläge
             * Pfand
             * sonstige Order Items
             */
            if (
                $typeId !== self::TYPE_VARIATION
                &&
                $typeId !== self::TYPE_BUNDLE_COMPONENT
            ) {
                continue;
            }


            if ($variationId <= 0) {
                continue;
            }


            if ($quantity <= 0) {
                continue;
            }


            /*
             * Prüfen, ob die konkrete Variante
             * "Palettenrelevant" besitzt.
             */
            $isRelevant = $palletRelevantService->isPalletRelevant(
                $variationId,
                $propertyId
            );


            if (!$isRelevant) {
                continue;
            }


            /*
             * Das Bruttogewicht der Variante wird von PlentyONE
             * in Gramm bereitgestellt. Mehrfach vorkommende Varianten
             * werden innerhalb einer Berechnung nur einmal geladen.
             */
            if (!array_key_exists($variationId, $variationWeights)) {
                $variation = $variationRepository->findById(
                    $variationId
                );

                $variationWeights[$variationId] =
                    $variation ? (float)$variation->weightG : 0.0;
            }

            $unitWeightG = $variationWeights[$variationId];


            if ($unitWeightG <= 0) {
                throw new \RuntimeException(
                    'Für die palettenrelevante Variante ' .
                    $variationId .
                    ' ist kein gültiges Bruttogewicht hinterlegt.'
                );
            }

            $positionWeightG = $unitWeightG * $quantity;


            /*
             * Menge auf die Gesamtmenge addieren.
             */
            $relevantQuantity += $quantity;
            $relevantWeightG += $positionWeightG;


            /*
             * Für Logging / Diagnose getrennt halten.
             */
            if ($typeId === self::TYPE_BUNDLE_COMPONENT) {
                $bundleComponentQuantity += $quantity;
            } else {
                $normalVariationQuantity += $quantity;
            }


            $relevantPositions[] = [
                'orderItemId' => (int)$orderItem->id,
                'variationId' => $variationId,
                'typeId' => $typeId,
                'quantity' => $quantity,
                'unitWeightG' => $unitWeightG,
                'positionWeightG' => $positionWeightG
            ];
        }


        /*
         * -------------------------------------------------
         * PALETTENANZAHL
         * -------------------------------------------------
         *
         * Beispiel:
         *
         * Das höhere Ergebnis aus Stückzahl- und Gewichtsgrenze
         * bestimmt die benötigte Palettenanzahl.
         */
        $palletCountByQuantity = 0;
        $palletCountByWeight = 0;


        if ($relevantQuantity > 0) {
            $palletCountByQuantity = (int)ceil(
                $relevantQuantity / $quantityPerPallet
            );

            $palletCountByWeight = (int)ceil(
                $relevantWeightG / ($maxPalletWeight * 1000)
            );
        }


        $palletCount = max(
            $palletCountByQuantity,
            $palletCountByWeight
        );


        return [
            'orderId' => $orderId,

            'relevantQuantity' => $relevantQuantity,

            'relevantWeightKg' =>
                $relevantWeightG / 1000,

            'normalVariationQuantity' =>
                $normalVariationQuantity,

            'bundleComponentQuantity' =>
                $bundleComponentQuantity,

            'quantityPerPallet' =>
                $quantityPerPallet,

            'maxPalletWeight' =>
                $maxPalletWeight,

            'palletCountByQuantity' =>
                $palletCountByQuantity,

            'palletCountByWeight' =>
                $palletCountByWeight,

            'palletCount' =>
                $palletCount,

            'relevantPositions' =>
                $relevantPositions
        ];
    }
}
