<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * D4a — High Official detection + self-service position guardrail.
 *
 * Official NCMB list (Sept 2026) — keywords must match EVERY variant below:
 *   Technical Services: OIC-Executive Director IV, OIC Deputy Executive Director IV,
 *   Deputy Executive Director IV, Director II (Technical Services), Chief/OIC Chief of
 *   CMD · WRED · VAD · OED
 *   Internal Services: Director II (Internal Services), Chief/OIC Chief of AD · FMD · RID,
 *   State Auditor III (COA — locked decision: INCLUDED in queue-jump)
 */
class HighOfficialTest extends TestCase
{
    use RefreshDatabase;

    private function userWithPosition(string $position): User
    {
        static $n = 0;
        $n++;

        return User::create([
            'full_name' => 'Official Test ' . $n,
            'email' => 'official-' . $n . '@test.com',
            'password' => bcrypt('password'),
            'role' => 'user',
            'is_active' => true,
            'position' => $position,
        ]);
    }

    public function test_accessor_matches_every_title_in_the_official_list(): void
    {
        $titles = [
            'OIC-Executive Director IV',
            'OIC Deputy Executive Director IV',
            'Deputy Executive Director IV',
            'Director II, Technical Services',
            'Director II, Internal Services',
            'Chief, CMD',
            'OIC Chief, WRED',
            'OIC-Chief, VAD',
            'Chief, AD',
            'Chief, FMD',
            'OIC, Chief, RID',
            'State Auditor III',
        ];

        foreach ($titles as $title) {
            $user = $this->userWithPosition($title);
            $this->assertTrue(
                $user->is_high_official,
                "Expected HIGH OFFICIAL: [{$title}]"
            );
        }
    }

    public function test_accessor_rejects_regular_positions_and_empty_values(): void
    {
        $notOfficials = [
            'Programmer I',
            "Director's Secretary",      // docs guardrail: must NOT match bare 'Director'
            'IT Support Staff',
            'Administrative Aide VI',
            'Records Officer',
            '',
        ];

        foreach ($notOfficials as $title) {
            $user = $this->userWithPosition($title);
            $this->assertFalse(
                $user->is_high_official,
                "Expected NOT high official: [{$title}]"
            );
        }

        // Null position (54 of 58 live users are empty/null today).
        $user = $this->userWithPosition('placeholder');
        $user->forceFill(['position' => null])->save();
        $this->assertFalse($user->is_high_official);
    }

    public function test_profile_update_cannot_change_position_self_inflation_blocked(): void
    {
        // D4.6 guardrail: position is set by Super Admin / Department Admin ONLY.
        // A regular user must NOT be able to type "Director" into their own
        // profile to jump the IT queue — the field is ignored by the controller.
        $user = $this->userWithPosition('Programmer I');

        $this->actingAs($user)
            ->put(route('profile.update'), [
                'full_name' => $user->full_name,
                'email' => $user->email,
                'position' => 'OIC-Executive Director IV', // attempted self-inflation
            ])
            ->assertRedirect();

        $this->assertSame('Programmer I', $user->fresh()->position);
    }

    public function test_profile_update_works_without_position_field(): void
    {
        // The self-service form no longer posts 'position' at all (read-only UI),
        // and validation declares it nullable — so the update must still succeed
        // for name/email/password changes.
        $user = $this->userWithPosition('Chief, FMD');

        $this->actingAs($user)
            ->put(route('profile.update'), [
                'full_name' => 'Renamed Without Position',
                'email' => $user->email,
            ])
            ->assertRedirect();

        $fresh = $user->fresh();
        $this->assertSame('Renamed Without Position', $fresh->full_name);
        $this->assertSame('Chief, FMD', $fresh->position, 'Position must survive profile updates untouched');
    }
}
