<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\Quotation;
use Illuminate\Http\Request;

class PrintController extends Controller
{
    public function printSale($id)
    {
        // Load sale with all necessary relationships including returns
        $sale = Sale::with(['customer', 'items.product', 'payments', 'returns' => function ($q) {
            $q->with('product');
        }])->findOrFail($id);

        // Return the print view
        return view('components.sale-receipt-print', compact('sale'));
    }

    public function printQuotation($id)
    {
        // Load quotation with all necessary relationships
        $quotation = Quotation::findOrFail($id);

        // Decode items if stored as JSON
        if (is_string($quotation->items)) {
            $quotation->items = json_decode($quotation->items, true);
        }

        // Return the print view
        return view('admin.quotations.print', compact('quotation'));
    }
}
