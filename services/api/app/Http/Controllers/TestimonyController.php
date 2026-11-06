<?php

namespace App\Http\Controllers;

use App\Models\FeatureSetting;
use App\Models\MinistrySetting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TestimonyController extends Controller
{
    private const CATEGORIES=['general','healing','provision','family','salvation','restoration','guidance','community'];

    public function publicIndex(Request $request): View
    {
        $this->enabled(); $category=(string)$request->query('category');
        $query=DB::table('testimonies')->leftJoin('people','people.id','=','testimonies.person_id')->where('testimonies.status','published')->where('testimonies.consent_public',true)->whereNotNull('testimonies.published_at')->where('testimonies.published_at','<=',now())->select('testimonies.*','people.profile_image_path');
        if(in_array($category,self::CATEGORIES,true)) $query->where('category',$category);
        $testimonies=$query->orderByDesc('testimonies.featured')->orderByDesc('testimonies.published_at')->paginate(12)->withQueryString();
        $testimonies->setCollection($this->withProfileImages($testimonies->getCollection()));
        return $this->publicView('public.testimonies',['testimonies'=>$testimonies,'categories'=>self::CATEGORIES,'activeCategory'=>$category]);
    }

    public function publicShow(string $slug): View
    {
        $this->enabled(); $testimony=DB::table('testimonies')->leftJoin('people','people.id','=','testimonies.person_id')->where('testimonies.slug',$slug)->where('testimonies.status','published')->where('testimonies.consent_public',true)->where('testimonies.published_at','<=',now())->select('testimonies.*','people.profile_image_path')->first(); abort_if(!$testimony,404);
        $testimony=$this->withProfileImages(collect([$testimony]))->first();
        return $this->publicView('public.testimony',['testimony'=>$testimony]);
    }

    public function memberIndex(Request $request): View
    {
        $this->enabled(); $person=$this->person($request);
        return view('member.testimonies',['settings'=>MinistrySetting::findOrFail(MinistrySetting::SINGLETON_ID),'features'=>FeatureSetting::query()->where('state','enabled')->pluck('key')->all(),'title'=>'My testimonies','testimonies'=>DB::table('testimonies')->where('person_id',$person->id)->latest()->get(),'categories'=>self::CATEGORIES,'profileImageUrl'=>$this->profileImageUrl($person->profile_image_path)]);
    }

    public function memberStore(Request $request): RedirectResponse
    {
        $this->enabled(); $person=$this->person($request); $request->validate(['consent_public'=>['accepted']]); $data=$this->validated($request,false);
        $id=(string)Str::uuid(); DB::table('testimonies')->insert([...$data,'id'=>$id,'person_id'=>$person->id,'submitted_by_user_id'=>$request->user()->id,'author_name'=>trim($person->first_name.' '.($person->last_name??'')),'slug'=>$this->slug($data['title']),'status'=>'submitted','consent_public'=>true,'submitted_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
        $this->audit($request,'testimony.member_submitted','testimonies',$id,['title'=>$data['title']]);
        return to_route('member.testimonies')->with('status','Your testimony was submitted for staff review. It is not public yet.');
    }

    public function memberWithdraw(Request $request,string $testimony): RedirectResponse
    {
        $person=$this->person($request); $row=DB::table('testimonies')->where('id',$testimony)->where('person_id',$person->id)->first(); abort_if(!$row,404); abort_unless(in_array($row->status,['submitted','rejected'],true),409);
        DB::table('testimonies')->where('id',$testimony)->update(['status'=>'withdrawn','updated_at'=>now()]); $this->audit($request,'testimony.member_withdrawn','testimonies',$testimony,[]);
        return to_route('member.testimonies')->with('status','Your testimony was withdrawn.');
    }

    public function staffIndex(Request $request): View
    {
        $this->enabled(); $status=(string)$request->query('status'); $query=DB::table('testimonies')->leftJoin('people','people.id','=','testimonies.person_id')->select('testimonies.*','people.profile_image_path')->latest('testimonies.created_at'); if(in_array($status,['submitted','draft','published','rejected','archived','withdrawn'],true))$query->where('testimonies.status',$status);
        $testimonies=$query->paginate(20)->withQueryString();
        $testimonies->setCollection($this->withProfileImages($testimonies->getCollection()));
        return view('staff.testimonies',['settings'=>MinistrySetting::findOrFail(MinistrySetting::SINGLETON_ID),'user'=>$request->user(),'testimonies'=>$testimonies,'categories'=>self::CATEGORIES,'activeStatus'=>$status]);
    }

    public function staffCreate(Request $request): RedirectResponse
    {
        $data=$this->validated($request,true); $id=(string)Str::uuid();
        DB::table('testimonies')->insert([...$data,'id'=>$id,'created_by_user_id'=>$request->user()->id,'slug'=>$this->slug($data['title']),'status'=>'draft','consent_public'=>$request->boolean('consent_public'),'show_author_name'=>$request->boolean('show_author_name'),'featured'=>$request->boolean('featured'),'created_at'=>now(),'updated_at'=>now()]);
        $this->audit($request,'testimony.staff_created','testimonies',$id,['title'=>$data['title']]); return to_route('staff.testimonies')->with('status','Testimony saved as a draft.');
    }

    public function staffUpdate(Request $request,string $testimony): RedirectResponse
    {
        $row=DB::table('testimonies')->where('id',$testimony)->first(); abort_if(!$row,404); $data=$this->validated($request,true);
        DB::table('testimonies')->where('id',$testimony)->update([...$data,'slug'=>$this->slug($data['title'],$testimony),'status'=>$row->status==='published'?'draft':$row->status,'consent_public'=>$request->boolean('consent_public'),'show_author_name'=>$request->boolean('show_author_name'),'featured'=>$request->boolean('featured'),'published_at'=>$row->status==='published'?null:$row->published_at,'updated_at'=>now()]);
        $this->audit($request,'testimony.updated','testimonies',$testimony,['previous_status'=>$row->status]); return to_route('staff.testimonies')->with('status','Testimony saved for review.');
    }

    public function staffReview(Request $request,string $testimony): RedirectResponse
    {
        $data=$request->validate(['action'=>['required',Rule::in(['publish','reject','archive'])],'review_notes'=>['nullable','string','max:2000'],'featured'=>['sometimes','boolean']]); $row=DB::table('testimonies')->where('id',$testimony)->first(); abort_if(!$row,404);
        if($data['action']==='publish'&&!$row->consent_public) return back()->withErrors(['consent_public'=>'Public consent is required before publishing.']);
        $status=match($data['action']){'publish'=>'published','reject'=>'rejected',default=>'archived'};
        DB::table('testimonies')->where('id',$testimony)->update(['status'=>$status,'review_notes'=>$data['review_notes']??null,'featured'=>$request->boolean('featured'),'reviewed_by_user_id'=>$request->user()->id,'reviewed_at'=>now(),'published_at'=>$status==='published'?now():null,'updated_at'=>now()]);
        $this->audit($request,'testimony.'.$status,'testimonies',$testimony,['previous_status'=>$row->status]); return to_route('staff.testimonies')->with('status','Testimony '.str($status)->headline().'.');
    }

    private function validated(Request $request,bool $staff): array { return $request->validate(['title'=>['required','string','max:180'],'author_name'=>[$staff?'required':'nullable','string','max:160'],'category'=>['required',Rule::in(self::CATEGORIES)],'body'=>['required','string','min:20','max:12000']]); }
    private function slug(string $title,?string $except=null): string { $base=Str::slug($title)?:'testimony';$slug=$base;$i=2;while(DB::table('testimonies')->where('slug',$slug)->when($except,fn($q)=>$q->where('id','!=',$except))->exists())$slug=$base.'-'.$i++;return $slug; }
    private function enabled(): void { abort_unless(FeatureSetting::query()->where(['key'=>'testimonies','state'=>'enabled'])->exists(),404); }
    private function person(Request $request): object { $person=DB::table('people')->where('user_id',$request->user()->id)->first(); if($person)return $person;$id=(string)Str::uuid();$names=preg_split('/\s+/',trim($request->user()->name),2);DB::table('people')->insert(['id'=>$id,'user_id'=>$request->user()->id,'first_name'=>$names[0]?:'Member','last_name'=>$names[1]??null,'email'=>$request->user()->email,'status'=>'member','consent_to_contact'=>false,'created_at'=>now(),'updated_at'=>now()]);return DB::table('people')->where('id',$id)->first(); }
    private function publicView(string $view,array $data): View { return view($view,[...$data,'settings'=>MinistrySetting::findOrFail(MinistrySetting::SINGLETON_ID),'features'=>FeatureSetting::query()->where('state','enabled')->pluck('key')->all()]); }
    private function withProfileImages($rows): object { return $rows->map(function ($row) { $row->profile_image_url=$this->profileImageUrl($row->profile_image_path); return $row; }); }
    private function profileImageUrl(?string $path): ?string { return $path && Storage::disk('public')->exists($path) ? route('public.storage',['path'=>$path]) : null; }
    private function audit(Request $r,string $action,string $type,string $id,array $changes): void { DB::table('audit_events')->insert(['id'=>(string)Str::uuid(),'actor_user_id'=>$r->user()?->id,'action'=>$action,'subject_type'=>$type,'subject_id'=>$id,'changes_json'=>json_encode($changes,JSON_THROW_ON_ERROR),'ip_address'=>$r->ip(),'created_at'=>now()]); }
}
