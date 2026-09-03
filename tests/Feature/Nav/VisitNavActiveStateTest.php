<?php

namespace Tests\Feature\Nav;

use App\Http\Middleware\ValidateSession;
use App\Models\Host;
use App\Models\User;
use App\Models\Visit;
use App\Models\Visitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class VisitNavActiveStateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // ValidateSession queries the sessions DB table which is not populated
        // when using actingAs() directly. Disable it for these nav tests so
        // we can focus on the active-link rendering logic.
        $this->withoutMiddleware(ValidateSession::class);
    }

    #[Test]
    public function only_new_visit_is_active_on_the_create_page(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $response = $this->actingAs($user)->get(route('visits.create'));

        $response->assertOk();

        // "New Visit" active, "Visit History" not active.
        $this->assertNavItemActive($response->getContent(), 'New Visit', true);
        $this->assertNavItemActive($response->getContent(), 'Visit History', false);
    }

    #[Test]
    public function only_visit_history_is_active_on_the_index_page(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $response = $this->actingAs($user)->get(route('visits.index'));

        $response->assertOk();

        $this->assertNavItemActive($response->getContent(), 'New Visit', false);
        $this->assertNavItemActive($response->getContent(), 'Visit History', true);
    }

    #[Test]
    public function only_visit_history_is_active_on_a_visit_show_page(): void
    {
        $user    = User::factory()->create(['email_verified_at' => now()]);
        $visitor = Visitor::factory()->create();
        $host    = Host::factory()->create();
        $visit   = Visit::factory()->create([
            'visitor_id' => $visitor->id,
            'host_id'    => $host->id,
        ]);

        $response = $this->actingAs($user)->get(route('visits.show', $visit));

        $response->assertOk();

        $this->assertNavItemActive($response->getContent(), 'New Visit', false);
        $this->assertNavItemActive($response->getContent(), 'Visit History', true);
    }

    /**
     * Assert whether the sidebar nav item with the given label carries the
     * "active" CSS class (bg-indigo-600) in the rendered HTML.
     */
    private function assertNavItemActive(string $html, string $label, bool $expectedActive): void
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);

        $anchor = null;
        foreach ($xpath->query('//nav//a') as $a) {
            if (str_contains(trim($a->textContent), $label)) {
                $anchor = $a;
                break;
            }
        }

        $this->assertNotNull($anchor, "Could not find nav item for label: {$label}");

        $classAttr = $anchor->getAttribute('class');
        $isActive = str_contains($classAttr, 'bg-indigo-600') && str_contains($classAttr, 'text-white');

        $this->assertSame(
            $expectedActive,
            $isActive,
            "Expected nav item '{$label}' active state to be " . ($expectedActive ? 'true' : 'false') . " but class was: {$classAttr}"
        );
    }
}
