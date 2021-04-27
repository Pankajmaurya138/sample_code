<?php

namespace App\Http\Controllers;

use App\DataTables\CouponDataTable;
use App\Http\Requests\CreateCouponRequest;
use App\Http\Requests\UpdateCouponRequest;
use App\Repositories\CouponRepository;
use App\Repositories\CustomFieldRepository;
use App\Criteria\Users\ClientsCriteria;
use Flash;
use App\Models\Coupon;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Notification;
use Prettus\Validator\Exceptions\ValidatorException;
use App\Repositories\UserRepository;
#loading Models 
use App\Models\ManageLoyaltyPoint;
use App\Models\UserCoupon;
use Carbon\Carbon;


class CouponController extends Controller
{
    /** @var  CouponRepository */
    private $couponRepository;

    /**
     * @var CustomFieldRepository
     */
    private $customFieldRepository;

    private $userRepository;

   

    public function __construct(CouponRepository $couponRepo, CustomFieldRepository $customFieldRepo,UserRepository $userRepo)
    {
        parent::__construct();
        $this->couponRepository = $couponRepo;
        $this->customFieldRepository = $customFieldRepo;
        $this->userRepository = $userRepo;

    }

    /**
     * Display a listing of the Coupon.
     *
     * @param CouponDataTable $couponDataTable
     * @return Response
     */
    public function index(CouponDataTable $couponDataTable)
    {
        return $couponDataTable->render('coupons.index');
    }

    /**
     * Show the form for creating a new coupon.
     *
     * @return Response
     */

   

    public function create(CouponDataTable $couponDataTable)
    {


        $hasCustomField = in_array($this->couponRepository->model(), setting('custom_field_models', []));
        if ($hasCustomField) {
            $customFields = $this->customFieldRepository->findByField('custom_field_model', $this->couponRepository->model());
            $html = generateCustomField($customFields);
        }
        


        return view('coupons.create')->with("customFields", isset($html) ? $html : false);
    }

    /**
     * Store a newly created Category in storage.
     *
     * @param CreateCouponRequest $request
     *
     * @return Response
     */
    public function store(CreateCouponRequest $request)
    {
        $input = $request->all();
         
         $checkCoupon = Coupon::where('coupon_code',$input['coupon_code'])->first();
        $checkName = Coupon::where('coupon_name',$input['coupon_name'])->first();

        if (!empty($checkCoupon)) {
            Flash::error('The coupon code has already been taken.');
            return redirect(route('coupons.create'));
        }

         if (!empty($checkName)) {
            Flash::error('The coupon name has already been taken.');
            return redirect(route('coupons.create'));
        }


        $input['expiry_date'] = ($input['expiry_date']!=null) ? implode("-", array_reverse(explode("/", $request->expiry_date)))  : null;



        $customFields = $this->customFieldRepository->findByField('custom_field_model', $this->couponRepository->model());
        try {
            $coupon = $this->couponRepository->create($input);
            $coupon->customFieldsValues()->createMany(getCustomFieldsValues($customFields, $request));

            $coupon_amount = ($input['discount_type']==2) ? strip_tags(getPrice($input['discount'])) : $input['discount'].'%';

            $coupon_gen_code =  $input['coupon_code'];
            
            foreach($request->users_group as $userId):

             

            $user = \App\Models\User::find($userId);
            $user_coupon = new UserCoupon;
            $user_coupon->user_id        = $user->id;
            $user_coupon->coupon_code    = $coupon_gen_code;
            $user_coupon->type           = 3;
            $user_coupon->save();
             sendCouponEmail($user->id,$coupon_gen_code);
             $notificationData = [
                                'coupon_code'  => $coupon_gen_code,
                                'coupon_type'  => 'general',
                                'discount_type' => ($input['discount_type']==2) ? 'flat' : 'percent',
                                'title' => "New Coupon Received",
                                'body' => "You have received a new coupon code " .$coupon_gen_code  . " and amount:" . strip_tags(getPrice($coupon_amount)),
                            ];

            Notification::send([$user], new \App\Notifications\AssignCoupon($notificationData));

        endforeach;

            
        } catch (ValidatorException $e) {
            Flash::error($e->getMessage());
        }

        Flash::success(__('lang.saved_successfully', ['operator' => 'Coupon']));

        return redirect(route('coupons.index'));
    }

