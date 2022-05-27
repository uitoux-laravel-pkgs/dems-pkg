<?php

namespace Uitoux\EYatra\Api;
use App\Http\Controllers\Controller;
use App\User;
use Auth;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use Uitoux\EYatra\AlternateApprove;
use Uitoux\EYatra\ActivityLog;
use Uitoux\EYatra\ApprovalLog;
use Uitoux\EYatra\EmployeeClaim;
use Uitoux\EYatra\Boarding;
use Uitoux\EYatra\Entity;
use Uitoux\EYatra\LocalTravel;
use Uitoux\EYatra\Lodging;
use Uitoux\EYatra\Trip;
use Uitoux\EYatra\Visit;

class TripClaimVerificationTwoController extends Controller
{
   public function listEYatraTripClaimVerificationTwoList(Request $r) {
        //dd(Auth::user()->entity_id);
        $trips = EmployeeClaim::join('trips', 'trips.id', 'ey_employee_claims.trip_id')
            ->join('visits as v', 'v.trip_id', 'trips.id')
            ->join('ncities as c', 'c.id', 'v.from_city_id')
            ->join('employees as e', 'e.id', 'trips.employee_id')
            ->join('employees as trip_manager_employee', 'trip_manager_employee.id', 'trips.manager_id')
            ->join('employees as se_manager_employee', 'se_manager_employee.id', 'trip_manager_employee.reporting_to_id')
            ->join('entities as purpose', 'purpose.id', 'trips.purpose_id')
            ->join('configs as status', 'status.id', 'trips.status_id')
            ->leftJoin('users', 'users.entity_id', 'trips.employee_id')
            ->where('users.user_type_id', 3121)
            ->select(
                'trips.id',
                'ey_employee_claims.number as claim_number',
                'trips.number',
                'e.code as ecode',
                'users.name as ename',
                DB::raw('GROUP_CONCAT(DISTINCT(c.name)) as cities'),
                DB::raw('DATE_FORMAT(trips.start_date,"%d-%m-%Y") as start_date'),
                DB::raw('DATE_FORMAT(trips.end_date,"%d-%m-%Y") as end_date'),
                'purpose.name as purpose',
                'trips.advance_received',
                'status.name as status'
            )
            ->where('e.company_id', Auth::user()->company_id)
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
            ->where(function ($query) use ($r) {
                if (!empty($r->from_date)) {
                    $query->where('trips.start_date', date("Y-m-d", strtotime($r->from_date)));
                }
            })
            ->where(function ($query) use ($r) {
                if (!empty($r->to_date)) {
                    $query->where('trips.end_date', date("Y-m-d", strtotime($r->to_date)));
                }
            })
            ->where(function ($query) {
                if (Auth::user()->entity_id) {
                    //$query->where('se_manager_employee.id', Auth::user()->entity_id);
                    //$trip_manager = Trip::select('manager_id')->where('id', $trips->id)->first();
                    //dd(Auth::user()->entity_id);
                    $now = date('Y-m-d');
                    $sub_employee_id = AlternateApprove::select('employee_id')
                        ->where('from', '<=', $now)
                        ->where('to', '>=', $now)
                        ->where('alternate_employee_id', Auth::user()->entity_id)
                        ->get()
                        ->toArray();
                    //dd($sub_employee_id);
                    $ids = array_column($sub_employee_id, 'employee_id');
                    //dd($ids);
                    array_push($ids, Auth::user()->entity_id);
                    //dd($ids);
                    if (count($sub_employee_id) > 0) {
                        $query->whereIn('se_manager_employee.id', $ids); //Alternate MANAGER
                    } else {
                        $query->where('se_manager_employee.id', Auth::user()->entity_id); //MANAGER
                    }

                }
            })
            ->where('ey_employee_claims.status_id', 3029) //SENIOR MANAGER APPROVAL PENDING
            ->groupBy('trips.id')
            ->orderBy('trips.created_at', 'desc');

        return response()->json(['success' => true, 'trips' => $trips]);
    }

    public function viewEYatraTripClaimVerificationTwo($trip_id) {
        return Trip::getClaimViewData($trip_id);
    }

