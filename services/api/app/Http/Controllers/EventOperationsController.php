<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EventOperationsController extends Controller
{
    public function webRegister(Request $r, string $event): RedirectResponse
    {
        $record = DB::table('events')->where('id', $event)->where('status', 'published')->first();
        abort_unless($record && ! $record->cancelled_at && $record->registration_open, 404);
        if ($record->registration_deadline && now()->greaterThan($record->registration_deadline)) return back()->withErrors(['registration'=>'Registration for this event has closed.']);
        $person = $r->user() ? DB::table('people')->where('user_id', $r->user()->id)->first() : null;
        $data = $r->validate(['name'=>['required','string','max:160'],'email'=>['nullable','email','max:254']]);
        if ($person && DB::table('event_registrations')->where(['event_id'=>$event,'person_id'=>$person->id])->whereIn('status',['registered','waitlisted','checked_in'])->exists()) return back()->with('status','You are already registered for this event.');
        if ($data['email'] && DB::table('event_registrations')->where(['event_id'=>$event,'email'=>$data['email']])->whereIn('status',['registered','waitlisted','checked_in'])->exists()) return back()->withInput()->withErrors(['email'=>'This email is already registered for the event.']);
        $registered = DB::table('event_registrations')->where('event_id',$event)->whereIn('status',['registered','checked_in'])->count();
        $status = $record->capacity !== null && $registered >= $record->capacity ? 'waitlisted' : 'registered';
        if ($status === 'waitlisted' && ! $record->waitlist_enabled) return back()->withErrors(['registration'=>'This event is full.']);
        DB::table('event_registrations')->insert(['id'=>(string)Str::uuid(),'event_id'=>$event,'person_id'=>$person?->id,'name'=>$data['name'],'email'=>$data['email'],'status'=>$status,'created_at'=>now(),'updated_at'=>now()]);
        return back()->with('status', $status === 'waitlisted' ? 'You joined the waitlist.' : 'Your registration is confirmed.');
    }

    public function memberCancel(Request $r, string $event): RedirectResponse
    {
        $person = DB::table('people')->where('user_id',$r->user()->id)->first();
        abort_unless($person, 404);
        $registration = DB::table('event_registrations')->where(['event_id'=>$event,'person_id'=>$person->id])->whereIn('status',['registered','waitlisted'])->first();
        abort_unless($registration, 404);
        $this->cancelRegistration($event, $registration->id);
        return back()->with('status','Event registration cancelled.');
    }
    public function show(string $event): JsonResponse
    {
        $record = DB::table('events')->where('id', $event)->first();
        abort_unless($record, 404);

        return response()->json(['data' => ['event' => $record, 'registrations' => DB::table('event_registrations')->where('event_id', $event)->orderByRaw("case when status='registered' then 0 when status='waitlisted' then 1 else 2 end")->oldest()->get()]]);
    }

    public function register(Request $r, string $event): JsonResponse
    {
        $d = $r->validate(['name' => ['required', 'string', 'max:160'], 'email' => ['nullable', 'email', 'max:254']]);

        return DB::transaction(function () use ($r, $event, $d) {
            $record = DB::table('events')->where('id', $event)->lockForUpdate()->first();
            abort_unless($record && $record->registration_open && ! $record->cancelled_at, 404);
            $registered = DB::table('event_registrations')->where('event_id', $event)->where('status', 'registered')->count();
            $status = $record->capacity !== null && $registered >= $record->capacity ? 'waitlisted' : 'registered';
            abort_if($status === 'waitlisted' && ! $record->waitlist_enabled, 409, 'This event is full.');
            $id = (string) Str::uuid();
            $person = $r->user()?->id ? DB::table('people')->where('user_id', $r->user()->id)->value('id') : null;
            DB::table('event_registrations')->insert([...$d, 'id' => $id, 'event_id' => $event, 'person_id' => $person, 'status' => $status, 'created_at' => now(), 'updated_at' => now()]);

            return response()->json(['data' => ['id' => $id, 'status' => $status]], 201);
        });
    }

    public function registrations(string $event): JsonResponse
    {
        return response()->json(['data' => DB::table('event_registrations')->where('event_id', $event)->orderByRaw("case when status='registered' then 0 else 1 end")->oldest()->get()]);
    }

    public function checkIn(Request $r, string $event, string $registration): JsonResponse
    {
        $q = DB::table('event_registrations')->where(['id' => $registration, 'event_id' => $event, 'status' => 'registered']);
        abort_unless($q->exists(), 404);
        $q->update(['status' => 'checked_in', 'checked_in_at' => now(), 'checked_in_by' => $r->user()->id, 'updated_at' => now()]);

        return response()->json(['data' => $q->first()]);
    }

    public function cancel(Request $r, string $event): JsonResponse
    {
        $d = $r->validate(['reason' => ['required', 'string', 'max:2000']]);
        $q = DB::table('events')->where('id', $event);
        abort_unless($q->exists(), 404);
        $q->update(['registration_open' => false, 'cancelled_at' => now(), 'cancellation_reason' => $d['reason'], 'updated_at' => now()]);

        return response()->json(['data' => $q->first()]);
    }

    public function cancelRegistration(string $event, string $registration): JsonResponse
    {
        return DB::transaction(function () use ($event, $registration) {
            $query = DB::table('event_registrations')->where(['id' => $registration, 'event_id' => $event])->whereIn('status', ['registered', 'waitlisted']);
            $record = $query->lockForUpdate()->first();
            abort_unless($record, 404);
            $wasRegistered = $record->status === 'registered';
            $query->update(['status' => 'cancelled', 'cancelled_at' => now(), 'updated_at' => now()]);
            $promoted = null;
            if ($wasRegistered) {
                $next = DB::table('event_registrations')->where(['event_id' => $event, 'status' => 'waitlisted'])->oldest()->lockForUpdate()->first();
                if ($next) {
                    DB::table('event_registrations')->where('id', $next->id)->update(['status' => 'registered', 'updated_at' => now()]);
                    $promoted = $next->id;
                }
            }

            return response()->json(['data' => ['cancelled_registration_id' => $registration, 'promoted_registration_id' => $promoted]]);
        });
    }
}
