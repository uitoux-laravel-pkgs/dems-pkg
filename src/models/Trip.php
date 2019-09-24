<?php

namespace Uitoux\EYatra;

//use App\Mail\TripNotificationMail;
use App\User;
use Auth;
use Carbon\Carbon;
use DateInterval;
use DatePeriod;
use DateTime;
use DB;
use Entrust;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Mail;
use Validator;

class Trip extends Model {
	use SoftDeletes;

	protected $fillable = [
		'id',
		'number',
		'employee_id',
		'purpose_id',
		'description',
		'status_id',
		'advance_received',
		'claim_amount',
		'claimed_date',
		'paid_amount',
		'payment_date',
		'start_date',
		'end_date',
		'trip_type',
		'created_by',
	];

	public function getStartDateAttribute($date) {
		return empty($date) ? '' : date('d-m-Y', strtotime($date));
	}
	public function getClaimedDateAttribute($date) {
		return empty($date) ? '' : date('d-m-Y', strtotime($date));
	}
	public function getEndDateAttribute($date) {
		return empty($date) ? '' : date('d-m-Y', strtotime($date));
	}

	public function setStartDateAttribute($date) {
		return $this->attributes['start_date'] = empty($date) ? date('Y-m-d') : date('Y-m-d', strtotime($date));
	}
	public function setEndDateAttribute($date) {
		return $this->attributes['end_date'] = empty($date) ? date('Y-m-d') : date('Y-m-d', strtotime($date));

	}
	public function getCreatedAtAttribute($value) {
		return empty($value) ? '' : date('d-m-Y', strtotime($value));
	}

	public function company() {
		return $this->belongsTo('App\Company');
	}

	public function visits() {
		return $this->hasMany('Uitoux\EYatra\Visit')->orderBy('id');
	}

	public function selfVisits() {
		return $this->hasMany('Uitoux\EYatra\Visit')->where('booking_method_id', 3040)->orderBy('id', 'ASC'); //Employee visits
	}

	public function agentVisits() {
		return $this->hasMany('Uitoux\EYatra\Visit')->where('booking_method_id', 3042)->orderBy('id', 'ASC');
	}

	public function cliam() {
		return $this->hasOne('Uitoux\EYatra\EmployeeClaim');
	}

	public function advanceRequestPayment() {
		return $this->hasOne('Uitoux\EYatra\Payment', 'entity_id')->where('payment_of_id', 3250); //Employee Advance Claim
	}

	public function employee() {
		return $this->belongsTo('Uitoux\EYatra\Employee')->withTrashed();
	}

	public function purpose() {
		return $this->belongsTo('Uitoux\EYatra\Entity', 'purpose_id');
	}

	public function status() {
		return $this->belongsTo('Uitoux\EYatra\Config', 'status_id');
	}

	public function advanceRequestStatus() {
		return $this->belongsTo('Uitoux\EYatra\Config', 'advance_request_approval_status_id');
	}

	public function lodgings() {
		return $this->hasMany('Uitoux\EYatra\Lodging');
	}

	public function boardings() {
		return $this->hasMany('Uitoux\EYatra\Boarding');
	}

	public function localTravels() {
		return $this->hasMany('Uitoux\EYatra\LocalTravel');
	}

	public static function create($employee, $trip_number, $faker, $trip_status_id, $admin) {
		$trip = new Trip();
		$trip->employee_id = $employee->id;
		$trip->number = 'TRP' . $trip_number++;
		$trip->purpose_id = $employee->grade->tripPurposes()->inRandomOrder()->first()->id;
		$trip->description = $faker->sentence;
		$trip->manager_id = $employee->reporting_to_id;
		$trip->status_id = $trip_status_id; //NEW
		$trip->advance_received = $faker->randomElement([0, 500, 100, 1500, 2000]);
		$trip->created_by = $admin->id;
		$trip->save();
		return $trip;

	}

	public static function saveTrip($request) {
		try {
			//validation
			$validator = Validator::make($request->all(), [
				'purpose_id' => [
					'required',
				],
				'visits' => [
					'required',
				],
			]);
			if ($validator->fails()) {
				return response()->json([
					'success' => false,
					'message' => 'Validation Errors',
					'errors' => $validator->errors()->all(),
				]);
			}

			DB::beginTransaction();
			if (!$request->id) {
				$trip = new Trip;
				$trip->created_by = Auth::user()->id;
				$trip->created_at = Carbon::now();
				$trip->updated_at = NULL;
				$activity['activity'] = "add";
			} else {
				$trip = Trip::find($request->id);

				$trip->updated_by = Auth::user()->id;
				$trip->updated_at = Carbon::now();
				$trip->visits()->delete();
				$activity['activity'] = "edit";

			}
			$employee = Employee::where('id', Auth::user()->entity->id)->first();

			if ($request->advance_received) {
				$trip->advance_received = $request->advance_received;
				if ($employee->self_approve == 1) {
					$trip->advance_request_approval_status_id = 3261;
				} else {
					$trip->advance_request_approval_status_id = 3260;
				}
			}
			$trip->fill($request->all());
			$trip->number = 'TRP' . rand();
			$trip->employee_id = Auth::user()->entity->id;
			// dd(Auth::user(), );
			$trip->manager_id = Auth::user()->entity->reporting_to_id;
			if ($employee->self_approve == 1) {
				$trip->status_id = 3028; //Manager Approved
			} else {
				$trip->status_id = 3021; //Manager Approval Pending
			}
			$trip->save();

			$trip->number = 'TRP' . $trip->id;
			$trip->save();
			$activity['entity_id'] = $trip->id;
			$activity['entity_type'] = 'trip';
			$activity['details'] = 'Trip is Added';
			//SAVING VISITS
			if ($request->visits) {
				$visit_count = count($request->visits);
				$i = 0;
				foreach ($request->visits as $key => $visit_data) {
					//if no agent found display visit count
					// dd(Auth::user()->entity->outlet->address);
					$visit_count = $i + 1;
					if ($i == 0) {
						$from_city_id = Auth::user()->entity->outlet->address->city->id;
					} else {
						$previous_value = $request->visits[$key - 1];
						$from_city_id = $previous_value['to_city_id'];
					}
					if ($from_city_id == $visit_data['to_city_id']) {
						return response()->json(['success' => false, 'errors' => "From City and To City should not be same,please choose another To city"]);
					}
					$visit = new Visit;
					$visit->prefered_departure_time = date('H:i:s', strtotime($visit_data['prefered_departure_time']));
					$visit->fill($visit_data);
					// dump($visit_data['date']);
					// dump(Carbon::createFromFormat('d/m/Y', $visit_data['date']));
					// $visit->date = date('Y-m-d', strtotime($visit_data['date']));
					$visit->departure_date = date('Y-m-d', strtotime($visit_data['date']));
					// dd($visit);
					$visit->from_city_id = $from_city_id;
					$visit->trip_id = $trip->id;
					//booking_method_name - changed for API - Dont revert - ABDUL
					$visit->booking_method_id = $visit_data['booking_method_name'] == 'Self' ? 3040 : 3042;
					$visit->booking_status_id = 3060; //PENDING
					$visit->status_id = 3220; //NEW
					$visit->manager_verification_status_id = 3080; //NEW
					if ($visit_data['booking_method_name'] == 'Agent') {
						$state = $trip->employee->outlet->address->city->state;

						$agent = $state->agents()->where('company_id', Auth::user()->company_id)->withPivot('travel_mode_id')->where('travel_mode_id', $visit_data['travel_mode_id'])->first();

						if (!$agent) {
							return response()->json(['success' => false, 'errors' => ['No agent found for visit - ' . $visit_count], 'message' => 'No agent found for visit - ' . $visit_count]);
						}
						$visit->agent_id = $agent->id;
					}
					$visit->save();
					$i++;
				}
			}
			if (!$request->id) {
				self::sendTripNotificationMail($trip);
			}
			// $activity_log = ActivityLog::saveLog($activity);
			DB::commit();
			return response()->json(['success' => true, 'message' => 'Trip saved successfully!', 'trip' => $trip]);
		} catch (Exception $e) {
			DB::rollBack();
			return response()->json(['success' => false, 'errors' => ['Exception Error' => $e->getMessage()]]);
		}
	}