    public function approveTripClaimVerificationTwo(Request $r) {
        $trip_id=$r->trip_id;
        $trip = Trip::find($trip_id);

        if (!$trip) {
            return response()->json(['success' => false, 'errors' => ['Trip not found']]);
        }
        $trip->verification_two_remarks=$r->verification_two_remarks;
        $activity['entity_id'] = $trip->id;
        $activity['entity_type'] = 'trip';
        $activity['details'] = "Employee Claims V2 Approved";
        $activity['activity'] = "approve";
        $activity_log = ActivityLog::saveLog($activity);
        //Approval Log
        $approval_log = ApprovalLog::saveApprovalLog(3581, $trip->id, 3602, Auth::user()->entity_id, Carbon::now());
        $employee_claim = EmployeeClaim::where('trip_id', $trip_id)->first();
        if (!$employee_claim) {
            return response()->json(['success' => false, 'errors' => ['Trip not found']]);
        }
        // if ($trip->advance_received > $employee_claim->claim_total_amount) {
        //  $trip->status_id = 3031; // Payment Pending for Employee
        //  $employee_claim->status_id = 3031; // Payment Pending for Employee
        // } else {
        //  $trip->status_id = 3025; // Payment Pending for Financier
        //  $employee_claim->status_id = 3025; // Payment Pending for Financier
        // }
        // // $employee_claim->status_id = 3223; //Payment Pending
        $additional_approve = Auth::user()->company->additional_approve;
        $financier_approve = Auth::user()->company->financier_approve;
        if ($additional_approve == '1') {
            $employee_claim->status_id = 3036; //Claim Verification Pending
            $trip->status_id = 3036; //Claim Verification Pending
        } else if ($financier_approve == '1') {
            $employee_claim->status_id = 3034; //Payment Pending
            $trip->status_id = 3034; //Payment Pending
        } else {
            $employee_claim->status_id = 3026; //Payment Completed
            $trip->status_id = 3026; //Payment Completed
        }
        $employee_claim->save();
        $trip->save();
        // Update attachment status by Karthick T on 20-01-2022
        $update_attachment_status = Attachment::where('entity_id', $trip->id)
                ->whereIn('attachment_of_id', [3180, 3181, 3182, 3183, 3185, 3189])
                ->where('attachment_type_id', 3200)
                ->where('view_status', 1)
                ->update(['view_status' => 0]);
        // Update attachment status by Karthick T on 20-01-2022

        $user = User::where('entity_id', $trip->employee_id)->where('user_type_id', 3121)->first();
        $notification = sendnotification($type = 6, $trip, $user, $trip_type = "Outstation Trip", $notification_type = 'Claim Approved');
        $notification = sendnotification($type = 13, $trip, $user, $trip_type = "Outstation Trip", $notification_type = 'Claim Approved');
        return response()->json(['success' => true]);
    }

    public function rejectTripClaimVerificationTwo(Request $r) {

        $trip = Trip::find($r->trip_id);
        if (!$trip) {
            return response()->json(['success' => false, 'errors' => ['Trip not found']]);
        }
        $employee_claim = EmployeeClaim::where('trip_id', $r->trip_id)->first();
        if (!$employee_claim) {
            return response()->json(['success' => false, 'errors' => ['Trip not found']]);
        }
        $employee_claim->status_id = 3024; //Claim Rejected
        $employee_claim->save();

        $trip->rejection_id = $r->reject_id;
        $trip->rejection_remarks = $r->remarks;
        $trip->status_id = 3024; //Claim Rejected
        $trip->save();
        // Update attachment status by Karthick T on 20-01-2022
        $update_attachment_status = Attachment::where('entity_id', $trip->id)
                ->whereIn('attachment_of_id', [3180, 3181, 3182, 3183, 3185, 3189])
                ->where('attachment_type_id', 3200)
                ->where('view_status', 1)
                ->update(['view_status' => 0]);
        // Update attachment status by Karthick T on 20-01-2022
        $activity['entity_id'] = $trip->id;
        $activity['entity_type'] = 'trip';
        $activity['details'] = "Employee Claims V2 Rejected";
        $activity['activity'] = "reject";
        $activity_log = ActivityLog::saveLog($activity);

        $user = User::where('entity_id', $trip->employee_id)->where('user_type_id', 3121)->first();
        $notification = sendnotification($type = 7, $trip, $user, $trip_type = "Outstation Trip", $notification_type = 'Claim Rejected');

        return response()->json(['success' => true]);
    }
}
