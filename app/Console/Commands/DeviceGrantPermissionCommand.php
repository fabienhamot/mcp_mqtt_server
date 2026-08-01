<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Models\User;
use App\Services\DevicePermissionService;
use Illuminate\Console\Command;

class DeviceGrantPermissionCommand extends Command
{
    protected $signature = 'device:grant
        {user : ID ou email de l\'utilisateur}
        {device : ID du device}
        {--actions=text,image,color,clear : Actions autorisées (CSV)}';

    protected $description = 'Attribue des permissions device à un utilisateur';

    public function handle(DevicePermissionService $permissions): int
    {
        $userArg = $this->argument('user');
        $user = is_numeric($userArg)
            ? User::query()->find($userArg)
            : User::query()->where('email', $userArg)->first();

        if ($user === null) {
            $this->error('Utilisateur introuvable.');

            return self::FAILURE;
        }

        $device = Device::query()->find($this->argument('device'));

        if ($device === null) {
            $this->error('Device introuvable.');

            return self::FAILURE;
        }

        $actions = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) $this->option('actions'))
        )));

        $permission = $permissions->grant($user, $device, $actions);

        $this->info(sprintf(
            'Permissions accordées user=#%d device=#%d actions=%s',
            $user->id,
            $device->id,
            implode(',', $permission->allowed_actions ?? [])
        ));

        return self::SUCCESS;
    }
}
