<?php

namespace App\Http\Controllers;

use App\DataTables\DeliveryPreferenceDataTable;
use App\Http\Requests\CreateDeliveryPreferenceRequest;
use App\Http\Requests\UpdateDeliveryPreferenceRequest;
use App\Repositories\DeliveryPreferenceRepository;
use App\Repositories\CustomFieldRepository;
use Flash;
use App\Models\DeliveryPreference;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Prettus\Validator\Exceptions\ValidatorException;

class DeliveryPreferenceController extends Controller
{
    /** @var  CouponRepository */
    private $deliveryPreferenceRepository;

    /**
     * @var CustomFieldRepository
     */
    private $customFieldRepository;

   

    public function __construct(DeliveryPreferenceRepository $deliveryPreferenceRepo, CustomFieldRepository $customFieldRepo)
    {
        parent::__construct();
        $this->deliveryPreferenceRepository = $deliveryPreferenceRepo;
        $this->customFieldRepository = $customFieldRepo;

    }

    /**
     * Display a listing of the Coupon.
     *
     * @param CouponDataTable $couponDataTable
     * @return Response
     */
    public function index(DeliveryPreferenceDataTable $deliveryPreferenceDataTable)
    {
        return $deliveryPreferenceDataTable->render('delivery_preferences.index');
    }

    /**
     * Show the form for creating a new coupon.
     *
     * @return Response
     */
    public function create()
    {

        $hasCustomField = in_array($this->deliveryPreferenceRepository->model(), setting('custom_field_models', []));
        if ($hasCustomField) {
            $customFields = $this->deliveryPreferenceRepository->findByField('custom_field_model', $this->deliveryPreferenceRepository->model());
            $html = generateCustomField($customFields);
        }
        return view('delivery_preferences.create')->with("customFields", isset($html) ? $html : false);
    }

    /**
     * Store a newly created Category in storage.
     *
     * @param CreateCouponRequest $request
     *
     * @return Response
     */
    public function store(CreateDeliveryPreferenceRequest $request)
    {
        $input = $request->all();
         
         $checkdeliveryPreference = DeliveryPreference::where('splint_id',$input['splint_id'])->first();
        if (!empty($checkdeliveryPreference)) {
            Flash::error('This Splint Name  has already been added.');
            return redirect(route('deliveryPreferences.create'));
        }

        $input['type'] = implode(",",$request->type);



        $customFields = $this->customFieldRepository->findByField('custom_field_model', $this->deliveryPreferenceRepository->model());
        try {
            $deliveryPreference = $this->deliveryPreferenceRepository->create($input);
            $deliveryPreference->customFieldsValues()->createMany(getCustomFieldsValues($customFields, $request));
            
        } catch (ValidatorException $e) {
            Flash::error($e->getMessage());
        }

        Flash::success(__('lang.saved_successfully', ['operator' => 'Delivery Preference']));

        return redirect(route('deliveryPreferences.index'));
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
        $deliveryPreference = $this->deliveryPreference->findWithoutFail($id);

        if (empty($deliveryPreference)) {
            Flash::error('Delivery Preference not found');

            return redirect(route('delivery_preferences.index'));
        }

        return view('delivery_preferences.show')->with('deliveryPreference', $deliveryPreference);
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
        $deliveryPreference = $this->deliveryPreferenceRepository->findWithoutFail($id);


        if (empty($deliveryPreference)) {
            Flash::error(__('lang.not_found', ['operator' => 'Delivery Preference']));

            return redirect(route('coupon.index'));
        }
        $customFieldsValues = $deliveryPreference->customFieldsValues()->with('customField')->get();
        $customFields = $this->customFieldRepository->findByField('custom_field_model', $this->deliveryPreferenceRepository->model());
        $hasCustomField = in_array($this->deliveryPreferenceRepository->model(), setting('custom_field_models', []));
        if ($hasCustomField) {
            $html = generateCustomField($customFields, $customFieldsValues);
        }

        return view('delivery_preferences.edit')->with('deliveryPreference', $deliveryPreference)->with("customFields", isset($html) ? $html : false);
    }

    /**
     * Update the specified Coupon in storage.
     *
     * @param int $id
     * @param UpdateCouponRequest $request
     *
     * @return Response
     */
    public function update($id, UpdateDeliveryPreferenceRequest $request)
    {
        $deliveryPreference = $this->deliveryPreferenceRepository->findWithoutFail($id);

        if (empty($deliveryPreference)) {
            Flash::error('Delivery Preference not found');
            return redirect(route('deliveryPreferences.index'));
        }
        $input = $request->all();

        //  if($deliveryPreference->splint_id!=$input['splint_id'])
        //  {
        //     $checkdeliveryPreference = DeliveryPreference::where('splint_id',$input['splint_id'])->first();
        // if (!empty($checkdeliveryPreference)) {
        //     Flash::error('This Splint Name  has already been added.');
        //     return redirect(route('deliveryPreferences.edit', $checkdeliveryPreference->id));
        //  } 
        //  }
        

       $input['type'] = implode(",",$request->type);
        $customFields = $this->customFieldRepository->findByField('custom_field_model', $this->deliveryPreferenceRepository->model());
        try {
            $coupon = $this->deliveryPreferenceRepository->update($input, $id);

            
            foreach (getCustomFieldsValues($customFields, $request) as $value) {
                $category->customFieldsValues()
                    ->updateOrCreate(['custom_field_id' => $value['custom_field_id']], $value);
            }
        } catch (ValidatorException $e) {
            Flash::error($e->getMessage());
        }

        Flash::success(__('lang.updated_successfully', ['operator' => 'Delivery Preference']));

        return redirect(route('deliveryPreferences.index'));
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
        $coupon = $this->deliveryPreferenceRepository->findWithoutFail($id);

        if (empty($coupon)) {
            Flash::error('Coupon not found');

            return redirect(route('deliveryPreferences.index'));
        }

        $this->couponRepository->delete($id);

        Flash::success(__('lang.deleted_successfully', ['operator' => 'Delivery Preference']));

        return redirect(route('deliveryPreferences.index'));
    }

    
   
}
