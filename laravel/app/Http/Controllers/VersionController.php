<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use \App\Models\ApplicationState;

class VersionController extends Controller
{
    public function banner_dismiss(Request $request)
    {
        request()->validate([
            'version' => ['required', 'string']
        ]);

        session(['dismissed_version' => request('version')]);

        return response()->json(['success' => true]);
    }

    public function status(Request $request)
    {
        $state = ApplicationState::find('app_version_status');
        if (!$state) {
            return response()->json([
                'has_update' => false,
            ]);
        }

        $data = $state->value;
        return response()->json([
            ...$data,
            'show_banner' => ($data['has_update'] && !session('version_banner_dismissed', false)),
        ]);
    }
}
