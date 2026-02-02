<?php

namespace App\Livewire\Salesman;

use App\Models\ProductDetail;
use App\Models\CategoryList;
use App\Services\StockAvailabilityService;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Title('Product List')]
#[Layout('components.layouts.app')]
class SalesmanProductList extends Component
{
    use WithPagination;

    public $search = '';
    public $categoryFilter = '';
    public $categories = [];

    protected StockAvailabilityService $stockService;

    public function boot(StockAvailabilityService $stockService)
    {
        $this->stockService = $stockService;
    }

    public function mount()
    {
        $this->categories = CategoryList::orderBy('name')->get();
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedCategoryFilter()
    {
        $this->resetPage();
    }

    public function render()
    {
        $query = ProductDetail::with(['stock', 'price', 'category', 'stocks', 'prices'])
            ->when($this->search, function ($q) {
                $q->where(function ($sq) {
                    $sq->where('name', 'like', '%' . $this->search . '%')
                        ->orWhere('code', 'like', '%' . $this->search . '%')
                        ->orWhere('model', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->categoryFilter, function ($q) {
                $q->where('category_id', $this->categoryFilter);
            })
            ->orderBy('name');

        $products = $query->paginate(20);

        // Add available stock info to each product
        $productIds = $products->pluck('id')->toArray();
        $stockInfo = $this->stockService->getAvailableStockBulk($productIds);

        return view('livewire.salesman.salesman-product-list', [
            'products' => $products,
            'stockInfo' => $stockInfo,
        ]);
    }
}
