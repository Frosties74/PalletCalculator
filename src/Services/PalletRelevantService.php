<?php

namespace PalletCalculator\Services;

use Plenty\Modules\Item\VariationProperty\Contracts\VariationPropertyValueRepositoryContract;

class PalletRelevantService
{
    /**
     * Cache, damit dieselbe Variante innerhalb eines Auftrags
     * nicht mehrfach über die Plenty-Schnittstelle geladen wird.
     *
     * @var array
     */
    private $cache = [];


    /**
     * Prüft, ob eine Variante die konfigurierte
     * Eigenschaft "Palettenrelevant" besitzt.
     *
     * Entscheidend ist hier nur das Vorhandensein der Eigenschaft.
     *
     * @param int $variationId
     * @param int $propertyId
     *
     * @return bool
     */
    public function isPalletRelevant(
        int $variationId,
        int $propertyId
    ): bool {
        if ($variationId <= 0 || $propertyId <= 0) {
            return false;
        }

        $cacheKey = $variationId . ':' . $propertyId;

        if (array_key_exists($cacheKey, $this->cache)) {
            return $this->cache[$cacheKey];
        }

        /** @var VariationPropertyValueRepositoryContract $repository */
        $repository = pluginApp(
            VariationPropertyValueRepositoryContract::class
        );

        try {
            $properties = $repository->findByVariationId(
                $variationId
            );

            foreach ($properties as $property) {
                if ((int)$property->propertyId === $propertyId) {
                    $this->cache[$cacheKey] = true;

                    return true;
                }
            }
        } catch (\Throwable $e) {
            $this->cache[$cacheKey] = false;

            return false;
        }

        $this->cache[$cacheKey] = false;

        return false;
    }
}
