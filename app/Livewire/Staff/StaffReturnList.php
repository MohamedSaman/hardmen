<?php

namespace App\Livewire\Staff;

use App\Models\StaffReturn;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Auth;

class StaffReturnList extends Component
{
    use WithPagination;

    public $search = '';
    public $statusFilter = 'all';
    public $damageFilter = 'all';

    public function render()
    {
        $returns = StaffReturn::query()
            ->where('staff_id', Auth::id())
            ->with(['product', 'customer'])
            ->when($this->search, function ($query) {
                $query->whereHas('product', function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                        ->orWhere('barcode', 'like', '%' . $this->search . '%');
                })
                    ->orWhereHas('customer', function ($q) {
                        $q->where('name', 'like', '%' . $this->search . '%');
                    });
            })
            ->when($this->statusFilter !== 'all', function ($query) {
                $query->where('status', $this->statusFilter);
            })
            ->when($this->damageFilter !== 'all', function ($query) {
                $query->where('is_damaged', $this->damageFilter === 'damaged');
            })
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return view('livewire.staff.staff-return-list', [
            'returns' => $returns,
        ]);
    }
}