	public static function getViewData($trip_id) {
		$data = [];
		$trip = Trip::with([
			'visits' => function ($q) {
				$q->orderBy('visits.id');
			},
			'visits.fromCity',
			'visits.toCity',
			'visits.travelMode',
			'visits.bookingMethod',
			'visits.bookingStatus',
			'visits.agent',
			'visits.agent.user',
			'visits.status',
			'visits.managerVerificationStatus',
			'employee',
			'employee.user',
			'employee.designation',
			'employee.grade',
			'employee.grade_details',
			'purpose',
			'status',
		])
			->find($trip_id);
		// dd($trip->employee->grade_details->claim_active_days);
		if (!$trip) {
			$data['success'] = false;
			$data['message'] = 'Trip not found';
			$data['errors'] = ['Trip not found'];
			return response()->json($data);
		}

		if (!Entrust::can('view-all-trips') && $trip->employee_id != Auth::user()->entity_id) {
			$data['success'] = false;
			$data['message'] = 'Trip belongs to you';
			$data['errors'] = ['Trip belongs to you'];
			return response()->json($data);
		}

		$start_date = $trip->visits()->select(DB::raw('DATE_FORMAT(MIN(visits.departure_date),"%d/%m/%Y") as start_date'))->first();
		$end_date = $trip->visits()->select(DB::raw('DATE_FORMAT(MAX(visits.departure_date),"%d/%m/%Y") as end_date'))->first();
		$days = Trip::select(DB::raw('DATEDIFF(end_date,start_date)+1 as days'))->where('id', $trip_id)->first();
		$trip->days = $days->days;
		$trip->purpose_name = $trip->purpose->name;
		$trip->status_name = $trip->status->name;
		$current_date = date('d-m-Y');
		$claim_date = $trip->employee->grade_details->claim_active_days ? $trip->employee->grade_details->claim_active_days : 5;
		$claim_last_date = date('d-m-Y', strtotime("+" . $claim_date . " day", strtotime($trip->end_date)));
		// dump($trip->end_date);
		// dump($claim_last_date);
		// dump($current_date);
		if ($current_date > $claim_last_date) {
			$data['claim_status'] = 0;
		} else {
			$data['claim_status'] = 1;
		}
		// dd($current_date);
		$data['trip'] = $trip;
		$data['success'] = true;
		return response()->json($data);

	}

	public static function getTripFormData($trip_id) {
		$data = [];
		if (!$trip_id) {
			$data['action'] = 'New';
			$trip = new Trip;
			$visit = new Visit;
			//Changed for API. dont revert. - Abdul
			$visit->booking_method = new Config(['name' => 'Self']);
			$trip->visits = [$visit];
			$trip->trip_type = '';
			$trip->from_city_details = DB::table('ncities')->leftJoin('nstates', 'ncities.state_id', 'nstates.id')->select('ncities.id', DB::raw('CONCAT(ncities.name,"/",nstates.name) as name'))->where('ncities.id', Auth::user()->entity->outlet->address->city_id)->first();

			$data['success'] = true;
		} else {
			$data['action'] = 'Edit';
			$data['success'] = true;

			$trip = Trip::find($trip_id);
			$trip->visits = $t_visits = $trip->visits;
			//dd($trip->visits->get);
			foreach ($t_visits as $key => $t_visit) {
				$b_name = Config::where('id', $trip->visits[$key]->booking_method_id)->select('name')->first();
				$trip->visits[$key]->booking_method_name = $b_name->name;
				$key_val = ($key - 1 < 0) ? 0 : $key - 1;
				$trip->visits[$key]->to_city_details = DB::table('ncities')->leftJoin('nstates', 'ncities.state_id', 'nstates.id')->select('ncities.id', DB::raw('CONCAT(ncities.name,"-",nstates.name) as name'))->where('ncities.id', $trip->visits[$key]->to_city_id)->first();
				$trip->visits[$key]->from_city_details = DB::table('ncities')->leftJoin('nstates', 'ncities.state_id', 'nstates.id')->select('ncities.id', DB::raw('CONCAT(ncities.name,"-",nstates.name) as name'))->where('ncities.id', $trip->visits[$key_val]->from_city_id)->first();
			}

			//dd($t_visits, $trip->visits, $key_val);

			if (!$trip) {
				$data['success'] = false;
				$data['message'] = 'Trip not found';
			}
		}
		$grade = Auth::user()->entity;
		//dd('ss', Auth::user()->id, Auth::user()->entity->outlet, Auth::user()->entity->outlet->address);
		$grade_eligibility = DB::table('grade_advanced_eligibility')->select('advanced_eligibility')->where('grade_id', $grade->grade_id)->first();
		if ($grade_eligibility) {
			$data['advance_eligibility'] = $grade_eligibility->advanced_eligibility;
		} else {
			$data['advance_eligibility'] = '';
		}
		//dd(Auth::user()->entity->outlet->address);

		$data['extras'] = [
			// 'purpose_list' => Entity::uiPurposeList(),
			'purpose_list' => DB::table('grade_trip_purpose')->select('trip_purpose_id', 'entities.name', 'entities.id')->join('entities', 'entities.id', 'grade_trip_purpose.trip_purpose_id')->where('grade_id', $grade->grade_id)->where('entities.company_id', Auth::user()->company_id)->get()->prepend(['id' => '', 'name' => 'Select Purpose']),
			// 'travel_mode_list' => Entity::uiTravelModeList(),
			'travel_mode_list' => DB::table('grade_travel_mode')->select('travel_mode_id', 'entities.name', 'entities.id')->join('entities', 'entities.id', 'grade_travel_mode.travel_mode_id')->where('grade_id', $grade->grade_id)->where('entities.company_id', Auth::user()->company_id)->get(),
			'city_list' => NCity::getList(),
			'employee_city' => Auth::user()->entity->outlet->address->city,
			'frequently_travelled' => Visit::join('ncities', 'ncities.id', 'visits.to_city_id')->where('ncities.company_id', Auth::user()->company_id)->select('ncities.id', 'ncities.name')->distinct()->limit(10)->get(),
		];
		$data['trip'] = $trip;

		return response()->json($data);
	}

	public static function getEmployeeList($request) {
		// dd(Auth::user()->company_id);
		$trips = Trip::from('trips')
			->join('visits as v', 'v.trip_id', 'trips.id')
			->join('ncities as c', 'c.id', 'v.from_city_id')
			->join('employees as e', 'e.id', 'trips.employee_id')
			->join('entities as purpose', 'purpose.id', 'trips.purpose_id')
			->join('configs as status', 'status.id', 'trips.status_id')
			->join('users as u', 'u.entity_id', 'e.id')
			->leftJoin('ey_employee_claims as claim', 'claim.trip_id', 'trips.id')

			->select(
				'trips.id',
				'trips.number',
				DB::raw('CONCAT(u.name," ( ",e.code," ) ") as ecode'),
				DB::raw('GROUP_CONCAT(DISTINCT(c.name)) as cities'),
				'trips.start_date',
				'trips.end_date',
				// DB::raw('DATE_FORMAT(MIN(v.departure_date),"%d/%m/%Y") as start_date'),
				// DB::raw('DATE_FORMAT(MAX(v.departure_date),"%d/%m/%Y") as end_date'),
				DB::raw('FORMAT(claim.total_amount,2) as claim_amount'),
				//Changed to purpose_name. do not revert - Abdul
				'purpose.name as purpose_name',
				'trips.advance_received',
				'trips.status_id as status_id',
				'status.name as status_name',
				DB::raw('DATE_FORMAT(MAX(trips.created_at),"%d/%m/%Y %h:%i %p") as date')
			)
			->where('u.user_type_id', 3121)
			->where('e.company_id', Auth::user()->company_id)
			->groupBy('trips.id')
			->orderBy('trips.created_at', 'desc')
		;
		if (!Entrust::can('view-all-trips')) {
			$trips->where('trips.employee_id', Auth::user()->entity_id);
		}

		//FILTERS
		if ($request->number) {
			$trips->where('trips.number', 'like', '%' . $request->number . '%');
		}
		if ($request->from_date && $request->to_date) {
			$trips->where('v.departure_date', '>=', $request->from_date);
			$trips->where('v.departure_date', '<=', $request->to_date);
		} else {
			$today = Carbon::today();
			$from_date = $today->copy()->subMonths(3);
			$to_date = $today->copy()->addMonths(3);
			$trips->where('v.departure_date', '>=', $from_date);
			$trips->where('v.departure_date', '<=', $to_date);
		}

		if ($request->status_ids && $request->status_ids[0]) {
			$status_ids = explode(',', $request->status_ids[0]);
			$trips->whereIn('trips.status_id', $status_ids);
		} else {
			$trips->whereNotIn('trips.status_id', [3026]);
		}
		if ($request->purpose_ids && $request->purpose_ids[0]) {
			$purpose_ids = explode(',', $request->purpose_ids[0]);
			$trips->whereIn('trips.purpose_id', $purpose_ids);
		}
		if ($request->from_city_id) {
			$trips->whereIn('v.from_city_id', $request->from_city_id);
		}
		if ($request->to_city_id) {
			$trips->whereIn('v.to_city_id', $request->to_city_id);
		}
		return $trips;
	}

