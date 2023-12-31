<?php

namespace Uitoux\EYatra;
use App\Http\Controllers\Controller;
use Auth;
use DB;
use Illuminate\Http\Request;
use Uitoux\EYatra\BookingMethod;
use Uitoux\EYatra\Config;
use Uitoux\EYatra\Payment;
use Uitoux\EYatra\Trip;
use Yajra\Datatables\Datatables;

class AgentRequestController extends Controller {
	public function listAgentRequest(Request $r) {
		$trips = Trip::from('trips')
			->join('visits as v', 'v.trip_id', 'trips.id')
			->join('ncities as c', 'c.id', 'v.from_city_id')
			->join('employees as e', 'e.id', 'trips.employee_id')
			->join('entities as purpose', 'purpose.id', 'trips.purpose_id')
			->join('configs as status', 'status.id', 'trips.status_id')
			->leftJoin('users', 'users.entity_id', 'trips.employee_id')
			->where('users.user_type_id', 3121)
			->select(
				'trips.id',
				'trips.number',
				'e.code as ecode',
				DB::raw('GROUP_CONCAT(DISTINCT(c.name)) as cities'),
				DB::raw('DATE_FORMAT(MIN(v.depature_date),"%d/%m/%Y") as start_date'),
				DB::raw('DATE_FORMAT(MAX(v.depature_date),"%d/%m/%Y") as end_date'),
				'purpose.name as purpose',
				'trips.advance_received',
				'trips.created_at',
				//DB::raw('DATE_FORMAT(trips.created_at,"%d/%m/%Y") as created_at'),
				'status.name as status'

			)
			->whereNotNull('trips.advance_received')
			->where('trips.status_id', 3028) //MANAGER APPROVED
			->where('trips.advance_request_approval_status_id', 3260) //NEW
			->groupBy('trips.id')
			->orderBy('trips.created_at', 'desc')
			->orderBy('trips.status_id', 'desc')
		;

		return Datatables::of($trips)
			->addColumn('action', function ($trip) {

				$img1 = asset('public/img/content/yatra/table/edit.svg');
				$img2 = asset('public/img/content/yatra/table/view.svg');
				$img1_active = asset('public/img/content/yatra/table/edit-active.svg');
				$img2_active = asset('public/img/content/yatra/table/view-active.svg');
				$img3 = asset('public/img/content/yatra/table/delete.svg');
				$img3_active = asset('public/img/content/yatra/table/delete-active.svg');
				return '
				<a href="#!/advance-claim/request/form/' . $trip->id . '">
					<img src="' . $img2 . '" alt="View" class="img-responsive" onmouseover=this.src="' . $img2_active . '" onmouseout=this.src="' . $img2 . '" >
				</a>
				';

			})
			->make(true);
	}

	public function financierRequestFormData($trip_id) {
		$trip = Trip::with([
			'agentVisits',
			'agentVisits.fromCity',
			'agentVisits.toCity',
			'agentVisits.travelMode',
			'agentVisits.bookingMethod',
			'agentVisits.bookingStatus',
			'agentVisits.bookings',
			'agentVisits.bookings.travelMode',
			'agentVisits.bookings.bookingMode',
			'agentVisits.agent',
			'agentVisits.status',
			'agentVisits.managerVerificationStatus',
			'employee',
			'employee.user',
			'employee.grade',
			'employee.designation',
			'purpose',
			'status',
		])
			->find($trip_id);

		if (!$trip) {
			return response()->json(['success' => false, 'errors' => ['Trip not found']]);
		}

		$age = '--';
		// dd(date('Y', strtotime($trip->employee->date_of_birth)));
		if ($trip->employee) {
			$age = date('Y') - date('Y', strtotime($trip->employee->date_of_birth));
		}
		$visits = $trip->visits;
		$trip_status = 'not_booked';
		$ticket_amount = 0;
		$service_charge = 0;
		$total_amount = 0;
		foreach ($visits as $key => $value) {
			if ($value->booking_status_id == 3061 || $value->booking_status_id == 3062) {
				$trip_status = 'booked';
			}
		}
		if ($trip_status == 'booked') {
			$visits = Trip::select(DB::raw('SUM(visit_bookings.amount) as amount'), DB::raw('SUM(visit_bookings.paid_amount) as paid_amount'), DB::raw('SUM(visit_bookings.tax) as tax'), DB::raw('SUM(visit_bookings.service_charge) as service_charge'))
				->join('visits', 'trips.id', 'visits.trip_id')
				->join('visit_bookings', 'visit_bookings.visit_id', 'visits.id')
				->where('visits.booking_method_id', 3042)
			// ->where('visit_bookings.created_by', Auth::user()->id)
				->where('visits.trip_id', $trip_id)
				->groupBy('visits.trip_id')
				->first();

			if ($visits) {
				$ticket_amount = $visits->amount + $visits->tax;
				$service_charge = $visits->service_charge;
				$total_amount = $visits->paid_amount;
			}

		}
		$start_date = $trip->visits()->select(DB::raw('DATE_FORMAT(MIN(visits.departure_date),"%d/%m/%Y") as start_date'))->first();
		$end_date = $trip->visits()->select(DB::raw('DATE_FORMAT(MAX(visits.departure_date),"%d/%m/%Y") as end_date'))->first();
		$days = $trip->visits()->select(DB::raw('DATEDIFF(MAX(visits.departure_date),MIN(visits.departure_date)) as days'))->first();
		$trip->start_date = $start_date->start_date;
		$trip->end_date = $end_date->end_date;
		$trip->days = $days->days;
		$this->data['travel_mode_list'] = $payment_mode_list = collect(Entity::agentTravelModeList())->prepend(['id' => '', 'name' => 'Select Travel Mode']);
		$this->data['booking_mode_list'] = $booking_mode_list = collect(Entity::bookingModeList())->prepend(['id' => '', 'name' => 'Select Booking Method']);
		$this->data['trip'] = $trip;
		$this->data['age'] = $age;
		$this->data['trip_status'] = $trip_status;
		$this->data['total_amount'] = $total_amount;
		$this->data['ticket_amount'] = $ticket_amount;
		$this->data['service_charge'] = $service_charge;
		$this->data['success'] = true;
		return response()->json($this->data);
	}

