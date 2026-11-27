<?php

declare(strict_types=1);

namespace App\Modules\Hr\Http\Controllers;

use App\Modules\Hr\Models\Candidate;
use App\Modules\Hr\Models\Department;
use App\Modules\Hr\Models\Interview;
use App\Modules\Hr\Models\Position;
use App\Modules\Hr\Models\Requisition;
use App\Modules\Hr\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * 招聘（H4-01 ~ H4-04）：需求、候选人、面试、漏斗。
 *
 * 阶段推进只在候选人页做，入职时回写需求已招人数（与编制缺口口径一致）。
 */
class RecruitmentController extends Controller
{
    /** @var array<string, string> */
    public const STAGE_TEXT = [
        'screening' => '简历筛选',
        'interview' => '面试中',
        'offer' => '已发 offer',
        'onboard' => '已入职',
        'reject' => '已淘汰',
    ];

    /** @var array<string, string> */
    public const URGENCY_TEXT = [
        'high' => '紧急',
        'normal' => '正常',
        'low' => '不紧急',
    ];

    /** 推进顺序（reject 不在链上） */
    private const NEXT_STAGE = [
        'screening' => 'interview',
        'interview' => 'offer',
        'offer' => 'onboard',
    ];

    // ---------- 招聘需求 ----------
    public function requisitions(Request $request): View
    {
        $status = (string) $request->query('status', '');
        $rows = Requisition::query()
            ->with(['position:id,code,name', 'department:id,name'])
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->orderByDesc('id')
            ->get();

        $all = Requisition::query()->get(['status', 'headcount', 'hired']);

        return view('hr::recruit.requisitions', [
            'rows' => $rows,
            'status' => $status,
            'statusText' => ['open' => '在招', 'closed' => '已关闭', 'cancelled' => '已取消'],
            'urgencyText' => self::URGENCY_TEXT,
            'kpi' => [
                'total' => $all->count(),
                'open' => $all->where('status', 'open')->count(),
                'planned' => (int) $all->sum('headcount'),
                'hired' => (int) $all->sum('hired'),
                'gap' => (int) ($all->sum('headcount') - $all->sum('hired')),
            ],
            'positions' => Position::query()->with('department:id,name')->orderBy('code')->get(['id', 'code', 'name', 'department_id']),
        ]);
    }

