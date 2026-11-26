@extends('shop::layouts.app')

@section('title', '期末结账')
@section('heading', '期末结账与期间关闭')

@section('content')
    @if (session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
    @endif

    <div class="alert alert-info">
        <i class="fa-solid fa-circle-info me-1"></i>
        结账前须将期间内全部凭证过账；存在草稿凭证时阻止关闭。损益结转分录留待后续阶段完善，本期关闭仅作期间锁定。
    </div>

    <div class="card p-0">
        <div class="table-wrapper">
            <table class="table table-hover mb-0">
                <caption class="visually-hidden">会计期间结账清单</caption>
                <thead>
                    <tr>
                        <th scope="col">期间名称</th>
                        <th scope="col">起始日期</th>
                        <th scope="col">截止日期</th>
                        <th scope="col" class="text-end">未过账凭证</th>
                        <th scope="col">状态</th>
                        <th scope="col">操作</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($periods as $row)
                        @php $period = $row['period']; @endphp
                        <tr>
                            <td>{{ $period->name }}</td>
                            <td>{{ optional($period->start_date)->toDateString() }}</td>
                            <td>{{ optional($period->end_date)->toDateString() }}</td>
                            <td class="text-end">{{ $row['draftCount'] }}</td>
                            <td>
                                @if ($period->isClosed())
                                    <span class="badge text-bg-secondary">已关闭</span>
                                @else
                                    <span class="badge text-bg-success">开放</span>
                                @endif
                            </td>
                            <td>
                                @if ($row['canClose'])
                                    <form method="post" action="{{ route('shop.closing.close', $period->id) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-primary"
                                            onclick="return confirm('确认结账关闭期间 {{ $period->name }}？关闭后不可再过账。')">
                                            结账关闭
                                        </button>
                                    </form>
                                @elseif ($period->isClosed())
                                    <span class="text-muted small">已锁定</span>
                                @else
                                    <span class="text-muted small">有待过账凭证</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">暂无会计期间</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