	public function agentRequestFormData($trip_id) {
		// dd($trip_id);
		$trip = Trip::with([
			'agentVisits',
			'agentVisits.fromCity',
			'agentVisits.toCity',
			'agentVisits.toCity.state',
			'agentVisits.toCity.state.operatingState',
			'agentVisits.travelMode',
			'agentVisits.bookingMethod',
			'agentVisits.bookingStatus',
			'agentVisits.booking',
			'agentVisits.bookings',
			'agentVisits.bookings.attachments',
			'agentVisits.bookings.travelMode',
			'agentVisits.bookings.bookingMode',
			'agentVisits.bookings.bookingCategory',
			'agentVisits.agent',
			'agentVisits.status',
			'agentVisits.managerVerificationStatus',
			'employee',
			'employee.outlet',
			'employee.outlet.address',
			'employee.outlet.address.city',
			'employee.outlet.address.city.state',
			'employee.user',
			'employee.grade',
			'employee.designation',
			'purpose',
			'status',
		])
			->find($trip_id);
		if (!$trip) {
			return response()->json(['success' => false, 'errors' => ['Trip not found']]);
		}

		$age = '--';
		if ($trip->employee) {
			$age = date('Y') - date('Y', strtotime($trip->employee->date_of_birth));
		}
		$visits = $trip->agentVisits;

		$trip_status = 'not_booked';
		$ticket_amount = 0;
		$service_charge = 0;
		$total_amount = 0;
		if ($visits->isNotEmpty()) {
			foreach ($visits as $key => $visit) {
				if ($visit->booking_status_id == 3061 || $visit->booking_status_id == 3062 || $visit->booking_status_id == 3064) {
					//3061 Booked //3062 Cancelled // 3064 Visit Reschedule
					$trip_status = 'booked';
				}
				$visits[$key]->toCityGstin = '';
				$visits[$key]->toCityGstCode = '';
				if ($visit->toCity && $visit->toCity->state) {
					if ($visit->toCity->state->operatingState) {
						$visits[$key]->toCityGstin = $visit->toCity->state->operatingState->gst_number;
						$visits[$key]->toCityGstCode = substr($visit->toCity->state->operatingState->gst_number, 0, 2);
					}
				}
			}
		}

		$trip->employee_gst_code = '';
		if ($trip && $trip->employee && $trip->employee->outlet && $trip->employee->outlet->address && $trip->employee->outlet->address->city && $trip->employee->outlet->address->city->state) {
			$trip->employee_gst_code = $trip->employee->outlet->address->city->state->gstin_state_code;
		}

		if ($trip_status == 'booked') {
			$visits = Trip::select([
				DB::raw('SUM(visit_bookings.amount) as amount'),
				DB::raw('SUM(visit_bookings.tax) as tax'),
				// DB::raw('SUM(visit_bookings.paid_amount) as paid_amount'),
				// DB::raw('SUM(visit_bookings.service_charge) as service_charge'),
				DB::raw('SUM(visit_bookings.total) as paid_amount'),
				DB::raw('SUM(visit_bookings.agent_service_charges) as service_charge'),
			])
				->join('visits', 'trips.id', 'visits.trip_id')
				->join('visit_bookings', 'visit_bookings.visit_id', 'visits.id')
				->where('visits.booking_method_id', 3042) //Agent
				->where('visit_bookings.created_by', Auth::user()->id)
				->where('visits.trip_id', $trip_id)
				->groupBy('visits.trip_id')
				->first();

			if ($visits) {
				$ticket_amount = $visits->amount + $visits->tax;
				$service_charge = $visits->service_charge;
				$total_amount = $visits->paid_amount;
			}

		}
		$start_date = $trip->visits()->select(DB::raw('DATE_FORMAT(MIN(visits.departure_date),"%d/%m/%Y") as start_date'))->first();
		$end_date = $trip->visits()->select(DB::raw('DATE_FORMAT(MAX(visits.departure_date),"%d/%m/%Y") as end_date'))->first();
		$days = $trip->visits()->select(DB::raw('DATEDIFF(MAX(visits.departure_date),MIN(visits.departure_date)) as days'))->first();
		$trip->start_date = $start_date->start_date;
		$trip->end_date = $end_date->end_date;
		$trip->days = $days->days;
		//$trip->attachments = $attachments;
		$this->data['travel_mode_list'] = $payment_mode_list = collect(Entity::agentTravelModeList())->prepend(['id' => '', 'name' => 'Select Travel Mode']);
		$this->data['booking_mode_list'] = $booking_mode_list = collect(Entity::bookingModeList())->prepend(['id' => '', 'name' => 'Select Booking Method']);
		$this->data['booking_category_list'] = Config::select('id', 'name')->where('config_type_id', 545)->get()->prepend(['id' => '', 'name' => 'Select Booking Category']);
		$this->data['bookingMethods'] = BookingMethod::pluck('amount', 'id');
		$this->data['booking_method_list'] = [];
		$this->data['trip'] = $trip;
		//$this->data['trip']['visit_booking'] = $visit_booking;

		$this->data['age'] = $age;
		$this->data['trip_status'] = $trip_status;
		$this->data['total_amount'] = $total_amount;
		$this->data['ticket_amount'] = $ticket_amount;
		$this->data['service_charge'] = $service_charge;
		$this->data['attach_path'] = url('storage/app/public/visit/booking-updates/attachments/');
		$this->data['success'] = true;
		return response()->json($this->data);
	}

