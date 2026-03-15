<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Delegado;
use App\Models\Microrregione;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UserManagementController extends Controller
{
    // List users with filter (Delegados/Enlaces)
    public function index(Request $request)
    {
        $role   = $request->get('role');
        $status = $request->get('status'); // 'activo', 'inactivo', or null = todos
        $rolesPermitidos = ['Delegado', 'Enlace', 'Auditor', 'delegado', 'enlace', 'auditor'];

        $query = User::query()->whereHas('roles', function ($q) use ($rolesPermitidos) {
            $q->whereIn('name', $rolesPermitidos);
        });

        if ($role) {
            $query->role($role);
        }

        if ($status === 'activo') {
            $query->where('activo', true);
        } elseif ($status === 'inactivo') {
            $query->where('activo', false);
        }

        $users = $query->with('microrregionesAsignadas', 'roles', 'delegado')->paginate(15)->withQueryString();
        return view('admin.user-management.index', compact('users', 'role', 'status'));
    }

    // Show create user form
    public function create()
    {
        $microrregiones   = Microrregione::orderBy('microrregion', 'asc')->get();
        $dependencias     = DB::table('dependencias_gobs')->orderBy('dependencia', 'asc')->get();
        return view('admin.user-management.create', compact('microrregiones', 'dependencias'));
    }

    // Store new user
    public function store(Request $request)
    {
        // Normalise role to lowercase so 'Delegado' and 'delegado' both pass
        $request->merge(['role' => strtolower($request->role)]);

        $validated = $request->validate([
            // users
            'name'             => 'required|string|max:255',
            'email'            => 'required|email|unique:users,email',
            'password'         => 'required|string|min:8|confirmed',
            'telefono'         => 'nullable|string|max:20',
            'activo'           => 'nullable|boolean',
            // role
            'role'             => 'required|in:delegado,enlace,auditor',
            // microrregiones
            'microrregion_id'  => 'required_if:role,delegado|nullable|exists:microrregiones,id',
            'microrregion_ids' => 'required_if:role,enlace|array|nullable',
            'microrregion_ids.*' => 'exists:microrregiones,id',
        ]);

        $userPayload = [
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'password' => bcrypt($validated['password']),
            'activo'   => $request->boolean('activo', true),
        ];

        if (Schema::hasColumn('users', 'telefono')) {
            $userPayload['telefono'] = $validated['telefono'] ?? null;
        }
        if (Schema::hasColumn('users', 'puesto')) {
            $userPayload['puesto'] = $validated['puesto'] ?? null;
        }

        $user = User::create($userPayload);
        $user->assignRole(ucfirst($validated['role']));

        if ($validated['role'] === 'auditor') {
            // Sin microrregiones; permisos vienen del rol Auditor (migración/seeder).
        } elseif ($validated['role'] === 'delegado') {
            $user->microrregionesAsignadas()->sync(
                isset($validated['microrregion_id']) ? [$validated['microrregion_id']] : []
            );

            // Create/update delegados record
            $delegadoPayload = [
                'nombre'     => $validated['name'],
                'ap_paterno' => '',
                'ap_materno' => '',
                'telefono'   => $validated['telefono'] ?? null,
                'email'      => $validated['email'],
                'microrregion_id' => $validated['microrregion_id'] ?? null,
            ];

            Delegado::updateOrCreate(['user_id' => $user->id], $delegadoPayload);
        } elseif ($validated['role'] === 'enlace') {
            $user->microrregionesAsignadas()->sync($validated['microrregion_ids'] ?? []);
        }

        return redirect()->route('admin.usuarios.index')->with('success', 'Usuario creado correctamente.');
    }

    // Show edit user form
    public function edit($id)
    {
        $user           = User::with('microrregionesAsignadas', 'roles', 'delegado')->findOrFail($id);
        $microrregiones = Microrregione::orderBy('microrregion', 'asc')->get();
        $dependencias   = DB::table('dependencias_gobs')->orderBy('dependencia', 'asc')->get();
        return view('admin.user-management.edit', compact('user', 'microrregiones', 'dependencias'));
    }

    // Update user
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        // Normalise role to lowercase
        $request->merge(['role' => strtolower($request->role)]);

        $validated = $request->validate([
            'name'             => 'required|string|max:255',
            'email'            => 'required|email|unique:users,email,' . $user->id,
            'password'         => 'nullable|string|min:8|confirmed',
            'telefono'         => 'nullable|string|max:20',
            'activo'           => 'nullable|boolean',
            'role'             => 'required|in:delegado,enlace,auditor',
            'microrregion_id'  => 'required_if:role,delegado|nullable|exists:microrregiones,id',
            'microrregion_ids' => 'required_if:role,enlace|array|nullable',
            'microrregion_ids.*' => 'exists:microrregiones,id',
        ]);

        $userPayload = [
            'name'   => $validated['name'],
            'email'  => $validated['email'],
            'activo' => $request->boolean('activo', (bool) $user->activo),
        ];

        if (Schema::hasColumn('users', 'telefono')) {
            $userPayload['telefono'] = $validated['telefono'] ?? null;
        }
        if (Schema::hasColumn('users', 'puesto')) {
            $userPayload['puesto'] = $validated['puesto'] ?? null;
        }

        $user->update($userPayload);
        if (!empty($validated['password'])) {
            $user->password = bcrypt($validated['password']);
            $user->save();
        }
        $user->syncRoles([ucfirst($validated['role'])]);

        if ($validated['role'] === 'auditor') {
            $user->microrregionesAsignadas()->sync([]);
        } elseif ($validated['role'] === 'delegado') {
            $user->microrregionesAsignadas()->sync(
                isset($validated['microrregion_id']) ? [$validated['microrregion_id']] : []
            );

            $delegadoPayload = [
                'nombre'     => $validated['name'],
                'ap_paterno' => '',
                'ap_materno' => '',
                'telefono'   => $validated['telefono'] ?? null,
                'email'      => $validated['email'],
                'microrregion_id' => $validated['microrregion_id'] ?? null,
            ];

            Delegado::updateOrCreate(['user_id' => $user->id], $delegadoPayload);
        } elseif ($validated['role'] === 'enlace') {
            $user->microrregionesAsignadas()->sync($validated['microrregion_ids'] ?? []);
        }

        return redirect()->route('admin.usuarios.index')->with('success', 'Usuario actualizado correctamente.');
    }

    // Delete user
    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $user->delete();
        return redirect()->route('admin.usuarios.index')->with('success', 'Usuario eliminado correctamente.');
    }

    // Activate/Deactivate user
    public function toggleStatus($id)
    {
        $user = User::findOrFail($id);
        $user->activo = !$user->activo;
        $user->save();
        return redirect()->route('admin.usuarios.index')->with('success', 'Estado del usuario actualizado.');
    }
}
