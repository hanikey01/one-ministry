<?php

namespace Tests\Feature;

use App\Actions\InstallMinistry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TestimonyWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_submission_is_private_until_staff_publishes_without_mfa(): void
    {
        $admin=app(InstallMinistry::class)->execute(['name'=>'Hope Community','domain'=>'hope.example.org','timezone'=>'UTC','location'=>'Main Campus','admin_name'=>'Ada Admin','admin_email'=>'ada@example.org','password'=>'long-private-password']);
        $admin->forceFill(['email_verified_at'=>now()])->save();
        DB::table('feature_settings')->updateOrInsert(['key'=>'testimonies'],['state'=>'enabled','created_at'=>now(),'updated_at'=>now()]);
        $member=User::factory()->create();

        $this->actingAs($member)->post('/member/testimonies',['title'=>'A new beginning','category'=>'restoration','body'=>str_repeat('A meaningful part of the story. ',3),'consent_public'=>'1'])->assertRedirect(route('member.testimonies'));
        $testimony=DB::table('testimonies')->first();
        $this->assertSame('submitted',$testimony->status);
        $this->get('/testimonies/'.$testimony->slug)->assertNotFound();

        $this->actingAs($admin)->patch('/staff/testimonies/'.$testimony->id.'/review',['action'=>'publish'])->assertRedirect(route('staff.testimonies'));
        $this->get('/testimonies/'.$testimony->slug)->assertOk()->assertSee('A new beginning');
        $this->assertDatabaseHas('audit_events',['action'=>'testimony.published','subject_id'=>$testimony->id]);
    }

    public function test_member_cannot_withdraw_another_members_testimony(): void
    {
        DB::table('feature_settings')->updateOrInsert(['key'=>'testimonies'],['state'=>'enabled','created_at'=>now(),'updated_at'=>now()]);
        $first=User::factory()->create(); $second=User::factory()->create();
        $this->actingAs($first)->post('/member/testimonies',['title'=>'My story','category'=>'general','body'=>str_repeat('This is my testimony. ',3),'consent_public'=>'1']);
        $id=DB::table('testimonies')->value('id');
        $this->actingAs($second)->delete('/member/testimonies/'.$id)->assertNotFound();
        $this->assertDatabaseHas('testimonies',['id'=>$id,'status'=>'submitted']);
    }
}