	public static function getVerficationPendingList($r) {
		// dd($r->all());
		/*if(isset($r->period))
		{
		$date = explode(' to ', $r->period);
		$from_date = $date[0];
		$to_date = $date[1];
		dd($from_date,$to_date);
		$from_date = date('Y-m-d', strtotime($from_date));
		$to_date = date('Y-m-d', strtotime($to_date));
		 */
		//dd('d');
		$trips = Trip::from('trips')
			->join('visits as v', 'v.trip_id', 'trips.id')
			->join('ncities as c', 'c.id', 'v.from_city_id')
			->join('employees as e', 'e.id', 'trips.employee_id')
			->join('entities as purpose', 'purpose.id', 'trips.purpose_id')
			->join('configs as status', 'status.id', 'trips.status_id')
			->leftJoin('users', 'users.entity_id', 'e.id')
			->select(
				'trips.id',
				'trips.number',
				'e.code as ecode',
				'users.name as ename',
				'trips.start_date',
				'trips.end_date',
				DB::raw('GROUP_CONCAT(DISTINCT(c.name)) as cities'),
				'purpose.name as purpose',
				DB::raw('FORMAT(trips.advance_received,2,"en_IN") as advance_received'),
				'trips.created_at',
				//DB::raw('DATE_FORMAT(trips.created_at,"%d/%m/%Y") as created_at'),
				'status.name as status'

			)
			->where('users.user_type_id', 3121)
			->where('trips.status_id', 3021) //MANAGER APPROVAL PENDING
			->groupBy('trips.id')
			->orderBy('trips.created_at', 'desc')
			->orderBy('trips.status_id', 'desc')
			->where(function ($query) use ($r) {
				if ($r->get('employee_id')) {
					$query->where("e.id", $r->get('employee_id'))->orWhere(DB::raw("-1"), $r->get('employee_id'));
				}
			})
			->where(function ($query) use ($r) {
				if ($r->get('purpose_id')) {
					$query->where("purpose.id", $r->get('purpose_id'))->orWhere(DB::raw("-1"), $r->get('purpose_id'));
				}
			})
			->where(function ($query) use ($r) {
				if ($r->get('status_id')) {
					$query->where("status.id", $r->get('status_id'))->orWhere(DB::raw("-1"), $r->get('status_id'));
				}
			})
		/*->where(function ($query) use ($r) {
			if ($r->get('period')) {
			$query->whereDate('v.date',">=",$from_date)->whereDate('v.date',"<=",$to_date);

			}
		*/
		;
		if (!Entrust::can('verify-all-trips')) {
			$now = date('Y-m-d');
			$sub_employee_id = AlternateApprove::select('employee_id')
				->where('from', '<=', $now)
				->where('to', '>=', $now)
				->where('alternate_employee_id', Auth::user()->entity_id)
				->get()
				->toArray();
			//dd($sub_employee_id);
			$ids = array_column($sub_employee_id, 'employee_id');
			array_push($ids, Auth::user()->entity_id);
			if (count($sub_employee_id) > 0) {
				$trips->whereIn('trips.manager_id', $ids); //Alternate MANAGER
			} else {
				$trips->where('trips.manager_id', Auth::user()->entity_id); //MANAGER
			}

			//$trips->where('trips.manager_id', Auth::user()->entity_id);
		}

		return $trips;
	}

	// public static function saveTripVerification($r) {
	// 	$trip = Trip::find($r->trip_id);
	// 	if (!$trip) {
	// 		return response()->json(['success' => false, 'errors' => ['Trip not found']]);
	// 	}

	// 	if (!Entrust::can('trip-verification-all') && $trip->manager_id != Auth::user()->entity_id) {
	// 		return response()->json(['success' => false, 'errors' => ['You are nor authorized to view this trip']]);
	// 	}

	// 	$trip->status_id = 3021;
	// 	$trip->save();

	// 	$trip->visits()->update(['manager_verification_status_id' => 3080]);
	// 	return response()->json(['success' => true]);
	// }

	public static function deleteTrip($trip_id) {

		//CHECK IF AGENT BOOKED TRIP VISITS
		$agent_visits_booked = Visit::where('trip_id', $trip_id)->where('booking_method_id', 3042)->where('booking_status_id', 3061)->first();
		if ($agent_visits_booked) {
			return response()->json(['success' => false, 'errors' => ['Trip cannot be deleted']]);
		}
		//CHECK IF STATUS IS NEW OR MANAGER REJECTED OR MANAGER APPROVAL PENDING
		$status_exist = Trip::where('id', $trip_id)->whereIn('status_id', [3020, 3021, 3022])->first();
		if (!$status_exist) {
			return response()->json(['success' => false, 'errors' => ['Manager Approved so this trip cannot be deleted']]);
		}
		$trip = Trip::where('id', $trip_id)->first();

		$activity['entity_id'] = $trip->id;
		$trip = $trip->forceDelete();

		//$trip = Trip::where('id', $trip_id)->forceDelete();
		$activity['entity_type'] = 'trip';
		$activity['details'] = 'Trip is Deleted';
		$activity['activity'] = "delete";
		//dd($activity);
		$activity_log = ActivityLog::saveLog($activity);

		if (!$trip) {
			return response()->json(['success' => false, 'errors' => ['Trip not found']]);
		}
		return response()->json(['success' => true]);

	}

	public static function cancelTrip($trip_id) {

		$trip = Trip::find($trip_id);
		if (!$trip) {
			return response()->json(['success' => false, 'errors' => ['Trip not found']]);
		}

		$trip->status_id = 3062;
		$trip->save();

		$activity['entity_id'] = $trip_id;

		$activity['entity_type'] = 'trip';
		$activity['details'] = 'Trip is Cancelled';
		$activity['activity'] = "cancel";
		//dd($activity);
		$activity_log = ActivityLog::saveLog($activity);
		$visit = Visit::where('trip_id', $trip_id)->update(['status_id' => 3221]);

		return response()->json(['success' => true]);
	}

	public static function deleteVisit($visit_id) {
		if ($visit_id) {
			$agent_visits_booked = Visit::where('id', $visit_id)->where('booking_method_id', 3042)->where('booking_status_id', 3061)->first();
			if ($agent_visits_booked) {
				return response()->json(['success' => false, 'errors' => ['Visit Cannot be Deleted']]);
			}
			$visit = Visit::where('id', $visit_id)->first();
			$activity['entity_id'] = $visit_id;
			$visit = $visit->forceDelete();
			$activity['entity_type'] = 'visit';
			$activity['details'] = 'Visit is Deleted';
			$activity['activity'] = "delete";

			$activity_log = ActivityLog::saveLog($activity);
			return response()->json(['success' => true]);
		} else {
			return response()->json(['success' => false, 'errors' => ['Visit Cannot be Deleted']]);
		}
	}

	public static function requestCancelVisitBooking($visit_id) {

		$visit = Visit::where('id', $visit_id)->update(['status_id' => 3221]);

		if (!$visit) {
			return response()->json(['success' => false, 'errors' => ['Booking Details not Found']]);
		}
		return response()->json(['success' => true]);
	}

	public static function cancelTripVisitBooking($visit_id) {
		if ($visit_id) {
			//CHECK IF AGENT BOOKED VISIT
			$agent_visits_booked = Visit::where('id', $visit_id)->where('booking_method_id', 3042)->where('booking_status_id', 3061)->first();
			if ($agent_visits_booked) {
				return response()->json(['success' => false, 'errors' => ['Visit cannot be deleted']]);
			}
			$visit = Visit::where('id', $visit_id)->first();
			$visit->booking_status_id = 3062; // Booking cancelled
			$visit->save();
			return response()->json(['success' => true]);
		} else {
			return response()->json(['success' => false, 'errors' => ['Bookings not cancelled']]);
		}
	}

	public static function getDashboardData() {
		$total_trips = Trip::where('employee_id', Auth::user()->entity_id)
			->count();

		$total_claims_pending = Trip::where('employee_id', Auth::user()->entity_id)->where('status_id', 3028)->where('end_date', '<', date('Y-m-d'))->count();

		$dashboard_details['total_trips'] = $total_trips;
		$dashboard_details['total_claims_pending'] = $total_claims_pending;
		return response()->json(['success' => true, 'dashboard_details' => $dashboard_details]);
	}
	public static function approveTrip($r) {
		$trip = Trip::find($r->trip_id);
		if (!$trip) {
			return response()->json(['success' => false, 'errors' => ['Trip not found']]);
		}
		$trip->status_id = 3028;
		$trip->save();
		$activity['entity_id'] = $trip->id;
		$activity['entity_type'] = 'trip';
		$activity['details'] = 'Trip is Approved by Manager';
		$activity['activity'] = "approve";
		//dd($activity);
		$activity_log = ActivityLog::saveLog($activity);
		$trip->visits()->update(['manager_verification_status_id' => 3081]);
		return response()->json(['success' => true, 'message' => 'Trip approved successfully!']);
	}

