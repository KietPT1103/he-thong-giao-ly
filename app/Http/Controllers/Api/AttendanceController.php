<?php
namespace App\Http\Controllers\Api;
use App\Http\Requests\Attendance\MarkAttendanceRequest;
use App\Http\Requests\Attendance\StoreAttendanceSessionRequest;
use App\Models\{AttendanceSession,CatechismClass};
use App\Services\AttendanceService;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
class AttendanceController extends ApiController {
 public function index(CatechismClass $class){$this->authorize('view',$class);return $this->success($class->attendanceSessions()->with('attendances')->latest('held_at')->paginate());}
 public function store(StoreAttendanceSessionRequest $request,CatechismClass $class){$this->authorize('takeAttendance',$class);$data=$request->validated();$data['held_at']=Carbon::parse($data['held_at'])->utc()->format('Y-m-d H:i:s');if($class->attendanceSessions()->where('held_at',$data['held_at'])->exists())return response()->json(['success'=>false,'message'=>'Phiên điểm danh tại thời điểm này đã tồn tại.'],422);$session=$class->attendanceSessions()->create($data+['taken_by'=>$request->user()->id]);return $this->success($session,'Đã tạo phiên điểm danh.');}
 public function show(AttendanceSession $session){$this->authorize('view',$session);return $this->success($session->load('attendances.child','catechismClass'));}
 public function mark(MarkAttendanceRequest $request,AttendanceSession $session,AttendanceService $service){$this->authorize('update',$session);return $this->success($service->mark($session,$request->validated('attendances'),$request->user()->id),'Attendance saved.');}
 public function markAll(Request $request,AttendanceSession $session,AttendanceService $service){$this->authorize('update',$session);return $this->success($service->markAllPresent($session,$request->user()->id),'All children marked present.');}
 public function summary(AttendanceSession $session){$this->authorize('view',$session);return $this->success($session->attendances()->selectRaw('status, count(*) as count')->groupBy('status')->pluck('count','status'));}
}
