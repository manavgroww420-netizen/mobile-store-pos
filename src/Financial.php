<?php
declare(strict_types=1);

namespace AMH;

/**
 * Authoritative Financial & Fixed-Precision Monetary Helper
 *
 * Prevents floating-point inaccuracies by handling currency calculations
 * with fixed 2-decimal precision (using bcmath where available, or precision rounding).
 */
class Financial
{
    private const SCALE = 2;

    /**
     * Format a monetary amount as an Indian Rupee string (e.g., "₹ 25,500.00").
     */
    public static function format(float|string|null $amount, bool $showSymbol = true): string
    {
        if ($amount === null || $amount === '') {
            $amount = 0.00;
        }

        $num = (float)$amount;
        $isNegative = $num < 0;
        $absNum = abs($num);

        $formatted = number_format($absNum, 2, '.', ',');
        $sign = $isNegative ? '-' : '';

        return $showSymbol ? ($sign . '₹ ' . $formatted) : ($sign . $formatted);
    }

    /**
     * Add two monetary values ($a + $b).
     */
    public static function add(string|float|int $a, string|float|int $b): string
    {
        $valA = (string)$a;
        $valB = (string)$b;

        if (function_exists('bcadd')) {
            return bcadd($valA, $valB, self::SCALE);
        }

        return number_format((float)$valA + (float)$valB, self::SCALE, '.', '');
    }

    /**
     * Subtract monetary value ($a - $b).
     */
    public static function sub(string|float|int $a, string|float|int $b): string
    {
        $valA = (string)$a;
        $valB = (string)$b;

        if (function_exists('bcsub')) {
            return bcsub($valA, $valB, self::SCALE);
        }

        return number_format((float)$valA - (float)$valB, self::SCALE, '.', '');
    }

    /**
     * Alias for sub()
     */
    public static function subtract(string|float|int $a, string|float|int $b): string
    {
        return self::sub($a, $b);
    }

    /**
     * Multiply monetary value ($a * $b).
     */
    public static function mul(string|float|int $a, string|float|int $b): string
    {
        $valA = (string)$a;
        $valB = (string)$b;

        if (function_exists('bcmul')) {
            return bcmul($valA, $valB, self::SCALE);
        }

        return number_format((float)$valA * (float)$valB, self::SCALE, '.', '');
    }

    /**
     * Alias for mul()
     */
    public static function multiply(string|float|int $a, string|float|int $b): string
    {
        return self::mul($a, $b);
    }

    /**
     * Divide monetary value ($a / $b).
     */
    public static function div(string|float|int $a, string|float|int $b): string
    {
        $valA = (string)$a;
        $valB = (string)$b;

        if ((float)$valB == 0) {
            return '0.00';
        }

        if (function_exists('bcdiv')) {
            return bcdiv($valA, $valB, self::SCALE);
        }

        return number_format((float)$valA / (float)$valB, self::SCALE, '.', '');
    }

    /**
     * Alias for div()
     */
    public static function divide(string|float|int $a, string|float|int $b): string
    {
        return self::div($a, $b);
    }

    /**
     * Format to fixed 2 decimals string without commas (e.g., "12500.00").
     */
    public static function toDecimal(mixed $val): string
    {
        return number_format((float)$val, self::SCALE, '.', '');
    }

    /**
     * Calculate Weighted Average Cost:
     * New Avg Cost = ((oldQty * oldAvgCost) + (newQty * newCost)) / (oldQty + newQty)
     */
    public static function calculateWeightedAverageCost(
        int|float $oldQty,
        string|float $oldAvg,
        int|float $newQty,
        string|float $newCost
    ): string {
        $totalQty = (float)$oldQty + (float)$newQty;
        if ($totalQty <= 0) {
            return self::toDecimal($newCost);
        }

        $oldVal = self::mul((string)$oldQty, (string)$oldAvg);
        $newVal = self::mul((string)$newQty, (string)$newCost);
        $totalVal = self::add($oldVal, $newVal);

        return self::div($totalVal, (string)$totalQty);
    }

    /**
     * Alias for calculateWeightedAverageCost
     */
    public static function weightedAverageCost(
        int|float $oldQty,
        string|float $oldAvg,
        int|float $newQty,
        string|float $newCost
    ): string {
        return self::calculateWeightedAverageCost($oldQty, $oldAvg, $newQty, $newCost);
    }

