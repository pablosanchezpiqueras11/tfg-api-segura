<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
    //Inicialmente se planteó la autorización con permisos granulares de Spatie, 
    // pero al tener solo tres roles con límites de acceso bien definidos, 
    // el control basado en roles resulta más simple y suficiente; 
    // por eso la autorización se implementa verificando el rol del usuario
{
    public function run(): void
    {
        // Limpiar caché de permisos
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Crear Permisos
        Permission::create(['name' => 'users.view']);
        Permission::create(['name' => 'users.create']);
        Permission::create(['name' => 'documents.view']);
        Permission::create(['name' => 'documents.create']);
        Permission::create(['name' => 'security_logs.view']);

        // 2. Crear Roles y asignar permisos
        $admin = Role::create(['name' => 'admin']);
        $admin->givePermissionTo(Permission::all());

        $manager = Role::create(['name' => 'manager']);
        $manager->givePermissionTo(['documents.view', 'documents.create']);

        $user = Role::create(['name' => 'user']);
        $user->givePermissionTo(['documents.view']);
    }
}