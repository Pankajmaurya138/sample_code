<?php

namespace App\Http\Controllers;

use App\DataTables\CouponLogDataTable;
use App\Models\CouponLog;
use App\Repositories\CustomFieldRepository;
use Illuminate\Http\Request;
use Flash;


class CouponLogController extends Controller
{

     /**
     * @var CustomFieldRepository
     */
    private $customFieldRepository;

   

    public function __construct(CustomFieldRepository $customFieldRepo)
    {
        parent::__construct();
        $this->customFieldRepository = $customFieldRepo;

    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
     public function index(CouponLogDataTable $couponLogDataTable)
    {
        return $couponLogDataTable->render('coupons_usage.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\CouponLog  $couponLog
     * @return \Illuminate\Http\Response
     */
    public function show($id,CouponLog $model)
    {
          $data   =  $model->newQuery()
            ->leftJoin('users','users.id',   '=','coupon_logs.user_id')
            ->leftJoin('orders','orders.id','=','coupon_logs.order_id')
            ->leftJoin('payments','payments.id','=','orders.payment_id')
            ->leftJoin('order_statuses','order_statuses.id','=','orders.order_status_id')
            ->leftJoin('coupons','coupons.coupon_code','=','coupon_logs.coupon_code')
            ->where('coupon_logs.id',$id)
            ->select('coupon_logs.*',"users.email","payments.price",'order_statuses.status','coupons.id as coupon_id','coupons.expiry_date')->first();

             if (empty($data)) {
            Flash::error('Coupon Usage not found');

            return redirect(route('couponUsage.index'));
         }

         $ref = \App\Models\User::find($data->user_id);

          $data['referred_user'] = '';
         if(!empty($ref->refer_id))
         {
               $referredUser =   \App\Models\User::find($ref->refer_id);


              $data['referred_user'] = $referredUser->name;   
         }
          


        return view('coupons_usage.show',['row'=>$data]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\CouponLog  $couponLog
     * @return \Illuminate\Http\Response
     */
    public function edit(CouponLog $couponLog)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\CouponLog  $couponLog
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, CouponLog $couponLog)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\CouponLog  $couponLog
     * @return \Illuminate\Http\Response
     */
    public function destroy(CouponLog $couponLog)
    {
        //
    }
}
