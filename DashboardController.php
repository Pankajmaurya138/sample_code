<?php

namespace App\Http\Controllers;

use App\Repositories\OrderRepository;
use App\Repositories\PaymentRepository;
use App\Repositories\RestaurantRepository;
use App\Repositories\UserRepository;
use App\Repositories\EarningRepository;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Earning;

class DashboardController extends Controller
{

    /** @var  OrderRepository */
    private $orderRepository;


    /**
     * @var UserRepository
     */
    private $userRepository;

    /** @var  RestaurantRepository */
    private $restaurantRepository;
    /** @var  PaymentRepository */
    private $paymentRepository;

     /** @var  EarningRepository */
    private $earningRepository;

    public function __construct(OrderRepository $orderRepo, UserRepository $userRepo, PaymentRepository $paymentRepo, RestaurantRepository $restaurantRepo,EarningRepository $earningRepo)
    {
        parent::__construct();
        $this->orderRepository = $orderRepo;
        $this->userRepository = $userRepo;
        $this->restaurantRepository = $restaurantRepo;
        $this->paymentRepository = $paymentRepo;
        $this->earningRepository = $earningRepo;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Order $order,Earning $earn)
    {

        if(auth()->user()->hasRole('admin')){
        $ordersCount = $this->orderRepository->count();
        $membersCount = $this->userRepository->count();
        $restaurantsCount = $this->restaurantRepository->count();
        $restaurants = $this->restaurantRepository->latest()->limit(4)->get();
        $earning = $this->earningRepository->all()->sum('admin_earning');
        $ajaxEarningUrl = route('payments.byMonth',['api_token'=>auth()->user()->api_token]);
//        dd($ajaxEarningUrl);
        return view('dashboard.index')
            ->with("ajaxEarningUrl", $ajaxEarningUrl)
            ->with("ordersCount", $ordersCount)
            ->with("restaurantsCount", $restaurantsCount)
            ->with("restaurants", $restaurants)
            ->with("membersCount", $membersCount)
            ->with("earning", $earning);
    }

    else{

        $ordersCount = $this->orderRepository->totalCount();


        $earning = $earn->newQuery()->with("restaurant")->latest('earnings.updated_at')
                ->leftJoin("user_restaurants", "user_restaurants.restaurant_id", "=", "earnings.restaurant_id")
                ->where('user_restaurants.user_id', auth()->id())
                ->select('earnings.*')->get()->map(function ($row) {
                $row['month'] = $row['created_at']->format('M');
                return $row;
            })->groupBy('month')->map(function ($row) {
                return $row->sum('total_restaurant_earning_exl_tax');
            });

        $earning = array_sum(array_values($earning->toArray()));


        $ajaxEarningUrl = route('payments.byMonth',['api_token'=>auth()->user()->api_token]);

        $restaurantsCount = $this->restaurantRepository->myRestaurants()->count();
        $restaurants = $this->restaurantRepository->myRestaurantsLatest();
//        dd($ajaxEarningUrl);
        return view('dashboard.index')
            ->with("ajaxEarningUrl", $ajaxEarningUrl)
            ->with("ordersCount", $ordersCount)
            ->with("earning", $earning)
            ->with("restaurantsCount", $restaurantsCount)
            ->with("restaurants", $restaurants);
    }
}


}//Dashboardcontroller close