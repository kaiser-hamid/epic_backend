<?php

namespace Modules\Shared\Billing\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Shared\Billing\Database\Factories\BillingItemFactory;
use Modules\Shared\TestCatalog\Models\TestItem;

#[Table('sh_billing_items')]
#[Fillable(['billing_id', 'test_item_id', 'quantity', 'rate', 'amount'])]
#[UseFactory(BillingItemFactory::class)]
final class BillingItem extends Model
{
    /** @use HasFactory<BillingItemFactory> */
    use HasFactory;

    public function billing(): BelongsTo
    {
        return $this->belongsTo(Billing::class, 'billing_id');
    }

    public function testItem(): BelongsTo
    {
        return $this->belongsTo(TestItem::class, 'test_item_id');
    }
}
