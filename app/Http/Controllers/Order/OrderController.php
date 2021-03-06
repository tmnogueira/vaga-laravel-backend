<?php

namespace App\Http\Controllers\Order;

use App\Constants\Order\OrderConstants;
use App\Http\Controllers\Controller;
use App\Http\Requests\Order\OrderStoreRequest;
use App\Http\Requests\Order\OrderUpateRequest;
use App\Notifications\OrderCheckoutMailNotification;
use App\Repositories\CustomerRepository;
use App\Repositories\OrderItemRepository;
use App\Repositories\OrderRepository;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class OrderController extends Controller
{
    /**
      type OrderRepository
    **/
    private $orderRepository;

    /**
      type OrderItemRepository
    **/
    private $orderItemRepository;

    /**
      type CustomerRepository
    **/
    private $customerRepository;

    function __construct(OrderRepository $orderRepository, OrderItemRepository $orderItemRepository, CustomerRepository $customerRepository)
    {
        $this->orderRepository = $orderRepository;
        $this->orderItemRepository = $orderItemRepository;
        $this->customerRepository = $customerRepository;
    }

     /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $order = $this->orderRepository->paginate(20);
        return response()->json($order);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(OrderStoreRequest $request)
    {
        $data = $request->validated();
        $data['status'] = OrderConstants::ORDER_STATUS_OPENED;
        $order = $this->orderRepository->create($data);
        return response()->json(['data' => $order], Response::HTTP_CREATED);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(int $id)
    {
        $order = $this->orderRepository->getById($id);
        return response()->json(['data' => $order], Response::HTTP_OK);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(OrderUpateRequest $request, int $id)
    {
        try {
            $data = $request->validated();
            $order = $this->orderRepository->getById($id);
            $orderHasItem = $this->orderItemRepository->where('order_id', $order->id)->first();

            if(!$orderHasItem){
                return response()->json(['message' => 'The order has no items yet.'], Response::HTTP_BAD_REQUEST); 
            }

            if($order->status === OrderConstants::ORDER_STATUS_CHECKOUT){
                return response()->json(['message' => 'The  order is already on checkout.'], Response::HTTP_BAD_REQUEST); 
            }

            $order->update($data);
            $order->refresh();

            $customer = $this->customerRepository->find($order->customer_id);
            $customer->notify(new OrderCheckoutMailNotification($customer));
            
            return response()->json(['data' => $order], Response::HTTP_OK); 
        } catch (\Throwable $th) {
            throw $th;
        }
        
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(int $id)
    {
        $orderHasItem = $this->orderItemRepository->where('order_id', $id)->first();

        if($orderHasItem){
            return response()->json(['message' => 'The order has items and cannot be destroyed.'], Response::HTTP_BAD_REQUEST); 
        }

        $this->orderRepository->destroy($id);
        return response()->json([], Response::HTTP_NO_CONTENT);
    }
}
