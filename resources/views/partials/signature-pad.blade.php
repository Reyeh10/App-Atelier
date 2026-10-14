{{--
    Cadre de signature électronique du client (doigt sur tablette/téléphone, souris sur PC).
    Le dessin est envoyé en data URL PNG dans un champ caché — enregistré par
    App\Services\SignatureService. Obligatoire : l'envoi du formulaire est bloqué
    tant que le client n'a pas signé.

    Paramètres :
      $nom    — nom du champ envoyé (ex. signature_client_image)
      $titre  — titre affiché au-dessus du cadre
      $texte  — phrase d'engagement lue par le client avant de signer
--}}
@php $idPad = 'sig-' . $nom; @endphp
<div class="signature-pad space-y-2" data-signature-pad="{{ $idPad }}">
    <div class="flex items-center justify-between">
        <p class="text-sm font-semibold text-slate-700">{{ $titre }} <span class="text-red-500">*</span></p>
        <button type="button" data-effacer
                class="text-xs px-3 py-1.5 border border-gray-300 rounded-lg text-slate-600 hover:bg-gray-50 transition-colors">
            Effacer
        </button>
    </div>
    <p class="text-xs text-slate-500">{{ $texte }}</p>
    <div class="relative rounded-xl border-2 border-slate-900 bg-white overflow-hidden" style="touch-action:none;">
        <canvas data-zone class="block w-full cursor-crosshair" style="height:200px;"></canvas>
        <span data-indication class="pointer-events-none absolute inset-0 flex items-center justify-center text-sm text-slate-300 select-none">
            Le client signe ici
        </span>
    </div>
    <input type="hidden" name="{{ $nom }}" value="{{ old($nom) }}" data-valeur>
    <p data-erreur class="text-xs text-red-600 {{ $errors->has($nom) ? '' : 'hidden' }}">
        {{ $errors->first($nom) ?: 'La signature du client est obligatoire.' }}
    </p>
</div>

@once
@push('scripts')
<script>
(function () {
    function initialiserPad(bloc) {
        const canvas     = bloc.querySelector('[data-zone]');
        const champ      = bloc.querySelector('[data-valeur]');
        const indication = bloc.querySelector('[data-indication]');
        const erreur     = bloc.querySelector('[data-erreur]');
        const ctx        = canvas.getContext('2d');
        let enTrait = false;
        let dernier = null;

        function preparerPinceau() {
            ctx.lineWidth   = 2.5 * (window.devicePixelRatio || 1);
            ctx.lineCap     = 'round';
            ctx.lineJoin    = 'round';
            ctx.strokeStyle = '#000';
        }

        function majAffichage() {
            indication.classList.toggle('hidden', !!champ.value);
            if (champ.value) erreur.classList.add('hidden');
        }

        // Redimensionne le canvas à sa taille affichée (écran net) en conservant le dessin
        function dimensionner() {
            const ratio   = window.devicePixelRatio || 1;
            const largeur = Math.round(canvas.clientWidth * ratio);
            const hauteur = Math.round(canvas.clientHeight * ratio);
            if (!largeur || (canvas.width === largeur && canvas.height === hauteur)) return;
            canvas.width  = largeur;
            canvas.height = hauteur;
            preparerPinceau();
            if (champ.value) {
                const img = new Image();
                img.onload = () => ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                img.src = champ.value;
            }
        }

        function position(e) {
            const r = canvas.getBoundingClientRect();
            return { x: (e.clientX - r.left) * canvas.width / r.width, y: (e.clientY - r.top) * canvas.height / r.height };
        }

        canvas.addEventListener('pointerdown', e => {
            e.preventDefault();
            canvas.setPointerCapture(e.pointerId);
            enTrait = true;
            dernier = position(e);
            // Un simple point si le client tape sans glisser
            ctx.beginPath();
            ctx.arc(dernier.x, dernier.y, ctx.lineWidth / 2, 0, Math.PI * 2);
            ctx.fillStyle = '#000';
            ctx.fill();
        });
        canvas.addEventListener('pointermove', e => {
            if (!enTrait) return;
            const p = position(e);
            ctx.beginPath();
            ctx.moveTo(dernier.x, dernier.y);
            ctx.lineTo(p.x, p.y);
            ctx.stroke();
            dernier = p;
        });
        const finTrait = () => {
            if (!enTrait) return;
            enTrait = false;
            champ.value = canvas.toDataURL('image/png');
            majAffichage();
        };
        canvas.addEventListener('pointerup', finTrait);
        canvas.addEventListener('pointercancel', finTrait);

        bloc.querySelector('[data-effacer]').addEventListener('click', () => {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            champ.value = '';
            majAffichage();
        });

        // Signature obligatoire : bloque l'envoi du formulaire tant que le cadre est vide
        const form = bloc.closest('form');
        if (form) {
            form.addEventListener('submit', e => {
                if (champ.value) return;
                e.preventDefault();
                e.stopImmediatePropagation();
                erreur.classList.remove('hidden');
                bloc.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }, true);
        }

        window.addEventListener('resize', dimensionner);
        dimensionner();
        majAffichage();
    }

    document.querySelectorAll('[data-signature-pad]').forEach(initialiserPad);
})();
</script>
@endpush
@endonce
