<?php

namespace App\Http\Controllers;

use App\Models\AbonnementPush;
use App\Models\NotificationInterne;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

use Illuminate\Support\Facades\Auth;

/**
 * Contrôleur des notifications internes (cloche en haut de l'écran).
 * Ces notifications préviennent un utilisateur d'un événement qui le concerne
 * (garantie à traiter, véhicule prêt à restituer...).
 */
class NotificationController extends Controller
{
    /**
     * Marque une notification comme lue et redirige vers l'OR concerné.
     */
    public function ouvrir(NotificationInterne $notification)
    {
        if ($notification->destinataire_id !== Auth::id()) abort(403);

        $notification->marquerLue();

        if ($notification->lien) {
            return redirect($notification->lien);
        }

        if ($notification->or_id) {
            return redirect()->route('ordres-reparations.show', $notification->or_id);
        }

        return back();
    }

    /**
     * Interrogé toutes les 20 s par les écrans ouverts : nombre de non lues et
     * notifications arrivées depuis la dernière vue (son + fenêtre dans la page).
     * Seules les notifications de moins de 2 minutes sont « sonnées » : on ne
     * rejoue pas l'historique en revenant sur le système.
     */
    public function nouvelles(Request $request)
    {
        $depuis = (int) $request->get('depuis');
        $mes    = NotificationInterne::where('destinataire_id', Auth::id());

        $nouvelles = $depuis > 0
            ? (clone $mes)->where('id', '>', $depuis)->where('created_at', '>=', now()->subMinutes(2))->orderBy('id')->limit(10)->get()
            : collect();

        return response()->json([
            'non_lues'   => (clone $mes)->whereNull('lu_at')->count(),
            'dernier_id' => (int) (clone $mes)->max('id'),
            'nouvelles'  => $nouvelles->map(fn (NotificationInterne $n) => [
                'id'    => $n->id,
                'titre' => $n->titre,
                'corps' => $n->corps,
                'url'   => route('notifications.ouvrir', $n),
            ])->values(),
        ]);
    }

    /** Abonne l'appareil aux notifications push (sonnerie de l'appareil, même système fermé) */
    public function abonner(Request $request)
    {
        $data = $request->validate([
            'endpoint'    => ['required', 'url', 'max:2000'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth'   => ['required', 'string', 'max:255'],
            'silencieux'  => ['nullable', 'boolean'],
        ]);

        // Un appareil partagé appartient au dernier utilisateur qui l'a activé
        $abonnement = AbonnementPush::updateOrCreate(
            ['endpoint_hash' => hash('sha256', $data['endpoint'])],
            [
                'user_id'  => Auth::id(),
                'endpoint' => $data['endpoint'],
                'p256dh'   => $data['keys']['p256dh'],
                'auth'     => $data['keys']['auth'],
                'appareil' => Str::limit((string) $request->userAgent(), 250, ''),
            ]
        );

        if (! $request->boolean('silencieux')) {
            NotificationService::testerAppareil($abonnement);
        }

        return response()->json(['ok' => true]);
    }

    /** Désabonne l'appareil */
    public function desabonner(Request $request)
    {
        $request->validate(['endpoint' => ['required', 'string', 'max:2000']]);

        AbonnementPush::where('endpoint_hash', hash('sha256', $request->input('endpoint')))
            ->where('user_id', Auth::id())
            ->delete();

        return response()->json(['ok' => true]);
    }

    /** Manifeste de l'application : nécessaire sur iPad / iPhone (site ajouté à l'écran d'accueil) */
    public function manifeste()
    {
        return response()->json([
            'name'             => 'App Atelier — STCD Motors',
            'short_name'       => 'Atelier STCD',
            'start_url'        => route('dashboard'),
            'scope'            => url('/') . '/',
            'display'          => 'standalone',
            'background_color' => '#0f172a',
            'theme_color'      => '#0f172a',
            'icons'            => [
                ['src' => asset('icone-192.png'), 'sizes' => '192x192', 'type' => 'image/png'],
                ['src' => asset('icone-512.png'), 'sizes' => '512x512', 'type' => 'image/png'],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json']);
    }

    /**
     * Marque toutes les notifications de l'utilisateur connecté comme lues.
     */
    public function toutMarquerLu()
    {
        NotificationInterne::where('destinataire_id', Auth::id())
            ->whereNull('lu_at')
            ->update(['lu_at' => now()]);

        return back()->with('success', 'Notifications marquées comme lues.');
    }
}