	public static function rejectTrip($r) {
		$trip = Trip::find($r->trip_id);
		if (!$trip) {
			return response()->json(['success' => false, 'errors' => ['Trip not found']]);
		}
		$trip->rejection_id = $r->reject_id;
		$trip->rejection_remarks = $r->remarks;
		$trip->status_id = 3022;
		$trip->save();
		$activity['entity_id'] = $trip->id;
		$activity['entity_type'] = 'trip';
		$activity['activity'] = "reject";
		$activity['details'] = 'Trip is Rejected by Manager';
		//dd($activity);
		$activity_log = ActivityLog::saveLog($activity);

		$trip->visits()->update(['manager_verification_status_id' => 3082]);
		return response()->json(['success' => true, 'message' => 'Trip rejected successfully!']);
	}

	public static function getClaimFormData($trip_id) {
		// if (!$trip_id) {
		//  $data['success'] = false;
		//  $data['message'] = 'Trip not found';
		//  $data['employee'] = [];
		// } else {
		$data = [];
		$trip = Trip::with(
			['visits' => function ($q) {
				$q->orderBy('id', 'asc');
			},
				'visits.fromCity',
				'visits.toCity',
				'visits.travelMode',
				'visits.bookingMethod',
				'visits.bookingStatus',
				'visits.selfBooking',
				'visits.attachments',
				'visits.agent',
				'visits.status',
				'visits.managerVerificationStatus',
				'cliam',
				'employee',
				'employee.user',
				'employee.tripEmployeeClaim' => function ($q) use ($trip_id) {
					$q->where('trip_id', $trip_id);
				},
				'purpose',
				'status',
				'selfVisits' => function ($q) {
					$q->orderBy('id', 'asc');
				},
				'lodgings',
				'lodgings.stateType',
				'lodgings.city',
				'lodgings.attachments',
				'boardings',
				'boardings.city',
				'boardings.attachments',
				'localTravels',
				'localTravels.city',
				'selfVisits.fromCity',
				'selfVisits.toCity',
				'selfVisits.travelMode',
				'selfVisits.bookingMethod',
				'selfVisits.selfBooking',
				'selfVisits.agent',
				'selfVisits.status',
				'selfVisits.attachments',
			])->find($trip_id);
		// dd($trip);
		if (!$trip) {
			$data['success'] = false;
			$data['message'] = 'Trip not found';
		}
		if (count($trip->localTravels) > 0) {
			$data['action'] = 'Edit';
		} else {
			$data['action'] = 'Add';
		}
		$travelled_cities_with_dates = array();
		$lodge_cities = array();

		//GET TRAVEL DATE LIST FROM TRIP START AND END DATE
		$travel_dates_list = array();
		$date_range = Trip::getDatesFromRange($trip->start_date, $trip->end_date);
		if (!empty($date_range)) {
			$travel_dates_list[0]['id'] = '';
			$travel_dates_list[0]['name'] = 'Select Date';
			foreach ($date_range as $range_key => $range_val) {
				$range_key++;
				$travel_dates_list[$range_key]['id'] = $range_val;
				$travel_dates_list[$range_key]['name'] = $range_val;
			}
		}

		$data['travelled_cities_with_dates'] = $travelled_cities_with_dates;
		$data['lodge_cities'] = $lodge_cities;
		$data['travel_dates_list'] = $travel_dates_list;

		$to_cities = Visit::where('trip_id', $trip_id)->pluck('to_city_id')->toArray();
		$data['success'] = true;
		$data['employee'] = $employee = Employee::select('users.name as name', 'employees.code as code', 'designations.name as designation', 'entities.name as grade', 'employees.grade_id', 'employees.id', 'employees.gender')
			->leftjoin('designations', 'designations.id', 'employees.designation_id')
			->leftjoin('users', 'users.entity_id', 'employees.id')
			->leftjoin('entities', 'entities.id', 'employees.grade_id')
			->where('employees.id', $trip->employee_id)
			->where('users.user_type_id', 3121)->first();
		$travel_cities = Visit::leftjoin('ncities as cities', 'visits.to_city_id', 'cities.id')
			->where('visits.trip_id', $trip->id)->pluck('cities.name')->toArray();
		$data['travel_cities'] = !empty($travel_cities) ? trim(implode(', ', $travel_cities)) : '--';
		// $start_date = $trip->visits()->select(DB::raw('DATE_FORMAT(MIN(visits.departure_date),"%d/%m/%Y") as start_date'))->first();
		// $end_date = $trip->visits()->select(DB::raw('DATE_FORMAT(MAX(visits.departure_date),"%d/%m/%Y") as end_date'))->first();
		// $days = $trip->visits()->select(DB::raw('DATEDIFF(MAX(visits.departure_date),MIN(visits.departure_date))+1 as days'))->first();

		//DAYS CALC BTW START & END DATE
		$datediff = strtotime($trip->end_date) - strtotime($trip->start_date);
		$no_of_days = ($datediff / (60 * 60 * 24)) + 1;
		$trip->days = $no_of_days ? $no_of_days : 0;

		//DONT REVERT - ABDUL
		$trip->cities = $data['cities'] = count($travel_cities) > 0 ? trim(implode(', ', $travel_cities)) : '--';
		$data['travel_dates'] = $travel_dates = Visit::select(DB::raw('MAX(DATE_FORMAT(visits.arrival_date,"%d/%m/%Y")) as max_date'), DB::raw('MIN(DATE_FORMAT(visits.departure_date,"%d/%m/%Y")) as min_date'))->where('visits.trip_id', $trip->id)->first();
		// }
		if (!empty($to_cities)) {
			$city_list = collect(NCity::select('id', 'name')->where('company_id', Auth::user()->company_id)->whereIn('id', $to_cities)->groupby('id')->get()->prepend(['id' => '', 'name' => 'Select City']));
		} else {
			$city_list = [];
		}

		$cities_with_expenses = NCity::select('id', 'name')->where('company_id', Auth::user()->company_id)->whereIn('id', $to_cities)->groupby('id')->get()->keyBy('id')->toArray();
		foreach ($cities_with_expenses as $key => $cities_with_expenses_value) {
			$city_category_id = NCity::where('id', $key)->where('company_id', Auth::user()->company_id)->first();
			if ($city_category_id) {
				$transport_expense_type = DB::table('grade_expense_type')->where('grade_id', $trip->employee->grade_id)->where('expense_type_id', 3000)->where('city_category_id', $city_category_id->category_id)->first();
				if (!$transport_expense_type) {
					$cities_with_expenses[$key]['transport']['id'] = 3000;
					$cities_with_expenses[$key]['transport']['grade_id'] = $trip->employee->grade_id;
					$cities_with_expenses[$key]['transport']['eligible_amount'] = '0.00';
				} else {
					$cities_with_expenses[$key]['transport']['id'] = 3000;
					$cities_with_expenses[$key]['transport']['grade_id'] = $trip->employee->grade_id;
					$cities_with_expenses[$key]['transport']['eligible_amount'] = $transport_expense_type->eligible_amount;
				}
				$lodge_expense_type = DB::table('grade_expense_type')->where('grade_id', $trip->employee->grade_id)->where('expense_type_id', 3001)->where('city_category_id', $city_category_id->category_id)->first();
				if (!$lodge_expense_type) {
					$cities_with_expenses[$key]['lodge']['id'] = 3001;
					$cities_with_expenses[$key]['lodge']['grade_id'] = $trip->employee->grade_id;
					$cities_with_expenses[$key]['lodge']['home']['eligible_amount'] = '0.00';
					$cities_with_expenses[$key]['lodge']['home']['perc'] = 0;
					$cities_with_expenses[$key]['lodge']['normal']['eligible_amount'] = '0.00';
				} else {

					//STAY TYPE HOME
					//GET GRADE STAY TYPE
					$grade_stay_type = DB::table('grade_advanced_eligibility')->where('grade_id', $trip->employee->grade_id)->first();
					if ($grade_stay_type) {
						if ($grade_stay_type->stay_type_disc) {
							$percentage = (int) $grade_stay_type->stay_type_disc;
							$totalWidth = $lodge_expense_type->eligible_amount;
							$home_eligible_amount = ($percentage / 100) * $totalWidth;
						} else {
							$percentage = 0;
							$home_eligible_amount = $lodge_expense_type->eligible_amount;
						}
					} else {
						$percentage = 0;
						$home_eligible_amount = $lodge_expense_type->eligible_amount;
					}
					$cities_with_expenses[$key]['lodge']['id'] = 3001;
					$cities_with_expenses[$key]['lodge']['grade_id'] = $trip->employee->grade_id;
					$cities_with_expenses[$key]['lodge']['home']['eligible_amount'] = $home_eligible_amount;
					$cities_with_expenses[$key]['lodge']['home']['perc'] = $percentage;
					$cities_with_expenses[$key]['lodge']['normal']['eligible_amount'] = $lodge_expense_type->eligible_amount;
				}
				$board_expense_type = DB::table('grade_expense_type')->where('grade_id', $trip->employee->grade_id)->where('expense_type_id', 3002)->where('city_category_id', $city_category_id->category_id)->first();
				if (!$board_expense_type) {
					$cities_with_expenses[$key]['board']['id'] = 3002;
					$cities_with_expenses[$key]['board']['grade_id'] = $trip->employee->grade_id;
					$cities_with_expenses[$key]['board']['eligible_amount'] = '0.00';
				} else {
					$cities_with_expenses[$key]['board']['id'] = 3002;
					$cities_with_expenses[$key]['board']['grade_id'] = $trip->employee->grade_id;
					$cities_with_expenses[$key]['board']['eligible_amount'] = $board_expense_type->eligible_amount;
				}
			} else {
				$cities_with_expenses[$key]['transport'] = [];
				$cities_with_expenses[$key]['lodge'] = [];
				$cities_with_expenses[$key]['board'] = [];
			}

		}
		$data['cities_with_expenses'] = $cities_with_expenses;
		$travel_cities_list = collect(Visit::leftjoin('ncities as cities', 'visits.to_city_id', 'cities.id')
				->where('visits.trip_id', $trip->id)
				->select('cities.id', 'cities.name')
				->orderBy('visits.id', 'asc')
				->get()->prepend(['id' => '', 'name' => 'Select City']));
		$booking_type_list = collect(Config::getBookingTypeTypeList()->prepend(['id' => '', 'name' => 'Select Booked By']));
		$purpose_list = collect(Entity::uiPurposeList()->prepend(['id' => '', 'name' => 'Select Purpose']));
		$travel_mode_list = collect(Entity::uiTravelModeList()->prepend(['id' => '', 'name' => 'Select Travel Mode']));
		$local_travel_mode_list = collect(Entity::uiLocaTravelModeList()->prepend(['id' => '', 'name' => 'Select Local Travel Mode']));
		$stay_type_list = collect(Config::getLodgeStayTypeList()->prepend(['id' => '', 'name' => 'Select Stay Type']));
		$data['extras'] = [
			'purpose_list' => $purpose_list,
			'travel_mode_list' => $travel_mode_list,
			'local_travel_mode_list' => $local_travel_mode_list,
			'city_list' => $city_list,
			'stay_type_list' => $stay_type_list,
			'booking_type_list' => $booking_type_list,
			'travel_cities_list' => $travel_cities_list,
		];
		$data['trip'] = $trip;
		return response()->json($data);
	}

