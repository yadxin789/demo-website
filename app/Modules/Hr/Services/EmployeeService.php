<?php

declare(strict_types=1);

namespace App\Modules\Hr\Services;

use App\Modules\Hr\Models\Department;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\EmployeeContract;
use App\Modules\Hr\Models\EmployeeMovement;
use App\Modules\Hr\Models\Headcount;
use App\Modules\Hr\Models\Position;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * 员工主数据服务（H1-08）。
 *
 * 一个员工的完整生命周期都在这里：
 *   入职 → 建工号 → 建档案 → 建合同 → 写 hire 异动 → 回写编制在职数
 *   转正 → 改状态 + 写 regular 异动
 *   调岗 → 改部门/岗位 + 写 transfer 异动 + 回写编制
 *   离职 → 改状态 + 写 leave 异动 + 回写编制
 */
class EmployeeService
{
    /** 下一个工号：HR2026 + 三位流水（按当年最大号 +1）。 */
    public function nextEmployeeNo(): string
    {
        $year = now()->format('Y');
        $prefix = 'HR' . $year;

        $max = Employee::query()
            ->where('employee_no', 'like', $prefix . '%')
            ->selectRaw('max(employee_no) as no')
            ->value('no');

        $seq = $max ? ((int) substr((string) $max, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
    }

    /** 下一个合同号：CT-2026-0001。 */
    public function nextContractNo(): string
    {
        $year = now()->format('Y');
        $prefix = 'CT-' . $year . '-';

        $max = EmployeeContract::query()
            ->where('contract_no', 'like', $prefix . '%')
            ->selectRaw('max(contract_no) as no')
            ->value('no');

        $seq = $max ? ((int) substr((string) $max, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    /**
     * 入职建档。
     *
     * @param  array<string, mixed>  $data
     */
    public function hire(array $data): Employee
    {
        return DB::connection('hr')->transaction(function () use ($data) {
            $employee = Employee::query()->create([
                'employee_no' => $data['employee_no'] ?? $this->nextEmployeeNo(),
                'name' => $data['name'],
                'gender' => $data['gender'] ?? 'female',
                'birthday' => $data['birthday'] ?? null,
                'id_card_masked' => $data['id_card_masked'] ?? null,
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'department_id' => isset($data['department_id']) ? (int) $data['department_id'] : null,
                'position_id' => isset($data['position_id']) ? (int) $data['position_id'] : null,
                'hire_date' => $data['hire_date'] ?? now()->toDateString(),
                'status' => $data['status'] ?? 'probation',
                'education' => $data['education'] ?? null,
                'remark' => $data['remark'] ?? null,
            ]);

            // 合同：给了类型就建一份
            $contractType = $data['contract_type'] ?? null;
            if ($contractType !== null && $contractType !== '') {
                $start = $data['contract_start'] ?? ($data['hire_date'] ?? now()->toDateString());
                $end = $data['contract_end'] ?? Carbon::parse($start)->addYears(3)->subDay()->toDateString();

                EmployeeContract::query()->create([
                    'employee_id' => $employee->id,
                    'contract_no' => $this->nextContractNo(),
                    'type' => $contractType,
                    'start_date' => $start,
                    'end_date' => $contractType === 'open' ? null : $end,
                    'sign_date' => $start,
                    'status' => 'active',
                ]);
            }

            $this->logMovement($employee, 'hire', [
                'to_dept' => $employee->department_id,
                'to_position' => $employee->position_id,
                'remark' => '入职建档',
            ]);

            $this->syncHeadcount($employee->department_id, $employee->position_id);

            return $employee;
        });
    }

    /**
     * 转正。
     */
    public function regularize(Employee $employee, ?string $date = null): Employee
    {
        $date = $date ?: now()->toDateString();

        $employee->update([
            'status' => 'active',
            'regular_date' => $date,
        ]);

        $this->logMovement($employee, 'regular', ['remark' => '试用期考核通过，转正']);

        return $employee;
    }

    /**
     * 调岗（部门 / 岗位任一变化）。
     */
    public function transfer(Employee $employee, int|string|null $departmentId, int|string|null $positionId, ?string $date = null): Employee
    {
        $fromDept = $employee->department_id;
        $fromPos = $employee->position_id;

        $departmentId = $departmentId !== null && $departmentId !== '' ? (int) $departmentId : null;
        $positionId = $positionId !== null && $positionId !== '' ? (int) $positionId : null;

        $employee->update([
            'department_id' => $departmentId,
            'position_id' => $positionId,
        ]);

        $this->logMovement($employee, 'transfer', [
            'effective_date' => $date ?: now()->toDateString(),
            'from_dept' => $fromDept,
            'to_dept' => $departmentId,
            'from_position' => $fromPos,
            'to_position' => $positionId,
            'remark' => '岗位调整',
        ]);

        $this->syncHeadcount($fromDept, $fromPos);
        $this->syncHeadcount($departmentId, $positionId);

        return $employee;
    }

    /**
     * 离职。
     */
    public function leave(Employee $employee, ?string $date = null, ?string $reason = null): Employee
    {
        $date = $date ?: now()->toDateString();

        $employee->update([
            'status' => 'left',
            'leave_date' => $date,
        ]);

        $this->logMovement($employee, 'leave', [
            'effective_date' => $date,
            'from_dept' => $employee->department_id,
            'from_position' => $employee->position_id,
            'remark' => $reason ?: '办理离职',
        ]);

        $this->syncHeadcount($employee->department_id, $employee->position_id);

        return $employee;
    }

    /**
     * 回写编制表的在职数（headcounts.filled 只是缓存，页面以实时 count 为准）。
     */
    public function syncHeadcount(int|string|null $departmentId, int|string|null $positionId): void
    {
        if (! $departmentId || ! $positionId) {
            return;
        }

        $departmentId = (int) $departmentId;
        $positionId = (int) $positionId;

        $row = Headcount::query()
            ->where('department_id', $departmentId)
            ->where('position_id', $positionId)
            ->first();

        if (! $row) {
            return;
        }

        $row->update([
            'filled' => Employee::query()
                ->where('department_id', $departmentId)
                ->where('position_id', $positionId)
                ->where('status', '!=', 'left')
                ->count(),
        ]);
    }

    /**
     * 写一条异动流水。
     *
     * @param  array<string, mixed>  $extra
     */
    public function logMovement(Employee $employee, string $type, array $extra = []): EmployeeMovement
    {
        return EmployeeMovement::query()->create([
            'employee_id' => $employee->id,
            'type' => $type,
            'effective_date' => $extra['effective_date'] ?? now()->toDateString(),
            'from_dept' => $extra['from_dept'] ?? null,
            'to_dept' => $extra['to_dept'] ?? null,
            'from_position' => $extra['from_position'] ?? null,
            'to_position' => $extra['to_position'] ?? null,
            'operator_id' => auth('hr')->id(),
            'remark' => $extra['remark'] ?? null,
        ]);
    }

    /**
     * 部门 / 岗位下拉数据。
     *
     * @return array<string, mixed>
     */
    public function formOptions(): array
    {
        return [
            'departments' => Department::query()->orderBy('level')->orderBy('sort')->get(['id', 'code', 'name', 'level']),
            'positions' => Position::query()->orderBy('code')->get(['id', 'code', 'name', 'grade', 'department_id']),
            'statusText' => self::statusText(),
        ];
    }

    /** @return array<string, string> */
    public static function statusText(): array
    {
        return [
            'probation' => '试用',
            'active' => '在职',
            'left' => '离职',
        ];
    }

    /** @return array<string, string> */
    public static function movementText(): array
    {
        return [
            'hire' => '入职',
            'regular' => '转正',
            'transfer' => '调岗',
            'promote' => '晋升',
            'leave' => '离职',
        ];
    }
}
