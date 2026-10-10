<?php

namespace App\Services\Customer;

use App\Models\Customer\Customer;
use App\Models\Customer\CustomerAddress;
use App\Models\Fulfillment\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CustomerMatchingService
{
    public function findOrCreateFromOrder(Order $order): ?Customer
    {
        $organizationId = $order->organization_id;
        $shippingName = $order->shipping_name;
        $email = $order->marketplace_data['buyer_email']
            ?? $order->marketplace_data['email']
            ?? $order->marketplace_data['BuyerInfo']['Email']
            ?? null;
        $marketplaceBuyerId = $order->marketplace_data['buyer_id']
            ?? $order->marketplace_data['buyer_user_id']
            ?? $order->marketplace_data['BuyerInfo']['BuyerUserId']
            ?? null;

        if (! $shippingName && ! $email && ! $marketplaceBuyerId) {
            return null;
        }

        return DB::transaction(function () use ($order, $organizationId, $shippingName, $email, $marketplaceBuyerId) {
            $customer = null;

            // Strategy 1: Match by email
            if ($email) {
                $customer = Customer::withoutGlobalScopes()
                    ->where('organization_id', $organizationId)
                    ->where('email', $email)
                    ->first();
            }

            // Strategy 2: Match by marketplace customer ID
            if (! $customer && $marketplaceBuyerId && $order->marketplace) {
                $customer = Customer::withoutGlobalScopes()
                    ->where('organization_id', $organizationId)
                    ->where('marketplace_source', $order->marketplace)
                    ->where('marketplace_customer_id', $marketplaceBuyerId)
                    ->first();
            }

            // Strategy 3: Match by name + postal code (fuzzy)
            if (! $customer && $shippingName && $order->shipping_postal_code) {
                $customer = Customer::withoutGlobalScopes()
                    ->where('organization_id', $organizationId)
                    ->where('name', 'ILIKE', $shippingName)
                    ->whereHas('addresses', function ($q) use ($order) {
                        $q->where('postal_code', $order->shipping_postal_code);
                    })
                    ->first();
            }

            // Create new customer if no match
            if (! $customer) {
                $customer = Customer::create([
                    'organization_id' => $organizationId,
                    'email' => $email,
                    'name' => $shippingName ?? 'Unknown',
                    'marketplace_source' => $order->marketplace,
                    'marketplace_customer_id' => $marketplaceBuyerId,
                    'first_order_at' => $order->created_at,
                    'last_order_at' => $order->created_at,
                    'total_orders' => 1,
                    'total_spent' => $order->total_amount ?? 0,
                    'average_order_value' => $order->total_amount ?? 0,
                ]);

                Log::info('Customer created from order', ['customer_id' => $customer->id, 'order_id' => $order->id]);
            } else {
                // Update marketplace info if missing
                if (! $customer->marketplace_customer_id && $marketplaceBuyerId) {
                    $customer->update([
                        'marketplace_source' => $order->marketplace,
                        'marketplace_customer_id' => $marketplaceBuyerId,
                    ]);
                }
                if (! $customer->email && $email) {
                    $customer->update(['email' => $email]);
                }
            }

            // Link order to customer
            $order->update(['customer_id' => $customer->id]);

            // Upsert shipping address
            $this->upsertAddress($customer, $order);

            // Recalculate stats
            $customer->recalculateStats();

            return $customer;
        });
    }

    protected function upsertAddress(Customer $customer, Order $order): void
    {
        if (! $order->shipping_line1) {
            return;
        }

        $existing = $customer->addresses()
            ->where('line1', $order->shipping_line1)
            ->where('postal_code', $order->shipping_postal_code)
            ->first();

        if (! $existing) {
            $isFirst = $customer->addresses()->count() === 0;
            CustomerAddress::create([
                'customer_id' => $customer->id,
                'type' => 'shipping',
                'is_default' => $isFirst,
                'name' => $order->shipping_name,
                'line1' => $order->shipping_line1,
                'line2' => $order->shipping_line2,
                'city' => $order->shipping_city,
                'state' => $order->shipping_state,
                'postal_code' => $order->shipping_postal_code,
                'country' => $order->shipping_country ?? 'US',
            ]);
        }
    }

    public function mergeCustomers(Customer $primary, Customer $secondary): Customer
    {
        return DB::transaction(function () use ($primary, $secondary) {
            // Move orders
            $secondary->orders()->update(['customer_id' => $primary->id]);

            // Move addresses that don't already exist
            foreach ($secondary->addresses as $address) {
                $exists = $primary->addresses()
                    ->where('line1', $address->line1)
                    ->where('postal_code', $address->postal_code)
                    ->exists();

                if (! $exists) {
                    $address->update(['customer_id' => $primary->id, 'is_default' => false]);
                }
            }

            // Fill missing fields
            if (! $primary->email && $secondary->email) {
                $primary->email = $secondary->email;
            }
            if (! $primary->phone && $secondary->phone) {
                $primary->phone = $secondary->phone;
            }
            if (! $primary->marketplace_customer_id && $secondary->marketplace_customer_id) {
                $primary->marketplace_source = $secondary->marketplace_source;
                $primary->marketplace_customer_id = $secondary->marketplace_customer_id;
            }

            // Merge tags
            $tags = array_unique(array_merge($primary->tags ?? [], $secondary->tags ?? []));
            $primary->tags = $tags;
            $primary->save();

            // Recalculate and delete secondary
            $primary->recalculateStats();
            $secondary->forceDelete();

            Log::info('Customers merged', ['primary' => $primary->id, 'secondary_deleted' => $secondary->id]);

            audit_event(
                action: 'customer.merged',
                description: "Merged customer #{$secondary->id} into #{$primary->id}",
                subject: $primary,
                metadata: [
                    'merged_into_id' => $primary->id,
                    'deleted_id' => $secondary->id,
                    'source' => 'web',
                ],
                severity: 'warning',
                category: 'customer',
            );

            return $primary->fresh();
        });
    }
}
