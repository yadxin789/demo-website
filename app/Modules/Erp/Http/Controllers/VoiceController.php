<?php

namespace App\Modules\Erp\Http\Controllers;

use App\Modules\Erp\Enums\VoiceRoomStatus;
use App\Modules\Erp\Models\Office\VoiceRoom;
use App\Modules\Erp\Services\NumberGenerator;
use Illuminate\Http\Request;

class VoiceController extends Controller
{
    public function index()
    {
        $rooms = VoiceRoom::with('host')->orderByDesc('id')->paginate(15);

        return view('erp::voice.index', compact('rooms'));
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:128']]);
        VoiceRoom::create([
            'room_no' => NumberGenerator::generate('voice'),
            'name' => $data['name'],
            'host_id' => auth()->id(),
            'status' => VoiceRoomStatus::Waiting->value,
        ]);

        return back()->with('success', '会议室已创建（WebRTC 待接入）。');
    }
}
