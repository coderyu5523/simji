<?php
namespace App\Services\Scoring\OyMsi;

class ItemScorer
{
    private const MAX = 3;

    /**
     * @param  array<string, int|null>  $rawByItemCode
     * @param  list<string>             $reverseItemCodes
     * @return array<string, int|null>
     */
    public function score(array $rawByItemCode, array $reverseItemCodes): array
    {
        $reverse = array_flip($reverseItemCodes);
        $out = [];
        foreach ($rawByItemCode as $code => $raw) {
            if ($raw === null) { $out[$code] = null; continue; }
            $out[$code] = isset($reverse[$code]) ? self::MAX - (int) $raw : (int) $raw;
        }
        return $out;
    }
}