	public static function getFilterData() {
		$data = [];
		$data['employee_list'] = collect(Employee::select(DB::raw('CONCAT(users.name, " / ", employees.code) as name'), 'employees.id')
				->leftJoin('users', 'users.entity_id', 'employees.id')
				->where('users.user_type_id', 3121)
				->where('employees.company_id', Auth::user()->company_id)
				->get())->prepend(['id' => '-1', 'name' => 'Select Employee Code/Name']);
		$data['purpose_list'] = collect(Entity::select('name', 'id')->where('entity_type_id', 501)->where('company_id', Auth::user()->company_id)->get())->prepend(['id' => '-1', 'name' => 'Select Purpose']);
		$data['trip_status_list'] = collect(Config::select('name', 'id')->where('config_type_id', 501)->get())->prepend(['id' => '-1', 'name' => 'Select Status']);
		$data['success'] = true;
		//dd($data);
		return response()->json($data);
	}
	// Function to get all the dates in given range
	public static function getDatesFromRange($start, $end, $format = 'd-m-Y') {
		// Declare an empty array
		$array = array();
		// Variable that store the date interval
		// of period 1 day
		$interval = new DateInterval('P1D');
		$realEnd = new DateTime($end);
		$realEnd->add($interval);
		$period = new DatePeriod(new DateTime($start), $interval, $realEnd);
		// Use loop to store date into array
		foreach ($period as $date) {
			$array[] = $date->format($format);
		}
		// Return the array elements
		return $array;
	}

