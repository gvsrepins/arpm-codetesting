<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index()
    {
        $completedOrdersQuery = Order::select('id', 'completed_at')
            ->where('status', 'completed')
            ->orderByDesc('completed_at')
            ->groupBy('id');

        /* TODO: move this query for the Order model */
        $orders = Order::with([
            'customer',
            'items',
            'cartItems' => function ($query) {
                $query->select('id', 'order_id', 'created_at')
                    ->oldest('created_at')
                    ->take(1);
            }
        ])
            ->withCount(['completed_orders_count' => function ($query) {
                $query->where('status', 'completed');
            }])
            ->withSum('items as total_amount', DB::raw('price * quantity'))
            ->withCount('items as items_count')
            ->leftJoinSub(
                $completedOrdersQuery,
                'latest_completed_order',
                'orders.id',
                '=',
                'latest_completed_order.id'
            )
            ->select('orders.*', 'latest_completed_order.completed_at as latest_completed_at')
            ->orderByDesc('latest_completed_at')
            ->paginate();

        $orderData = [];

        // Transform each item in the paginated collection
        $orders->getCollection()->transform(function ($order) {
            return [
                'order_id' => $order->id,
                'customer_name' => $order->customer->name ?? null,
                'total_amount' => $order->total_amount,
                'items_count' => $order->items_count,
                'last_added_to_cart' => $order->cartItems->created_at ?? null,
                'completed_order_exists' => $order->completed_orders_count > 1,
                'created_at' => $order->created_at,
            ];
        });

        return view('orders.index', ['orders' => $orders]);
    }
}
