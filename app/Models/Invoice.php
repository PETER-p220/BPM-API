<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    protected $table = 'invoices';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'title',
        'item_description',
        'number_of_cars',
        'period_months',
        'uom',
        'unit_price',
        'gross_value',
        'status',
        'created_by',
        'updated_by',
        'invoice_number',
        'invoice_date',
        'due_date',
        'client_name',
        'client_email',
        'client_phone',
        'tin',
        'address',
        'vrn',
        'notes',
        'tax_rate',
        'tax_amount',
        'total_amount',
        // Company/Issuer fields
        'company_name',
        'company_email',
        'company_phone',
        'company_website',
        'company_tin',
        'company_vrn',
        'company_address',
        'company_logo',
        // Legacy fields for compatibility
        'payment',
        'item',
        'ref_number',
        'amount',
        'department_id',
        'iscreated_by',
        'description',
        'project_id',
        'project_name',
        'tender_id',
        'budget',
        'contract',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'number_of_cars' => 'decimal:2',
        'period_months' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'gross_value' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'invoice_date' => 'date',
        'due_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'amount' => 'decimal:2',
        'budget' => 'decimal:2'
    ];

    // Relationships
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by', 'user_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id','department_id');
    }

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id','project_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function requestForProject()
    {
        return $this->belongsTo(RequestForProject::class, 'request_id', 'request_id');
    }

    public function tender()
    {
        return $this->belongsTo(Tender::class, 'tender_id', 'tender_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function scopeOverdue($query)
    {
        return $query->where('due_date', '<', now())->where('status', '!=', 'paid');
    }

    // Accessors
    public function getFormattedUnitPriceAttribute()
    {
        return number_format($this->unit_price, 2);
    }

    public function getFormattedGrossValueAttribute()
    {
        return number_format($this->gross_value, 2);
    }

    public function getFormattedTotalAmountAttribute()
    {
        return number_format($this->total_amount, 2);
    }

    public function getStatusBadgeAttribute()
    {
        $badges = [
            'draft' => '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">Draft</span>',
            'sent' => '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">Sent</span>',
            'paid' => '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Paid</span>',
            'overdue' => '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">Overdue</span>',
            'cancelled' => '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">Cancelled</span>'
        ];

        return $badges[$this->status] ?? '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">Unknown</span>';
    }

    // Mutators
    public function setGrossValueAttribute($value)
    {
        $this->attributes['gross_value'] = $this->number_of_cars * $this->period_months * $this->unit_price;
    }

    public function setTaxAmountAttribute($value)
    {
        $this->attributes['tax_amount'] = $this->gross_value * ($this->tax_rate / 100);
    }

    public function setTotalAmountAttribute($value)
    {
        $this->attributes['total_amount'] = $this->gross_value + $this->tax_amount;
    }
}
