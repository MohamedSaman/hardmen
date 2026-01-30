<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use App\Models\ProductVariant;
use Livewire\Attributes\Title;
use Livewire\Attributes\On;
use App\Livewire\Concerns\WithDynamicLayout;
use Illuminate\Support\Facades\DB;
use Exception;

#[Title("Manage Variants")]
class ProductVariants extends Component
{
    use WithDynamicLayout;

    public $variant_name = '';
    public $variant_values = [];
    public $variant_value_input = '';
    public $status = 'active';

    // Edit fields
    public $editVariantId;
    public $editVariantName = '';
    public $editVariantValues = [];
    public $editVariantValueInput = '';
    public $editStatus = 'active';

    // Delete field
    public $deleteId;

    protected $rules = [
        'variant_name' => 'required|string|min:3|max:50',
        'variant_values' => 'required|array|min:1',
        'variant_values.*' => 'required|string|min:1',
        'status' => 'required|in:active,inactive',
    ];

    public function render()
    {
        // Eager-load products count to avoid N+1 queries in the view
        $variants = ProductVariant::withCount('products')->orderBy('id', 'desc')->get();
        return view('livewire.admin.product-variant-list', compact('variants'))->layout($this->layout);
    }

    /**
     * Open create modal
     */
    public function createVariant()
    {
        $this->resetForm();
        $this->dispatch('create-variant');
    }

    /**
     * Add a variant value
     */
    public function addVariantValue()
    {
        if (!empty(trim($this->variant_value_input))) {
            $value = trim($this->variant_value_input);

            if (!in_array($value, $this->variant_values)) {
                $this->variant_values[] = $value;
                $this->variant_value_input = '';
            }
        }
    }

    /**
     * Remove a variant value
     */
    public function removeVariantValue($value)
    {
        $key = array_search($value, $this->variant_values);
        if ($key !== false) {
            unset($this->variant_values[$key]);
            $this->variant_values = array_values($this->variant_values);
        }
    }

    /**
     * Save new variant
     */
    public function saveVariant()
    {
        $this->validate();

        try {
            ProductVariant::create([
                'variant_name' => $this->variant_name,
                'variant_values' => $this->variant_values,
                'status' => $this->status,
            ]);

            $this->resetForm();
            $this->js("$('#createVariantModal').modal('hide')");
            $this->js("Swal.fire('Success!', 'Variant created successfully!', 'success')");
        } catch (Exception $e) {
            $this->js("Swal.fire('Error!', '" . $e->getMessage() . "', 'error')");
        }
    }

    /**
     * Open edit modal
     */
    public function editVariant($id)
    {
        $variant = ProductVariant::find($id);
        if (!$variant) {
            $this->js("Swal.fire('Error!', 'Variant not found', 'error')");
            return;
        }

        $this->editVariantId = $variant->id;
        $this->editVariantName = $variant->variant_name;
        $this->editVariantValues = $variant->variant_values ?? [];
        $this->editStatus = $variant->status;
        $this->editVariantValueInput = '';
        $this->resetErrorBag();
        $this->resetValidation();
        $this->dispatch('edit-variant');
    }

    /**
     * Add value to edit form
     */
    public function addEditVariantValue()
    {
        if (!empty(trim($this->editVariantValueInput))) {
            $value = trim($this->editVariantValueInput);

            if (!in_array($value, $this->editVariantValues)) {
                $this->editVariantValues[] = $value;
                $this->editVariantValueInput = '';
            }
        }
    }

    /**
     * Remove value from edit form
     */
    public function removeEditVariantValue($value)
    {
        $key = array_search($value, $this->editVariantValues);
        if ($key !== false) {
            unset($this->editVariantValues[$key]);
            $this->editVariantValues = array_values($this->editVariantValues);
        }
    }

    /**
     * Update variant
     */
    public function updateVariant()
    {
        $this->validate([
            'editVariantName' => 'required|string|min:3|max:50',
            'editVariantValues' => 'required|array|min:1',
            'editVariantValues.*' => 'required|string|min:1',
            'editStatus' => 'required|in:active,inactive',
        ]);

        try {
            $variant = ProductVariant::find($this->editVariantId);
            if ($variant) {
                $variant->update([
                    'variant_name' => $this->editVariantName,
                    'variant_values' => $this->editVariantValues,
                    'status' => $this->editStatus,
                ]);

                $this->resetForm();
                $this->js("$('#editVariantModal').modal('hide')");
                $this->js("Swal.fire('Success!', 'Variant updated successfully!', 'success')");
            }
        } catch (Exception $e) {
            $this->js("Swal.fire('Error!', '" . $e->getMessage() . "', 'error')");
        }
    }

    /**
     * Confirm delete
     */
    public function confirmDelete($id)
    {
        $this->deleteId = $id;
        $this->dispatch('confirm-delete');
    }

    /**
     * Delete variant
     */
    #[On('confirmDelete')]
    public function deleteVariant()
    {
        try {
            $variant = ProductVariant::find($this->deleteId);
            if ($variant) {
                // Check if variant is used in products
                $usageCount = $variant->products()->count();

                if ($usageCount > 0) {
                    $this->js("Swal.fire('Error!', 'Cannot delete! This variant is used in $usageCount product(s).', 'error')");
                    return;
                }

                $variant->delete();
                $this->js("Swal.fire('Success!', 'Variant deleted successfully!', 'success')");
            }
        } catch (Exception $e) {
            $this->js("Swal.fire('Error!', '" . $e->getMessage() . "', 'error')");
        }
    }

    /**
     * Reset form fields
     */
    private function resetForm()
    {
        $this->variant_name = '';
        $this->variant_values = [];
        $this->variant_value_input = '';
        $this->status = 'active';
        $this->editVariantId = null;
        $this->editVariantName = '';
        $this->editVariantValues = [];
        $this->editVariantValueInput = '';
        $this->editStatus = 'active';
        $this->resetValidation();
    }
}
