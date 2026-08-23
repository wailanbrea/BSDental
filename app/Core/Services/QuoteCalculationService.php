<?php

namespace App\Core\Services;

use App\Core\Models\Quote;

class QuoteCalculationService
{
    /**
     * Generate sequential quote number.
     */
    public function generateQuoteNumber(): string
    {
        $count = Quote::withTrashed()->count() + 1;

        do {
            $formatted = sprintf('PRE-%05d', $count);
            $exists = Quote::withTrashed()->where('quote_number', $formatted)->exists();
            if ($exists) {
                $count++;
            }
        } while ($exists);

        return $formatted;
    }

    /**
     * Recalculate quote totals from items.
     */
    public function recalculate(Quote $quote): Quote
    {
        $items = $quote->items()->get();

        $subtotal = 0.0;
        $discountTotal = 0.0;
        $taxTotal = 0.0;
        $grandTotal = 0.0;

        foreach ($items as $item) {
            $basePrice = $item->unit_price * $item->quantity;
            $discount = $basePrice * ($item->discount_percentage / 100);
            $itemSubtotal = $basePrice - $discount;
            $itemTax = $itemSubtotal * ($item->tax / 100);
            $itemTotal = $itemSubtotal + $itemTax;

            $item->update([
                'subtotal' => $itemSubtotal,
                'total' => $itemTotal,
            ]);

            $subtotal += $basePrice;
            $discountTotal += $discount;
            $taxTotal += $itemTax;
            $grandTotal += $itemTotal;
        }

        $quote->update([
            'subtotal' => $subtotal,
            'discount_total' => $discountTotal,
            'tax_total' => $taxTotal,
            'grand_total' => $grandTotal,
        ]);

        return $quote;
    }
}