    /**
     * Display the specified Category.
     *
     * @param int $id
     *
     * @return Response
     */
    public function show($id)
    {
        $coupon = $this->couponRepository->findWithoutFail($id);

        if (empty($coupon)) {
            Flash::error('Coupon not found');

            return redirect(route('coupons.index'));
        }

        return view('coupons.show')->with('coupon', $coupon);
    }

    /**
     * Show the form for editing the specified Coupon.
     *
     * @param int $id
     *
     * @return Response
     */
    public function edit($id)
    {
        $coupon = $this->couponRepository->findWithoutFail($id);


        if (empty($coupon)) {
            Flash::error(__('lang.not_found', ['operator' => __('lang.category')]));

            return redirect(route('coupon.index'));
        }
        $customFieldsValues = $coupon->customFieldsValues()->with('customField')->get();
        $customFields = $this->customFieldRepository->findByField('custom_field_model', $this->couponRepository->model());
        $hasCustomField = in_array($this->couponRepository->model(), setting('custom_field_models', []));
        if ($hasCustomField) {
            $html = generateCustomField($customFields, $customFieldsValues);
        }

        $selectedCoupon = $coupon->discount_type;

        $usersgroup = UserCoupon::where('coupon_code',$coupon->coupon_code)
        ->join('users','users.id','=','user_coupons.user_id')
        ->select('users.name')
        ->get()->pluck('name')->toArray();
   
         $usersgroupNames = implode(", ", array_values($usersgroup));

        return view('coupons.edit')->with('coupon', $coupon)->with("customFields", isset($html) ? $html : false)->with('selectedCoupon',$selectedCoupon)->with('usersgroupNames',$usersgroupNames );
    }

    /**
     * Update the specified Coupon in storage.
     *
     * @param int $id
     * @param UpdateCouponRequest $request
     *
     * @return Response
     */
    public function update($id, UpdateCouponRequest $request)
    {
        $coupon = $this->couponRepository->findWithoutFail($id);

        if (empty($coupon)) {
            Flash::error('Coupon not found');
            return redirect(route('coupons.index'));
        }
        $input = $request->all();

          
         if($coupon->coupon_name!=$input['coupon_name'])
         {
            $checkName = Coupon::where('coupon_name',$input['coupon_name'])->first();
        if (!empty($checkName)) {
            Flash::error('The coupon name has already been taken.');
            return redirect(route('coupons.edit', $coupon->id));
         } 
         }
        

        $input['expiry_date'] = ($input['expiry_date']!=null) ? implode("-", array_reverse(explode("/", $request->expiry_date)))  : null;

        $customFields = $this->customFieldRepository->findByField('custom_field_model', $this->couponRepository->model());
        try {
            $coupon = $this->couponRepository->update($input, $id);

            
            foreach (getCustomFieldsValues($customFields, $request) as $value) {
                $category->customFieldsValues()
                    ->updateOrCreate(['custom_field_id' => $value['custom_field_id']], $value);
            }

            $coupon_amount = ($input['discount_type']==2) ? strip_tags(getPrice($input['discount'])) : $input['discount'].'%';

            $coupon_gen_code =  $coupon->coupon_code;


            if($request->users_group!=null):
            
            foreach($request->users_group as $userId):

             

            $user = \App\Models\User::find($userId);
            $user_coupon = new UserCoupon;
            $user_coupon->user_id        = $user->id;
            $user_coupon->coupon_code    = $coupon_gen_code;
            $user_coupon->type           = 3;
            $user_coupon->save();
            sendCouponEmail($user->id,$coupon_gen_code);
             $notificationData = [
                                'coupon_code'  => $coupon_gen_code,
                                'coupon_type'  => 'general',
                                'discount_type' => ($input['discount_type']==2) ? 'flat' : 'percent',
                                'title' => "New Coupon Received",
                                'body' => "You have received a new coupon code " .$coupon_gen_code  . " and amount:" . strip_tags(getPrice($coupon_amount)),
                            ];

            Notification::send([$user], new \App\Notifications\AssignCoupon($notificationData));

        endforeach;
         endif;
        } catch (ValidatorException $e) {
            Flash::error($e->getMessage());
        }

        Flash::success(__('lang.updated_successfully', ['operator' => 'Coupon']));

        return redirect(route('coupons.index'));
    }

