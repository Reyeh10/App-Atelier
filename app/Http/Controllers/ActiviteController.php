<?php

namespace App\Http\Controllers;

use App\Models\Activite;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ActiviteController extends Controller
{
    public function index(Request $request)
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->hasPermission('voir_activites')) {
            abort(403);
        }

        $query = Activite::orderByDesc('created_at');

        if ($userId = $request->get('user_id')) {
            $query->where('user_id', $userId);
        }

        if ($action = $request->get('action')) {
            $query->where('action', 'like', "%{$action}%");
        }

        if ($date = $request->get('date')) {
            $query->whereDate('created_at', $date);
        }

        $activites   = $query->paginate(50)->withQueryString();
        $utilisateurs = User::orderBy('name')->get();

        return view('activites.index', compact('activites', 'utilisateurs'));
    }
}
