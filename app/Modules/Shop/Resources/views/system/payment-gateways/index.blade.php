@extends('shop::layouts.app')

@section('title', '微信 / 支付宝接入')
@section('heading', '微信 / 支付宝接入引导')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">微信 / 支付宝开通引导</h1>
            <p class="text-muted mb-0 small">首次运行按步骤登记商户资料，保存后检测必填项、密钥格式与官方网关连通性。</p>
        </div>
        <div class="text-end">
            <span class="badge text-bg-primary">已通过检测 {{ $summary['ready'] }} / {{ $summary['total'] }}</span>
            <span class="badge text-bg-secondary ms-1">已登记 {{ $summary['configured'] }} / {{ $summary['total'] }}</span>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success py-2" role="status">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger py-2" role="alert">{{ session('error') }}</div>
    @endif

    @if ($summary['configured'] === 0)
        <div class="alert alert-info" role="status">
            <h2 class="h6 mb-1"><i class="fa-solid fa-rocket me-1"></i>首次接入建议</h2>
            <p class="mb-0 small">
                先完成一个通道的商户资料，再切换另一个通道。每填完一个通道点击“保存并检测连接”；
                检测通过只代表资料与网络可用，真实收款还需完成支付网关下单、回调验签与退款联调。
            </p>
        </div>
    @elseif ($summary['ready'] < $summary['total'])
        <div class="alert alert-warning py-2" role="status">
            <i class="fa-solid fa-circle-exclamation me-1"></i>
            还有 {{ $summary['total'] - $summary['ready'] }} 个支付通道未通过配置检测，请查看下方状态。
        </div>
    @endif

    <div class="row g-3 mb-3">
        @foreach ($providers as $provider)
            <div class="col-12 col-md-6">
                <div class="card h-100">
                    <div class="card-body d-flex justify-content-between align-items-start gap-3">
                        <div class="min-w-0">
                            <p class="h6 mb-1">
                                <i class="{{ $provider['icon'] }} me-1"></i>{{ $provider['name'] }}
                            </p>
                            <p class="small text-muted mb-0">必填 {{ $provider['required_done'] }} / {{ $provider['required_total'] }} 项</p>
                        </div>
                        <span class="badge text-bg-{{ $provider['status_tone'] }}">{{ $provider['status_label'] }}</span>
                    </div>
                    <div class="progress rounded-0" role="progressbar"
                         aria-label="{{ $provider['name'] }}资料完整度"
                         aria-valuenow="{{ $provider['required_done'] }}"
                         aria-valuemin="0" aria-valuemax="{{ $provider['required_total'] }}">
                        @php
                            $percent = $provider['required_total'] > 0
                                ? round($provider['required_done'] / $provider['required_total'] * 100)
                                : 0;
                        @endphp
                        <div class="progress-bar bg-{{ $provider['status_tone'] }}" style="width: {{ $percent }}%">{{ $percent }}%</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card">
        <div class="card-body">
            <nav class="nav nav-pills flex-wrap gap-2 mb-3" role="tablist" aria-label="支付通道选择">
                @foreach ($providers as $key => $provider)
                    <button class="nav-link @if ($activeProvider === $key) active @endif"
                            id="gateway-tab-{{ $key }}"
                            data-bs-toggle="pill"
                            data-bs-target="#gateway-pane-{{ $key }}"
                            type="button" role="tab"
                            aria-controls="gateway-pane-{{ $key }}"
                            aria-selected="{{ $activeProvider === $key ? 'true' : 'false' }}">
                        <i class="{{ $provider['icon'] }} me-1"></i>{{ $provider['name'] }}
                        <span class="badge text-bg-{{ $provider['status_tone'] }} ms-1">{{ $provider['status_label'] }}</span>
                    </button>
                @endforeach
            </nav>

            <div class="tab-content">
                @foreach ($providers as $key => $provider)
                    @php
                        $setting = $provider['setting'];
                    @endphp
                    <div class="tab-pane fade @if ($activeProvider === $key) show active @endif"
                         id="gateway-pane-{{ $key }}" role="tabpanel"
                         aria-labelledby="gateway-tab-{{ $key }}" tabindex="0">

                        <div class="row g-3">
                            <div class="col-12 col-xl-5">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <h2 class="h6 mb-2"><i class="fa-solid fa-list-check me-1"></i>开通步骤</h2>
                                        <p class="small text-muted">{{ $provider['description'] }}</p>

                                        <div class="d-flex flex-wrap gap-2 mb-3">
                                            <a class="btn btn-sm btn-outline-primary" href="{{ $provider['portal_url'] }}"
                                               target="_blank" rel="noopener noreferrer">
                                                <i class="fa-solid fa-arrow-up-right-from-square me-1"></i>{{ $provider['portal_name'] }}
                                            </a>
                                            <a class="btn btn-sm btn-outline-secondary" href="{{ $provider['secondary_url'] }}"
                                               target="_blank" rel="noopener noreferrer">
                                                {{ $provider['secondary_name'] }}
                                            </a>
                                        </div>

                                        <ol class="small mb-3 ps-3">
                                            @foreach ($provider['steps'] as $step)
                                                <li class="mb-1">{{ $step }}</li>
                                            @endforeach
                                        </ol>

                                        <h3 class="h6 mb-2">资料检查</h3>
                                        <ul class="list-unstyled small mb-3">
                                            @foreach ($provider['required'] as $field)
                                                @php
                                                    $fieldLabel = match ($field) {
                                                        'app_id' => $provider['app_label'],
                                                        'merchant_id' => $provider['merchant_label'],
                                                        'api_secret' => $provider['secret_label'],
                                                        'private_key' => $provider['private_key_label'],
                                                        'public_key' => $provider['public_key_label'],
                                                        'cert_serial' => $provider['cert_serial_label'],
                                                        default => $field,
                                                    };
                                                    $isMissing = in_array($fieldLabel, $provider['missing'], true);
                                                @endphp
                                                <li class="d-flex align-items-start gap-2 mb-1">
                                                    <i class="fa-solid {{ $isMissing ? 'fa-circle-xmark text-danger' : 'fa-circle-check text-success' }} mt-1"></i>
                                                    <span>{{ $fieldLabel }}{{ $isMissing ? '（待填写）' : '（已填写）' }}</span>
                                                </li>
                                            @endforeach
                                        </ul>

                                        @if ($provider['notices'])
                                            <h3 class="h6 mb-2">注意事项</h3>
                                            <ul class="small text-muted mb-0 ps-3">
                                                @foreach ($provider['notices'] as $notice)
                                                    <li class="mb-1">{{ $notice }}</li>
                                                @endforeach
                                            </ul>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <div class="col-12 col-xl-7">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                                            <div>
                                                <h2 class="h6 mb-1">接入资料</h2>
                                                <p class="small text-muted mb-0">保存时密钥字段加密入库，页面不回显完整内容。</p>
                                            </div>
                                            <span class="badge text-bg-{{ $provider['status_tone'] }}">{{ $provider['status_label'] }}</span>
                                        </div>

                                        @if ($setting->last_check_status)
                                            <div class="alert {{ $setting->last_check_status === 'pass' ? 'alert-success' : 'alert-danger' }} py-2 small"
                                                 role="status">
                                                <i class="fa-solid {{ $setting->last_check_status === 'pass' ? 'fa-circle-check' : 'fa-triangle-exclamation' }} me-1"></i>
                                                {{ $setting->last_check_message }}
                                                @if ($setting->checkedLabel())
                                                    <span class="text-muted ms-1">（{{ $setting->checkedLabel() }}）</span>
                                                @endif
                                            </div>
                                        @elseif ($setting->exists)
                                            <div class="alert alert-secondary py-2 small" role="status">
                                                <i class="fa-solid fa-clock me-1"></i>资料已保存，尚未检测。修改资料后需重新检测。
                                            </div>
                                        @endif

                                        <form method="post" action="{{ route('shop.payment-gateways.update', ['provider' => $key]) }}">
                                            @csrf
                                            @method('PUT')

                                            <div class="row g-3">
                                                <div class="col-12">
                                                    <label for="{{ $key }}-app-id" class="form-label">{{ $provider['app_label'] }}</label>
                                                    <input type="text" class="form-control @error('app_id') is-invalid @enderror"
                                                           id="{{ $key }}-app-id" name="app_id" maxlength="80"
                                                           value="{{ old('app_id', $setting->app_id) }}"
                                                           autocomplete="off" spellcheck="false">
                                                    <div class="form-text">{{ $provider['app_hint'] }}</div>
                                                    @error('app_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                                </div>

                                                <div class="col-12 col-md-7">
                                                    <label for="{{ $key }}-merchant-id" class="form-label">{{ $provider['merchant_label'] }}</label>
                                                    <input type="text" class="form-control @error('merchant_id') is-invalid @enderror"
                                                           id="{{ $key }}-merchant-id" name="merchant_id" maxlength="80"
                                                           value="{{ old('merchant_id', $setting->merchant_id) }}"
                                                           autocomplete="off" spellcheck="false">
                                                    <div class="form-text">{{ $provider['merchant_hint'] }}</div>
                                                    @error('merchant_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                                </div>

                                                <div class="col-12 col-md-5">
                                                    <label for="{{ $key }}-cert-serial" class="form-label">{{ $provider['cert_serial_label'] }}</label>
                                                    <input type="text" class="form-control @error('cert_serial') is-invalid @enderror"
                                                           id="{{ $key }}-cert-serial" name="cert_serial" maxlength="80"
                                                           value="{{ old('cert_serial', $setting->cert_serial) }}"
                                                           autocomplete="off" spellcheck="false">
                                                    <div class="form-text">{{ $provider['cert_serial_hint'] }}</div>
                                                    @error('cert_serial')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                                </div>

                                                <div class="col-12">
                                                    <label for="{{ $key }}-api-secret" class="form-label">
                                                        {{ $provider['secret_label'] }}
                                                        @if ($setting->hasSecret('api_secret'))
                                                            <span class="badge text-bg-success ms-1">已保存</span>
                                                        @endif
                                                    </label>
                                                    <input type="password" class="form-control @error('api_secret') is-invalid @enderror"
                                                           id="{{ $key }}-api-secret" name="api_secret" maxlength="4096"
                                                           value="" autocomplete="new-password" spellcheck="false"
                                                           placeholder="{{ $setting->hasSecret('api_secret') ? '已保存，留空则不修改' : '粘贴接口密钥' }}">
                                                    <div class="form-text">{{ $provider['secret_hint'] }}</div>
                                                    @error('api_secret')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                                </div>

                                                <div class="col-12">
                                                    <label for="{{ $key }}-private-key" class="form-label">
                                                        {{ $provider['private_key_label'] }}
                                                        @if ($setting->hasSecret('private_key'))
                                                            <span class="badge text-bg-success ms-1">已保存</span>
                                                        @endif
                                                    </label>
                                                    <textarea class="form-control font-monospace @error('private_key') is-invalid @enderror"
                                                              id="{{ $key }}-private-key" name="private_key" rows="4" maxlength="8192"
                                                              spellcheck="false"
                                                              placeholder="{{ $setting->hasSecret('private_key') ? '已保存，留空则不修改' : '-----BEGIN PRIVATE KEY-----' }}"></textarea>
                                                    <div class="form-text">{{ $provider['private_key_hint'] }}</div>
                                                    @error('private_key')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                                </div>

                                                <div class="col-12">
                                                    <label for="{{ $key }}-public-key" class="form-label">
                                                        {{ $provider['public_key_label'] }}
                                                        @if ($setting->hasSecret('public_key'))
                                                            <span class="badge text-bg-success ms-1">已保存</span>
                                                        @endif
                                                    </label>
                                                    <textarea class="form-control font-monospace @error('public_key') is-invalid @enderror"
                                                              id="{{ $key }}-public-key" name="public_key" rows="4" maxlength="8192"
                                                              spellcheck="false"
                                                              placeholder="{{ $setting->hasSecret('public_key') ? '已保存，留空则不修改' : '粘贴平台公钥' }}"></textarea>
                                                    <div class="form-text">{{ $provider['public_key_hint'] }}</div>
                                                    @error('public_key')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                                </div>

                                                <div class="col-12">
                                                    <label for="{{ $key }}-gateway-url" class="form-label">官方网关地址</label>
                                                    <input type="url" class="form-control font-monospace @error('gateway_url') is-invalid @enderror"
                                                           id="{{ $key }}-gateway-url" name="gateway_url" maxlength="255"
                                                           value="{{ old('gateway_url', $setting->gateway_url ?: $provider['gateway_url']) }}"
                                                           spellcheck="false">
                                                    <div class="form-text">默认即可。检测时只允许官方域名 {{ implode(' / ', $provider['gateway_hosts']) }}。</div>
                                                    @error('gateway_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                                </div>
                                            </div>

                                            <div class="alert alert-warning py-2 small mt-3 mb-3" role="note">
                                                <i class="fa-solid fa-shield-halved me-1"></i>
                                                本页只完成资料登记和连通性检查，不会发起真实扣款。上线前仍需完成 API 下单、异步回调验签、退款与对账对接。
                                            </div>

                                            @if (auth()->user()->hasPermission('system.user.manage'))
                                                <div class="d-flex flex-wrap gap-2">
                                                    <button type="submit" name="check" value="1" class="btn btn-primary">
                                                        <i class="fa-solid fa-plug-circle-check me-1"></i>保存并检测连接
                                                    </button>
                                                    <button type="submit" class="btn btn-outline-secondary">
                                                        <i class="fa-solid fa-floppy-disk me-1"></i>仅保存
                                                    </button>
                                                    @if ($setting->exists)
                                                        <button type="submit" class="btn btn-outline-primary"
                                                                formaction="{{ route('shop.payment-gateways.check', ['provider' => $key]) }}"
                                                                formmethod="post">
                                                            <i class="fa-solid fa-vial me-1"></i>检测已保存配置
                                                        </button>
                                                    @endif
                                                </div>
                                            @else
                                                <p class="small text-muted mb-0">
                                                    <i class="fa-solid fa-lock me-1"></i>保存与检测需要系统管理员权限，请联系管理员。
                                                </p>
                                            @endif
                                        </form>

                                        @if ($provider['missing'] !== [])
                                            <p class="small text-danger mt-3 mb-0">
                                                <i class="fa-solid fa-circle-exclamation me-1"></i>
                                                待补全：{{ implode('、', $provider['missing']) }}
                                            </p>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endsection
