<?php

namespace Appstract\Stock;

use DateTimeInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

trait HasStock
{
    /*
     |--------------------------------------------------------------------------
     | Accessors
     |--------------------------------------------------------------------------
     */

    /**
     * Stock accessor.
     *
     * @return int
     */
    public function getStockAttribute()
    {
        return $this->stock();
    }

    /*
     |--------------------------------------------------------------------------
     | Methods
     |--------------------------------------------------------------------------
     */

    /**
     * Returns the stock at a given date and warehouse (optionals)
     *
     * @param null $date
     * @param null $warehouse
     * @return int
     */
    public function stock($date = null, $warehouse = null)
    {
        $date = $date ?: Carbon::now();

        if (! $date instanceof DateTimeInterface) {
            $date = Carbon::create($date);
        }

        $mutations = $this->stockMutations()->where('created_at', '<=', $date->format('Y-m-d H:i:s'));

        if ($warehouse != null) {
            $mutations->where([
                'warehouse_id' => $warehouse,
            ]);
        }

        return (int) $mutations->sum('amount');
    }

    public function increaseStock($amount = 1, $arguments = [])
    {
        return $this->createStockMutation($amount, $arguments);
    }

    public function decreaseStock($amount = 1, $arguments = [])
    {
        return $this->createStockMutation(-1 * abs($amount), $arguments);
    }

    public function mutateStock($amount = 1, $arguments = [])
    {
        return $this->createStockMutation($amount, $arguments);
    }

    public function clearStock($newAmount = null, $arguments = [])
    {
        $this->stockMutations()->delete();

        if (! is_null($newAmount)) {
            $this->createStockMutation($newAmount, $arguments);
        }

        return true;
    }

    public function setStock($newAmount, $arguments = [])
    {
        $currentStock = $this->stock(null, $arguments['warehouse_id'] ?? null);

        if ($deltaStock = $newAmount - $currentStock) {
            return $this->createStockMutation($deltaStock, $arguments);
        }

        return false;
    }

    public function inStock($amount = 1)
    {
        return $this->stock > 0 && $this->stock >= $amount;
    }

    public function outOfStock()
    {
        return $this->stock <= 0;
    }

    /**
     * Function to handle mutations (increase, decrease).
     *
     * @param  int $amount
     * @param  array  $arguments
     * @return bool
     */
    protected function createStockMutation($amount, $arguments = [])
    {
        $reference = Arr::get($arguments, 'reference');

        $createArguments = collect([
            'amount' => $amount,
            'description' => Arr::get($arguments, 'description'),
            'warehouse_id' => Arr::get($arguments, 'warehouse_id'),
        ])->when($reference, function ($collection) use ($reference) {
            return $collection
                ->put('reference_type', $reference->getMorphClass())
                ->put('reference_id', $reference->getKey());
        })->toArray();

        return $this->stockMutations()->create($createArguments);
    }

    /*
     |--------------------------------------------------------------------------
     | Scopes
     |--------------------------------------------------------------------------
     */

    public function scopeWhereInStock($query)
    {
        return $query->where(function ($query) {
            return $query->whereHas('stockMutations', function ($query) {
                return $query->select('stockable_id')
                    ->groupBy('stockable_id')
                    ->havingRaw('SUM(amount) > 0');
            });
        });
    }

    public function scopeWhereOutOfStock($query)
    {
        return $query->where(function ($query) {
            return $query->whereHas('stockMutations', function ($query) {
                return $query->select('stockable_id')
                    ->groupBy('stockable_id')
                    ->havingRaw('SUM(amount) <= 0');
            })->orWhereDoesntHave('stockMutations');
        });
    }

    /*
     |--------------------------------------------------------------------------
     | Relations
     |--------------------------------------------------------------------------
     */

    /**
     * Relation with StockMutation.
     *
     * @return \Illuminate\Database\Eloquent\Relations\morphMany
     */
    public function stockMutations()
    {
        return $this->morphMany(StockMutation::class, 'stockable');
    }
}