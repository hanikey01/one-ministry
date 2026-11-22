<?php

namespace Tests\Feature;

use App\Actions\InstallMinistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GivingManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_create_edit_and_archive_a_purpose_from_the_staff_page(): void
    {
        $admin=$this->administrator();

        $this->actingAs($admin)->post('/api/v1/staff/giving/funds',[
            'name'=>'Missions','currency'=>'USD','description'=>'Global mission work','suggested_amounts_text'=>'2500, 5000','active'=>'1','public_visible'=>'1','display_order'=>2,
        ])->assertRedirect(route('staff.giving'));

        $fund=DB::table('giving_funds')->where('name','Missions')->first();
        $this->assertNotNull($fund);
        $this->assertSame([2500,5000],json_decode($fund->suggested_amounts,true));

        $this->actingAs($admin)->patch('/api/v1/staff/giving/funds/'.$fund->id,[
            'name'=>'Global missions','currency'=>'EUR','description'=>'Updated','suggested_amounts_text'=>'3000','active'=>'1','display_order'=>1,
        ])->assertRedirect(route('staff.giving'));
        $this->assertDatabaseHas('giving_funds',['id'=>$fund->id,'name'=>'Global missions','currency'=>'EUR','public_visible'=>false]);

        $this->actingAs($admin)->delete('/api/v1/staff/giving/funds/'.$fund->id)->assertRedirect(route('staff.giving'));
        $this->assertDatabaseHas('giving_funds',['id'=>$fund->id,'active'=>false,'public_visible'=>false]);
        $this->assertDatabaseHas('audit_events',['action'=>'giving.fund_archived','subject_id'=>$fund->id]);
    }

    public function test_bank_details_can_be_created_edited_and_archived(): void
    {
        $admin=$this->administrator();
        $this->actingAs($admin)->post('/api/v1/staff/giving/offline-methods',[
            'label'=>'International transfer','country'=>'GB','currency'=>'GBP','bank_name'=>'Ministry Bank','account_name'=>'Hope Community','iban'=>'GB00TEST','active'=>'1',
        ])->assertRedirect(route('staff.giving'));
        $method=DB::table('offline_giving_methods')->first();
        $this->actingAs($admin)->put('/api/v1/staff/giving/offline-methods/'.$method->id,[
            'label'=>'UK transfer','country'=>'GB','currency'=>'GBP','bank_name'=>'Ministry Bank','account_name'=>'Hope Community','iban'=>'GB00TEST',
        ])->assertRedirect(route('staff.giving'));
        $this->assertDatabaseHas('offline_giving_methods',['id'=>$method->id,'label'=>'UK transfer','active'=>false]);
        $this->actingAs($admin)->delete('/api/v1/staff/giving/offline-methods/'.$method->id)->assertRedirect(route('staff.giving'));
        $this->assertDatabaseHas('audit_events',['action'=>'giving.offline_method_archived','subject_id'=>$method->id]);
    }

    public function test_public_giving_uses_the_guided_layout_without_a_service_time_header_action(): void
    {
        $this->administrator();

        $this->get(route('public.giving'))
            ->assertOk()
            ->assertSee('giving-page-layout', false)
            ->assertSee('Give with purpose')
            ->assertSee('Your payment stays with the provider')
            ->assertDontSee('public-worship-action', false);
    }

    private function administrator()
    {
        $admin=app(InstallMinistry::class)->execute(['name'=>'Hope Community','domain'=>'hope.example.org','timezone'=>'UTC','location'=>'Main Campus','admin_name'=>'Ada Admin','admin_email'=>'ada@example.org','password'=>'long-private-password']);
        $admin->forceFill(['email_verified_at'=>now()])->save();
        return $admin;
    }
}
