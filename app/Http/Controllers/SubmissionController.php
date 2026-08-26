<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SubmissionController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'working_title' => ['required', 'string', 'max:255'],
            'genre' => ['nullable', 'string', 'max:120'],
            'abstract' => ['required', 'string', 'max:7500'],
            'manuscript' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:20480'],
        ]);

        $file = $request->file('manuscript');
        $path = $file?->store('submissions', 'local');
        unset($data['manuscript']);

        DB::table('manuscript_submissions')->insert($data + [
            'user_id' => $request->user()?->id,
            'attachment_path' => $path,
            'attachment_mime' => $file?->getMimeType(),
            'status' => 'new',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Your proposal has been received. We will contact you after an initial editorial review.');
    }
}
