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
#[Layout('components.layouts.salesman')]
class SalesmanProductList extends Component
{
    use WithPagination;

    public $search = '';
    public $categoryFilter = '';
    public $categories = [];

    // Product detail modal
    public $selectedProduct = null;
    public $showProductModal = false;

    protected StockAvailabilityService $stockService;

    public function boot(StockAvailabilityService $stockService)
    {
        $this->stockService = $stockService;
    }

    public function mount()
    {
        $this->categories = CategoryList::orderBy('category_name')->get();
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedCategoryFilter()
    {
        $this->resetPage();
    }

    public function viewProduct($productId)
    {
        $this->selectedProduct = ProductDetail::with(['stock', 'price', 'category', 'variant', 'brand'])
            ->find($productId);
        $this->showProductModal = true;
    }

    public function closeProductModal()
    {
        $this->showProductModal = false;
        $this->selectedProduct = null;
    }

    public function render()
    {
        $query = ProductDetail::with(['stock', 'price', 'category', 'stocks', 'prices', 'variant'])
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
            ->orderBy('code')
            ->orderBy('variant_id');

        $products = $query->paginate(24);

        // Add available stock info to each product
        $stockInfo = [];
        foreach ($products as $product) {
            $stockInfo[$product->id] = $this->stockService->getAvailableStock($product->id);
        }

        return view('livewire.salesman.salesman-product-list', [
            'products' => $products,
            'stockInfo' => $stockInfo,
        ]);
    }
}