	public static function getClaimViewData($trip_id) {
		$data = [];
		if (!$trip_id) {
			$data['success'] = false;
			$data['message'] = 'Trip not found';
			return response()->json($data);
		}

		$trip = Trip::with([
			'visits' => function ($q) {
				$q->orderBy('id', 'asc');
			},
			'visits.fromCity',
			'visits.toCity',
			'visits.travelMode',
			'visits.bookingMethod',
			'visits.bookingStatus',
			'visits.selfBooking',
			'visits.attachments',
			'visits.agent',
			'visits.status',
			'visits.managerVerificationStatus',
			'advanceRequestStatus',
			'employee',
			'employee.user',
			'employee.tripEmployeeClaim' => function ($q) use ($trip_id) {
				$q->where('trip_id', $trip_id);
			},
			'employee.grade',
			'employee.designation',
			'employee.reportingTo',
			'employee.reportingTo.user',
			'employee.outlet',
			'employee.Sbu',
			'employee.Sbu.lob',
			'selfVisits' => function ($q) {
				$q->orderBy('id', 'asc');
			},
			'purpose',
			'lodgings',
			'lodgings.city',
			'lodgings.stateType',
			'lodgings.attachments',
			'boardings',
			'boardings.city',
			'boardings.attachments',
			'localTravels',
			'localTravels.fromCity',
			'localTravels.toCity',
			'localTravels.travelMode',
			'localTravels.attachments',
			'selfVisits.fromCity',
			'selfVisits.toCity',
			'selfVisits.travelMode',
			'selfVisits.bookingMethod',
			'selfVisits.selfBooking',
			'selfVisits.agent',
			'selfVisits.status',
			'selfVisits.attachments',
		])->find($trip_id);

		if (!$trip) {
			$data['success'] = false;
			$data['message'] = 'Trip not found';
		}
		$travel_cities = Visit::leftjoin('ncities as cities', 'visits.to_city_id', 'cities.id')
			->where('visits.trip_id', $trip->id)->pluck('cities.name')->toArray();

		$transport_total = Visit::select(
			DB::raw('COALESCE(SUM(visit_bookings.amount), 0.00) as visit_amount'),
			DB::raw('COALESCE(SUM(visit_bookings.tax), 0.00) as visit_tax')
		)
			->leftjoin('visit_bookings', 'visit_bookings.visit_id', 'visits.id')
			->where('visits.trip_id', $trip_id)
			->groupby('visits.id')
			->get()
			->toArray();
		$visit_amounts = array_column($transport_total, 'visit_amount');
		$visit_taxes = array_column($transport_total, 'visit_tax');
		$visit_amounts_total = array_sum($visit_amounts);
		$visit_taxes_total = array_sum($visit_taxes);

		$transport_total_amount = $visit_amounts_total ? $visit_amounts_total : 0.00;
		$transport_total_tax = $visit_taxes_total ? $visit_taxes_total : 0.00;
		$data['transport_total_amount'] = number_format($transport_total_amount, 2, '.', '');

		$lodging_total = Lodging::select(
			DB::raw('COALESCE(SUM(amount), 0.00) as amount'),
			DB::raw('COALESCE(SUM(tax), 0.00) as tax')
		)
			->where('trip_id', $trip_id)
			->groupby('trip_id')
			->first();
		$lodging_total_amount = $lodging_total ? $lodging_total->amount : 0.00;
		$lodging_total_tax = $lodging_total ? $lodging_total->tax : 0.00;
		$data['lodging_total_amount'] = number_format($lodging_total_amount, 2, '.', '');

		$boardings_total = Boarding::select(
			DB::raw('COALESCE(SUM(amount), 0.00) as amount'),
			DB::raw('COALESCE(SUM(tax), 0.00) as tax')
		)
			->where('trip_id', $trip_id)
			->groupby('trip_id')
			->first();
		$boardings_total_amount = $boardings_total ? $boardings_total->amount : 0.00;
		$boardings_total_tax = $boardings_total ? $boardings_total->tax : 0.00;
		$data['boardings_total_amount'] = number_format($boardings_total_amount, 2, '.', '');

		$local_travels_total = LocalTravel::select(
			DB::raw('COALESCE(SUM(amount), 0.00) as amount'),
			DB::raw('COALESCE(SUM(tax), 0.00) as tax')
		)
			->where('trip_id', $trip_id)
			->groupby('trip_id')
			->first();
		$local_travels_total_amount = $local_travels_total ? $local_travels_total->amount : 0.00;
		$local_travels_total_tax = $local_travels_total ? $local_travels_total->tax : 0.00;
		$data['local_travels_total_amount'] = number_format($local_travels_total_amount, 2, '.', '');

		$total_amount = $transport_total_amount + $transport_total_tax + $lodging_total_amount + $lodging_total_tax + $boardings_total_amount + $boardings_total_tax + $local_travels_total_amount + $local_travels_total_tax;
		$data['total_amount'] = number_format($total_amount, 2, '.', '');
		$data['travel_cities'] = !empty($travel_cities) ? trim(implode(', ', $travel_cities)) : '--';
		$data['travel_dates'] = $travel_dates = Visit::select(DB::raw('MAX(DATE_FORMAT(visits.arrival_date,"%d/%m/%Y")) as max_date'), DB::raw('MIN(DATE_FORMAT(visits.departure_date,"%d/%m/%Y")) as min_date'))->where('visits.trip_id', $trip->id)->first();

		$data['trip_claim_rejection_list'] = collect(Entity::trip_claim_rejection()->prepend(['id' => '', 'name' => 'Select Rejection Reason']));

		$data['success'] = true;

		$data['trip'] = $trip;

		return response()->json($data);
	}
	public static function sendTripNotificationMail($trip) {
		try {

			$trip_id = $trip->id;
			$trip = Trip::findorFail($trip->id);

			$from_mail = config('eyatra.common_from_email');
			//dd($from_mail);
			$employee_details = Employee::select(
				'employees.code',
				'users.name',
				'designations.name as designation',
				'entities.name as grade',
				'employee_manager.name as manager_name'
			)
				->join('users', 'users.entity_id', 'employees.id')
				->join('users as employee_manager', 'employee_manager.entity_id', 'employees.reporting_to_id')
				->join('designations', 'designations.id', 'employees.designation_id')
				->join('entities', 'entities.id', 'employees.grade_id')
				->where('employees.id', Auth::User()->entity_id)
				->first();
			//dd($employee_details);
			$trip_visits = $trip->visits;
			//dd($trip_visits);
			if ($trip_visits) {
				//agent Booking Count checking

				$agents_visit = Visit::select('agent_id')
					->where('visits.trip_id', $trip_id)
					->where('visits.booking_method_id', 3042)
					->groupby('agent_id')
					->get();
				//dd($agents_visit);
				//dd($agents_visit);
				foreach ($agents_visit as $key => $agent) {
					$visit_agents = Visit::select(
						'visits.id',
						'trips.id as trip_id',
						'users.name as employee_name',
						DB::raw('DATE_FORMAT(visits.departure_date,"%d/%m/%Y") as visit_date'),
						'fromcity.name as fromcity_name',
						'tocity.name as tocity_name',
						'travel_modes.name as travel_mode_name',
						'booking_modes.name as booking_method_name'
					)
						->join('trips', 'trips.id', 'visits.trip_id')
						->leftjoin('users', 'trips.employee_id', 'users.id')
						->join('ncities as fromcity', 'fromcity.id', 'visits.from_city_id')
						->join('ncities as tocity', 'tocity.id', 'visits.to_city_id')
						->join('entities as travel_modes', 'travel_modes.id', 'visits.travel_mode_id')
						->join('configs as booking_modes', 'booking_modes.id', 'visits.booking_method_id')
						->where('booking_method_id', 3042)
						->where('trip_id', $trip_id)
						->where('visits.agent_id', $agent->agent_id)
						->get();
					// dd($visit_agents);
					// $visit_agent_count = $visit_agents->count();
					if (count($visit_agents) > 0) {
						$arr['from_mail'] = $from_mail;
						$arr['from_mail'] = 'saravanan@uitoux.in';
						$arr['from_name'] = 'Agent';
						$agent_user = User::select('name', 'email')
							->where('entity_id', $agent->agent_id)
							->where('user_type_id', 3122)
							->where('company_id', Auth::user()->company_id)
							->first();
						if ($agent_user) {
							if ($agent_user->email) {
								$arr['to_email'] = $agent_user->email;
								$arr['to_name'] = $agent_user->name;
								$arr['to_email'] = 'saravanan@uitoux.in';
								$arr['subject'] = 'Ticket booking request';
								$arr['body'] = 'Employee ticket booking notification';
								$arr['visits'] = $visit_agents;
								$arr['employee_details'] = $employee_details;
								$arr['trip'] = $trip;
								$arr['type'] = 1;
								$MailInstance = new TripNotificationMail($arr);
								$Mail = Mail::send($MailInstance);

							}
						}
					}

				}
				// Manager mail trigger
				$visit_manager = Visit::select(
					'visits.id',
					'visits.notes_to_agent',
					'trips.id as trip_id',
					'users.name as employee_name',
					DB::raw('DATE_FORMAT(visits.departure_date,"%d/%m/%Y") as visit_date'),
					'fromcity.name as fromcity_name',
					'tocity.name as tocity_name',
					'travel_modes.name as travel_mode_name',
					'booking_modes.name as booking_method_name'
				)
					->join('trips', 'trips.id', 'visits.trip_id')
					->leftjoin('users', 'trips.employee_id', 'users.id')
					->join('ncities as fromcity', 'fromcity.id', 'visits.from_city_id')
					->join('ncities as tocity', 'tocity.id', 'visits.to_city_id')
					->join('entities as travel_modes', 'travel_modes.id', 'visits.travel_mode_id')
					->join('configs as booking_modes', 'booking_modes.id', 'visits.booking_method_id')
					->where('visits.trip_id', $trip_id)
					->get();
				if ($visit_manager) {
					$arr['from_mail'] = $from_mail;
					$arr['from_mail'] = 'saravanan@uitoux.in';
					$arr['from_name'] = 'Manager';
					$to_employee = Employee::select('users.email as email', 'users.name as name')
						->join('users', 'users.entity_id', 'employees.reporting_to_id')
						->where('users.user_type_id', 3121)
						->where('employees.id', Auth::user()->entity_id)
						->first();
					if ($to_employee->email) {
						$arr['to_email'] = $to_employee->email;
						//$arr['to_email'] = 'saravanan@uitoux.in';
						//$arr['to_name'] = 'parthiban';
						$arr['to_name'] = $to_employee->name;
						$arr['subject'] = 'Trip Approval Request';
						$arr['body'] = 'Employee ticket booking notification';
						$arr['employee_details'] = $employee_details;
						$arr['visits'] = $visit_manager;
						$arr['trip'] = $trip;
						$arr['type'] = 2;
						$MailInstance = new TripNotificationMail($arr);
						$Mail = Mail::send($MailInstance);
					}

				}
				// Financier mail trigger
				$visit_financier = Visit::select(
					'visits.id',
					'visits.notes_to_agent',
					'trips.id as trip_id',
					'trips.advance_received as advance_amount',
					'users.name as employee_name',
					DB::raw('DATE_FORMAT(visits.departure_date,"%d/%m/%Y") as visit_date'),
					'fromcity.name as fromcity_name',
					'tocity.name as tocity_name',
					'travel_modes.name as travel_mode_name',
					'booking_modes.name as booking_method_name'
				)
					->join('trips', 'trips.id', 'visits.trip_id')
					->leftjoin('users', 'trips.employee_id', 'users.id')
					->join('ncities as fromcity', 'fromcity.id', 'visits.from_city_id')
					->join('ncities as tocity', 'tocity.id', 'visits.to_city_id')
					->join('entities as travel_modes', 'travel_modes.id', 'visits.travel_mode_id')
					->join('configs as booking_modes', 'booking_modes.id', 'visits.booking_method_id')
					->where('visits.trip_id', $trip_id)
					->where('trips.advance_received', '>', 0)
					->get();
				$visit_financier_count = $visit_financier->count();
				if ($visit_financier_count > 0) {
					$arr['from_mail'] = $from_mail;
					$arr['from_mail'] = 'saravanan@uitoux.in';
					$arr['from_name'] = 'Financier';
					$financier = User::select('name', 'email')
						->join('role_user', 'role_user.user_id', 'users.id')
						->where('role_user.role_id', 505)
						->where('users.company_id', Auth::user()->company_id)
						->first();
					if ($financier->email) {
						$arr['to_email'] = $financier->email;
						$arr['to_name'] = $financier->name;
						//$arr['to_email'] = 'saravanan@uitoux.in';
						//$arr['to_name'] = 'parthiban';
						//dd($user_details_cc['email']);
						$arr['subject'] = 'Trip Advance Request';
						$arr['body'] = 'Employee ticket booking notification';
						$arr['employee_details'] = $employee_details;
						$arr['visits'] = $visit_financier;
						$arr['trip'] = $trip;
						$arr['type'] = 3;
						$MailInstance = new TripNotificationMail($arr);
						$Mail = Mail::send($MailInstance);
					}
				}

				// Employee Mail trigger
				$visit_employee = Visit::select(
					'visits.id',
					'visits.notes_to_agent',
					'trips.id as trip_id',
					'users.name as employee_name',
					DB::raw('DATE_FORMAT(visits.departure_date,"%d/%m/%Y") as visit_date'),
					'fromcity.name as fromcity_name',
					'tocity.name as tocity_name',
					'travel_modes.name as travel_mode_name',
					'booking_modes.name as booking_method_name'
				)
					->join('trips', 'trips.id', 'visits.trip_id')
					->leftjoin('users', 'trips.employee_id', 'users.id')
					->join('ncities as fromcity', 'fromcity.id', 'visits.from_city_id')
					->join('ncities as tocity', 'tocity.id', 'visits.to_city_id')
					->join('entities as travel_modes', 'travel_modes.id', 'visits.travel_mode_id')
					->join('configs as booking_modes', 'booking_modes.id', 'visits.booking_method_id')
					->where('visits.trip_id', $trip_id)
					->get();
				if ($visit_employee) {
					$arr['from_mail'] = $from_mail;
					$arr['from_mail'] = 'saravanan@uitoux.in';
					$arr['from_name'] = 'Employee';
					$to_employee = Employee::select('users.email as email', 'users.name as name')
						->join('users', 'users.entity_id', 'employees.id')
						->where('users.user_type_id', 3121)
						->where('employees.id', Auth::user()->entity_id)
						->first();
					if ($to_employee->email) {
						$arr['to_email'] = $to_employee->email;
						$arr['to_name'] = $to_employee->name;
						$arr['subject'] = 'Trip Approval Request';
						$arr['body'] = 'Employee ticket booking notification';
						$arr['employee_details'] = $employee_details;
						$arr['visits'] = $visit_manager;
						$arr['trip'] = $trip;
						$arr['type'] = 4;
						$MailInstance = new TripNotificationMail($arr);
						$Mail = Mail::send($MailInstance);
					}

				}
			}
		} catch (Exception $e) {
			return response()->json(['success' => false, 'errors' => ['Error_Message' => $e->getMessage()]]);
		}
	}

