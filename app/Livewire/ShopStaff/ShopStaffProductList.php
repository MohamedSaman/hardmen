<?php

namespace App\Livewire\ShopStaff;

use App\Models\ProductDetail;
use App\Models\CategoryList;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Title('Product List')]
#[Layout('components.layouts.shop-staff')]
class ShopStaffProductList extends Component
{
    use WithPagination;

    public $search = '';
    public $categoryFilter = '';
    public $stockFilter = 'all'; // all, in_stock, low_stock, out_of_stock
    public $categories = [];

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

    public function updatedStockFilter()
    {
        $this->resetPage();
    }

    public function render()
    {
        $query = ProductDetail::with(['stock', 'price', 'category'])
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
            ->when($this->stockFilter === 'in_stock', function ($q) {
                $q->whereHas('stock', function ($sq) {
                    $sq->where('available_stock', '>', 10);
                });
            })
            ->when($this->stockFilter === 'low_stock', function ($q) {
                $q->whereHas('stock', function ($sq) {
                    $sq->where('available_stock', '>', 0)
                        ->where('available_stock', '<=', 10);
                });
            })
            ->when($this->stockFilter === 'out_of_stock', function ($q) {
                $q->whereHas('stock', function ($sq) {
                    $sq->where('available_stock', '<=', 0);
                });
            })
            ->orderBy('name');

        return view('livewire.shop-staff.shop-staff-product-list', [
            'products' => $query->paginate(20),
        ]);
    }
}