    /**
     * Remove the specified Coupon from storage.
     *
     * @param int $id
     *
     * @return Response
     */
    public function destroy($id)
    {
        $coupon = $this->couponRepository->findWithoutFail($id);

        if (empty($coupon)) {
            Flash::error('Coupon not found');

            return redirect(route('coupon.index'));
        }

        $this->couponRepository->delete($id);

        Flash::success(__('lang.deleted_successfully', ['operator' => 'Coupon']));

        return redirect(route('coupons.index'));
    }

    public function generateCouponCode()
    {
        return response()->json([
         'status'=>200,
         'coupon_code' => generateReferCode('coupon_code')
        ]);
    }


    public function loyalty(Request $request)
    {
           if($request->isMethod("POST")):
             $rules = [
                        'points_treshold' => 'required|integer',
                        'points_per_euro'=>'required|regex:/^\d+(\.\d{1,2})?$/',
                        'coupon_amount'=>'required|integer',

                       ];

              try {
                $this->validate($request,$rules);
                $id = ManageLoyaltyPoint::first();
                $loyalty = ManageLoyaltyPoint::find($id->id);
                $loyalty->points_treshold  = $request->points_treshold;
                $loyalty->points_per_euro  = $request->points_per_euro;
                 $loyalty->coupon_amount  = $request->coupon_amount;
                $loyalty->save();

            
        } catch (ValidatorException $e) {
            Flash::error($e->getMessage());
        }
       Flash::success(__('lang.updated_successfully', ['operator' => 'Loyalty Points']));

        return redirect(route('couponPreferences/loyalty'));

           else:
              $data = ManageLoyaltyPoint::first();
             return view('coupons.loyalty',['row'=>$data]);
          endif;  
       
        
    }


    public function referral(Request $request)
    {
           if($request->isMethod("POST")):

             $rules = [
                        'referral_amount' => 'required|numeric',
                        'expiry_date' => 'nullable|date_format:d/m/Y',
                       ];

              try {
                $this->validate($request,$rules);
                $expiry_date = ($request->expiry_date!=null) ? implode("-", array_reverse(explode("/", $request->expiry_date)))  : null;
                $refer               = Coupon::find(1);
                $refer->discount     = $request->referral_amount;
                $refer->expiry_date  = $expiry_date;
                $refer->save();

            
        } catch (ValidatorException $e) {
            Flash::error($e->getMessage());
        }
       Flash::success(__('lang.updated_successfully', ['operator' => 'Referral Amount']));

        return redirect(route('couponPreferences/referral'));

           else:
              $data = Coupon::where('id',1)->first();
             return view('coupons.referral',['row'=>$data]);
          endif;  
       
        
    }


    public function usersgroup(Request $request)
    {

       
        $userIds = $this->userRepository->getByCriteria(new ClientsCriteria())->pluck('id');

          
          $days = $request->days;
          $group = null;
          $groupDate = null;
          $current_date  = null;

          if($days!="all")
          {
          $group = Carbon::now()->subDays($days);
          $groupDate = $group->toDateString();
          $current_date  = date('Y-m-d');
          }
         
         
         $userIdsCheck=[0];
         if($request->action == 'edit')
         {
            //checking user already has coupon 
            $userAlreadyCoupon=\App\Models\UserCoupon::where(['coupon_code'=>$request->coupon_code,'used'=>0])->pluck('user_id');
            $userIdsCheck=$userAlreadyCoupon;

         }
         
      
        $usersGroup = null;

          if($days!="all")
          {
             $usersGroup = User::whereDate('created_at','>=', $groupDate)->whereDate('created_at','<=',$current_date)->whereIn('id',$userIds)->whereNotIn('id',$userIdsCheck)->get()->pluck('name','id');
          }
          else{
              $usersGroup = User::whereIn('id',$userIds)->whereNotIn('id',$userIdsCheck)->get()->pluck('name','id');
          }

         
         $usersGroupSelected=null;
            if( is_array($request->usersSelected) && count($request->usersSelected)>0)
            {
                  $usersGroupSelected = $request->usersSelected;
            }
          
         
    
        return view('coupons.usersgroup')->with('usersGroup',$usersGroup)->with('usersGroupSelected',$usersGroupSelected);
    }//method closeusersgroup 


    
   
}