    public function requisitionStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'position_id' => ['required', 'integer', 'exists:hr.positions,id'],
            'headcount' => ['required', 'integer', 'min:1', 'max:50'],
            'urgency' => ['required', 'in:high,normal,low'],
            'remark' => ['nullable', 'string', 'max:120'],
        ], [], [
            'position_id' => '岗位',
            'headcount' => '招聘人数',
            'urgency' => '紧急度',
            'remark' => '备注',
        ]);

        $position = Position::query()->findOrFail($data['position_id']);

        $lastNo = (string) Requisition::query()->orderByDesc('req_no')->value('req_no');
        $seq = (int) substr($lastNo, -3) + 1;

        Requisition::query()->create([
            'req_no' => 'REQ-' . now()->format('Y') . '-' . str_pad((string) $seq, 3, '0', STR_PAD_LEFT),
            'position_id' => $position->id,
            'department_id' => $position->department_id,
            'headcount' => $data['headcount'],
            'hired' => 0,
            'urgency' => $data['urgency'],
            'status' => 'open',
            'remark' => $data['remark'] ?? null,
            'raised_on' => now()->toDateString(),
        ]);

        return redirect()->route('hr.recruit.requisitions')
            ->with('status', "招聘需求已开单：{$position->name}，计划 {$data['headcount']} 人。");
    }

    public function requisitionClose(Requisition $requisition): RedirectResponse
    {
        if ($requisition->status !== 'open') {
            return back()->with('error', '这条需求不是"在招"状态，无法关闭。');
        }

        $requisition->update(['status' => 'closed']);

        return redirect()->route('hr.recruit.requisitions')
            ->with('status', "{$requisition->req_no} 已关闭，已招 {$requisition->hired} / 计划 {$requisition->headcount}。");
    }

    // ---------- 候选人 ----------
    public function candidates(Request $request): View
    {
        $stage = (string) $request->query('stage', '');
        $reqId = (int) $request->query('req', 0);
        $q = trim((string) $request->query('q', ''));

        $rows = Candidate::query()
            ->with(['requisition:id,req_no,position_id', 'requisition.position:id,name'])
            ->withCount('interviews')
            ->when($stage !== '', fn ($query) => $query->where('stage', $stage))
            ->when($reqId > 0, fn ($query) => $query->where('requisition_id', $reqId))
            ->when($q !== '', fn ($query) => $query->where('name', 'like', "%{$q}%"))
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $funnel = Candidate::query()
            ->selectRaw('stage, count(*) as n')
            ->groupBy('stage')
            ->pluck('n', 'stage');

        return view('hr::recruit.candidates', [
            'rows' => $rows,
            'stage' => $stage,
            'reqId' => $reqId,
            'q' => $q,
            'stageText' => self::STAGE_TEXT,
            'funnel' => $funnel,
            'funnelTotal' => max(1, (int) $funnel->sum()),
            'nextStage' => self::NEXT_STAGE,
            'requisitions' => Requisition::query()->with('position:id,name')->where('status', 'open')
                ->orderBy('req_no')->get(['id', 'req_no', 'position_id', 'headcount', 'hired']),
        ]);
    }

    public function candidateStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'requisition_id' => ['required', 'integer', 'exists:hr.requisitions,id'],
            'name' => ['required', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:60'],
            'source' => ['required', 'in:内推,猎头,BOSS,校招'],
            'years' => ['required', 'integer', 'min:0', 'max:40'],
            'resume_summary' => ['nullable', 'string', 'max:120'],
        ], [], [
            'requisition_id' => '应聘需求',
            'name' => '姓名',
            'source' => '来源',
            'years' => '工作年限',
        ]);

        Candidate::query()->create($data + ['stage' => 'screening']);

        return redirect()->route('hr.recruit.candidates')
            ->with('status', "候选人 {$data['name']} 已录入，当前阶段：简历筛选。");
    }

    public function candidateAdvance(Candidate $candidate): RedirectResponse
    {
        if ($candidate->stage === 'reject') {
            return back()->with('error', '已淘汰的候选人不能再推进。');
        }

        $next = self::NEXT_STAGE[$candidate->stage] ?? null;
        if ($next === null) {
            return back()->with('error', '这位候选人已经入职，没有下一阶段。');
        }

        DB::connection('hr')->transaction(function () use ($candidate, $next) {
            $candidate->update(['stage' => $next]);

            if ($next === 'onboard') {
                Requisition::query()->whereKey($candidate->requisition_id)->increment('hired');
            }
        });

        $text = self::STAGE_TEXT[$next] ?? $next;

        return redirect()->route('hr.recruit.candidates')
            ->with('status', "{$candidate->name} 已推进到「{$text}」。");
    }

    public function candidateReject(Request $request, Candidate $candidate): RedirectResponse
    {
        if ($candidate->stage === 'onboard') {
            return back()->with('error', '已入职的候选人不能标记淘汰。');
        }

        $reason = trim((string) $request->input('remark', ''));
        $candidate->update([
            'stage' => 'reject',
            'remark' => $reason !== '' ? $reason : $candidate->remark,
        ]);

        return redirect()->route('hr.recruit.candidates')
            ->with('status', "{$candidate->name} 已标记淘汰。");
    }

    // ---------- 面试 ----------
    public function interviews(Request $request): View
    {
        $round = (int) $request->query('round', 0);
        $result = (string) $request->query('result', '');

        $rows = Interview::query()
            ->with(['candidate:id,name,requisition_id,stage', 'candidate.requisition.position:id,name', 'interviewer:id,name'])
            ->when($round > 0, fn ($query) => $query->where('round', $round))
            ->when($result !== '', fn ($query) => $query->where('result', $result))
            ->orderByDesc('interviewed_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $all = Interview::query()->get(['score', 'result', 'round']);

        return view('hr::recruit.interviews', [
            'rows' => $rows,
            'round' => $round,
            'result' => $result,
            'resultText' => ['pass' => '通过', 'fail' => '未通过', 'hold' => '待定'],
            'stageText' => self::STAGE_TEXT,
            'kpi' => [
                'total' => $all->count(),
                'pass' => $all->where('result', 'pass')->count(),
                'fail' => $all->where('result', 'fail')->count(),
                'avg' => $all->count() > 0 ? round((float) $all->avg('score'), 1) : 0,
            ],
            'candidates' => Candidate::query()
                ->with('requisition.position:id,name')
                ->whereNotIn('stage', ['reject'])
                ->orderBy('name')
                ->get(['id', 'name', 'requisition_id', 'stage']),
            'interviewers' => User::query()->orderBy('id')->get(['id', 'name', 'role_code']),
            'departments' => Department::query()->orderBy('level')->get(['id', 'name']),
        ]);
    }

    public function interviewStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'candidate_id' => ['required', 'integer', 'exists:hr.candidates,id'],
            'round' => ['required', 'integer', 'min:1', 'max:5'],
            'interviewer_id' => ['nullable', 'integer', 'exists:hr.users,id'],
            'score' => ['required', 'numeric', 'min:0', 'max:10'],
            'result' => ['required', 'in:pass,fail,hold'],
            'feedback' => ['nullable', 'string', 'max:200'],
        ], [], [
            'candidate_id' => '候选人',
            'round' => '轮次',
            'interviewer_id' => '面试官',
            'score' => '评分',
            'result' => '结论',
        ]);

        $candidate = Candidate::query()->findOrFail($data['candidate_id']);

        Interview::query()->create($data + ['interviewed_at' => now()]);

        // 面试通过且候选人还在筛选阶段时，自动推进到"面试中"，避免漏斗与记录不一致
        if ($data['result'] === 'pass' && $candidate->stage === 'screening') {
            $candidate->update(['stage' => 'interview']);
        }

        $resultText = ['pass' => '通过', 'fail' => '未通过', 'hold' => '待定'][$data['result']];

        return redirect()->route('hr.recruit.interviews')
            ->with('status', "{$candidate->name} 第 {$data['round']} 轮面试已记录：{$resultText}，{$data['score']} 分。");
    }
}
