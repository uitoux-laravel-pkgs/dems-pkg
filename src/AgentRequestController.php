<?php

namespace Uitoux\EYatra;
use App\Http\Controllers\Controller;
use Auth;
use DB;
use Illuminate\Http\Request;
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
			->select(
				'trips.id',
				'trips.number',
				'e.code as ecode',
				DB::raw('GROUP_CONCAT(DISTINCT(c.name)) as cities'),
				DB::raw('DATE_FORMAT(MIN(v.date),"%d/%m/%Y") as start_date'),
				DB::raw('DATE_FORMAT(MAX(v.date),"%d/%m/%Y") as end_date'),
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
				<a href="#!/eyatra/advance-claim/request/form/' . $trip->id . '">
					<img src="' . $img2 . '" alt="View" class="img-responsive" onmouseover=this.src="' . $img2_active . '" onmouseout=this.src="' . $img2 . '" >
				</a>
				';

			})
			->make(true);
	}

	public function agentRequestFormData($trip_id) {
		$trip = Trip::with([
			'agentVisits',
			'agentVisits.fromCity',
			'agentVisits.toCity',
			'agentVisits.travelMode',
			'agentVisits.bookingMethod',
			'agentVisits.bookingStatus',
			'agentVisits.bookings',
			'agentVisits.bookings.travelMode',
			'agentVisits.agent',
			'agentVisits.status',
			'agentVisits.managerVerificationStatus',
			'employee',
			'employee.user',
			'purpose',
			'status',
		])
			->find($trip_id);

		if (!$trip) {
			return response()->json(['success' => false, 'errors' => ['Trip not found']]);
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
				->where('visit_bookings.created_by', Auth::user()->id)
				->where('visits.trip_id', $trip_id)
				->groupBy('visits.trip_id')
				->first();
			$ticket_amount = $visits->amount + $visits->tax;
			$service_charge = $visits->service_charge;
			$total_amount = $visits->paid_amount;
		}
		$start_date = $trip->visits()->select(DB::raw('DATE_FORMAT(MIN(visits.date),"%d/%m/%Y") as start_date'))->first();
		$end_date = $trip->visits()->select(DB::raw('DATE_FORMAT(MIN(visits.date),"%d/%m/%Y") as start_date'))->first();
		$trip->start_date = $start_date->start_date;
		$trip->end_date = $start_date->end_date;
		$this->data['travel_mode_list'] = $payment_mode_list = collect(Entity::travelModeList())->prepend(['id' => '', 'name' => 'Select Travel Mode']);
		$this->data['trip'] = $trip;
		$this->data['trip_status'] = $trip_status;
		$this->data['total_amount'] = $total_amount;
		$this->data['ticket_amount'] = $ticket_amount;
		$this->data['service_charge'] = $service_charge;
		$this->data['success'] = true;
		return response()->json($this->data);
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