    /**
     * Calculate Total Cost Basis for a second-hand phone:
     * Total Cost = purchase_cost + repair_cost + other_cost
     */
    public static function calculateUsedPhoneCostBasis(
        string|float $purchaseCost,
        string|float $repairCost,
        string|float $otherCost
    ): string {
        $step1 = self::add($purchaseCost, $repairCost);
        return self::add($step1, $otherCost);
    }

    /**
     * Calculate Gross Profit:
     * Gross Profit = Revenue - COGS
     */
    public static function calculateGrossProfit(string|float $revenue, string|float $cogs): string
    {
        return self::sub($revenue, $cogs);
    }

    /**
     * Calculate Customer Repair Profit:
     * Profit = Amount Charged - (Parts Cost + Labour Amount)
     */
    public static function calculateRepairProfit(
        string|float $amountCharged,
        string|float $partsCost,
        string|float $labourAmount
    ): string {
        $totalCost = self::add($partsCost, $labourAmount);
        return self::sub($amountCharged, $totalCost);
    }

    /**
     * Allocate a header discount proportionally across multiple cart/sale line items.
     * Guarantees exact mathematical reconciliation between header discount and sum of line discounts.
     *
     * @param array<array{id: mixed, subtotal: float|string, cost: float|string}> $items
     * @param float|string $headerDiscount Total flat discount on the sale
     * @return array<array{id: mixed, subtotal: string, discount: string, net_price: string, cost: string, profit: string}>
     */
    public static function allocateDiscountProportionally(array $items, float|string $headerDiscount): array
    {
        $discountTotal = (float)$headerDiscount;
        $saleSubtotal = 0.00;

        foreach ($items as $item) {
            $saleSubtotal += (float)$item['subtotal'];
        }

        $result = [];
        $allocatedSum = 0.00;
        $maxItemIndex = 0;
        $maxSubtotal = -1.0;

        if ($saleSubtotal <= 0 || $discountTotal <= 0) {
            foreach ($items as $idx => $item) {
                $subtotal = self::toDecimal($item['subtotal']);
                $cost = self::toDecimal($item['cost']);
                $profit = self::sub($subtotal, $cost);

                $result[] = [
                    'id'        => $item['id'] ?? $idx,
                    'subtotal'  => $subtotal,
                    'discount'  => '0.00',
                    'net_price' => $subtotal,
                    'cost'      => $cost,
                    'profit'    => $profit,
                ];
            }
            return $result;
        }

        // Distribute discount proportionately
        foreach ($items as $idx => $item) {
            $itemSubtotal = (float)$item['subtotal'];
            if ($itemSubtotal > $maxSubtotal) {
                $maxSubtotal = $itemSubtotal;
                $maxItemIndex = $idx;
            }

            $ratio = $itemSubtotal / $saleSubtotal;
            $lineDiscount = round($discountTotal * $ratio, 2);
            $allocatedSum += $lineDiscount;

            $subtotalStr = self::toDecimal($itemSubtotal);
            $discountStr = self::toDecimal($lineDiscount);
            $netPriceStr = self::sub($subtotalStr, $discountStr);
            $costStr = self::toDecimal($item['cost']);
            $profitStr = self::sub($netPriceStr, $costStr);

            $result[] = [
                'id'        => $item['id'] ?? $idx,
                'subtotal'  => $subtotalStr,
                'discount'  => $discountStr,
                'net_price' => $netPriceStr,
                'cost'      => $costStr,
                'profit'    => $profitStr,
            ];
        }

        // Reconcile any 1-paisa rounding discrepancy on the largest item
        $diff = round($discountTotal - $allocatedSum, 2);
        if (abs($diff) > 0.0001 && isset($result[$maxItemIndex])) {
            $correctedDiscount = self::add($result[$maxItemIndex]['discount'], $diff);
            $correctedNetPrice = self::sub($result[$maxItemIndex]['subtotal'], $correctedDiscount);
            $correctedProfit = self::sub($correctedNetPrice, $result[$maxItemIndex]['cost']);

            $result[$maxItemIndex]['discount'] = $correctedDiscount;
            $result[$maxItemIndex]['net_price'] = $correctedNetPrice;
            $result[$maxItemIndex]['profit'] = $correctedProfit;
        }

        return $result;
    }
}
