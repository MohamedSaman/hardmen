<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\Sale;
use App\Models\StaffSale;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

#[Layout('components.layouts.admin')]
#[Title('Staff Sales')]
class StaffSalesView extends Component
{
    public $staffSales = [];
    public $selectedStaff = null;
    public $staffMembers = [];
    public $searchTerm = '';
    public $salesData = [];
    public $paginationData = [];
    public $filterStatus = 'all'; // all, partial, completed
    public $showViewModal = false;
    public $selectedSale = null;
    public $perPage = 10;

    public function mount()
    {
        $this->loadStaffMembers();
        $this->loadStaffSales();
    }

    public function loadStaffMembers()
    {
        // Get all staff members
        $this->staffMembers = User::where('role', 'staff')
            ->orderBy('name')
            ->get()
            ->toArray();
    }

    public function loadStaffSales()
    {
        $query = StaffSale::with(['staff', 'products']);

        // Filter by selected staff
        if ($this->selectedStaff) {
            $query->where('staff_id', $this->selectedStaff);
        }

        // Filter by status
        if ($this->filterStatus !== 'all') {
            $query->where('status', $this->filterStatus);
        }

        $this->staffSales = $query->orderByDesc('created_at')->get()->toArray();

        // Load sales for each staff
        $this->loadSalesData();
    }

    public function loadSalesData()
    {
        $query = Sale::with(['customer', 'items', 'payments', 'user'])
            ->where('sale_type', 'staff');

        if ($this->selectedStaff) {
            $query->where('user_id', $this->selectedStaff);
        }

        if ($this->searchTerm) {
            $query->where(function ($q) {
                $q->where('invoice_number', 'like', '%' . $this->searchTerm . '%')
                    ->orWhere('sale_id', 'like', '%' . $this->searchTerm . '%')
                    ->orWhereHas('customer', function ($q) {
                        $q->where('name', 'like', '%' . $this->searchTerm . '%');
                    });
            });
        }

        $paginated = $query->orderByDesc('created_at')->paginate($this->perPage);

        // Store data as array for Livewire
        $this->salesData = $paginated->items();

        // Store pagination metadata
        $this->paginationData = [
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'total' => $paginated->total(),
            'per_page' => $paginated->perPage(),
            'from' => $paginated->firstItem(),
            'to' => $paginated->lastItem(),
        ];
    }

    public function updatedSearchTerm()
    {
        $this->loadSalesData();
    }

    public function updatedSelectedStaff()
    {
        $this->loadStaffSales();
        $this->loadSalesData();
    }

    public function updatedFilterStatus()
    {
        $this->loadStaffSales();
    }

    public function updatedPerPage()
    {
        $this->loadSalesData();
    }

    public function nextPage()
    {
        if ($this->paginationData['current_page'] < $this->paginationData['last_page']) {
            // Re-query with the next page
            $this->loadSalesDataForPage($this->paginationData['current_page'] + 1);
        }
    }

    public function previousPage()
    {
        if ($this->paginationData['current_page'] > 1) {
            $this->loadSalesDataForPage($this->paginationData['current_page'] - 1);
        }
    }

    public function goToPage($page)
    {
        if ($page >= 1 && $page <= $this->paginationData['last_page']) {
            $this->loadSalesDataForPage($page);
        }
    }

    private function loadSalesDataForPage($page)
    {
        $query = Sale::with(['customer', 'items', 'payments', 'user'])
            ->where('sale_type', 'staff');

        if ($this->selectedStaff) {
            $query->where('user_id', $this->selectedStaff);
        }

        if ($this->searchTerm) {
            $query->where(function ($q) {
                $q->where('invoice_number', 'like', '%' . $this->searchTerm . '%')
                    ->orWhere('sale_id', 'like', '%' . $this->searchTerm . '%')
                    ->orWhereHas('customer', function ($q) {
                        $q->where('name', 'like', '%' . $this->searchTerm . '%');
                    });
            });
        }

        $paginated = $query->orderByDesc('created_at')->paginate($this->perPage, ['*'], 'page', $page);

        $this->salesData = $paginated->items();
        $this->paginationData = [
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'total' => $paginated->total(),
            'per_page' => $paginated->perPage(),
            'from' => $paginated->firstItem(),
            'to' => $paginated->lastItem(),
        ];
    }

    public function viewStaffDetail($staffId)
    {
        $this->selectedStaff = $staffId;
        $this->loadSalesData();
    }

    public function clearFilter()
    {
        $this->selectedStaff = null;
        $this->searchTerm = '';
        $this->filterStatus = 'all';
        $this->loadStaffSales();
        $this->loadSalesData();
    }

    public function getSaleStatus($paymentStatus)
    {
        return match ($paymentStatus) {
            'paid' => 'Paid',
            'partial' => 'Partial Payment',
            'pending' => 'Pending Approval',
            default => 'Unknown'
        };
    }

    public function viewSale($saleId)
    {
        $this->selectedSale = Sale::with(['customer', 'items', 'payments', 'user'])
            ->findOrFail($saleId);
        $this->showViewModal = true;
    }

    public function closeViewModal()
    {
        $this->showViewModal = false;
        $this->selectedSale = null;
    }

    public function render()
    {
        return view('livewire.admin.staff-sales-view', [
            'staffSales' => $this->staffSales,
            'staffMembers' => $this->staffMembers,
            'salesData' => $this->salesData,
            'paginationData' => $this->paginationData,
        ]);
    }
}
