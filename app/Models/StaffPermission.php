<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StaffPermission extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'permission_key',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the user that owns the permission.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Available permission keys and their descriptions
     */
    public static function availablePermissions()
    {
        return [
            // Dashboard
            'menu_dashboard' => 'Dashboard/Overview',

            // POS
            'menu_pos' => 'POS (Point of Sale)',

            // Stock Management
            'menu_stock' => 'Stock Overview',

            // Sales Menu
            'menu_sales' => 'Sales Menu',
            'menu_sales_add' => 'Add Sales',
            'menu_sales_list' => 'List Sales',

            // Quotation Menu
            'menu_quotation' => 'Quotation Menu',
            'menu_quotation_add' => 'Add Quotation',
            'menu_quotation_list' => 'List Quotations',

            // Payment Management Menu
            'menu_payment' => 'Payment Management Menu',
            'menu_payment_add' => 'Add Payment (Due Payments)',
            'menu_payment_list' => 'Payment List',

            // Products Menu (if staff needs to view products)
            'menu_products' => 'Products Menu',
            'menu_products_list' => 'List Products',
            'menu_products_brand' => 'Product Brands',
            'menu_products_category' => 'Product Categories',

            // Purchase Menu
            'menu_purchase' => 'Purchase Menu',
            'menu_purchase_order' => 'Purchase Order List',
            'menu_purchase_grn' => 'GRN (Goods Received Note)',

            // Return Menu
            'menu_return' => 'Return Menu',
            'menu_return_customer_add' => 'Add Customer Return',
            'menu_return_customer_list' => 'List Customer Returns',
            'menu_return_supplier_add' => 'Add Supplier Return',
            'menu_return_supplier_list' => 'List Supplier Returns',

            // Cheque/Banks Menu
            'menu_banks' => 'Cheque/Banks Menu',
            'menu_banks_deposit' => 'Deposit By Cash',
            'menu_banks_cheque_list' => 'Cheque List',
            'menu_banks_return_cheque' => 'Return Cheque',

            // Expenses Menu
            'menu_expenses' => 'Expenses Menu',
            'menu_expenses_list' => 'List Expenses',

            // People Menu
            'menu_people' => 'People Menu',
            'menu_people_suppliers' => 'Manage Suppliers',
            'menu_people_customers' => 'Manage Customers',
            'menu_people_staff' => 'List Staff',

            // Reports
            'menu_reports' => 'Reports',

            // Analytics
            'menu_analytics' => 'Analytics',
        ];
    }

    /**
     * Permission categories for organized display
     */
    public static function permissionCategories()
    {
        return [
            'Dashboard' => [
                'menu_dashboard',
            ],
            'Point of Sale' => [
                'menu_pos',
            ],
            'Stock Management' => [
                'menu_stock',
            ],
            'Sales Management' => [
                'menu_sales',
                'menu_sales_add',
                'menu_sales_list',
            ],
            'Quotation Management' => [
                'menu_quotation',
                'menu_quotation_add',
                'menu_quotation_list',
            ],
            'Payment Management' => [
                'menu_payment',
                'menu_payment_add',
                'menu_payment_list',
            ],
            'Products Management' => [
                'menu_products',
                'menu_products_list',
                'menu_products_brand',
                'menu_products_category',
            ],
            'Purchase Management' => [
                'menu_purchase',
                'menu_purchase_order',
                'menu_purchase_grn',
            ],
            'Return Management' => [
                'menu_return',
                'menu_return_customer_add',
                'menu_return_customer_list',
                'menu_return_supplier_add',
                'menu_return_supplier_list',
            ],
            'Cheque & Banks' => [
                'menu_banks',
                'menu_banks_deposit',
                'menu_banks_cheque_list',
                'menu_banks_return_cheque',
            ],
            'Expenses Management' => [
                'menu_expenses',
                'menu_expenses_list',
            ],
            'People Management' => [
                'menu_people',
                'menu_people_suppliers',
                'menu_people_customers',
                'menu_people_staff',
            ],
            'Reports & Analytics' => [
                'menu_reports',
                'menu_analytics',
            ],
        ];
    }

    /**
     * Check if a user has a specific permission
     */
    public static function hasPermission($userId, $permissionKey)
    {
        return self::where('user_id', $userId)
            ->where('permission_key', $permissionKey)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Get all permissions for a user
     */
    public static function getUserPermissions($userId)
    {
        return self::where('user_id', $userId)
            ->where('is_active', true)
            ->pluck('permission_key')
            ->toArray();
    }

    /**
     * Sync permissions for a user
     */
    public static function syncPermissions($userId, array $permissions)
    {
        // Delete existing permissions
        self::where('user_id', $userId)->delete();

        // Create new permissions
        foreach ($permissions as $permission) {
            self::create([
                'user_id' => $userId,
                'permission_key' => $permission,
                'is_active' => true,
            ]);
        }
    }
}