	public function getBookingMethodsByTravelMode($travelTypeId,$visitId) {
		$visit = Visit::find($visitId);
					$trip = Trip::find($visit->trip_id);
					$state = $trip->employee->outlet->address->city->state;
		$travelType = $service_charge = Agent::join('state_agent_travel_mode', 'state_agent_travel_mode.agent_id', 'agents.id')
		                ->where('state_agent_travel_mode.agent_id', $visit->agent_id)
						->where('state_agent_travel_mode.state_id', $state->id)
						->where('state_agent_travel_mode.travel_mode_id', $travelTypeId)
						->pluck('state_agent_travel_mode.service_charge')->first();
		if (!$travelType) {
			return response()->json([
				'success' => false,
				'error' => 'Agent Service Charge not found',
			]);
		}

		return response()->json([
			'success' => true,
			'booking_method_list' => $travelType,
		]);
	}

	public function saveAgentRequest(Request $r) {
		$trip = Trip::find($r->trip_id);
		if (!$trip) {
			return response()->json(['success' => false, 'errors' => ['Trip not found']]);
		}
		$trip->advance_request_approval_status_id = 3261;
		$trip->save();

		//PAYMENT SAVE
		$payment = Payment::firstOrNew(['entity_id' => $trip->id]);
		$payment->fill($r->all());
		$payment->payment_of_id = 3250;
		$payment->entity_id = $trip->id;
		$payment->created_by = Auth::user()->id;
		$payment->save();

		//BANK DETAIL SAVE
		if ($r->bank_name) {
			$bank_detail = BankDetail::firstOrNew(['entity_id' => $trip->id]);
			$bank_detail->fill($r->all());
			$bank_detail->detail_of_id = 3243;
			$bank_detail->entity_id = $trip->id;
			$bank_detail->account_type_id = 3243;
			$bank_detail->save();
		}

		//WALLET SAVE
		if ($r->type_id) {
			$wallet_detail = WalletDetail::firstOrNew(['entity_id' => $trip->id]);
			$wallet_detail->fill($r->all());
			$wallet_detail->wallet_of_id = 3243;
			$wallet_detail->entity_id = $trip->id;
			$wallet_detail->save();
		}

		// $trip->visits()->update(['manager_verification_status_id' => 3080]);
		return response()->json(['success' => true]);
	}

	public function approveAdvanceClaimRequest($trip_id) {
		$trip = Trip::find($trip_id);
		if (!$trip) {
			return response()->json(['success' => false, 'errors' => ['Trip not found']]);
		}
		$trip->status_id = 3028;
		$trip->save();

		$trip->visits()->update(['manager_verification_status_id' => 3081]);
		return response()->json(['success' => true]);
	}

	public function rejectAdvanceClaimRequest($trip_id) {
		$trip = Trip::find($trip_id);
		if (!$trip) {
			return response()->json(['success' => false, 'errors' => ['Trip not found']]);
		}
		$trip->status_id = 3022;
		$trip->save();

		$trip->visits()->update(['manager_verification_status_id' => 3082]);
		return response()->json(['success' => true]);
	}

}
