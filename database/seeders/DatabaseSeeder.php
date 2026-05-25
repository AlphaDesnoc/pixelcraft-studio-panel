<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\TaskList;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = $this->createUser('Alphadmin', 'alphadmin', User::ROLE_ADMIN);
        $hunter = $this->createUser('HunterSora', 'huntersora', User::ROLE_ADMIN);
        $narkoo = $this->createUser('Narkoo', 'narkoo', User::ROLE_ADMIN);
        $alphadesnoc = $this->createUser('AlphaDesnoc', 'alphadesnoc', User::ROLE_MEMBER);

        $cobblemon = Project::updateOrCreate(
            ['slug' => 'cobblemon-academy'],
            [
                'name' => 'Cobblemon Academy',
                'description' => 'Pas de description',
                'image' => null,
                'color' => '#7c5cff',
                'status' => Project::STATUS_ACTIVE,
                'owner_id' => $admin->id,
            ]
        );

        $cobblemon->members()->syncWithoutDetaching([
            $admin->id => ['role' => 'owner', 'joined_at' => now()],
            $hunter->id => ['role' => 'member', 'joined_at' => now()],
            $narkoo->id => ['role' => 'member', 'joined_at' => now()],
        ]);

        if ($cobblemon->lists()->count() === 0) {
            $cobblemon->lists()->createMany(
                collect(TaskList::defaultsFor($cobblemon->id))
                    ->map(fn ($l) => collect($l)->except('project_id')->all())
                    ->all()
            );
        }
    }

    private function createUser(string $name, string $pseudo, string $role): User
    {
        return User::updateOrCreate(
            ['email' => $pseudo.'@'.config('pixelcraft.email_domain')],
            [
                'name' => $name,
                'password' => Hash::make('password'),
                'role' => $role,
                'email_verified_at' => now(),
            ]
        );
    }
}
