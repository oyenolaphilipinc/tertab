<?php

namespace App\Http\Controllers;

use App\Models\InstitutionAttended;
use App\Models\PlatformSetting;
use App\Models\State;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class InstitutionAttendedController extends Controller
{
    public function index()
    {
        $institutions = auth()->user()->attended()->with('documents', 'state', 'institution')->get();
        $settings = PlatformSetting::first();
        $states = State::orderBy('name')->get();
        return view('institution_attended.index', compact('institutions', 'settings', 'states'));
    }

    public function create()
    {
        return view('institutions.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'state' => 'required',
            'institution' => 'required',
            'type' => 'required',
            'field_of_study' => 'nullable|string',
            'position' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'documents.*' => 'file|mimes:pdf,jpg,jpeg,png,doc,docx|max:2048',
            'school_email' => [
                'nullable',
                'email',
                function ($attribute, $value, $fail) {
                    if ($value && !preg_match('/\.(edu|edu\.ng)$/', $value)) {
                        $fail('The school email must be a valid .edu or .edu.ng email address.');
                    }
                }
            ]
        ]);

        // For lecturers, require school email
        if ($request->type === 'lecturer') {
            $request->validate([
                'school_email' => [
                    'required',
                    'email',
                    function ($attribute, $value, $fail) {
                        if (!preg_match('/\.(edu|edu\.ng)$/', $value)) {
                            $fail('The school email must be a valid .edu or .edu.ng email address.');
                        }
                    }
                ]
            ], [
                'school_email.required' => 'School email is required for lecturers.'
            ]);
        }

        $institutionAttended = InstitutionAttended::create([
            'user_id' => auth()->id(),
            'state_id' => $request->state,
            'institution_id' => $request->institution,
            'type' => $request->type,
            'field_of_study' => $request->field_of_study,
            'position' => $request->position,
            'start_date' => $request->start_date,
            'end_date' => $request->filled('end_date') ? $request->end_date : null,
            'school_email' => $request->school_email,
            'status' => $request->type === 'lecturer' ? 'pending' : 'verified',
        ]);

        if ($request->hasFile('documents')) {
            \Log::info('Documents found in request', ['count' => count($request->file('documents'))]);
            
            foreach ($request->file('documents') as $file) {
                $path = $file->store('institution_documents', 'public');
                
                \Log::info('Storing document', [
                    'original_name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'institution_id' => $institutionAttended->id
                ]);

                try {
                    $document = $institutionAttended->documents()->create([
                        'user_id' => auth()->id(),
                        'path' => $path,
                        'name' => $file->getClientOriginalName(),
                        'type' => 'institution',
                        'institution_attended_id' => $institutionAttended->id
                    ]);
                    
                    \Log::info('Document created successfully', ['document_id' => $document->id]);
                } catch (\Exception $e) {
                    \Log::error('Failed to create document', [
                        'error' => $e->getMessage(),
                        'file' => $file->getClientOriginalName()
                    ]);
                }
            }
        } else {
            \Log::info('No documents found in request');
        }

        // Send verification email for lecturers
        if ($request->type === 'lecturer' && $request->school_email) {
            try {
                $token = $institutionAttended->generateVerificationToken();
                
                Mail::send('emails.institution-verification', [
                    'institution' => $institutionAttended,
                    'token' => $token,
                    'user' => auth()->user()
                ], function ($message) use ($institutionAttended) {
                    $message->to($institutionAttended->school_email)
                            ->subject('Verify Your Institution Email - Tertab');
                });

                return redirect()->route('institution.attended.show')
                               ->with('success', 'Institution added successfully! Please check your school email to verify your account.');
            } catch (\Exception $e) {
                return redirect()->route('institution.attended.show')
                               ->with('success', 'Institution added successfully! However, we could not send the verification email. Please contact support.');
            }
        }

        return redirect()->route('institution.attended.show')->with('success', 'Institution added successfully!');
    }

    public function show()
    {
        $institutions = auth()->user()->attended()->with('documents', 'state', 'institution')->get();
        
        // Debug: Log the institutions and their documents
        foreach ($institutions as $institution) {
            \Log::info('Institution loaded', [
                'id' => $institution->id,
                'name' => $institution->institution->name ?? 'N/A',
                'documents_count' => $institution->documents->count(),
                'documents' => $institution->documents->map(function($doc) {
                    return ['id' => $doc->id, 'name' => $doc->name, 'path' => $doc->path];
                })->toArray()
            ]);
        }
        
        $settings = PlatformSetting::first();
        $states = State::orderBy('name')->get();
        return view('institution_attended.index', compact('institutions', 'settings', 'states'));
    }

    public function destroy(InstitutionAttended $institutionAttended)
    {
        // Check if user owns this institution
        if ($institutionAttended->user_id !== auth()->id()) {
            abort(403, 'Unauthorized action.');
        }

        try {
            // Delete associated documents from storage
            foreach ($institutionAttended->documents as $document) {
                if (\Storage::disk('public')->exists($document->path)) {
                    \Storage::disk('public')->delete($document->path);
                }
                $document->delete();
            }

            // Delete the institution
            $institutionAttended->delete();

            return redirect()->route('institution.attended.show')
                           ->with('success', 'Institution deleted successfully.');
        } catch (\Exception $e) {
            \Log::error('Failed to delete institution', [
                'institution_id' => $institutionAttended->id,
                'error' => $e->getMessage()
            ]);

            return redirect()->route('institution.attended.show')
                           ->with('error', 'Failed to delete institution. Please try again.');
        }
    }
}
