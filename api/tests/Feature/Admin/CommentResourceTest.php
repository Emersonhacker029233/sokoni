<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Comments\Pages\ListComments;
use App\Models\Comment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CommentResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_load_comments(): void
    {
        $admin = User::factory()->admin()->create();
        Comment::factory()->count(3)->create();

        $this->actingAsAdmin($admin)->get('/admin/comments')->assertOk();
    }

    public function test_reply_threading_is_visible_via_the_parent_relation(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create();
        $root = Comment::factory()->create(['product_id' => $product->id, 'body' => 'Is this still available?']);
        $reply = Comment::factory()->create(['product_id' => $product->id, 'parent_id' => $root->id]);

        $this->actingAsAdmin($admin);

        $component = Livewire::test(ListComments::class);
        $component->assertCanSeeTableRecords([$root, $reply]);
        $this->assertSame($root->id, $reply->fresh()->parent->id);
    }

    public function test_an_admin_can_hide_and_unhide_a_comment(): void
    {
        $admin = User::factory()->admin()->create();
        $comment = Comment::factory()->create(['is_hidden' => false]);

        $this->actingAsAdmin($admin);

        Livewire::test(ListComments::class)
            ->callTableAction('hide', $comment)
            ->assertHasNoTableActionErrors();

        $this->assertTrue($comment->fresh()->is_hidden);
    }

    public function test_a_hidden_comment_does_not_appear_in_the_public_product_comment_list(): void
    {
        $product = Product::factory()->create();
        $visible = Comment::factory()->create(['product_id' => $product->id, 'is_hidden' => false]);
        $hidden = Comment::factory()->create(['product_id' => $product->id, 'is_hidden' => true]);

        $response = $this->getJson("/api/products/{$product->id}/comments")->assertOk();
        $ids = collect($response->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($visible->id));
        $this->assertFalse($ids->contains($hidden->id));
    }
}
