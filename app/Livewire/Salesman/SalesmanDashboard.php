<?php

namespace App\Livewire\Salesman;

use App\Models\Sale;
use App\Models\Customer;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Auth;

#[Title('Salesman Dashboard')]
#[Layout('components.layouts.app')]
class SalesmanDashboard extends Component
{
    public $totalSales = 0;
    public $pendingSales = 0;
    public $approvedSales = 0;
    public $rejectedSales = 0;
    public $totalCustomers = 0;
    public $recentSales = [];

    public function mount()
    {
        $userId = Auth::id();

        // Get sales statistics for this salesman
        $this->totalSales = Sale::where('user_id', $userId)->count();
        $this->pendingSales = Sale::where('user_id', $userId)->where('status', 'pending')->count();
        $this->approvedSales = Sale::where('user_id', $userId)->where('status', 'confirm')->count();
        $this->rejectedSales = Sale::where('user_id', $userId)->where('status', 'rejected')->count();

        // Get total customers served
        $this->totalCustomers = Sale::where('user_id', $userId)
            ->distinct('customer_id')
            ->count('customer_id');

        // Get recent sales
        $this->recentSales = Sale::where('user_id', $userId)
            ->with('customer')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();
    }

    public function render()
    {
        return view('livewire.salesman.salesman-dashboard');
    }
}