	public static function saveEYatraTripClaim($request) {
		//validation
		try {
			// $validator = Validator::make($request->all(), [
			// 	'purpose_id' => [
			// 		'required',
			// 	],
			// ]);
			// if ($validator->fails()) {
			// 	return response()->json(['success' => false, 'errors' => $validator->errors()->all()]);
			// }

			DB::beginTransaction();

			if (empty($request->trip_id)) {
				return response()->json(['success' => false, 'errors' => ['Trip not found']]);
			}

			//SAVING VISITS
			if ($request->visits) {
				// dd($request->visits);
				foreach ($request->visits as $visit_data) {
					if (!empty($visit_data['id'])) {
						$visit = Visit::find($visit_data['id']);

						//CONCATENATE DATE & TIME
						$depart_date = $visit_data['departure_date'];
						$depart_time = $visit_data['departure_time'];
						$arrival_date = $visit_data['arrival_date'];
						$arrival_time = $visit_data['arrival_time'];
						$visit->departure_date = date('Y-m-d H:i:s', strtotime("$depart_date $depart_time"));
						$visit->arrival_date = date('Y-m-d H:i:s', strtotime("$arrival_date $arrival_time"));

						//GET BOOKING TYPE
						$booked_by = strtolower($visit_data['booked_by']);
						if ($booked_by == 'self') {
							$visit->travel_mode_id = $visit_data['travel_mode_id'];
						}
						$visit->save();
						// dd($visit_data['id']);

						//UPDATE VISIT BOOKING STATUS ONLY FOR SELF
						if ($booked_by == 'self') {
							$visit_booking = VisitBooking::firstOrNew(['visit_id' => $visit_data['id']]);
							$visit_booking->visit_id = $visit_data['id'];
							$visit_booking->type_id = 3100;
							$visit_booking->travel_mode_id = $visit_data['travel_mode_id'];
							$visit_booking->reference_number = $visit_data['reference_number'];
							$visit_booking->remarks = $visit_data['remarks'];
							$visit_booking->amount = $visit_data['amount'];
							$visit_booking->tax = $visit_data['tax'];
							$visit_booking->gstin = $visit_data['gstin'];
							$visit_booking->service_charge = '0.00';
							$visit_booking->total = $visit_data['total'];
							$visit_booking->paid_amount = $visit_data['total'];
							$visit_booking->created_by = Auth::user()->id;
							$visit_booking->status_id = 3241; //Claimed
							$visit_booking->save();
						}

					}
				}

				//CHECK NEXT VISIT EXIST
				//ONLY SELF VISITS WILL COME IN POST NOT AGENT BOOKED ==> NOT BEEN USED NOW

				// $lodge_checkin_out_date_range_list = array();
				// $trip = Trip::with(
				// 	['visits' => function ($q) {
				// 		$q->orderBy('id', 'asc');
				// 	},
				// 	])->find($request->trip_id);
				// foreach ($trip->visits as $visit_data_key => $visit_data_val) {
				// 	$next_visit = $visit_data_key;
				// 	$next_visit++;
				// 	//LODGE CHECK IN & OUT DATE LIST
				// 	if (isset($trip->visits[$next_visit])) {
				// 		$date_range = Trip::getDatesFromRange($visit_data_val['departure_date'], $trip->visits[$next_visit]['departure_date']);
				// 		if (!empty($date_range)) {
				// 			$lodge_checkin_out_date_range_list[$visit_data_key][0]['id'] = '';
				// 			$lodge_checkin_out_date_range_list[$visit_data_key][0]['name'] = 'Select Date';
				// 			foreach ($date_range as $range_key => $range_val) {
				// 				$range_key++;
				// 				$lodge_checkin_out_date_range_list[$visit_data_key][$range_key]['id'] = $range_val;
				// 				$lodge_checkin_out_date_range_list[$visit_data_key][$range_key]['name'] = $range_val;
				// 			}
				// 		}
				// 	}
				// }

				DB::commit();
				return response()->json(['success' => true]);
			}

			//SAVING LODGINGS
			if ($request->is_lodging) {
				// dd($request->all());
				//REMOVE LODGING ATTACHMENT
				if (!empty($request->lodgings_attach_removal_ids)) {
					$lodgings_attach_removal_ids = json_decode($request->lodgings_attach_removal_ids, true);
					Attachment::whereIn('id', $lodgings_attach_removal_ids)->delete();
				}

				//REMOVE LODGING AND THIER ATTACHMENTS
				if (!empty($request->lodgings_removal_id)) {
					$lodgings_removal_id = json_decode($request->lodgings_removal_id, true);
					Lodging::whereIn('id', $lodgings_removal_id)->delete();
					Attachment::whereIn('entity_id', $lodgings_removal_id)->delete();
				}

				//SAVE
				if ($request->lodgings) {
					// dd($request->lodgings);
					// LODGE STAY DAYS SHOULD NOT EXCEED TOTAL TRIP DAYS
					$lodge_stayed_days = (int) array_sum(array_column($request->lodgings, 'stayed_days'));
					$trip_total_days = (int) $request->trip_total_days;
					if ($lodge_stayed_days > $trip_total_days) {
						return response()->json(['success' => false, 'errors' => ['Total lodging days should be less than total trip days']]);
					}

					foreach ($request->lodgings as $lodging_data) {

						$lodging = Lodging::firstOrNew([
							'id' => $lodging_data['id'],
						]);
						$lodging->fill($lodging_data);
						$lodging->trip_id = $request->trip_id;

						//CONCATENATE DATE & TIME
						$check_in_date = $lodging_data['check_in_date'];
						$check_in_time = $lodging_data['check_in_time'];
						$checkout_date = $lodging_data['checkout_date'];
						$checkout_time = $lodging_data['checkout_time'];
						$lodging->check_in_date = date('Y-m-d H:i:s', strtotime("$check_in_date $check_in_time"));
						$lodging->checkout_date = date('Y-m-d H:i:s', strtotime("$checkout_date $checkout_time"));
						$lodging->created_by = Auth::user()->id;
						$lodging->save();

						//STORE ATTACHMENT
						$item_images = storage_path('app/public/trip/lodgings/attachments/');
						Storage::makeDirectory($item_images, 0777);
						if (!empty($lodging_data['attachments'])) {
							foreach ($lodging_data['attachments'] as $key => $attachement) {
								$name = $attachement->getClientOriginalName();
								$attachement->move(storage_path('app/public/trip/lodgings/attachments/'), $name);
								$attachement_lodge = new Attachment;
								$attachement_lodge->attachment_of_id = 3181;
								$attachement_lodge->attachment_type_id = 3200;
								$attachement_lodge->entity_id = $lodging->id;
								$attachement_lodge->name = $name;
								$attachement_lodge->save();
							}
						}
					}
				}

				//GET SAVED LODGINGS
				$saved_lodgings = Trip::with([
					'lodgings',
					'lodgings.city',
					'lodgings.stateType',
					'lodgings.attachments',
				])->find($request->trip_id);

				//BOARDING CITIES LIST ==> NOT BEEN USED NOW

				// $boarding_dates_list = array();
				// $travel_dates = Visit::select(DB::raw('MAX(DATE_FORMAT(visits.arrival_date,"%d-%m-%Y")) as max_date'), DB::raw('MIN(DATE_FORMAT(visits.departure_date,"%d-%m-%Y")) as min_date'))->where('visits.trip_id', $request->trip_id)->first();

				// if ($travel_dates) {
				// 	$boarding_date_range = Trip::getDatesFromRange($travel_dates->min_date, $travel_dates->max_date);
				// 	if (!empty($boarding_date_range)) {
				// 		$boarding_dates_list[0]['id'] = '';
				// 		$boarding_dates_list[0]['name'] = 'Select Date';
				// 		foreach ($boarding_date_range as $boarding_date_range_key => $boarding_date_range_val) {
				// 			$boarding_date_range_key++;
				// 			$boarding_dates_list[$boarding_date_range_key]['id'] = $boarding_date_range_val;
				// 			$boarding_dates_list[$boarding_date_range_key]['name'] = $boarding_date_range_val;
				// 		}
				// 	}
				// } else {
				// 	$boarding_dates_list = array();
				// }

				DB::commit();
				return response()->json(['success' => true, 'saved_lodgings' => $saved_lodgings]);
			}
			//SAVING BOARDINGS
			if ($request->is_boarding) {

				//REMOVE BOARDINGS ATTACHMENT
				if (!empty($request->boardings_attach_removal_ids)) {
					$boardings_attach_removal_ids = json_decode($request->boardings_attach_removal_ids, true);
					Attachment::whereIn('id', $boardings_attach_removal_ids)->delete();
				}
				//REMOVE BOARDINGS
				if (!empty($request->boardings_removal_id)) {
					$boardings_removal_id = json_decode($request->boardings_removal_id, true);
					Boarding::whereIn('id', $boardings_removal_id)->delete();
					Attachment::whereIn('entity_id', $boardings_removal_id)->delete();
				}

				//SAVE
				if ($request->boardings) {

					//TOTAL BOARDING DAYS SHOULD NOT EXCEED TOTAL TRIP DAYS
					$boarding_days = (int) array_sum(array_column($request->boardings, 'days'));
					$trip_total_days = (int) $request->trip_total_days;
					if ($boarding_days > $trip_total_days) {
						return response()->json(['success' => false, 'errors' => ['Total boarding days should be less than total trip days']]);
					}

					foreach ($request->boardings as $boarding_data) {
						$boarding = Boarding::firstOrNew([
							'id' => $boarding_data['id'],
						]);
						$boarding->fill($boarding_data);
						$boarding->trip_id = $request->trip_id;
						$boarding->from_date = date('Y-m-d', strtotime($boarding_data['from_date']));
						$boarding->to_date = date('Y-m-d', strtotime($boarding_data['to_date']));
						$boarding->created_by = Auth::user()->id;
						$boarding->save();

						//STORE ATTACHMENT
						$item_images = storage_path('app/public/trip/boarding/attachments/');
						Storage::makeDirectory($item_images, 0777);
						if (!empty($boarding_data['attachments'])) {
							foreach ($boarding_data['attachments'] as $key => $attachement) {
								$name = $attachement->getClientOriginalName();
								$attachement->move(storage_path('app/public/trip/boarding/attachments/'), $name);
								$attachement_board = new Attachment;
								$attachement_board->attachment_of_id = 3182;
								$attachement_board->attachment_type_id = 3200;
								$attachement_board->entity_id = $boarding->id;
								$attachement_board->name = $name;
								$attachement_board->save();
							}
						}
					}
				}

				//GET SAVED BOARDINGS
				$saved_boardings = Trip::with([
					'boardings',
					'boardings.city',
					'boardings.attachments',
				])->find($request->trip_id);

				DB::commit();
				return response()->json(['success' => true, 'saved_boardings' => $saved_boardings]);
			}

			//FINAL SAVE LOCAL TRAVELS
			if ($request->is_local_travel) {
				// dd($request->all());
				//GET EMPLOYEE DETAILS
				$employee = Employee::where('id', $request->employee_id)->first();

				//UPDATE TRIP STATUS
				$trip = Trip::find($request->trip_id);

				//CHECK IF EMPLOYEE SELF APPROVE
				if ($employee->self_approve == 1) {
					$trip->status_id = 3025; // Payment Pending
				} else {
					$trip->status_id = 3023; //claimed
				}
				$trip->claim_amount = $request->claim_total_amount; //claimed
				$trip->claimed_date = date('Y-m-d H:i:s');
				$trip->save();

				//SAVE EMPLOYEE CLAIMS
				$employee_claim = EmployeeClaim::firstOrNew(['trip_id' => $trip->id]);
				$employee_claim->fill($request->all());
				$employee_claim->trip_id = $trip->id;
				$employee_claim->total_amount = $request->claim_total_amount;
				$employee_claim->remarks = $request->remarks;

				//CHECK IS JUSTIFY MY TRIP CHECKBOX CHECKED OR NOT
				if ($request->is_justify_my_trip) {
					$employee_claim->is_justify_my_trip = 1;
				} else {
					$employee_claim->is_justify_my_trip = 0;
				}
				//CHECK IF EMPLOYEE SELF APPROVE
				if ($employee->self_approve == 1) {
					$employee_claim->status_id = 3223; //PAYMENT PENDING
				} else {
					$employee_claim->status_id = 3222; //CLAIM REQUESTED
				}
				//CHECK EMPLOYEE GRADE HAS DEVIATION ELIGIBILITY ==> IF DEVIATION ELIGIBILITY IS 2-NO MEANS THERE IS NO DEVIATION, 1-YES MEANS NEED TO CHECK IN REQUEST
				$grade_advance_eligibility = GradeAdvancedEligiblity::where('grade_id', $request->grade_id)->first();
				if ($grade_advance_eligibility && $grade_advance_eligibility->deviation_eligiblity == 2) {
					$employee_claim->is_deviation = 0; //NO DEVIATION DEFAULT
				} else {
					$employee_claim->is_deviation = $request->is_deviation;
				}
				$employee_claim->created_by = Auth::user()->id;
				$employee_claim->save();

				//STORE GOOGLE ATTACHMENT
				$item_images = storage_path('app/public/trip/ey_employee_claims/google_attachments/');
				Storage::makeDirectory($item_images, 0777);
				if ($request->hasfile('google_attachments')) {
					foreach ($request->file('google_attachments') as $image) {
						$name = $image->getClientOriginalName();
						$image->move(storage_path('app/public/trip/ey_employee_claims/google_attachments/'), $name);
						$attachement = new Attachment;
						$attachement->attachment_of_id = 3185;
						$attachement->attachment_type_id = 3200;
						$attachement->entity_id = $employee_claim->id;
						$attachement->name = $name;
						$attachement->save();
					}

				}

				$activity['entity_id'] = $trip->id;
				$activity['entity_type'] = "Trip";
				$activity['details'] = "Trip is Claimed";
				$activity['activity'] = "claim";
				$activity_log = ActivityLog::saveLog($activity);

				if (!empty($request->local_travels_removal_id)) {
					$local_travels_removal_id = json_decode($request->local_travels_removal_id, true);
					LocalTravel::whereIn('id', $local_travels_removal_id)->delete();
				}
				if ($request->local_travels) {
					foreach ($request->local_travels as $local_travel_data) {
						$local_travel = LocalTravel::firstOrNew([
							'id' => $local_travel_data['id'],
						]);
						$local_travel->fill($local_travel_data);
						$local_travel->trip_id = $request->trip_id;
						$local_travel->date = date('Y-m-d', strtotime($local_travel_data['date']));
						$local_travel->created_by = Auth::user()->id;
						$local_travel->save();

						//STORE ATTACHMENT
						$item_images = storage_path('app/public/trip/local_travels/attachments/');
						Storage::makeDirectory($item_images, 0777);
						if (!empty($local_travel_data['attachments'])) {
							foreach ($local_travel_data['attachments'] as $key => $attachement) {
								$name = $attachement->getClientOriginalName();
								$attachement->move(storage_path('app/public/trip/local_travels/attachments/'), $name);
								$attachement_local_travel = new Attachment;
								$attachement_local_travel->attachment_of_id = 3183;
								$attachement_local_travel->attachment_type_id = 3200;
								$attachement_local_travel->entity_id = $local_travel->id;
								$attachement_local_travel->name = $name;
								$attachement_local_travel->save();
							}
						}
					}
				}

				DB::commit();
				return response()->json(['success' => true]);
			}

			$request->session()->flash('success', 'Trip saved successfully!');
			return response()->json(['success' => true]);
		} catch (Exception $e) {
			DB::rollBack();
			return response()->json(['success' => false, 'errors' => ['Exception Error' => $e->getMessage()]]);
		}
	}

	//GET TRAVEL MODE CATEGORY STATUS TO CHECK IF IT IS NO VEHICLE CLAIM
	public static function getVisitTrnasportModeClaimStatus($request) {
		if (!empty($request->travel_mode_id)) {
			$travel_mode_category_type = DB::table('travel_mode_category_type')->where('travel_mode_id', $request->travel_mode_id)->where('category_id', 3402)->first();
			if ($travel_mode_category_type) {
				$is_no_vehicl_claim = true;
			} else {
				$is_no_vehicl_claim = false;
			}
		} else {
			$is_no_vehicl_claim = false;
		}
		return response()->json(['is_no_vehicl_claim' => $is_no_vehicl_claim]);
	}

}
