<?php

namespace App\Modules\Shop\Http\Controllers\Master;

use App\Modules\Shop\Http\Controllers\Controller;
use App\Modules\Shop\Http\Requests\Master\SequenceRequest;
use App\Modules\Shop\Models\Sequence;
use App\Modules\Shop\Support\SequenceGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SequenceController extends Controller
{
    public function index(SequenceGenerator $generator): View
    {
        $sequences = Sequence::orderBy('code')->get();
        $previews = $sequences->mapWithKeys(fn (Sequence $seq) => [
            $seq->id => $generator->preview($seq),
        ]);

        return view('shop::master.sequences.index', compact('sequences', 'previews'));
    }

    public function edit(Sequence $sequence): View
    {
        return view('shop::master.sequences.edit', compact('sequence'));
    }

    public function update(SequenceRequest $request, Sequence $sequence): RedirectResponse
    {
        $sequence->update($request->validated());

        return redirect()->route('shop.sequences.index')->with('status', '编号规则已更新');
    }
}
