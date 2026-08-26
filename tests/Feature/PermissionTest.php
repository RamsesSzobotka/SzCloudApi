<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\FilePermission;
use App\Models\Folder;
use App\Models\FolderPermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class PermissionTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected User $viewer;
    protected User $editor;
    protected Folder $folder;
    protected File $file;

    protected function setUp(): void
    {
        // Override phpunit.xml env vars at superglobal level before app boots
        $_ENV['DB_CONNECTION'] = 'pgsql';
        $_ENV['DB_DATABASE'] = 'szcloud';
        $_SERVER['DB_CONNECTION'] = 'pgsql';
        $_SERVER['DB_DATABASE'] = 'szcloud';

        parent::setUp();

        $this->owner = User::create([
            'name' => 'Owner',
            'email' => 'owner@test.com',
            'password' => bcrypt('password'),
        ]);

        $this->viewer = User::create([
            'name' => 'Viewer',
            'email' => 'viewer@test.com',
            'password' => bcrypt('password'),
        ]);

        $this->editor = User::create([
            'name' => 'Editor',
            'email' => 'editor@test.com',
            'password' => bcrypt('password'),
        ]);

        $this->folder = Folder::create([
            'user_id' => $this->owner->id,
            'name' => 'Test Folder',
        ]);

        $this->file = File::create([
            'user_id' => $this->owner->id,
            'folder_id' => $this->folder->id,
            'original_name' => 'test.txt',
            'storage_name' => 'test-uuid.txt',
            'storage_path' => 'test-uuid.txt',
            'mime_type' => 'text/plain',
            'extension' => 'txt',
            'size' => 100,
            'hash' => 'abc123',
        ]);
    }

    protected function actingAsJwt(User $user): static
    {
        $token = JWTAuth::fromUser($user);
        $this->withHeader('Authorization', "Bearer $token");
        return $this;
    }

    // ─── File Permission Tests ─────────────────────────────

    public function test_owner_can_share_file(): void
    {
        $response = $this->actingAsJwt($this->owner)
            ->postJson("/api/storage/file/{$this->file->id}/permissions", [
                'user_id' => $this->viewer->id,
                'permission' => 'viewer',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('file_permissions', [
            'file_id' => $this->file->id,
            'user_id' => $this->viewer->id,
            'permission' => 'viewer',
        ]);
    }

    public function test_non_owner_cannot_share_file(): void
    {
        $response = $this->actingAsJwt($this->editor)
            ->postJson("/api/storage/file/{$this->file->id}/permissions", [
                'user_id' => $this->viewer->id,
                'permission' => 'viewer',
            ]);

        $response->assertStatus(403);
    }

    public function test_viewer_can_access_file(): void
    {
        FilePermission::create([
            'file_id' => $this->file->id,
            'user_id' => $this->viewer->id,
            'permission' => 'viewer',
        ]);

        $response = $this->actingAsJwt($this->viewer)
            ->getJson("/api/storage/file/{$this->file->id}/permissions");

        $response->assertOk();
    }

    public function test_list_file_permissions(): void
    {
        FilePermission::create([
            'file_id' => $this->file->id,
            'user_id' => $this->viewer->id,
            'permission' => 'viewer',
        ]);

        $response = $this->actingAsJwt($this->owner)
            ->getJson("/api/storage/file/{$this->file->id}/permissions");

        $response->assertOk();
        $response->assertJsonCount(1, 'permissions');
    }

    public function test_revoke_file_permission(): void
    {
        FilePermission::create([
            'file_id' => $this->file->id,
            'user_id' => $this->viewer->id,
            'permission' => 'viewer',
        ]);

        $response = $this->actingAsJwt($this->owner)
            ->deleteJson("/api/storage/file/{$this->file->id}/permissions/{$this->viewer->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('file_permissions', [
            'file_id' => $this->file->id,
            'user_id' => $this->viewer->id,
        ]);
    }

    public function test_update_file_permission(): void
    {
        FilePermission::create([
            'file_id' => $this->file->id,
            'user_id' => $this->viewer->id,
            'permission' => 'viewer',
        ]);

        $response = $this->actingAsJwt($this->owner)
            ->patchJson("/api/storage/file/{$this->file->id}/permissions/{$this->viewer->id}", [
                'permission' => 'editor',
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('file_permissions', [
            'file_id' => $this->file->id,
            'user_id' => $this->viewer->id,
            'permission' => 'editor',
        ]);
    }

    // ─── Folder Permission Tests ───────────────────────────

    public function test_owner_can_share_folder(): void
    {
        $response = $this->actingAsJwt($this->owner)
            ->postJson("/api/storage/folder/{$this->folder->id}/permissions", [
                'user_id' => $this->viewer->id,
                'permission' => 'viewer',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('folder_permissions', [
            'folder_id' => $this->folder->id,
            'user_id' => $this->viewer->id,
            'permission' => 'viewer',
        ]);
    }

    public function test_share_folder_creates_recursive_file_permissions(): void
    {
        $response = $this->actingAsJwt($this->owner)
            ->postJson("/api/storage/folder/{$this->folder->id}/permissions", [
                'user_id' => $this->viewer->id,
                'permission' => 'viewer',
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('file_permissions', [
            'file_id' => $this->file->id,
            'user_id' => $this->viewer->id,
            'permission' => 'viewer',
        ]);
    }

    public function test_list_folder_permissions(): void
    {
        FolderPermission::create([
            'folder_id' => $this->folder->id,
            'user_id' => $this->viewer->id,
            'permission' => 'viewer',
        ]);

        $response = $this->actingAsJwt($this->owner)
            ->getJson("/api/storage/folder/{$this->folder->id}/permissions");

        $response->assertOk();
        $response->assertJsonCount(1, 'permissions');
    }

    public function test_revoke_folder_permission_removes_file_permissions(): void
    {
        $this->actingAsJwt($this->owner)
            ->postJson("/api/storage/folder/{$this->folder->id}/permissions", [
                'user_id' => $this->viewer->id,
                'permission' => 'viewer',
            ]);

        $response = $this->actingAsJwt($this->owner)
            ->deleteJson("/api/storage/folder/{$this->folder->id}/permissions/{$this->viewer->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('folder_permissions', [
            'folder_id' => $this->folder->id,
            'user_id' => $this->viewer->id,
        ]);
        $this->assertDatabaseMissing('file_permissions', [
            'file_id' => $this->file->id,
            'user_id' => $this->viewer->id,
        ]);
    }

    public function test_non_owner_cannot_share_folder(): void
    {
        $response = $this->actingAsJwt($this->editor)
            ->postJson("/api/storage/folder/{$this->folder->id}/permissions", [
                'user_id' => $this->viewer->id,
                'permission' => 'viewer',
            ]);

        $response->assertStatus(403);
    }

    public function test_cannot_share_file_with_owner(): void
    {
        $response = $this->actingAsJwt($this->owner)
            ->postJson("/api/storage/file/{$this->file->id}/permissions", [
                'user_id' => $this->owner->id,
                'permission' => 'viewer',
            ]);

        $response->assertStatus(422);
    }

    public function test_invalid_permission_value(): void
    {
        $response = $this->actingAsJwt($this->owner)
            ->postJson("/api/storage/file/{$this->file->id}/permissions", [
                'user_id' => $this->viewer->id,
                'permission' => 'admin',
            ]);

        $response->assertStatus(422);
    }

    public function test_unauthenticated_cannot_access_permissions(): void
    {
        $response = $this->getJson("/api/storage/file/{$this->file->id}/permissions");

        $response->assertStatus(401);
    }

    public function test_update_permission_requires_owner(): void
    {
        FilePermission::create([
            'file_id' => $this->file->id,
            'user_id' => $this->viewer->id,
            'permission' => 'viewer',
        ]);

        $response = $this->actingAsJwt($this->editor)
            ->patchJson("/api/storage/file/{$this->file->id}/permissions/{$this->viewer->id}", [
                'permission' => 'editor',
            ]);

        $response->assertStatus(403);
    }
}
